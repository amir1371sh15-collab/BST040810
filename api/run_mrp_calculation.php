<?php
// api/run_mrp_calculation.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// --- (Helper Functions) ---

/**
 * Helper 1: Get all Supply (Finished Goods & WIP) from relevant stations
 */
function get_supply_by_station($pdo, $partWeights) {
    // Station 8: Anbar Monfaseleh (WIP), Station 9: Anbar Nahayi (Finished Goods)
    $supply = ['station_8' => [], 'station_9' => []];

    $stock_query = "
        SELECT 
            t.PartID, t.ToStationID, t.StatusAfterID,
            SUM(t.NetWeightKG) AS TotalNetWeightKG,
            SUM(CASE WHEN t.ToStationID = 11 THEN t.CartonQuantity ELSE 0 END) AS TotalCartonQuantity
        FROM tbl_stock_transactions t
        WHERE t.ToStationID IN (8, 9, 11) AND t.TransactionDate <= NOW()
        GROUP BY t.PartID, t.ToStationID, t.StatusAfterID 
        HAVING TotalNetWeightKG > 0.001 OR TotalCartonQuantity > 0
    ";
    $stmt = $pdo->query($stock_query);
    $all_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_stock as $stock_item) {
        $part_id = (int)$stock_item['PartID'];
        $station_id_key = 'station_' . $stock_item['ToStationID'];
        $status_id = (int)($stock_item['StatusAfterID'] ?? 0); // Use 0 if status is NULL
        
        // Key is PartID_StatusID
        $part_key = 'PART_' . $part_id . '_STATUS_' . $status_id;
        
        $quantity = 0;
        $weight_gr = $partWeights[$part_id] ?? 0;
        $source_kg = (float)$stock_item['TotalNetWeightKG'];
        $source_carton = (int)$stock_item['TotalCartonQuantity'];

        // Convert KG to PIECES (Count)
        if ($source_kg > 0 && $weight_gr > 0.001) { 
            $quantity = (int)($source_kg / ($weight_gr / 1000));
        }
        
        if (!isset($supply[$station_id_key][$part_key])) {
            $supply[$station_id_key][$part_key] = [
                'quantity' => 0, 
                'source_kg' => 0, 
                'source_carton' => 0,
                'unit_weight_gr' => $weight_gr
            ];
        }
        
        $supply[$station_id_key][$part_key]['quantity'] += $quantity; 
        $supply[$station_id_key][$part_key]['source_kg'] += $source_kg;
        $supply[$station_id_key][$part_key]['source_carton'] += $source_carton;
    }
    return $supply;
}

/**
 * Helper 2: Get Raw Material Supply (in Grams)
 */
function get_raw_supply($pdo) {
    $raw_supply_data = [];
    $raw_query = "
        SELECT ItemID, SUM(Quantity) AS TotalQuantityKG
        FROM tbl_raw_transactions 
        GROUP BY ItemID
        HAVING TotalQuantityKG > 0
    ";
    $stmt = $pdo->query($raw_query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw_key = 'RAW_' . $row['ItemID'];
        $total_kg = (float)$row['TotalQuantityKG'];
        $raw_supply_data[$raw_key] = [
            'quantity' => $total_kg * 1000, // Supply in GRAMS
            'source_kg' => $total_kg
        ];
    }
    return $raw_supply_data;
}

/**
 * Helper 3: Recursive explosion function.
 */
