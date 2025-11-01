<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['header_id']) || !is_numeric($_GET['header_id'])) {
    $response['message'] = 'شناسه گزارش نامعتبر است.';
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$headerId = (int)$_GET['header_id'];

try {
    $header_stmt = $pdo->prepare("SELECT LogDate, MachineType FROM tbl_prod_downtime_header WHERE HeaderID = ?");
    $header_stmt->execute([$headerId]);
    $header = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        $response['message'] = 'گزارش توقف یافت نشد.';
        http_response_code(404);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $details_stmt = $pdo->prepare("
        SELECT 
            d.Duration,
            m.MachineName,
            mo.MoldName,
            dr.ReasonDescription
        FROM tbl_prod_downtime_details d
        JOIN tbl_machines m ON d.MachineID = m.MachineID
        JOIN tbl_molds mo ON d.MoldID = mo.MoldID
        JOIN tbl_downtimereasons dr ON d.ReasonID = dr.ReasonID
        WHERE d.HeaderID = ?
        ORDER BY m.MachineName
    ");
    $details_stmt->execute([$headerId]);
    $details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = [
        'log_date' => to_jalali($header['LogDate']),
        'machine_type' => $header['MachineType'],
        'downtimes' => $details
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_downtime_details.php: " . $e->getMessage());
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

