<?php
// api/generate_production_plan.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * Finds the *next* production step based on the current status
 */
function find_next_station($pdo, $part_id, $current_status_id) {
    // 1. Find the family of the part
    $family_stmt = $pdo->prepare("SELECT FamilyID FROM tbl_parts WHERE PartID = ?");
    $family_stmt->execute([$part_id]);
    $family_id = $family_stmt->fetchColumn();
    if (!$family_id) {
        return null; // Part has no family, cannot find route
    }

    // 2. Find the *source* station for this status
    // We look for a route that *outputs* this status
    $route_sql = "
        SELECT FromStationID, ToStationID, NewStatusID
        FROM tbl_routes
        WHERE FamilyID = ? AND NewStatusID = ?
        LIMIT 1
    ";
    $route_stmt = $pdo->prepare($route_sql);
    $route_stmt->execute([$family_id, $current_status_id]);
    $source_route = $route_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source_route) {
        // Maybe it's a non-standard route? Check overrides
        $override_sql = "
            SELECT FromStationID, ToStationID, OutputStatusID
            FROM tbl_route_overrides
            WHERE FamilyID = ? AND OutputStatusID = ? AND IsActive = 1
            LIMIT 1
        ";
        $override_stmt = $pdo->prepare($override_sql);
        $override_stmt->execute([$family_id, $current_status_id]);
        $source_route = $override_stmt->fetch(PDO::FETCH_ASSOC);
        if(!$source_route) {
             return null; // Cannot find where this status comes from
        }
    }

    // 3. Now find the *next* step *from* that station
    $current_station_id = $source_route['ToStationID'];
    
    $next_route_sql = "
        SELECT ToStationID, NewStatusID 
        FROM tbl_routes 
        WHERE FamilyID = ? AND FromStationID = ?
        LIMIT 1
    ";
    $next_stmt = $pdo->prepare($next_route_sql);
    $next_stmt->execute([$family_id, $current_station_id]);
    $next_step = $next_stmt->fetch(PDO::FETCH_ASSOC);

    if ($next_step) {
        return [
            'NextStationID' => $next_step['ToStationID'],
            'TargetStatusID' => $next_step['NewStatusID']
        ];
    }
    
    // Check overrides as well
     $next_override_sql = "
        SELECT ToStationID, OutputStatusID 
        FROM tbl_route_overrides 
        WHERE FamilyID = ? AND FromStationID = ? AND IsActive = 1
        LIMIT 1
    ";
    $next_override_stmt = $pdo->prepare($next_override_sql);
    $next_override_stmt->execute([$family_id, $current_station_id]);
    $next_override_step = $next_override_stmt->fetch(PDO::FETCH_ASSOC);

     if ($next_override_step) {
        return [
            'NextStationID' => $next_override_step['ToStationID'],
            'TargetStatusID' => $next_override_step['OutputStatusID']
        ];
    }

    return null; // No next step found
}


$response = ['success' => false, 'message' => ''];
$pdo->beginTransaction();

