<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => 'شناسه گزارش نامعتبر است.'];

if (!has_permission('production.assembly_hall.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده خلاصه روزانه را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

$header_id = isset($_GET['header_id']) && is_numeric($_GET['header_id']) ? (int)$_GET['header_id'] : null;

if (!$header_id) {
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    // 1. Get Header Info
    // <<< ADD PackagingHeaderID to SELECT >>>
    $header_stmt = $pdo->prepare("SELECT PackagingHeaderID, LogDate, AvailableTimeMinutes, Description FROM tbl_packaging_log_header WHERE PackagingHeaderID = ?");
    $header_stmt->execute([$header_id]);
    $header = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        http_response_code(404);
        $response['message'] = 'گزارش بسته‌بندی یافت نشد.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
    }

    // 2. Get Shifts (Personnel with times)
    $shifts_stmt = $pdo->prepare("
        SELECT e.name as EmployeeName, pls.StartTime, pls.EndTime -- <<< Corrected alias >>>
        FROM tbl_packaging_log_shifts pls
        JOIN tbl_employees e ON pls.EmployeeID = e.EmployeeID
        WHERE pls.PackagingHeaderID = ?
        ORDER BY e.name
    ");
    $shifts_stmt->execute([$header_id]);
    $shifts = $shifts_stmt->fetchAll(PDO::FETCH_ASSOC); // Renamed from $personnel
     // Format times for consistency in modal display
    foreach ($shifts as &$shift) {
        $shift['StartTimeFmt'] = $shift['StartTime'] ? date('H:i', strtotime($shift['StartTime'])) : null;
        $shift['EndTimeFmt'] = $shift['EndTime'] ? date('H:i', strtotime($shift['EndTime'])) : null;
    }
    unset($shift); // Unset reference


    // 3. Get Details (Carton counts)
    $details_stmt = $pdo->prepare("
        SELECT pld.CartonsPackaged, p.PartName
        FROM tbl_packaging_log_details pld
        JOIN tbl_parts p ON pld.PartID = p.PartID
        WHERE pld.PackagingHeaderID = ? AND pld.CartonsPackaged > 0
        ORDER BY p.PartName
    ");
    $details_stmt->execute([$header_id]);
    $details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_cartons = array_sum(array_column($details, 'CartonsPackaged'));

    // 4. Combine data
    $response['success'] = true;
    $response['data'] = [
        'header_id' => $header['PackagingHeaderID'], // <<< ADD header_id >>>
        'log_date_jalali' => to_jalali($header['LogDate']),
        'available_time' => $header['AvailableTimeMinutes'], // <<< Corrected key name >>>
        'description' => $header['Description'],
        'shifts' => $shifts, // Changed key name
        'details' => $details,
        'total_cartons' => (int)$total_cartons // Ensure integer
    ];
    $response['message'] = '';

} catch (Exception $e) {
    error_log("API Get Packaging Daily Summary Error: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای داخلی سرور در محاسبه خلاصه روزانه بسته‌بندی.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
