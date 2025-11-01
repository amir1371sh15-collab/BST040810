<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => ''];

if (!has_permission('warehouse.inventory.snapshot')) { // Use the specific permission
    http_response_code(403);
    $response['message'] = 'شما مجوز ثبت عکس لحظه‌ای موجودی را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'فقط متد POST مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    $family_id = filter_input(INPUT_POST, 'family_id', FILTER_VALIDATE_INT) ?: null;
    $part_id = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT) ?: null;
    $status_id_str = $_POST['status_id'] ?? null; // Get the status filter
    $inventory_data_json = $_POST['inventory_data'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$inventory_data_json || !$user_id) {
        throw new Exception('اطلاعات ارسالی برای ثبت ناقص است (کاربر یا داده‌های موجودی).');
    }
    
    // Determine the status ID to store (can be null)
    $status_id = null;
    if ($status_id_str === 'NULL') {
        $status_id = null;
    } elseif (is_numeric($status_id_str)) {
        $status_id = (int)$status_id_str;
    }

    // Validate JSON data
    $inventory_data = json_decode($inventory_data_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($inventory_data)) {
         throw new Exception('فرمت داده‌های موجودی ارسال شده نامعتبر است.');
    }

    $data_to_insert = [
        'RecordedByUserID' => $user_id,
        'FilterFamilyID' => $family_id,
        'FilterPartID' => $part_id,
        'FilterStatusID' => $status_id, // Store the filtered status ID (can be null)
        'InventoryData' => $inventory_data_json // Store the original JSON string
    ];

    $result = insert_record($pdo, 'tbl_inventory_snapshots', $data_to_insert);

    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'عکس لحظه‌ای موجودی با موفقیت ثبت شد.';
    } else {
        throw new Exception($result['message']);
    }

} catch (Exception $e) {
    error_log("API Record Snapshot Error: " . $e->getMessage() . " | Input: " . print_r($_POST, true));
    $response['message'] = 'خطای سرور هنگام ثبت: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
