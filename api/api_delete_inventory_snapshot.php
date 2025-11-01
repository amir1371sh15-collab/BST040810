<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => ''];

if (!has_permission('warehouse.inventory.snapshot')) { // Use the specific permission
    http_response_code(403);
    $response['message'] = 'شما مجوز حذف عکس لحظه‌ای موجودی را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'فقط متد POST مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    $snapshot_id = filter_input(INPUT_POST, 'snapshot_id', FILTER_VALIDATE_INT);

    if (!$snapshot_id) {
        throw new Exception('شناسه عکس لحظه‌ای برای حذف نامعتبر است.');
    }

    $result = delete_record($pdo, 'tbl_inventory_snapshots', $snapshot_id, 'SnapshotID');

    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'عکس لحظه‌ای موجودی با موفقیت حذف شد.';
    } else {
        throw new Exception($result['message'] ?: 'خطا در حذف رکورد از پایگاه داده.');
    }

} catch (Exception $e) {
    error_log("API Delete Snapshot Error: " . $e->getMessage() . " | Input: " . print_r($_POST, true));
    $response['message'] = 'خطای سرور هنگام حذف: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