function explode_bom_and_net_wip(
    $part_id, 
    $required_status_id, 
    $qty_to_build, 
    &$full_detailed_list, 
    &$net_shortage_list,
    &$wip_supply,             
    &$total_raw_demand,       
    $boms, 
    $raw_boms,
    $all_parts,
    $all_statuses
) {
    // 1. Check WIP supply (Station 8) for this specific part/status
    // Note: Use status ID 0 if required status is NULL for lookup
    $status_key = (int)($required_status_id ?? 0);
    $wip_key = 'PART_' . $part_id . '_STATUS_' . $status_key;
    $on_hand_wip_obj = $wip_supply[$wip_key] ?? ['quantity' => 0, 'source_kg' => 0, 'source_carton' => 0, 'unit_weight_gr' => 0];
    $on_hand_wip_qty = $on_hand_wip_obj['quantity'];

    $net_qty_for_child = $qty_to_build - $on_hand_wip_qty;
    $is_shortage = $net_qty_for_child > 0;

    // 2. Consume supply and determine net quantity to build
    $qty_to_build_children = 0;
    if ($net_qty_for_child <= 0) {
        if (isset($wip_supply[$wip_key])) {
             // Consume what we needed and stop. (Mutating the global supply array)
            $wip_supply[$wip_key]['quantity'] = $on_hand_wip_qty - $qty_to_build;
        }
    } else {
        // We need to build the net quantity. Consume all available WIP supply.
        if (isset($wip_supply[$wip_key])) {
            $wip_supply[$wip_key]['quantity'] = 0; // All supply is used up
        }
        $qty_to_build_children = $net_qty_for_child; // This is what we must build
    }

    // 3. Add to the FULL detailed report list
    $part_name = $all_parts[$part_id]['PartName'] ?? 'ناشناخته';
    $status_name = $all_statuses[$required_status_id] ?? '-- بدون وضعیت --';
    
    $report_item = [
        'ItemType' => 'قطعه',
        'ItemID' => $part_id,
        'ItemStatusID' => $required_status_id, // Store the required status ID
        'ItemStatusName' => $status_name,
        'ItemName' => $part_name,
        'GrossRequirement' => round($qty_to_build),
        'AvailableSupply' => round($on_hand_wip_qty),
        'NetRequirement' => round($net_qty_for_child > 0 ? $net_qty_for_child : 0),
        'Unit' => 'عدد',
        'Supply_Source_KG' => (float)($on_hand_wip_obj['source_kg'] ?? 0),
        'Supply_Source_Carton' => (int)($on_hand_wip_obj['source_carton'] ?? 0),
        'Supply_Unit_Weight_GR' => (float)($on_hand_wip_obj['unit_weight_gr'] ?? 0)
    ];
    $full_detailed_list[] = $report_item;

    // 4. If there is a shortage, add it to the NET shortage list
    if ($is_shortage) {
        $net_shortage_list[] = $report_item;
    }

    // 5. If we must build (qty_to_build_children > 0), explode children
    if ($qty_to_build_children > 0) {
        
        // --- Explode to Semi-finished Parts (Recursive) ---
        if (isset($boms[$part_id])) {
            foreach ($boms[$part_id] as $child) {
                $child_id = (int)$child['ChildPartID'];
                $child_req_status_id = (int)($child['RequiredStatusID'] ?? 0);
                $child_qty_per = (float)$child['QuantityPerParent'];
                
                // Recursive call
                explode_bom_and_net_wip(
                    $child_id, 
                    $child_req_status_id, 
                    $qty_to_build_children * $child_qty_per, 
                    $full_detailed_list, 
                    $net_shortage_list,
                    $wip_supply,
                    $total_raw_demand, 
                    $boms, 
                    $raw_boms,
                    $all_parts,
                    $all_statuses
                );
            }
        }

        // --- Explode to Raw Materials ---
        if (isset($raw_boms[$part_id])) {
            foreach ($raw_boms[$part_id] as $raw) {
                $raw_id = (int)$raw['RawMaterialItemID'];
                $raw_qty_per_gr = (float)$raw['QuantityGram'];
                $raw_key = 'RAW_' . $raw_id;

                // Raw demand is based on the net quantity we need to build for *this* part
                $raw_demand_gr = $qty_to_build_children * $raw_qty_per_gr;

                if (!isset($total_raw_demand[$raw_key])) {
                    $total_raw_demand[$raw_key] = [
                        'RawID' => $raw_id, 
                        'Name' => $all_parts[$raw_id]['ItemName'] ?? 'ناشناخته', 
                        'TotalDemandGR' => 0
                    ];
                }
                $total_raw_demand[$raw_key]['TotalDemandGR'] += $raw_demand_gr;
            }
        }
    }
}


