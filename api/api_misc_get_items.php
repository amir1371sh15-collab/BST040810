<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    $category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
    
    $sql = "SELECT mi.ItemID, mi.ItemName, u.Symbol as UnitSymbol, mi.SafetyStock
            FROM tbl_misc_items mi
            JOIN tbl_units u ON mi.UnitID = u.UnitID";
    $params = [];

    if ($category_id) {
        $sql .= " WHERE mi.CategoryID = ?";
        $params[] = $category_id;
    }
    $sql .= " ORDER BY mi.ItemName";

    $items = find_all($pdo, $sql, $params);

    $response['success'] = true;
    $response['data'] = $items;

} catch (Exception $e) {
    error_log("API Error in api_misc_get_items.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
