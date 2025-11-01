<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/warehouse_helpers.php'; // Include the helper functions

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

// This API handles GET requests for calculations
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    error_log("Get Warehouse Calculations API called with action: $action");

    // Basic permission check (assuming warehouse view is sufficient)
    if (!has_permission('warehouse.view')) {
        http_response_code(403);
        $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
    }


    try {
        if ($action === 'calculate_details') {
            $part_id = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT);
            $from_station_id = filter_input(INPUT_GET, 'from_station_id', FILTER_VALIDATE_INT);
            $to_station_id = filter_input(INPUT_GET, 'to_station_id', FILTER_VALIDATE_INT);
            $transaction_date_jalali = $_GET['transaction_date'] ?? null;

            if (!$part_id || !$from_station_id || !$to_station_id || !$transaction_date_jalali) {
                error_log("Calculate details failed: Missing parameters.");
                throw new Exception('پارامترهای ورودی ناقص است.');
            }
            error_log("Calling calculate_details with PartID: $part_id, From: $from_station_id, To: $to_station_id, Date: $transaction_date_jalali");
            $details = calculate_details($pdo, $part_id, $from_station_id, $to_station_id, $transaction_date_jalali);
            error_log("Calculate details result: " . json_encode($details));
            $response = ['success' => true, 'data' => $details]; // Overwrite initial response

        } elseif ($action === 'get_relevant_stations') {
             $family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);
             if (!$family_id) {
                 error_log("Get relevant stations failed: Invalid FamilyID.");
                 throw new Exception('شناسه خانواده نامعتبر است.');
             }
             error_log("Calling get_relevant_stations_for_family with FamilyID: $family_id");
             $station_ids = get_relevant_stations_for_family($pdo, $family_id);
             error_log("Get relevant stations result: " . json_encode($station_ids));
             $response = ['success' => true, 'data' => $station_ids]; // Overwrite initial response

        } elseif ($action === 'get_receivers') { // *** بلوک اضافه شده برای رفع خطا ***
            error_log("Calling get_receivers");
            $receivers = find_all($pdo, "SELECT ReceiverID, ReceiverName FROM tbl_receivers ORDER BY ReceiverName");
            error_log("Get receivers result: " . json_encode($receivers));
            $response = ['success' => true, 'data' => $receivers, 'message' => 'لیست تحویل گیرندگان دریافت شد'];

        } else {
             error_log("Invalid action received: $action");
             throw new Exception('اکشن نامعتبر است.');
        }
    } catch (Exception $e) {
        error_log("Get Warehouse Calculations API Exception: " . $e->getMessage());
        http_response_code(400); // Changed to 400 for client-side errors
        $response['message'] = $e->getMessage();
    }
} else {
    // Handle invalid requests (not GET or no action)
    http_response_code(400);
    $response['message'] = 'درخواست نامعتبر یا اکشن مشخص نشده است.';
    error_log("Invalid request to get_warehouse_calculations.php. Method: " . $_SERVER['REQUEST_METHOD'] . ", Action: " . ($_GET['action'] ?? 'Not set'));
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

