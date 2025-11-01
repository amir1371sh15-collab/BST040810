<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['machine_id']) || !is_numeric($_GET['machine_id'])) {
    $response['message'] = 'شناسه دستگاه نامعتبر است.';
    echo json_encode($response);
    exit;
}

$machineId = (int)$_GET['machine_id'];

try {
    $stmt = $pdo->prepare("
        SELECT m.MoldID, m.MoldName 
        FROM tbl_molds m
        JOIN tbl_mold_machine_compatibility mmc ON m.MoldID = mmc.MoldID
        WHERE mmc.MachineID = ?
        ORDER BY m.MoldName
    ");
    $stmt->execute([$machineId]);
    $molds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $molds;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