// --- (Main API Logic) ---
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if (!has_permission('planning.mrp.run')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $selected_order_ids = $input['orders'] ?? [];
    $run_date = $input['run_date'] ?? date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? null;

    if (empty($selected_order_ids)) {
        throw new Exception('هیچ سفارشی برای محاسبه انتخاب نشده است.');
    }
    
    $pdo->beginTransaction(); // Start transaction for creating RunID

    // 0. Create a new MRP Run record (Initial Status: 'New')
    $insert_run_sql = "INSERT INTO tbl_planning_mrp_run (RunDate, RunByUserID, Status) VALUES (?, ?, 'New')";
    $stmt_run_insert = $pdo->prepare($insert_run_sql);
    $stmt_run_insert->execute([$run_date, $user_id]);
    $newly_created_run_id = $pdo->lastInsertId();

    // --- 1. Load All Static Data into Memory ---
    
    $all_parts_raw = find_all($pdo, "SELECT PartID, PartName, FamilyID, SizeID FROM tbl_parts");
    $all_parts = array_column($all_parts_raw, null, 'PartID');

    $all_statuses_raw = find_all($pdo, "SELECT StatusID, StatusName FROM tbl_part_statuses");
    $all_statuses = array_column($all_statuses_raw, 'StatusName', 'StatusID');
    // Add default status for NULL case
    $all_statuses[0] = '-- بدون وضعیت --';

    $all_raw_raw = find_all($pdo, "SELECT ItemID, ItemName FROM tbl_raw_items");
    $all_raw = array_column($all_raw_raw, null, 'ItemID');
    
    $weight_query = "
        SELECT pw.PartID, pw.WeightGR 
        FROM tbl_part_weights pw
        INNER JOIN (
            SELECT PartID, MAX(EffectiveFrom) AS MaxDate
            FROM tbl_part_weights
            WHERE (EffectiveTo IS NULL OR EffectiveTo >= CURDATE()) AND WeightGR > 0
            GROUP BY PartID
        ) AS latest_weight 
        ON pw.PartID = latest_weight.PartID AND pw.EffectiveFrom = latest_weight.MaxDate
        WHERE (pw.EffectiveTo IS NULL OR pw.EffectiveTo >= CURDATE()) AND pw.WeightGR > 0
    ";
    $part_weights_raw = find_all($pdo, $weight_query);
    $part_weights = array_column($part_weights_raw, 'WeightGR', 'PartID');
    
    $boms_raw = find_all($pdo, "SELECT * FROM tbl_bom_structure");
    $boms = [];
    foreach ($boms_raw as $bom) {
        $boms[$bom['ParentPartID']][] = $bom;
    }
    
    $raw_boms_raw = find_all($pdo, "SELECT * FROM tbl_part_raw_materials");
    $raw_boms = [];
    foreach ($raw_boms_raw as $raw_bom) {
        $raw_boms[$raw_bom['PartID']][] = $raw_bom;
    }

    // --- 2. Get All Available Supply ---
    $all_supply = get_supply_by_station($pdo, $part_weights);
    $raw_supply = get_raw_supply($pdo);
    
    $wip_supply = $all_supply['station_8']; // Mutable copy for WIP netting
    $finished_good_supply = $all_supply['station_9']; // Mutable copy for FG netting

    // --- 3. (Phase 1) Calculate Gross FG Requirements (Level 0) ---
    $full_detailed_list = []; // This caches every node visit
    $net_shortage_list = []; // This caches only net required items
    $gross_req_for_level_1 = []; // This feeds the next step
    
    // Get and aggregate demand from selected sales orders
    $placeholders = rtrim(str_repeat('?,', count($selected_order_ids)), ',');
    $selected_orders = find_all($pdo, "
        SELECT PartID, SUM(QuantityRequired) AS TotalQuantity 
        FROM tbl_sales_orders 
        WHERE SalesOrderID IN ($placeholders)
        GROUP BY PartID
    ", $selected_order_ids);

    foreach ($selected_orders as $order) {
        $part_id = (int)$order['PartID'];
        $gross_demand_qty = (int)$order['TotalQuantity'];
        
        // Find *any* supply for this PartID in Station 9 (Finished Goods)
        $on_hand_fg_qty = 0;
        $on_hand_fg_obj = ['quantity' => 0, 'source_kg' => 0, 'source_carton' => 0, 'unit_weight_gr' => 0];
        
        foreach($finished_good_supply as $key => $details) {
            if (strpos($key, 'PART_' . $part_id . '_STATUS_') === 0) {
                 $on_hand_fg_qty += $details['quantity'];
                 $on_hand_fg_obj['quantity'] += $details['quantity'];
                 $on_hand_fg_obj['source_kg'] += $details['source_kg'];
                 $on_hand_fg_obj['source_carton'] += $details['source_carton'];
                 $on_hand_fg_obj['unit_weight_gr'] = $details['unit_weight_gr']; // Assume same weight
            }
        }
        
        $net_qty_for_fg = $gross_demand_qty - $on_hand_fg_qty;

        $qty_to_build = 0;
        if ($net_qty_for_fg > 0) {
            $qty_to_build = $net_qty_for_fg;
        }

        // Add this Finished Good to the FULL report
        $part_name = $all_parts[$part_id]['PartName'] ?? 'ناشناخته';
        $fg_report_item = [
            'ItemType' => 'محصول نهایی',
            'ItemID' => $part_id,
            'ItemStatusID' => null, // Final product has no required status
            'ItemStatusName' => '-- نهایی --',
            'ItemName' => $part_name . ' <small class="text-muted">(سفارش مشتری)</small>',
            'GrossRequirement' => round($gross_demand_qty),
            'AvailableSupply' => round($on_hand_fg_qty),
            'NetRequirement' => round($qty_to_build),
            'Unit' => 'عدد',
            'Supply_Source_KG' => (float)($on_hand_fg_obj['source_kg'] ?? 0),
            'Supply_Source_Carton' => (int)($on_hand_fg_obj['source_carton'] ?? 0),
            'Supply_Unit_Weight_GR' => (float)($on_hand_fg_obj['unit_weight_gr'] ?? 0)
        ];
        $full_detailed_list[] = $fg_report_item;
        if ($qty_to_build > 0) {
            $net_shortage_list[] = $fg_report_item;

            // If we need to build, add it to the list for Level 1 explosion
            if (!isset($gross_req_for_level_1[$part_id])) {
                $gross_req_for_level_1[$part_id] = 0;
            }
            $gross_req_for_level_1[$part_id] += $qty_to_build;
        }
    }

    // --- 4. (Phase 2) Explode BOM for Net FG and Net against WIP (Level 1+) ---
    $total_raw_demand = []; // This will be populated by the recursive function

    foreach ($gross_req_for_level_1 as $parent_part_id => $parent_net_qty) {
        if (isset($boms[$parent_part_id])) {
            foreach ($boms[$parent_part_id] as $child) {
                $child_id = (int)$child['ChildPartID'];
                // Note: If RequiredStatusID is NULL, the check below handles it as 0.
                $child_req_status_id = (int)($child['RequiredStatusID'] ?? 0);
                $child_qty_per = (float)$child['QuantityPerParent'];
                
                // Call the recursive function for each *first-level* child
                explode_bom_and_net_wip(
                    $child_id,
                    $child_req_status_id,
                    $parent_net_qty * $child_qty_per, // Gross demand for this child
                    $full_detailed_list,
                    $net_shortage_list,
                    $wip_supply, 
                    $total_raw_demand,
                    $boms,
                    $raw_boms,
                    $all_parts,
                    $all_statuses
                );
            }
        }
    }

    // --- 5. (Phase 3) Net Raw Material Requirements ---
    foreach ($total_raw_demand as $raw_key => $demand_data) {
        $raw_id = $demand_data['RawID'];
        $gross_demand_gr = $demand_data['TotalDemandGR'];
        
        $on_hand_raw_obj = $raw_supply[$raw_key] ?? ['quantity' => 0, 'source_kg' => 0];
        $on_hand_raw_gr = $on_hand_raw_obj['quantity']; // Already in grams

        $net_qty_raw_gr = $gross_demand_gr - $on_hand_raw_gr;
        $net_qty_raw_kg = $net_qty_raw_gr / 1000;

        $raw_report_item = [
            'ItemType' => 'ماده اولیه',
            'ItemID' => $raw_id,
            'ItemStatusID' => null,
            'ItemStatusName' => '-- خام --',
            'ItemName' => $demand_data['Name'],
            'GrossRequirement' => round($gross_demand_gr / 1000, 2),
            'AvailableSupply' => round($on_hand_raw_gr / 1000, 2),
            'NetRequirement' => round($net_qty_raw_kg > 0 ? $net_qty_raw_kg : 0, 2),
            'Unit' => 'KG',
            'Supply_Source_KG' => (float)($on_hand_raw_obj['source_kg'] ?? 0),
            'Supply_Source_Carton' => 0,
            'Supply_Unit_Weight_GR' => 0 
        ];
        
        $full_detailed_list[] = $raw_report_item;
        
        if ($net_qty_raw_kg > 0) {
            $net_shortage_list[] = $raw_report_item;
        }
    }
    
    $pdo->commit(); // Commit the transaction to save the new RunID

    $response['success'] = true;
    $response['data'] = [
        'run_id' => $newly_created_run_id,
        'net_requirements' => $net_shortage_list,
        'full_report' => $full_detailed_list // Send all calculated data
    ];

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); } // Rollback the RunID creation
    http_response_code($e->getCode() > 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
