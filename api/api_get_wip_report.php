<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => null, 'message' => ''];

// چک کردن مجوز
if (!has_permission('warehouse.transactions.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این گزارش را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    $response['message'] = 'فقط متد GET مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    exit;
}

try {
    // دریافت پارامترها
    $from_date_jalali = $_GET['from_date'] ?? null;
    $to_date_jalali = $_GET['to_date'] ?? null;
    $selected_station_id = !empty($_GET['station_id']) ? (int)$_GET['station_id'] : null;

    // تبدیل تاریخ جلالی به میلادی - استفاده از to_gregorian مثل inventory report
    $from_date_only = to_gregorian($from_date_jalali);
    $to_date_only = to_gregorian($to_date_jalali);
    
    if (!$from_date_only || !$to_date_only) {
        throw new Exception("بازه زمانی نامعتبر است.");
    }
    
    // اضافه کردن ساعت
    $from_date = $from_date_only . ' 00:00:00';
    $to_date = $to_date_only . ' 23:59:59';

    // Get helper data
    $part_weights = get_all_part_weights($pdo);
    $bom_map = get_bom_map($pdo);
    $packaging_configs = get_packaging_configs($pdo);
    $weight_changes = get_weight_changes($pdo);
    // --- 2. Get Stations to Report On ---
    $station_query_sql = "SELECT StationID, StationName FROM tbl_stations WHERE StationType = 'Production'";
    if ($selected_station_id) {
        $station_query_sql .= " AND StationID = :station_id";
    }
    $station_query_sql .= " ORDER BY StationName";
    
    $station_stmt = $pdo->prepare($station_query_sql);
    if ($selected_station_id) {
        $station_stmt->execute(['station_id' => $selected_station_id]);
    } else {
        $station_stmt->execute();
    }
    $stations = $station_stmt->fetchAll(PDO::FETCH_ASSOC);

    $report_data = [];

    // --- 3. Main Loop: Process Each Station ---
    foreach ($stations as $station) {
        $station_id = (int)$station['StationID'];
        $station_name = $station['StationName'];
        $station_unit = 'KG'; // Default
        $part_results = [];

        // Determine parts that ever moved through this station
        $relevant_parts_stmt = $pdo->prepare("
            SELECT DISTINCT t.PartID, p.PartName, p.FamilyID, r.RequiredStatusID
            FROM tbl_stock_transactions t
            JOIN tbl_parts p ON t.PartID = p.PartID
            -- اصلاح: یافتن وضعیت مورد نیاز برای *ورود* به این ایستگاه
            LEFT JOIN tbl_routes r ON p.FamilyID = r.FamilyID AND r.ToStationID = ? 
            WHERE (t.ToStationID = ? OR t.FromStationID = ?) AND t.PartID IS NOT NULL
            GROUP BY t.PartID, p.PartName, p.FamilyID, r.RequiredStatusID
        ");
        $relevant_parts_stmt->execute([$station_id, $station_id, $station_id]);
        $relevant_parts = $relevant_parts_stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 4. Special Logic Switch ---
        switch ($station_id) {
            
            // --- ایستگاه بسته بندی (تبدیل واحد) ---
            case 10: // 10 = 'بسته بندی'
                $station_unit = 'Pcs';
                foreach ($relevant_parts as $part) {
                    $part_id = $part['PartID'];
                    if (!isset($part_weights[$part_id]) || $part_weights[$part_id] <= 0 || !isset($packaging_configs[$part_id]) || $packaging_configs[$part_id] <= 0) {
                        continue; // Skip parts without weight or packaging config
                    }
                    $weight_gr = $part_weights[$part_id];
                    $qty_per_carton = $packaging_configs[$part_id];

                    // Opening
                    $opening_in_kg = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, null, $from_date);
                    $opening_out_cartons = get_sum($pdo, 'CartonQuantity', 'FromStationID', $part_id, $station_id, null, $from_date);
                    $opening_in_pcs = ($opening_in_kg * 1000) / $weight_gr;
                    $opening_out_pcs = $opening_out_cartons * $qty_per_carton;
                    $opening = $opening_in_pcs - $opening_out_pcs;

                    // In
                    $total_in_kg = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, $from_date, $to_date);
                    $total_in_pcs = ($total_in_kg * 1000) / $weight_gr;

                    // Out
                    $total_out_cartons = get_sum($pdo, 'CartonQuantity', 'FromStationID', $part_id, $station_id, $from_date, $to_date);
                    $total_out_pcs = $total_out_cartons * $qty_per_carton;
                    
                    $system = $opening + $total_in_pcs - $total_out_pcs;

                    if (abs($opening) < 0.001 && abs($total_in_pcs) < 0.001 && abs($total_out_pcs) < 0.001) continue;

                    $part_results[] = [
                        'PartID' => $part_id,
                        'PartName' => $part['PartName'],
                        'FamilyID' => $part['FamilyID'],
                        'StatusID' => $part['RequiredStatusID'],
                        'Opening' => $opening,
                        'In' => $total_in_pcs,
                        'Out' => $total_out_pcs,
                        'System' => $system,
                        'TooltipIn' => "برابر با " . number_format($total_in_kg, 2) . " کیلوگرم",
                        'TooltipOut' => "برابر با " . number_format($total_out_cartons) . " کارتن"
                    ];
                }
                break;

            // --- ایستگاه مونتاژ (مصرف تئوریک) ---
            case 12: // 12 = 'مونتاژ'
                $station_unit = 'KG (Component)';
                $consumed_components = []; // [component_id => kg_consumed]
                
                // Find all *products* that left assembly
                $products_out_stmt = $pdo->prepare("
                    SELECT t.PartID, SUM(t.NetWeightKG) as TotalKG
                    FROM tbl_stock_transactions t
                    JOIN tbl_parts p ON t.PartID = p.PartID 
                    WHERE t.FromStationID = ? AND p.FamilyID IN (3, 9) -- 3=بست بزرگ, 9=بست کوچک
                    AND t.TransactionDate BETWEEN ? AND ?
                    GROUP BY t.PartID
                ");
                $products_out_stmt->execute([$station_id, $from_date, $to_date]);
                
                while ($product = $products_out_stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!isset($part_weights[$product['PartID']]) || $part_weights[$product['PartID']] <= 0 || !isset($bom_map[$product['PartID']])) {
                        continue;
                    }
                    $product_weight_gr = $part_weights[$product['PartID']];
                    $product_pcs = ($product['TotalKG'] * 1000) / $product_weight_gr;
                    
                    // Find components consumed
                    foreach ($bom_map[$product['PartID']] as $component) {
                        $comp_id = $component['ChildPartID'];
                        if (!isset($part_weights[$comp_id])) continue;
                        
                        $comp_weight_gr = $part_weights[$comp_id];
                        $comp_pcs_needed = $product_pcs * $component['QuantityPerParent'];
                        $comp_kg_consumed = ($comp_pcs_needed * $comp_weight_gr) / 1000;
                        
                        if (!isset($consumed_components[$comp_id])) $consumed_components[$comp_id] = 0;
                        $consumed_components[$comp_id] += $comp_kg_consumed;
                    }
                }
                
                // Now run standard report for components, but override 'Out'
                foreach ($relevant_parts as $part) {
                    $part_id = $part['PartID'];
                    
                    // Skip if it's a finished product (families 3, 9)
                    if (in_array($part['FamilyID'], [3, 9])) continue;

                    $opening = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, null, $from_date);
                    $opening -= get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, null, $from_date); // Subtract any physical out
                    
                    $total_in = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, $from_date, $to_date);
                    
                    // Use theoretical consumption for 'Out'
                    $theoretical_out = $consumed_components[$part_id] ?? 0;
                    // Also subtract any *physical* 'Out' transactions (e.g., sending back to warehouse)
                    $physical_out = get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, $from_date, $to_date);
                    $total_out = $theoretical_out + $physical_out;
                    
                    $system = $opening + $total_in - $total_out;

                    if (abs($opening) < 0.001 && abs($total_in) < 0.001 && abs($total_out) < 0.001) continue;
                    
                    $tooltip_out_text = "مصرف تئوریک: " . number_format($theoretical_out, 2) . " KG";
                    if ($physical_out > 0) {
                        $tooltip_out_text .= " | خروج فیزیکی: " . number_format($physical_out, 2) . " KG";
                    }

                    $part_results[] = [
                        'PartID' => $part_id,
                        'PartName' => $part['PartName'],
                        'FamilyID' => $part['FamilyID'],
                        'StatusID' => $part['RequiredStatusID'],
                        'Opening' => $opening,
                        'In' => $total_in,
                        'Out' => $total_out,
                        'System' => $system,
                        'TooltipIn' => null,
                        'TooltipOut' => $tooltip_out_text
                    ];
                }
                break;
                
            // --- ایستگاه دنده زنی (کاهش وزن) ---
            case 3: // 3 = 'دنده زنی'
            // --- ایستگاه های ساده ---
            default: // 1=شستشو, 2=پرسکاری, 4=آبکاری, 5=رول, 6=پیچ سازی
                $station_unit = 'KG';
                foreach ($relevant_parts as $part) {
                    $part_id = $part['PartID'];

                    $opening = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, null, $from_date);
                    $opening -= get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, null, $from_date);
                    
                    $total_in = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, $from_date, $to_date);
                    $raw_out = get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, $from_date, $to_date);
                    
                    $total_out = $raw_out;
                    $tooltip_out = null;

                    // Apply Gearing Logic (Station 3)
                    if ($station_id == 3 && $raw_out > 0) {
                        if (isset($weight_changes[$part_id]) && isset($weight_changes[$part_id][$station_id])) {
                            $percent = $weight_changes[$part_id][$station_id];
                            // User logic: "کاهش وزن" -> 97kg with 3% loss becomes 100kg
                            // Normalized = Raw / (1 - (Percent/100))
                            if ($percent > 0 && $percent < 100) { // Assuming loss percent is stored as positive
                                $total_out = $raw_out / (1 - ($percent / 100));
                                $tooltip_out = "وزن خام: " . number_format($raw_out, 3) . " KG (نرمال‌شده با " . $percent . "% کاهش)";
                            }
                        }
                    }
                    
                    $system = $opening + $total_in - $total_out;

                    if (abs($opening) < 0.001 && abs($total_in) < 0.001 && abs($total_out) < 0.001) continue;

                    $part_results[] = [
                        'PartID' => $part_id,
                        'PartName' => $part['PartName'],
                        'FamilyID' => $part['FamilyID'],
                        'StatusID' => $part['RequiredStatusID'],
                        'Opening' => $opening,
                        'In' => $total_in,
                        'Out' => $total_out,
                        'System' => $system,
                        'TooltipIn' => null,
                        'TooltipOut' => $tooltip_out
                    ];
                }
                break;
        }

        $report_data[$station_id] = [
            'station_name' => $station_name,
            'unit' => $station_unit,
            'parts' => $part_results
        ];
    }

    echo json_encode(['success' => true, 'data' => $report_data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// --- Helper Functions for API ---

/**
 * Get simple SUM of a column for a part at a station in a timeframe.
 */
function get_sum($pdo, $column, $station_col, $part_id, $station_id, $start_date, $end_date) {
    $sql = "SELECT SUM($column) FROM tbl_stock_transactions 
            WHERE $station_col = ? AND PartID = ?";
    $params = [$station_id, $part_id];

    if ($start_date && $end_date) {
        $sql .= " AND TransactionDate BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif ($end_date) { // Used for opening balance (null, from_date)
        $sql .= " AND TransactionDate < ?";
        $params[] = $end_date;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

/**
 * Fetches all part weights [part_id => weight_gr]
 */
function get_all_part_weights($pdo) {
    $stmt = $pdo->query("SELECT PartID, WeightGR FROM tbl_part_weights WHERE EffectiveTo IS NULL");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Fetches BOM map [parent_id => [[child_id, qty], ...]]
 */
function get_bom_map($pdo) {
    $stmt = $pdo->query("SELECT ParentPartID, ChildPartID, QuantityPerParent FROM tbl_bom_structure");
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int)$row['ParentPartID']][] = [
            'ChildPartID' => (int)$row['ChildPartID'],
            'QuantityPerParent' => (float)$row['QuantityPerParent']
        ];
    }
    return $map;
}

/**
 * Fetches packaging configs [part_id => contained_qty]
 */
function get_packaging_configs($pdo) {
     $stmt = $pdo->query("
        SELECT p.PartID, pc.ContainedQuantity
        FROM tbl_parts p
        JOIN tbl_packaging_configs pc ON p.SizeID = pc.SizeID
        WHERE p.SizeID IS NOT NULL
    ");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Fetches weight change rules [part_id => [from_station_id => percent]]
 */
function get_weight_changes($pdo) {
    $stmt = $pdo->query("
        SELECT PartID, FromStationID, WeightChangePercent
        FROM tbl_process_weight_changes
        WHERE EffectiveTo IS NULL
    ");
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int)$row['PartID']][(int)$row['FromStationID']] = (float)$row['WeightChangePercent'];
    }
    return $map;
}
?>