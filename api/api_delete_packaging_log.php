<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and DB

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

// Basic permission check
if (!has_permission('production.assembly_hall.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز حذف را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_header_id']) || !is_numeric($_POST['delete_header_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه گزارش نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$header_id_to_delete = (int)$_POST['delete_header_id'];

// Deleting the header will automatically cascade delete related personnel and details due to FOREIGN KEY constraints
$result = delete_record($pdo, 'tbl_packaging_log_header', $header_id_to_delete, 'PackagingHeaderID');

if ($result['success']) {
    $response['success'] = true;
    $response['message'] = 'گزارش بسته‌بندی با موفقیت حذف شد.';
    // Set flash message for redirection
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = 'success';
} else {
    http_response_code(500);
    $response['message'] = $result['message']; // Use the message from delete_record
    // Set flash message for redirection
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = 'danger';
}

// Redirect back to the packaging page (or send JSON response if preferred)
// Sending JSON and relying on JS to redirect might be slightly cleaner
// header("Location: " . BASE_URL . "modules/production/assembly_hall/packaging.php");
// exit;
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
