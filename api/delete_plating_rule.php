<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر است.', 405);
    }
    if (!has_permission('planning_constraints.manage')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    $primary_part_id = filter_input(INPUT_POST, 'primary_part_id', FILTER_VALIDATE_INT);
    $compatible_part_id = filter_input(INPUT_POST, 'compatible_part_id', FILTER_VALIDATE_INT);

    if (empty($primary_part_id) || empty($compatible_part_id)) {
        throw new Exception('شناسه‌های قطعه نامعتبر هستند.');
    }

    $pdo->beginTransaction();

    // Delete A -> B
    $stmt = $pdo->prepare("DELETE FROM tbl_planning_batch_compatibility WHERE PrimaryPartID = ? AND CompatiblePartID = ?");
    $stmt->execute([$primary_part_id, $compatible_part_id]);

    // Delete B -> A (the reverse rule)
    $stmt = $pdo->prepare("DELETE FROM tbl_planning_batch_compatibility WHERE PrimaryPartID = ? AND CompatiblePartID = ?");
    $stmt->execute([$compatible_part_id, $primary_part_id]);

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'قانون با موفقیت حذف شد.';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code($e->getCode() > 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>

