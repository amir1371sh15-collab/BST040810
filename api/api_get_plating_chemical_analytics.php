<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => []];

try {
    // --- Filters ---
    $params = [];
    $where_clauses = [];
    $start_date = !empty($_GET['start_date']) ? to_gregorian($_GET['start_date']) : null;
    $end_date = !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : null;
    // $chemical_type_id removed - always filter by plating additives unless specific chemical is chosen
    $chemical_id = !empty($_GET['chemical_id']) ? (int)$_GET['chemical_id'] : null;
    $vat_id = !empty($_GET['vat_id']) ? (int)$_GET['vat_id'] : null;

    // Build base WHERE clause for additions
    $add_where_clauses = [];
    $add_params = [];
    if ($start_date) { $add_where_clauses[] = "h.LogDate >= ?"; $add_params[] = $start_date; }
    if ($end_date) { $add_where_clauses[] = "h.LogDate <= ?"; $add_params[] = $end_date; }
    if ($chemical_id) { 
        $add_where_clauses[] = "a.ChemicalID = ?"; 
        $add_params[] = $chemical_id; 
    } else {
        // If no specific chemical is selected, ALWAYS filter by 'Plating Additives' type
        $additive_type_id_q = $pdo->query("SELECT ChemicalTypeID FROM tbl_chemical_types WHERE TypeName = 'افزودنی های وان آبکاری'")->fetchColumn();
        if ($additive_type_id_q) {
            $add_where_clauses[] = "c.ChemicalTypeID = ?";
            $add_params[] = $additive_type_id_q;
        }
    }
    if ($vat_id) { $add_where_clauses[] = "a.VatID = ?"; $add_params[] = $vat_id; }
   
    $add_where_sql = count($add_where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $add_where_clauses) : '';

    $base_add_query_from = "FROM tbl_plating_log_additions a
                           JOIN tbl_plating_log_header h ON a.PlatingHeaderID = h.PlatingHeaderID
                           JOIN tbl_chemicals c ON a.ChemicalID = c.ChemicalID
                           LEFT JOIN tbl_plating_vats v ON a.VatID = v.VatID
                           LEFT JOIN tbl_chemical_types ct ON c.ChemicalTypeID = ct.ChemicalTypeID
                           {$add_where_sql}";

    // 1. Total Consumption per Chemical
    $total_consumption_sql = "SELECT c.ChemicalName, SUM(a.Quantity) as TotalQuantity, a.Unit
                              {$base_add_query_from}
                              GROUP BY a.ChemicalID, c.ChemicalName, a.Unit 
                              ORDER BY TotalQuantity DESC";
    $response['data']['total_consumption'] = find_all($pdo, $total_consumption_sql, $add_params);

    // 2. Consumption per Vat
    $per_vat_sql = "SELECT v.VatName, c.ChemicalName, SUM(a.Quantity) as TotalQuantity, a.Unit
                    {$base_add_query_from} AND a.VatID IS NOT NULL
                    GROUP BY a.VatID, v.VatName, a.ChemicalID, c.ChemicalName, a.Unit
                    ORDER BY v.VatName, TotalQuantity DESC";
     $response['data']['consumption_per_vat'] = find_all($pdo, $per_vat_sql, $add_params);

    // 3. Consumption Rates (Per Barrel & Per KG Plated)
    $response['data']['consumption_rates'] = null;
    $response['data']['consumption_rates_trend'] = null;
    if ($chemical_id) { // Only calculate if a specific chemical is chosen
        // Get total additions for the selected chemical using the already built query
        $total_added_sql = "SELECT SUM(a.Quantity) as TotalQuantity, a.Unit
                            {$base_add_query_from}
                            GROUP BY a.Unit";
        $total_added_raw = find_all($pdo, $total_added_sql, $add_params);
        $total_added = !empty($total_added_raw) ? (float)$total_added_raw[0]['TotalQuantity'] : 0;
        $unit_added = !empty($total_added_raw) ? $total_added_raw[0]['Unit'] : '';

        // Get total barrels and total plated KG for the period (using ONLY date filters)
        $prod_params_rates = [];
        $prod_where_clauses_rates = [];
        if ($start_date) { $prod_where_clauses_rates[] = "h.LogDate >= ?"; $prod_params_rates[] = $start_date; }
        if ($end_date) { $prod_where_clauses_rates[] = "h.LogDate <= ?"; $prod_params_rates[] = $end_date; }
        $prod_where_sql_rates = count($prod_where_clauses_rates) > 0 ? 'WHERE ' . implode(' AND ', $prod_where_clauses_rates) : '';

        $prod_agg_sql = "SELECT SUM(h.NumberOfBarrels) as TotalBarrels,
                                SUM(d.PlatedKG) as TotalPlatedKG
                         FROM tbl_plating_log_header h
                         LEFT JOIN tbl_plating_log_details d ON h.PlatingHeaderID = d.PlatingHeaderID
                         {$prod_where_sql_rates}";
        $prod_agg_data = find_all($pdo, $prod_agg_sql, $prod_params_rates);
        $total_barrels = (int)($prod_agg_data[0]['TotalBarrels'] ?? 0);
        $total_plated_kg = (float)($prod_agg_data[0]['TotalPlatedKG'] ?? 0);

        $rate_per_barrel = ($total_barrels > 0) ? round($total_added / $total_barrels, 3) : 0;
        $rate_per_kg = ($total_plated_kg > 0) ? round($total_added / $total_plated_kg, 3) : 0;

        $response['data']['consumption_rates'] = [
            'chemical_name' => find_by_id($pdo, 'tbl_chemicals', $chemical_id, 'ChemicalID')['ChemicalName'] ?? 'Unknown',
            'total_added' => $total_added,
            'unit' => $unit_added,
            'total_barrels' => $total_barrels,
            'total_plated_kg' => $total_plated_kg,
            'rate_per_barrel' => $rate_per_barrel,
            'rate_per_kg' => $rate_per_kg
        ];
        
        // --- Trend Data for Rates (Daily Calculation - REVISED) ---
        $rates_trend_params_daily = [];
        $rates_trend_where_clauses_daily = [];
        if ($start_date) { $rates_trend_where_clauses_daily[] = "h.LogDate >= ?"; $rates_trend_params_daily[] = $start_date; }
        if ($end_date) { $rates_trend_where_clauses_daily[] = "h.LogDate <= ?"; $rates_trend_params_daily[] = $end_date; }
        $rates_trend_where_sql_daily = count($rates_trend_where_clauses_daily) > 0 ? 'WHERE ' . implode(' AND ', $rates_trend_where_clauses_daily) : '';

        $rates_trend_sql = "
           SELECT 
              h.LogDate,
              h.NumberOfBarrels as DailyBarrels,
              COALESCE((SELECT SUM(d_inner.PlatedKG) 
                        FROM tbl_plating_log_details d_inner 
                        WHERE d_inner.PlatingHeaderID = h.PlatingHeaderID), 0) as DailyPlatedKG,
              COALESCE((SELECT SUM(a_inner.Quantity) 
                        FROM tbl_plating_log_additions a_inner 
                        WHERE a_inner.PlatingHeaderID = h.PlatingHeaderID AND a_inner.ChemicalID = ?), 0) as DailyQuantityAdded
           FROM tbl_plating_log_header h
           {$rates_trend_where_sql_daily}
           ORDER BY h.LogDate ASC
        ";
        // Add chemical_id to the beginning of params for the subquery
        $final_rates_trend_params = array_merge([$chemical_id], $rates_trend_params_daily);
        $rates_trend_data_raw = find_all($pdo, $rates_trend_sql, $final_rates_trend_params);

        $rates_trend = [];
        foreach ($rates_trend_data_raw as $day) {
            $daily_barrels = (int)$day['DailyBarrels'];
            $daily_plated_kg = (float)$day['DailyPlatedKG'];
            $daily_added = (float)$day['DailyQuantityAdded'];
            
            $daily_rate_per_barrel = ($daily_barrels > 0) ? round($daily_added / $daily_barrels, 3) : 0;
            $daily_rate_per_kg = ($daily_plated_kg > 0) ? round($daily_added / $daily_plated_kg, 3) : 0;

            $rates_trend[] = [
                'LogDate' => $day['LogDate'],
                'RatePerBarrel' => $daily_rate_per_barrel,
                'RatePerKG' => $daily_rate_per_kg
            ];
        }
         $response['data']['consumption_rates_trend'] = $rates_trend;
    }

    $response['success'] = true;

} catch (Exception $e) {
    // Log the detailed error for debugging
    error_log("Plating Chemical API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $response['message'] = 'خطای داخلی سرور رخ داده است. لطفا با پشتیبانی تماس بگیرید.';
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE); // Ignore invalid UTF8 chars if any

