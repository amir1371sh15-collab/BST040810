<?php
// api/get_mrp_results.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!has_permission('planning.mrp.run')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$run_id = filter_input(INPUT_GET, 'run_id', FILTER_VALIDATE_INT);

if (empty($run_id)) {
    http_response_code(400);
    $response['message'] = 'شناسه اجرا (RunID) مشخص نشده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Fetch only net requirements (کسری)
    $results = find_all($pdo, "
        SELECT ItemType, ItemName, NetRequirement, Unit
        FROM tbl_planning_mrp_results 
        WHERE RunID = ? AND NetRequirement > 0
        ORDER BY ItemType, ItemName
    ", [$run_id]);
    
    $response['success'] = true;
    $response['data'] = $results;
    if (empty($results)) {
        $response['message'] = 'هیچ نیازمندی خالصی (کسری) برای این اجرا ثبت نشده است.';
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    error_log("Get MRP Results Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

