<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

// --- Permission Check ---
if (!has_permission('engineering.maintenance.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'شما مجوز انجام این عملیات را ندارید.']);
    exit;
}

$item_type = $_POST['item_type'] ?? null;
$name = trim($_POST['name'] ?? '');
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if (empty($item_type) || empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'نوع یا نام آیتم جدید مشخص نشده است.']);
    exit;
}

$pdo->beginTransaction();
try {
    $new_id = null;
    
    if ($item_type === 'breakdown') {
        $result = insert_record($pdo, 'tbl_maintenance_breakdown_types', ['Description' => $name]);
        if (!$result['success']) throw new Exception('خطا در ثبت نوع خرابی جدید.');
        $new_id = $result['id'];

    } elseif ($item_type === 'cause') {
        if (empty($parent_id)) throw new Exception('شناسه خرابی والد برای ثبت علت جدید الزامی است.');
        $result = insert_record($pdo, 'tbl_maintenance_causes', ['CauseDescription' => $name]);
        if (!$result['success']) throw new Exception('خطا در ثبت علت جدید.');
        $new_id = $result['id'];
        // Auto-link to parent breakdown
        $pdo->prepare("INSERT IGNORE INTO tbl_maintenance_breakdown_cause_links (BreakdownTypeID, CauseID) VALUES (?, ?)")
            ->execute([$parent_id, $new_id]);

    } elseif ($item_type === 'action') {
        if (empty($parent_id)) throw new Exception('شناسه علت والد برای ثبت اقدام جدید الزامی است.');
        $result = insert_record($pdo, 'tbl_maintenance_actions', ['ActionDescription' => $name]);
        if (!$result['success']) throw new Exception('خطا در ثبت اقدام جدید.');
        $new_id = $result['id'];
        // Auto-link to parent cause
        $pdo->prepare("INSERT IGNORE INTO tbl_maintenance_cause_action_links (CauseID, ActionID) VALUES (?, ?)")
            ->execute([$parent_id, $new_id]);
    } else {
        throw new Exception('نوع آیتم نامعتبر است.');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'new_id' => $new_id, 'new_name' => $name]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