try {
    if (!has_permission('planning.mrp.run')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.');
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $run_id = $input['run_id'] ?? 0;
    $selected_reqs_ids = $input['net_requirements'] ?? [];
    $selected_wip_items = $input['wip_items'] ?? [];

    if (empty($run_id) || (empty($selected_reqs_ids) && empty($selected_wip_items))) {
        throw new Exception('اطلاعات ورودی ناقص است.');
    }
    
    // Load all stations for name mapping
    $all_stations_raw = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations");
    $all_stations = array_column($all_stations_raw, 'StationName', 'StationID');
    
    // Clear old work orders for this run to prevent duplicates
    $delete_stmt = $pdo->prepare("DELETE FROM tbl_planning_work_orders WHERE RunID = ?");
    $delete_stmt->execute([$run_id]);

    $work_orders = [];
    $today = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+7 days')); // Default due date: 1 week

    // --- 1. Process Net Requirements ---
    if (!empty($selected_reqs_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($selected_reqs_ids)), ',');
        $net_reqs = find_all($pdo, "
            SELECT * FROM tbl_planning_mrp_results 
            WHERE RunID = ? AND ResultID IN ($placeholders)
        ", array_merge([$run_id], $selected_reqs_ids));

        foreach ($net_reqs as $req) {
            if ($req['ItemType'] == 'ماده اولیه') {
                // TODO: Handle raw material purchase orders (Phase 3)
                continue;
            }
            
            $part_id = (int)$req['ItemID'];
            $required_status_id = (int)$req['ItemStatusID']; // This is the *output* status we need
            
            // We need to find the station that *produces* this status
            $route_info = find_next_station($pdo, $part_id, $required_status_id); // This is a helper, but we need the *source* station
            
            // Simplified logic: Find the station that *produces* this status
            // This is a complex query, for now, let's find the route that *outputs* this
             $source_route_sql = "
                SELECT FromStationID, ToStationID 
                FROM tbl_routes r
                JOIN tbl_parts p ON r.FamilyID = p.FamilyID
                WHERE p.PartID = ? AND r.NewStatusID = ?
                LIMIT 1
            ";
            $source_stmt = $pdo->prepare($source_route_sql);
            $source_stmt->execute([$part_id, $required_status_id]);
            $source_route = $source_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$source_route) {
                // Cannot find a standard route. This logic needs improvement, but skip for now.
                continue; 
            }
            
            $station_id = (int)$source_route['ToStationID']; // The station that *does the work*
            $input_status_id = (int)$source_route['FromStationID']; // This is complex, simplification:
            
            // Find the *previous* status
            // This logic is complex. For Phase 2, we just schedule the work at the station.
            // We assume the *input* status is the one *before* this on the route.
            
            // --- (USER LOGIC) ---
            // "برنامه ریزی بر اساس مسیرها باید انجام بشه"
            // This means we only schedule the *first* step.
            // The first step is the one with no prerequisite part (it comes from raw)
            // OR the one with the lowest "level" in the BOM.
            
            // --- SIMPLIFIED LOGIC for this step ---
            // We just create a work order for the *station* that builds the *net required item*.
            
             $work_orders[] = [
                'RunID' => $run_id,
                'StationID' => $station_id,
                'PartID' => $part_id,
                'RequiredStatusID' => $required_status_id, // This is the *target* status
                'TargetStatusID' => $required_status_id, // Simplification
                'Quantity' => $req['NetRequirement'],
                'Unit' => $req['Unit'],
                'DueDate' => $due_date,
                'StationName' => $all_stations[$station_id] ?? 'ناشناخته'
            ];
        }
    }
    
    // --- 2. Process WIP Items ---
    if (!empty($selected_wip_items)) {
         foreach ($selected_wip_items as $wip_item) {
            list($part_id, $current_status_id) = explode(':', $wip_item);
            $part_id = (int)$part_id;
            $current_status_id = (int)$current_status_id;

            // Find the *next* step for this WIP item
            $next_step = find_next_station($pdo, $part_id, $current_status_id);
            
            if ($next_step) {
                $station_id = (int)$next_step['NextStationID'];
                
                // Get the quantity from WIP table (this is inefficient, but necessary)
                $wip_qty_stmt = $pdo->prepare("
                    SELECT SUM(NetWeightKG) AS TotalKG 
                    FROM tbl_stock_transactions 
                    WHERE ToStationID = 8 AND PartID = ? AND StatusAfterID = ?
                ");
                $wip_qty_stmt->execute([$part_id, $current_status_id]);
                $quantity_kg = $wip_qty_stmt->fetchColumn();
                
                 $work_orders[] = [
                    'RunID' => $run_id,
                    'StationID' => $station_id,
                    'PartID' => $part_id,
                    'RequiredStatusID' => $current_status_id, // The *input* status
                    'TargetStatusID' => (int)$next_step['TargetStatusID'], // The *output* status
                    'Quantity' => $quantity_kg,
                    'Unit' => 'KG', // WIP is always in KG
                    'DueDate' => $due_date,
                    'StationName' => $all_stations[$station_id] ?? 'ناشناخته'
                ];
            }
        }
    }
    
    // --- 3. (USER LOGIC) Filter for Dependency ---
    // "برنامه ریزی برای مونتاژش بی معناست"
    // This is the most complex part. We must only create WOs for stations
    // whose *input* materials are available.
    
    // For now, we create all WOs and will refine this in the next step.
    // This simplified API saves all potential work.
    
    $wo_sql = "
        INSERT INTO tbl_planning_work_orders
        (RunID, StationID, PartID, RequiredStatusID, TargetStatusID, Quantity, Unit, DueDate)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $wo_stmt = $pdo->prepare($wo_sql);
    
    $work_orders_by_station = [];

    foreach ($work_orders as $wo) {
        $wo_stmt->execute([
            $wo['RunID'],
            $wo['StationID'],
            $wo['PartID'],
            $wo['RequiredStatusID'],
            $wo['TargetStatusID'],
            $wo['Quantity'],
            $wo['Unit'],
            $wo['DueDate']
        ]);
        $work_orders_by_station[$wo['StationName']][] = $wo;
    }


    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'برنامه اولیه تولید با موفقیت ایجاد شد.';
    $response['data']['work_orders_by_station'] = $work_orders_by_station;

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
