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
    $stmt = $pdo->prepare("
        SELECT 
            d.ProductionKG,
            p.PartName,
            m.MachineName,
            mo.MoldName
        FROM tbl_prod_daily_log_details d
        JOIN tbl_parts p ON d.PartID = p.PartID
        JOIN tbl_machines m ON d.MachineID = m.MachineID
        LEFT JOIN tbl_molds mo ON d.MoldID = mo.MoldID
        WHERE d.HeaderID = ?
        ORDER BY m.MachineName, p.PartName
    ");
    $stmt->execute([$headerId]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = $details;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_production_details.php: " . $e->getMessage());
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}