<?php
// Set headers FIRST
header('Content-Type: application/json; charset=utf-8');

// Include init to start session and get base functions
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

// Log initial request and session state
error_log("api_update_session.php called. Session ID: " . session_id());
error_log("Initial \$_SESSION state: " . print_r($_SESSION, true));


// Allow access only via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'فقط متد POST مجاز است.';
    error_log("Method not POST. Request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user has permission (Using a general permission, adjust if needed)
// Added warehouse.raw.view
if (!has_permission('warehouse.view') && !has_permission('production.assembly_hall.view') && !has_permission('warehouse.raw.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز بروزرسانی اطلاعات را ندارید.';
    error_log("Permission denied for user ID: " . ($_SESSION['user_id'] ?? 'Not logged in'));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}


$key = $_POST['key'] ?? null;
$value = $_POST['value'] ?? null;

// Log received key and value
error_log("Received key: " . print_r($key, true));
error_log("Received value: " . print_r($value, true));


// Validate the key to prevent arbitrary session modification
$allowed_keys = [
    'assembly_available_time',
    'assembly_daily_plan',
    'assembly_log_date',
    'assembly_description',
    'rolling_available_time',
    'rolling_log_date',
    'rolling_description',
    'packaging_available_time',
    'packaging_log_date',
    'packaging_description',
    'warehouse_transaction_date', // For main warehouse transactions
    'stocktake_log_date',         // For main warehouse stocktake
    'misc_stocktake_log_date',    // For misc warehouse stocktake
    'misc_transaction_date',       // For misc warehouse transactions
    'raw_stocktake_log_date',     // *** NEW ***
    'raw_transaction_date'        // *** NEW ***
];

if ($key && in_array($key, $allowed_keys) && isset($value)) {
    try {
        // Log before update
        error_log("Updating session key '{$key}' from '" . ($_SESSION[$key] ?? 'NOT SET') . "' to '{$value}'");

        $_SESSION[$key] = $value;

        // Log after update
        error_log("Session key '{$key}' updated. New \$_SESSION state: " . print_r($_SESSION, true));

        $response['success'] = true;
        $response['message'] = 'Session updated.';
    } catch (Exception $e) {
        http_response_code(500);
        $response['message'] = 'خطای سرور هنگام بروزرسانی Session.';
        error_log("Session update error for key '$key': " . $e->getMessage());
    }
} else {
    http_response_code(400);
    $response['message'] = 'کلید یا مقدار نامعتبر برای بروزرسانی Session.';
    error_log("Invalid key ('{$key}') or value ('{$value}') provided.");
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
