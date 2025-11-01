<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function create_api_response(bool $success, $data = [], string $message = ''): string {
    return json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
}

if (!isset($_GET['report_id']) || !is_numeric($_GET['report_id'])) {
    http_response_code(400);
    echo create_api_response(false, [], 'شناسه گزارش نامعتبر است.');
    exit;
}

$report_id = (int)$_GET['report_id'];

try {
    $sql = "
        SELECT 
            b.Description as Breakdown, 
            c.CauseDescription as Cause, 
            a.ActionDescription as Action
        FROM tbl_maintenance_report_entries e
        JOIN tbl_maintenance_breakdown_types b ON e.BreakdownTypeID = b.BreakdownTypeID
        JOIN tbl_maintenance_causes c ON e.CauseID = c.CauseID
        JOIN tbl_maintenance_actions a ON e.ActionID = a.ActionID
        WHERE e.ReportID = ?
        ORDER BY b.Description, c.CauseDescription, a.ActionDescription
    ";
    
    $details = find_all($pdo, $sql, [$report_id]);

    // Group the results for easier display
    $structured_data = [];
    foreach ($details as $row) {
        if (!isset($structured_data[$row['Breakdown']])) {
            $structured_data[$row['Breakdown']] = [];
        }
        if (!isset($structured_data[$row['Breakdown']][$row['Cause']])) {
            $structured_data[$row['Breakdown']][$row['Cause']] = [];
        }
        $structured_data[$row['Breakdown']][$row['Cause']][] = $row['Action'];
    }

    echo create_api_response(true, $structured_data, 'اطلاعات با موفقیت دریافت شد.');

} catch (Exception $e) {
    http_response_code(500);
    echo create_api_response(false, [], $e->getMessage());
}
