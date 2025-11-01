<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['mold_id']) || !is_numeric($_GET['mold_id'])) {
    $response['message'] = 'شناسه قالب نامعتبر است.';
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$moldId = (int)$_GET['mold_id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.PartID, p.PartName 
        FROM tbl_parts p
        JOIN tbl_mold_producible_parts mpp ON p.PartID = mpp.PartID
        WHERE mpp.MoldID = ?
        ORDER BY p.PartName
    ");
    $stmt->execute([$moldId]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $parts;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_producible_parts_by_mold.php: " . $e->getMessage());
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}