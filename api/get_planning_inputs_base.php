<?php
// api/get_planning_inputs_base.php
// این API موجودی WIP و نیازمندی‌های خالص MRP را برای نمایش اولیه آماده می‌کند.
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// Station ID Constant
define('WIP_STATION_ID', 8);

$response = ['success' => false, 'message' => '', 'data' => ['input_items' => []]];

try {
    if (!has_permission('planning.production_schedule.view')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    $input_items = [];
    
    // --- ۱. بارگذاری موجودی WIP در انبار نیمه‌ساخته (Station 8) ---
    // این بخش موجودی‌های WIP را واکشی می‌کند.
    $wip_query = "
        SELECT 
            t.PartID, 
            t.StatusAfterID AS CurrentStatusID, 
            p.PartName, 
            p.PartCode, 
            ps.StatusName,
            SUM(t.NetWeightKG) AS TotalWeightKG,
            SUM(t.CartonQuantity) AS TotalCartonQuantity
        FROM tbl_stock_transactions t
        JOIN tbl_parts p ON p.PartID = t.PartID
        LEFT JOIN tbl_part_statuses ps ON ps.StatusID = t.StatusAfterID
        WHERE t.ToStationID = ? -- فقط انبار منفصله (WIP)
        GROUP BY t.PartID, t.StatusAfterID, p.PartName, p.PartCode, ps.StatusName
        HAVING TotalWeightKG > 0.01 OR TotalCartonQuantity > 0
    ";
    $wip_data = find_all($pdo, $wip_query, [WIP_STATION_ID]);

    foreach ($wip_data as $item) {
        $unit_name = (float)$item['TotalWeightKG'] > 0 ? 'KG' : 'کارتن';
        $available_quantity = (float)$item['TotalWeightKG'] > 0 ? (float)$item['TotalWeightKG'] : (int)$item['TotalCartonQuantity'];

        $input_items[] = [
            'Key' => $item['PartID'] . '_' . ($item['CurrentStatusID'] ?? 0) . '_WIP', // کلید: PartID_StatusID_WIP
            'Source' => 'موجودی WIP',
            'PartID' => (int)$item['PartID'],
            'CurrentStatusID' => $item['CurrentStatusID'] === null ? 0 : (int)$item['CurrentStatusID'],
            'PartName' => $item['PartName'],
            'CurrentStatusName' => $item['StatusName'] ?? '--- بدون وضعیت ---',
            'AvailableQuantity' => $available_quantity,
            'UnitName' => $unit_name
        ];
    }

    // --- ۲. بارگذاری نیازمندی‌های خالص MRP (فاز ۱) ---
    // 1. یافتن آخرین RunID موفق
    $last_run_sql = "SELECT RunID FROM tbl_planning_mrp_run WHERE Status = 'Completed' ORDER BY RunID DESC LIMIT 1";
    $last_run_id = find_all($pdo, $last_run_sql)[0]['RunID'] ?? 0;

    if ($last_run_id > 0) {
        // 2. واکشی کسری خالص (NetRequirement > 0) برای آن RunID
        $net_reqs = find_all($pdo, "
            SELECT 
                r.ItemID as PartID, 
                r.NetRequirement as NetQuantity, 
                r.Unit,
                p.PartName, 
                p.PartCode -- اضافه کردن PartCode برای اطلاعات بیشتر
            FROM tbl_planning_mrp_results r
            JOIN tbl_parts p ON p.PartID = r.ItemID
            WHERE r.RunID = ? AND r.ItemType != 'ماده اولیه' AND r.NetRequirement > 0
        ", [$last_run_id]);

        foreach ($net_reqs as $item) {
            $input_items[] = [
                'Key' => $item['PartID'] . '_0_MRP_' . $last_run_id, // کلید: PartID_0_MRP_RunID
                'Source' => 'کسری MRP',
                'PartID' => (int)$item['PartID'],
                'CurrentStatusID' => 0, // وضعیت مبدا: صفر (شروع تولید از ماده خام/کسری)
                'PartName' => $item['PartName'] . ' (' . ($item['PartCode'] ?? 'N/A') . ')',
                'CurrentStatusName' => 'کسری MRP (شروع تولید)',
                'AvailableQuantity' => (float)$item['NetQuantity'],
                'UnitName' => $item['Unit']
            ];
        }
    }


    $response['success'] = true;
    $response['data']['input_items'] = $input_items;

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'خطا در بارگذاری موجودی و کسری: ' . $e->getMessage();
    error_log("API Error in get_planning_inputs_base.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
