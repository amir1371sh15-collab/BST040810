<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$response = ['success' => false, 'data' => []];

if (!isset($_GET['mold_id']) || !is_numeric($_GET['mold_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه قالب نامعتبر است.';
    echo json_encode($response);
    exit;
}

$moldId = (int)$_GET['mold_id'];

try {
    $stmt = $pdo->prepare("SELECT PartID FROM tbl_mold_producible_parts WHERE MoldID = ?");
    $stmt->execute([$moldId]);
    
    // Fetch just the PartID column into a simple array
    $partIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $response['success'] = true;
    $response['data'] = $partIds;
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
    echo json_encode($response);
}