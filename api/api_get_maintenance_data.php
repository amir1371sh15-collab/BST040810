<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function create_api_response(bool $success, $data = [], string $message = ''): string {
    return json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
}

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($type) || $id === 0) {
    http_response_code(400);
    echo create_api_response(false, [], 'پارامترهای ورودی نامعتبر است.');
    exit;
}

try {
    $data = [];
    if ($type === 'get_causes') {
        $sql = "SELECT c.CauseID, c.CauseDescription 
                FROM tbl_maintenance_causes c
                JOIN tbl_maintenance_breakdown_cause_links l ON c.CauseID = l.CauseID
                WHERE l.BreakdownTypeID = ?
                ORDER BY c.CauseDescription";
        $data = find_all($pdo, $sql, [$id]);
    } elseif ($type === 'get_actions') {
        $sql = "SELECT a.ActionID, a.ActionDescription 
                FROM tbl_maintenance_actions a
                JOIN tbl_maintenance_cause_action_links l ON a.ActionID = l.ActionID
                WHERE l.CauseID = ?
                ORDER BY a.ActionDescription";
        $data = find_all($pdo, $sql, [$id]);
    } else {
        throw new Exception("نوع درخواست نامعتبر است.");
    }
    echo create_api_response(true, $data, 'اطلاعات با موفقیت دریافت شد.');

} catch (Exception $e) {
    http_response_code(500);
    echo create_api_response(false, [], $e->getMessage());
}
