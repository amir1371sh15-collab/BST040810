<?php
// api/get_scheduling_inputs.php
// این API موجودی WIP (WIP Ready) و نیازمندی‌های خالص MRP را برای برنامه‌ریزی روزانه آماده می‌کند.
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// Station ID Constant (Only loading WIP from Station 8: Anbar Monfaseleh)
define('WIP_STATION_ID', 8);

$response = ['success' => false, 'message' => '', 'data' => ['wip_ready' => []]];

try {
    if (!has_permission('planning.production_schedule.view')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    $ready_for_scheduling = [];
    
    // --- ۱. بارگذاری موجودی WIP در انبار نیمه‌ساخته (Station 8) ---
    $wip_query = "
        SELECT 
            t.PartID, 
            t.StatusAfterID AS CurrentStatusID, 
            p.FamilyID,
            p.PartName, 
            p.PartCode, 
            ps.StatusName,
            SUM(t.NetWeightKG) AS TotalWeightKG,
            SUM(t.CartonQuantity) AS TotalCartonQuantity
        FROM tbl_stock_transactions t
        JOIN tbl_parts p ON p.PartID = t.PartID
        LEFT JOIN tbl_part_statuses ps ON ps.StatusID = t.StatusAfterID
        WHERE t.ToStationID = ? -- فقط انبار منفصله (WIP)
        GROUP BY t.PartID, t.StatusAfterID, p.FamilyID, p.PartName, p.PartCode, ps.StatusName
        HAVING TotalWeightKG > 0.01 OR TotalCartonQuantity > 0
    ";
     $wip_data = find_all($pdo, $wip_query, [WIP_STATION_ID]);

    // --- ۲. پردازش موجودی WIP برای تعیین ایستگاه بعدی ---
    foreach ($wip_data as $item) {
        $part_id = (int)$item['PartID'];
        $current_status_id = $item['CurrentStatusID'] === null ? 0 : (int)$item['CurrentStatusID'];
        $family_id = (int)$item['FamilyID'];

        // --- تعیین واحد و مقدار ---
        $unit_name = 'KG';
        $available_quantity = (float)$item['TotalWeightKG'];
        if ($item['TotalCartonQuantity'] > 0 && (int)$item['TotalWeightKG'] <= 0) {
            $unit_name = 'کارتن';
            $available_quantity = (int)$item['TotalCartonQuantity'];
        }
        
        // --- یافتن ایستگاه بعدی (گامی که RequiredStatusID آن برابر با CurrentStatusID است) ---
        // کوئری برای یافتن مسیرهای استاندارد یا مجاز فعال: RequiredStatusID باید با CurrentStatusID ما تطابق داشته باشد.
        // اگر CurrentStatusID برابر 0 باشد، به دنبال RequiredStatusID IS NULL می‌گردیم.
        $status_condition = ($current_status_id === 0) ? 'r.RequiredStatusID IS NULL' : 'r.RequiredStatusID = ?';
        $status_condition_override = ($current_status_id === 0) ? 'ro.RequiredStatusID IS NULL' : 'ro.RequiredStatusID = ?';
        
        $params_wip = [$family_id];
        if ($current_status_id !== 0) {
            $params_wip[] = $current_status_id;
        }
        $params_wip = array_merge($params_wip, $params_wip); // Double params for UNION

        $next_step_sql = "
            (SELECT 
                'Standard' as SourceType, r.ToStationID, s.StationName, r.NewStatusID as NextStatusID, r.StepNumber
             FROM tbl_routes r
             JOIN tbl_stations s ON r.ToStationID = s.StationID
             WHERE r.FamilyID = ? AND {$status_condition})
             UNION ALL
            (SELECT 
                'Override' as SourceType, ro.ToStationID, s.StationName, ro.OutputStatusID as NextStatusID, ro.StepNumber
             FROM tbl_route_overrides ro
             JOIN tbl_stations s ON ro.ToStationID = s.StationID
             WHERE ro.FamilyID = ? AND {$status_condition_override} AND ro.IsActive = 1)
             ORDER BY StepNumber ASC
             LIMIT 1
        ";
        
        $next_step_route = find_all($pdo, $next_step_sql, $params_wip);
        $next_step = $next_step_route ? $next_step_route[0] : null;

        if ($next_step) {
             $next_operation_name = $next_step['StationName']; 
            
            $ready_for_scheduling[] = [
                'Source' => 'WIP (' . $next_step['SourceType'] . ')',
                'PartID' => $part_id,
                'StatusID' => $current_status_id, 
                'PartName' => $item['PartName'],
                'PartCode' => $item['PartCode'],
                'CurrentStatusName' => $item['StatusName'] ?? 'نامشخص',
                'NextStationID' => (int)$next_step['ToStationID'],
                'NextStationName' => $next_step['StationName'],
                'NextOperationName' => $next_operation_name,
                'NextStatusID' => (int)$next_step['NextStatusID'],
                'AvailableQuantity' => $available_quantity,
                'UnitName' => $unit_name
            ];
        }
    }
    
    // --- ۳. بارگذاری نیازمندی‌های خالص MRP (فاز ۱) ---
    $last_run_raw = find_all($pdo, "SELECT * FROM tbl_planning_mrp_run ORDER BY RunDate DESC LIMIT 1");
    $last_run = $last_run_raw[0] ?? null; 
    $last_run_id = $last_run['RunID'] ?? 0;

    if ($last_run_id > 0) {
        $net_reqs = find_all($pdo, "
            SELECT 
                r.ItemID as PartID, 
                r.NetRequirement as NetQuantity, 
                r.Unit,
                p.PartName, 
                p.FamilyID
            FROM tbl_planning_mrp_results r
            JOIN tbl_parts p ON p.PartID = r.ItemID
            WHERE r.RunID = ? AND r.ItemType != 'ماده اولیه' AND r.NetRequirement > 0
        ", [$last_run_id]);

        foreach ($net_reqs as $item) {
            $part_id = (int)$item['PartID'];
            $family_id = (int)$item['FamilyID'];

            // اولین گام تولیدی این قطعه (RequiredStatusID IS NULL) را پیدا می‌کنیم.
            $first_route_sql = "
                SELECT r.ToStationID, s.StationName, r.NewStatusID as NextStatusID, r.StepNumber
                FROM tbl_routes r
                JOIN tbl_stations s ON r.ToStationID = s.StationID
                WHERE r.FamilyID = ? AND r.RequiredStatusID IS NULL
                ORDER BY r.StepNumber ASC
                LIMIT 1
            ";
            $first_route_raw = find_all($pdo, $first_route_sql, [$family_id]);
            $first_route = $first_route_raw[0] ?? null; 

            if ($first_route) {
                 $next_operation_name = $first_route['StationName'];
                
                $ready_for_scheduling[] = [
                    'Source' => 'MRP (RunID: ' . $last_run_id . ')',
                    'PartID' => $part_id,
                    'PartCode' => $item['PartCode'] ?? '',
                    'StatusID' => 0, // وضعیت مبدا: صفر (شروع تولید از ماده خام)
                    'CurrentStatusName' => 'کسری MRP',
                    'NextStationID' => (int)$first_route['ToStationID'],
                    'NextStationName' => $first_route['StationName'],
                    'NextOperationName' => $next_operation_name,
                    'NextStatusID' => (int)$first_route['NextStatusID'],
                    'AvailableQuantity' => (float)$item['NetQuantity'],
                    'UnitName' => $item['Unit']
                ];
            }
        }
    }


    $response['success'] = true;
    $response['data']['wip_ready'] = $ready_for_scheduling;

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'خطا در بارگذاری موجودی برای برنامه‌ریزی: ' . $e->getMessage();
    error_log("API Error in get_scheduling_inputs.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

?>