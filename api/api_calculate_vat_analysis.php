<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => ''];

// --- Hardcoded Chemical IDs ---
// !!! ADJUST THESE IDs based on your tbl_chemicals table !!!
define('CYANIDE_ID', 1);
define('CAUSTIC_SODA_ID', 2);
define('ZINC_ID', 3);
// ---

try {
    // --- Input Validation ---
    $analysis_id = !empty($_GET['analysis_id']) ? (int)$_GET['analysis_id'] : null;
    $end_date = !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : date('Y-m-d'); // Default to today

    if (!$analysis_id) {
        throw new Exception("شناسه آنالیز مبدأ انتخاب نشده است.");
    }

    // a. Get the selected initial analysis record
    $initial_analysis_sql = "SELECT va.*, pv.VatName FROM tbl_plating_vat_analysis va JOIN tbl_plating_vats pv ON va.VatID = pv.VatID WHERE va.AnalysisID = ? LIMIT 1";
    $stmt = $pdo->prepare($initial_analysis_sql);
    $stmt->execute([$analysis_id]);
    $initial_analysis = $stmt->fetch(PDO::FETCH_ASSOC); 

    if (!$initial_analysis) {
        throw new Exception("رکورد آنالیز مبدأ یافت نشد.");
    }
    $vat_id = $initial_analysis['VatID'];
    $calc_start_date_actual = $initial_analysis['AnalysisDate']; 

    // b. Get Vat Volume
    $vat_info = find_by_id($pdo, 'tbl_plating_vats', $vat_id, 'VatID');
    if (!$vat_info || !$vat_info['VolumeLiters']) {
        throw new Exception("حجم وان ({$initial_analysis['VatName']}) برای محاسبه یافت نشد. لطفاً در اطلاعات پایه ثبت کنید.");
    }
    $vat_volume = (int)$vat_info['VolumeLiters'];

    // c. Get Chemical Consumption Factors (g/barrel, g/kg)
    $factors_sql = "SELECT ChemicalID, ChemicalName, consumption_g_per_barrel, consumption_g_per_kg FROM tbl_chemicals WHERE ChemicalID IN (?, ?, ?)";
    $factors_raw = find_all($pdo, $factors_sql, [CYANIDE_ID, CAUSTIC_SODA_ID, ZINC_ID]);
    $factors = [];
    $chemical_names = [];
    foreach ($factors_raw as $f) {
        $factors[$f['ChemicalID']] = $f;
        $chemical_names[$f['ChemicalID']] = $f['ChemicalName'];
    }

    // d. Get Total Barrels and Plated KG between analysis date (exclusive) and end date (inclusive)
    $period_params = [];
    $period_where_clauses = ["h.LogDate > ?"]; // Start AFTER the analysis date
    $period_params[] = $calc_start_date_actual;
    $period_where_clauses[] = "h.LogDate <= ?";
    $period_params[] = $end_date;
    $period_where_sql = 'WHERE ' . implode(' AND ', $period_where_clauses);

    // ---
    // --- CORRECTED AGGREGATION QUERY ---
    // ---
    $prod_agg_sql = "
        SELECT 
            SUM(sub.TotalBarrels) as TotalBarrels,
            SUM(sub.TotalPlatedKG) as TotalPlatedKG
        FROM (
            SELECT 
                h.NumberOfBarrels as TotalBarrels,
                COALESCE(SUM(d.PlatedKG), 0) as TotalPlatedKG
            FROM tbl_plating_log_header h
            LEFT JOIN tbl_plating_log_details d ON h.PlatingHeaderID = d.PlatingHeaderID
            {$period_where_sql} -- This WHERE applies to 'h'
            GROUP BY h.PlatingHeaderID, h.NumberOfBarrels 
        ) as sub
    ";
    // --- END OF CORRECTION ---
    // ---
    
    $prod_agg_data = find_all($pdo, $prod_agg_sql, $period_params);
    $total_barrels_period = (int)($prod_agg_data[0]['TotalBarrels'] ?? 0);
    $total_plated_kg_period = (float)($prod_agg_data[0]['TotalPlatedKG'] ?? 0);

    // e. Get Total Additions for the specific Vat in the period (assuming additions are in KG)
    $additions_sql = "SELECT ChemicalID, SUM(Quantity) as TotalAddedKG
                      FROM tbl_plating_log_additions a
                      JOIN tbl_plating_log_header h ON a.PlatingHeaderID = h.PlatingHeaderID
                      {$period_where_sql} AND a.VatID = ? AND a.ChemicalID IN (?, ?, ?) AND a.Unit = 'kg'
                      GROUP BY ChemicalID";
    $additions_params = array_merge($period_params, [$vat_id, CYANIDE_ID, CAUSTIC_SODA_ID, ZINC_ID]);
    $additions_raw = find_all($pdo, $additions_sql, $additions_params);
    $additions = [];
     foreach ($additions_raw as $a) { $additions[$a['ChemicalID']] = (float)$a['TotalAddedKG'] * 1000; } // Convert KG to Grams

    // f. Count active vats for division
    $active_vats_count = (int)$pdo->query("SELECT COUNT(*) FROM tbl_plating_vats WHERE IsActive = 1")->fetchColumn();
    $vat_divisor = max(1, $active_vats_count); 

    // g. Perform Calculations for each chemical
    $results = [];
    $chemicals_to_calc = [
        CYANIDE_ID => 'Cyanide_gL',
        CAUSTIC_SODA_ID => 'CausticSoda_gL',
        ZINC_ID => 'Zinc_gL'
    ];

    foreach ($chemicals_to_calc as $chem_id => $analysis_col) {
        if (!isset($factors[$chem_id])) {
            error_log("Missing consumption factors for Chemical ID: " . $chem_id);
            continue; 
        }
        
        $initial_conc = (float)($initial_analysis[$analysis_col] ?? 0);
        $factor_barrel = (float)($factors[$chem_id]['consumption_g_per_barrel'] ?? 0);
        $factor_kg = (float)($factors[$chem_id]['consumption_g_per_kg'] ?? 0);
        $total_added_g = (float)($additions[$chem_id] ?? 0);

        $barrels_per_vat = $total_barrels_period / $vat_divisor;
        $plated_kg_per_vat = $total_plated_kg_period / $vat_divisor;

        $consumed_barrel_g = $barrels_per_vat * $factor_barrel;
        $net_change_barrel_g = $total_added_g - $consumed_barrel_g;
        $net_change_barrel_gl = ($vat_volume > 0) ? ($net_change_barrel_g / $vat_volume) : 0;
        $predicted_barrel_gl = $initial_conc + $net_change_barrel_gl;

        $consumed_kg_g = $plated_kg_per_vat * $factor_kg;
        $net_change_kg_g = $total_added_g - $consumed_kg_g;
        $net_change_kg_gl = ($vat_volume > 0) ? ($net_change_kg_g / $vat_volume) : 0;
        $predicted_kg_gl = $initial_conc + $net_change_kg_gl;

        $results[$chem_id] = [
            'chemical_name' => $chemical_names[$chem_id] ?? 'Unknown',
            'initial_conc_gl' => $initial_conc,
            'consumed_barrel_g' => round($consumed_barrel_g, 1),
            'consumed_kg_g' => round($consumed_kg_g, 1),
            'total_added_g' => round($total_added_g, 1),
            'net_change_barrel_gl' => round($net_change_barrel_gl, 2),
            'net_change_kg_gl' => round($net_change_kg_gl, 2),
            'predicted_barrel_gl' => round(max(0, $predicted_barrel_gl), 2), 
            'predicted_kg_gl' => round(max(0, $predicted_kg_gl), 2),   
        ];
    }

    $response['success'] = true;
    $response['data'] = [
        'vat_name' => $vat_info['VatName'],
        'vat_volume' => $vat_volume,
        'analysis_date_start' => $calc_start_date_actual,
        'analysis_date_start_jalali' => to_jalali($calc_start_date_actual),
        'period_end_date' => $end_date,
        'period_end_date_jalali' => to_jalali($end_date),
        'period_barrels_total' => $total_barrels_period,
        'period_plated_kg_total' => round($total_plated_kg_period, 1),
        'active_vats_count' => $vat_divisor,
        'barrels_per_vat' => round($barrels_per_vat, 1),
        'plated_kg_per_vat' => round($plated_kg_per_vat, 1),
        'calculations' => $results
    ];


} catch (Exception $e) {
    error_log("Vat Analysis Calculation API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $response['message'] = 'خطا در محاسبه: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

