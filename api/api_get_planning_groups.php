<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!has_permission('planning.constraints.view')) { // Simple view permission
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    $groups = find_all($pdo, "SELECT GroupID, GroupName FROM tbl_planning_plating_groups ORDER BY GroupName");
    
    $response['success'] = true;
    $response['data'] = $groups;
    $response['message'] = 'گروه‌ها با موفقیت دریافت شدند.';

} catch (Exception $e) {
    error_log("API Get Plating Groups Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

