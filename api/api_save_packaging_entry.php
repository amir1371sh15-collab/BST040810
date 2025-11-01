<?php
// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');
// Include the main initialization file (handles DB connection, helpers, session)
require_once __DIR__ . '/../config/init.php';

// Initialize the response array
$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

// --- Permission Check ---
// Ensure the user has permission to manage packaging entries
if (!has_permission('production.assembly_hall.manage')) { // Using assembly hall manage permission
    http_response_code(403); // Forbidden
    $response['message'] = 'شما مجوز انجام این عملیات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Method Check ---
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'فقط متد POST مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Data Reading and Basic Validation ---
// Read field names corresponding to the form in packaging.php
$log_date_jalali = $_POST['log_date_field'] ?? null;
$available_time = !empty($_POST['available_time_field']) ? (int)$_POST['available_time_field'] : null;
$description = trim($_POST['description_field'] ?? '');
$personnel_shifts = $_POST['personnel_shifts'] ?? [];
$packaged_cartons = $_POST['packaged_cartons'] ?? []; // Array of [PartID => CartonCount]

// Header ID for update (not used in the current frontend logic for initial creation)
// $header_id_to_update = !empty($_POST['header_id']) ? (int)$_POST['header_id'] : null;

// Check if at least one personnel shift with an employee ID is provided
$has_personnel = false;
foreach ($personnel_shifts as $shift) {
    if (!empty($shift['employee_id'])) {
        $has_personnel = true;
        break;
    }
}

// More specific validation messages
$errors = [];
if (empty($log_date_jalali)) {
    $errors[] = "تاریخ";
}
// Make available time optional or adjust validation if needed
// if (empty($available_time) || !is_numeric($available_time) || $available_time <= 0) {
//     $errors[] = "زمان در دسترس (باید عدد مثبت باشد)";
// }
if (!$has_personnel) {
    // If personnel is optional, remove this check
    // $errors[] = "حداقل یک نفر پرسنل";
}
// Check if at least one carton count is entered (optional validation)
$has_cartons = false;
foreach ($packaged_cartons as $count) {
    if (is_numeric($count) && (int)$count > 0) {
        $has_cartons = true;
        break;
    }
}
if (!$has_cartons) {
     $errors[] = "حداقل یک تعداد کارتن";
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'خطا: فیلد(های) ' . implode('، ', $errors) . ' الزامی/نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Convert date *after* basic validation
$log_date_gregorian = to_gregorian($log_date_jalali);
if (!$log_date_gregorian) {
    http_response_code(400); // Bad Request
    $response['message'] = 'خطا: فرمت تاریخ نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Start database transaction
$pdo->beginTransaction();
try {
    // --- Step 1: Determine Header ID (Insert or Find/Update Existing) ---
    $header_id = null;
    $header_data = [
        'LogDate' => $log_date_gregorian,
        'AvailableTimeMinutes' => $available_time, // Can be null if optional
        'Description' => $description
    ];

    // Check if a header already exists for this date
    $existingHeaderStmt = $pdo->prepare("SELECT PackagingHeaderID FROM tbl_packaging_log_header WHERE LogDate = ?");
    $existingHeaderStmt->execute([$log_date_gregorian]);
    $existingHeaderId = $existingHeaderStmt->fetchColumn();

    if ($existingHeaderId) {
        // Update existing header (e.g., if available time or description changed)
        $header_id = $existingHeaderId;
        $updateResult = update_record($pdo, 'tbl_packaging_log_header', $header_data, $header_id, 'PackagingHeaderID');
        if (!$updateResult['success']) {
            throw new Exception("خطا در به‌روزرسانی هدر گزارش بسته‌بندی موجود.");
        }
        $response['message'] = 'آمار بسته‌بندی با موفقیت به‌روزرسانی شد.'; // Update success message
    } else {
        // Insert new header
        $insertResult = insert_record($pdo, 'tbl_packaging_log_header', $header_data);
        if (!$insertResult['success']) {
            throw new Exception("خطا در ایجاد هدر گزارش بسته‌بندی.");
        }
        $header_id = $insertResult['id'];
        $response['message'] = 'آمار بسته‌بندی با موفقیت ثبت شد.'; // Insert success message
    }


    // --- Step 2: Update Personnel Shifts (Delete and Re-insert) ---
    // 1. Delete existing shifts for this header
    $deleteShiftsStmt = $pdo->prepare("DELETE FROM tbl_packaging_log_shifts WHERE PackagingHeaderID = ?");
    $deleteShiftsStmt->execute([$header_id]);

    // 2. Insert selected shifts
    $insertShiftStmt = $pdo->prepare("INSERT INTO tbl_packaging_log_shifts (PackagingHeaderID, EmployeeID, StartTime, EndTime) VALUES (?, ?, ?, ?)");
    foreach ($personnel_shifts as $shift) {
        $emp_id = $shift['employee_id'] ?? null;
        if (!empty($emp_id) && is_numeric($emp_id)) {
            // Add seconds ':00' if time is provided
            $start_time = !empty($shift['start_time']) ? $shift['start_time'] . ':00' : null;
            $end_time = !empty($shift['end_time']) ? $shift['end_time'] . ':00' : null;
            $insertShiftStmt->execute([$header_id, (int)$emp_id, $start_time, $end_time]);
        }
    }

    // --- Step 3: Update Details (Delete and Re-insert) ---
    // Note: This part saves the CARTON COUNT. It does not fetch or depend on the base weight source (tbl_parts vs tbl_part_weights).
    // Weight calculations based on these counts happen elsewhere (e.g., reports, inventory calculations).
    // 1. Delete existing details for this header
    $deleteDetailsStmt = $pdo->prepare("DELETE FROM tbl_packaging_log_details WHERE PackagingHeaderID = ?");
    $deleteDetailsStmt->execute([$header_id]);

    // 2. Insert new details where cartons > 0
    $insertDetailStmt = $pdo->prepare("INSERT INTO tbl_packaging_log_details (PackagingHeaderID, PartID, CartonsPackaged) VALUES (?, ?, ?)");
    foreach ($packaged_cartons as $part_id => $cartons_str) {
        // Ensure cartons is numeric and positive
        $cartons = is_numeric($cartons_str) ? (int)$cartons_str : 0;
        if ($cartons > 0 && is_numeric($part_id)) { // Validate part_id as well
            $insertDetailStmt->execute([$header_id, (int)$part_id, $cartons]);
        }
    }

    // Commit the transaction
    $pdo->commit();
    $response['success'] = true;
    // Message is set based on insert/update status above

} catch (Exception $e) {
    // An error occurred, rollback
    $pdo->rollBack();
    // Log the detailed error
    error_log("API Save Packaging Error: " . $e->getMessage() . " | Data: " . print_r($_POST, true));
    http_response_code(500); // Internal Server Error
    // Provide a more user-friendly error message
    $response['message'] = 'خطای داخلی سرور هنگام ذخیره‌سازی رخ داد. لطفاً دوباره امتحان کنید یا با پشتیبانی تماس بگیرید.';
    // $response['message'] = 'خطای داخلی سرور: ' . $e->getMessage(); // Uncomment for detailed debugging info
}

// Send the JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
