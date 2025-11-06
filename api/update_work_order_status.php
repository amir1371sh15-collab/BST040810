<?php
// api/update_work_order_status.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';


$work_order_id = $_POST['work_order_id'] ?? null;
$new_status = $_POST['new_status'] ?? null;

if (empty($work_order_id) || !is_numeric($work_order_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه دستور کار نامعتبر است.']);
    exit;
}

$valid_statuses = ['Generated', 'InProgress', 'Completed', 'Cancelled'];
if (empty($new_status) || !in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'وضعیت انتخاب شده نامعتبر است.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tbl_planning_work_orders SET Status = ? WHERE WorkOrderID = ?");
    $stmt->execute([$new_status, $work_order_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'وضعیت با موفقیت به‌روزرسانی شد.']);
    } else {
        // این حالت ممکن است رخ دهد اگر وضعیت قبلی با وضعیت جدید یکی باشد
        echo json_encode(['success' => true, 'message' => 'وضعیت بدون تغییر باقی ماند.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Update Work Order Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای دیتابیس: ' . $e->getMessage()]);
}
?>
