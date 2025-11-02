<?php
// api/get_mrp_inputs.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

if (!has_permission('planning.mrp.run')) {
    echo json_encode(['success' => false, 'message' => 'شما مجوز دسترسی به این اطلاعات را ندارید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = [
    'success' => false,
    'data' => [
        'sales_orders' => [],
        'wip' => []
    ]
];

try {
    // 1. Fetch External Demand (Sales Orders)
    $response['data']['sales_orders'] = find_all($pdo, "
        SELECT so.SalesOrderID, so.PartID, p.PartName, so.QuantityRequired, so.DueDate 
        FROM tbl_sales_orders so
        JOIN tbl_parts p ON so.PartID = p.PartID
        WHERE so.Status = 'Open'
        ORDER BY so.DueDate ASC
    ");

    // 2. Fetch Internal Demand (WIP)
    // --- START EDIT (Based on user logic) ---
    // بر اساس منطق جدید شما، ما فقط موجودی‌هایی را به عنوان تقاضای WIP در نظر می‌گیریم
    // که در انبارهای واسط (انبار منفصله = 8، انبار نهایی = 9) قرار دارند.
    // موجودی در ایستگاه‌های تولیدی (مانند پرسکاری) در حال پردازش هستند و تقاضا محسوب نمی‌شوند.
    $wip_query = "
        SELECT 
            t.PartID,
            p.PartName,
            t.StatusAfterID,
            ps.StatusName,
            t.ToStationID,
            s.StationName,
            SUM(t.NetWeightKG) AS TotalNetWeightKG,
            SUM(t.CartonQuantity) AS TotalCartonQuantity
        FROM tbl_stock_transactions t
        JOIN tbl_parts p ON t.PartID = p.PartID
        LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
        LEFT JOIN tbl_stations s ON t.ToStationID = s.StationID
        WHERE 
            t.ToStationID IN (8, 9) -- 8='انبار منفصله', 9='انبار نهایی'
        GROUP BY t.PartID, t.StatusAfterID, t.ToStationID
        HAVING TotalNetWeightKG > 0.01 OR TotalCartonQuantity > 0
    ";
    // --- END EDIT ---
    
    $stock_stmt = $pdo->prepare($wip_query);
    $stock_stmt->execute();
    $response['data']['wip'] = $stock_stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

