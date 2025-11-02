<?php
include_once '../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Access Denied. Please login.']);
    exit;
}
// check_permission('planning_constraints.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;

if ($rule_id === 0) {
    echo json_encode(['success' => false, 'error' => 'شناسه قانون نامعتبر است.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM tbl_planning_station_capacity_rules WHERE RuleID = ?");
    $stmt->execute([$rule_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'قانون با موفقیت حذف شد.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'قانون مورد نظر یافت نشد.']);
    }

} catch (Exception $e) {
    error_log("Error in delete_capacity_rule.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

