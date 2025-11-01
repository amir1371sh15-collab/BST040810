<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['machine_id']) || !is_numeric($_GET['machine_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه دستگاه نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$machineId = (int)$_GET['machine_id'];

try {
    // Find parts whose family is linked to the given machine
    $sql = "SELECT p.PartID, p.PartName, pf.FamilyName
            FROM tbl_parts p
            JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
            JOIN tbl_machine_producible_families mpf ON pf.FamilyID = mpf.FamilyID
            WHERE mpf.MachineID = ?
            ORDER BY pf.FamilyName, p.PartName"; // Order by family then part name

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$machineId]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($parts) {
        $response['success'] = true;
        $response['data'] = $parts;
    } else {
        $response['success'] = false; // Indicate failure if no parts found
        $response['message'] = 'هیچ قطعه قابل تولیدی برای این دستگاه یافت نشد (یا خانواده‌ای به آن تخصیص داده نشده).';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_producible_parts_by_machine.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
