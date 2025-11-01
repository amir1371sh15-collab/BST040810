<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and DB

$response = ['success' => false, 'message' => 'درخواست نامعتبر.', 'new_entry_id' => null];

// Basic permission check
if (!has_permission('production.assembly_hall.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز انجام این عملیات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'فقط متد POST مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Data Validation ---
$log_date_str = $_POST['log_date'] ?? null;
$available_time = !empty($_POST['available_time']) ? (int)$_POST['available_time'] : null;
$description = trim($_POST['description'] ?? ''); // Get description
$machine_id = !empty($_POST['machine_id']) ? (int)$_POST['machine_id'] : null;
$operator_id = !empty($_POST['operator_id']) ? (int)$_POST['operator_id'] : null;
$part_id = !empty($_POST['part_id']) ? (int)$_POST['part_id'] : null;
$production_kg_str = $_POST['production_kg'] ?? '';
$start_time = !empty($_POST['start_time']) ? $_POST['start_time'] . ':00' : null;
$end_time = !empty($_POST['end_time']) ? $_POST['end_time'] . ':00' : null;
$entry_id_to_update = isset($_POST['entry_id']) && $_POST['entry_id'] !== 'null' && $_POST['entry_id'] !== '' ? (int)$_POST['entry_id'] : null;

// Required fields check
if (!$log_date_str || !$available_time || !$machine_id || !$operator_id || !$part_id || $production_kg_str === '') {
    http_response_code(400);
    $response['message'] = 'خطا: تاریخ، زمان در دسترس، اپراتور، دستگاه، محصول و میزان تولید الزامی است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_numeric($production_kg_str) || (float)$production_kg_str <= 0) {
    http_response_code(400);
    $response['message'] = 'خطا: میزان تولید باید عددی مثبت باشد.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
$production_kg = (float)$production_kg_str;

$log_date_gregorian = to_gregorian($log_date_str);
if (!$log_date_gregorian) {
    http_response_code(400);
    $response['message'] = 'خطا: فرمت تاریخ نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo->beginTransaction();
try {
    // --- Find or Create/Update Header ---
    $header_id = null;
    $header_data_for_update = [
        'AvailableTimeMinutes' => $available_time,
        'Description' => $description // Include description
    ];

    if ($entry_id_to_update) {
        $stmt = $pdo->prepare("SELECT RollingHeaderID FROM tbl_rolling_log_entries WHERE RollingEntryID = ?");
        $stmt->execute([$entry_id_to_update]);
        $header_id = $stmt->fetchColumn();
        if (!$header_id) throw new Exception("رکورد اصلی برای ویرایش یافت نشد.");
        update_record($pdo, 'tbl_rolling_log_header', $header_data_for_update, $header_id, 'RollingHeaderID');
    } else {
        $header_stmt = $pdo->prepare("SELECT RollingHeaderID FROM tbl_rolling_log_header WHERE LogDate = ?");
        $header_stmt->execute([$log_date_gregorian]);
        $header_id = $header_stmt->fetchColumn();

        if ($header_id) {
            update_record($pdo, 'tbl_rolling_log_header', $header_data_for_update, $header_id, 'RollingHeaderID');
        } else {
            $header_data_for_insert = array_merge(['LogDate' => $log_date_gregorian], $header_data_for_update);
            $header_res = insert_record($pdo, 'tbl_rolling_log_header', $header_data_for_insert);
            if (!$header_res['success']) throw new Exception("خطا در ایجاد هدر گزارش رول.");
            $header_id = $header_res['id'];
        }
    }

    // --- Insert or Update Entry ---
    if ($entry_id_to_update) {
        $stmt = $pdo->prepare("UPDATE tbl_rolling_log_entries SET MachineID=?, OperatorID=?, StartTime=?, EndTime=?, PartID=?, ProductionKG=? WHERE RollingEntryID=?");
        $success = $stmt->execute([$machine_id, $operator_id, $start_time, $end_time, $part_id, $production_kg, $entry_id_to_update]);
        $message = $success ? 'رکورد با موفقیت ویرایش شد.' : 'خطا در ویرایش رکورد.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tbl_rolling_log_entries (RollingHeaderID, MachineID, OperatorID, StartTime, EndTime, PartID, ProductionKG) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$header_id, $machine_id, $operator_id, $start_time, $end_time, $part_id, $production_kg]);
        $message = $success ? 'رکورد با موفقیت ذخیره شد.' : 'خطا در ذخیره رکورد.';
        if ($success) $response['new_entry_id'] = $pdo->lastInsertId();
    }

    if (!$success) {
        throw new Exception($message);
    }

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = $message;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("API Save Rolling Error: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای داخلی سرور: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

