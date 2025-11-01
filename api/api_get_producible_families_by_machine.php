<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and PDO

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['machine_id']) || !is_numeric($_GET['machine_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه دستگاه نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$machineId = (int)$_GET['machine_id'];

try {
    $sql = "SELECT pf.FamilyID, pf.FamilyName
            FROM tbl_part_families pf
            JOIN tbl_machine_producible_families mpf ON pf.FamilyID = mpf.FamilyID
            WHERE mpf.MachineID = ?
            ORDER BY pf.FamilyName";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$machineId]);
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $families;
    if (empty($families)) {
        $response['message'] = 'هیچ خانواده محصولی برای این دستگاه تعریف نشده است.';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_producible_families_by_machine.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
