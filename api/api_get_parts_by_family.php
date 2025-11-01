<?php
header('Content-Type: application/json; charset=utf-8');
// Corrected: Include init.php for $pdo and helpers, adjust path
require_once __DIR__ . '/../config/init.php'; // Corrected path

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['family_id']) || !is_numeric($_GET['family_id'])) {
    $response['message'] = 'شناسه خانواده نامعتبر است.';
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$familyId = (int)$_GET['family_id'];

try {
    // $pdo is now available via init.php
    $stmt = $pdo->prepare("SELECT PartID, PartName FROM tbl_parts WHERE FamilyID = ? ORDER BY PartName");
    $stmt->execute([$familyId]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $parts;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_parts_by_family.php: " . $e->getMessage());
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>

