<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function create_api_response(bool $success, $data = [], string $message = ''): string {
    return json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
}

$deptId = isset($_GET['department_id']) && is_numeric($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$typeId = isset($_GET['tool_type_id']) && is_numeric($_GET['tool_type_id']) ? (int)$_GET['tool_type_id'] : null;

try {
    $sql = "SELECT ToolID, ToolName, ToolCode FROM tbl_eng_tools WHERE 1=1";
    $params = [];

    if ($deptId !== null) {
        $sql .= " AND DepartmentID = ?";
        $params[] = $deptId;
    }
    if ($typeId !== null) {
        $sql .= " AND ToolTypeID = ?";
        $params[] = $typeId;
    }
    $sql .= " ORDER BY ToolName";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tools = $stmt->fetchAll();
    
    echo create_api_response(true, $tools, 'ابزارها با موفقیت یافت شدند.');

} catch (PDOException $e) {
    error_log("API Error in api_get_eng_tools.php: " . $e->getMessage());
    http_response_code(500);
    echo create_api_response(false, [], 'خطای سرور در دریافت اطلاعات.');
}
