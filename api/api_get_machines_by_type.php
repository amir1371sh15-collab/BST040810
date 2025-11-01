<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['machine_type']) || empty($_GET['machine_type'])) {
    $response['message'] = 'نوع دستگاه مشخص نشده است.';
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$machineType = $_GET['machine_type'];

try {
    // Join with tbl_machine_current_setup to get the last known mold for each machine
    $stmt = $pdo->prepare("
        SELECT 
            m.MachineID, 
            m.MachineName,
            mcs.CurrentMoldID 
        FROM tbl_machines m
        LEFT JOIN tbl_machine_current_setup mcs ON m.MachineID = mcs.MachineID
        WHERE m.MachineType = ? 
        ORDER BY m.MachineID ASC
    ");
    $stmt->execute([$machineType]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = $machines;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_machines_by_type.php: " . $e->getMessage());
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

