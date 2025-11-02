<?php
// api/delete_vibration_rule.php
require_once __DIR__ . '/../config/init.php';

// Set header to JSON
header('Content-Type: application/json; charset=utf-8');

// Initialize response array
$response = ['success' => false, 'message' => 'خطای ناشناخته.'];

try {
    // 1. Check permissions
    if (!has_permission('planning_constraints.manage')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    // 2. Check HTTP Method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('متد درخواست نامعتبر است.', 405);
    }

    // 3. Get and validate IDs
    $primary_part_id = filter_input(INPUT_POST, 'primary_part_id', FILTER_VALIDATE_INT);
    $incompatible_part_id = filter_input(INPUT_POST, 'incompatible_part_id', FILTER_VALIDATE_INT);

    if (empty($primary_part_id) || empty($incompatible_part_id)) {
        throw new Exception('شناسه‌های قطعه نامعتبر هستند.');
    }

    // 4. Database Transaction
    $pdo->beginTransaction();

    // 4a. Delete the (A -> B) rule
    $stmt1 = $pdo->prepare("DELETE FROM tbl_planning_vibration_incompatibility WHERE PrimaryPartID = ? AND IncompatiblePartID = ?");
    $stmt1->execute([$primary_part_id, $incompatible_part_id]);

    // 4b. Delete the reverse (B -> A) rule
    $stmt2 = $pdo->prepare("DELETE FROM tbl_planning_vibration_incompatibility WHERE PrimaryPartID = ? AND IncompatiblePartID = ?");
    $stmt2->execute([$incompatible_part_id, $primary_part_id]);

    // 4c. Commit transaction
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'قانون ناسازگاری با موفقیت حذف شد.';

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Set appropriate HTTP status code
    $code = ($e->getCode() >= 400) ? $e->getCode() : 500;
    http_response_code($code); 
    
    $response['message'] = $e->getMessage();
}

// 5. Echo final JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);

