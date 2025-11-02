<?php
// --- Robust API Start ---
ob_start(); // Start output buffering
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    if (!has_permission('planning_constraints.manage')) {
        throw new Exception('شما مجوز دسترسی به این اطلاعات را ندارید.', 403);
    }

    $family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);
    if (empty($family_id)) {
        throw new Exception('شناسه خانواده نامعتبر است.', 400);
    }

    $parts = find_all($pdo, "SELECT PartID, PartName FROM tbl_parts WHERE FamilyID = ? ORDER BY PartName", [$family_id]);
    
    $response['success'] = true;
    $response['data'] = $parts;

} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
} catch (Exception $e) {
    // Application error (like permissions or bad input)
    http_response_code($e->getCode() > 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

// --- Robust API End ---
ob_end_clean(); // Clean (discard) any output, notices, or warnings
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>

