<?php
// api/delete_mrp_run.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if (!has_permission('planning.mrp.run')) { // یا یک دسترسی مدیریتی قوی‌تر
    http_response_code(403);
    $response['message'] = 'شما مجوز حذف اجراهای MRP را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['run_id']) || !is_numeric($_POST['run_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه اجرای (RunID) نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$run_id_to_delete = (int)$_POST['run_id'];

try {
    // به لطف ON DELETE CASCADE در دیتابیس،
    // حذف از tbl_planning_mrp_run باید رکوردهای tbl_planning_mrp_results
    // و tbl_planning_work_orders را نیز حذف کند.
    
    $result = delete_record($pdo, 'tbl_planning_mrp_run', $run_id_to_delete, 'RunID');
    
    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = "اجرای MRP با شناسه $run_id_to_delete و تمام نتایج و دستور کارهای مرتبط با آن با موفقیت حذف شد.";
    } else {
        throw new Exception($result['message']);
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'خطای سرور هنگام حذف: ' . $e->getMessage();
    error_log("Delete MRP Run Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

