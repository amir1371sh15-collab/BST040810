<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and DB

$response = ['success' => false, 'data' => [], 'message' => 'تاریخ مشخص نشده است.'];

// Basic permission check
if (!has_permission('production.assembly_hall.view')) { // Use view permission
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده تاریخچه را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Get date from query string (expecting Jalali)
$log_date_jalali = $_GET['log_date_jalali'] ?? null; // Expect Jalali date like '1403/07/25'

if (!$log_date_jalali) {
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Convert Jalali date to Gregorian for DB query
$log_date_gregorian = to_gregorian($log_date_jalali);
if (!$log_date_gregorian) {
    http_response_code(400);
    $response['message'] = 'فرمت تاریخ نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Query to get history for the specific date
    $sql = "
        SELECT
            h.PackagingHeaderID,
            h.LogDate,
            (SELECT COUNT(*) FROM tbl_packaging_log_shifts s WHERE s.PackagingHeaderID = h.PackagingHeaderID) as PersonnelCount,
            COALESCE((SELECT SUM(d.CartonsPackaged) FROM tbl_packaging_log_details d WHERE d.PackagingHeaderID = h.PackagingHeaderID), 0) as TotalCartons
        FROM tbl_packaging_log_header h
        WHERE h.LogDate = ?
        ORDER BY h.PackagingHeaderID DESC -- Or however you want to order if multiple entries per day were possible
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$log_date_gregorian]);
    $history_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format date back to Jalali for display
    foreach ($history_data as &$row) {
        $row['LogDateJalali'] = to_jalali($row['LogDate']);
    }
    unset($row); // Unset reference

    $response['success'] = true;
    $response['data'] = $history_data;
    $response['message'] = '';

} catch (Exception $e) {
    error_log("API Get Packaging History Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['message'] = 'خطای داخلی سرور در واکشی تاریخچه بسته‌بندی.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
