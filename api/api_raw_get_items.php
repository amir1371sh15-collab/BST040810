<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Changed path

$response = ['success' => false, 'data' => null, 'message' => ''];

// Keep permission from parent module
if (!has_permission('planning.manage') && !has_permission('warehouse.raw.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null; // New filter

try {
    $sql = "SELECT i.ItemID, i.ItemName, c.CategoryName, u.Symbol as UnitSymbol
            FROM tbl_raw_items i
            JOIN tbl_raw_categories c ON i.CategoryID = c.CategoryID
            JOIN tbl_units u ON i.UnitID = u.UnitID";
    
    $params = [];
    if ($category_id) {
        $sql .= " WHERE i.CategoryID = ?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY c.CategoryName, i.ItemName";
    
    // The find_all function returns a flat array, which is exactly
    // what the JavaScript in raw_stocktake.php expects.
    $items_raw = find_all($pdo, $sql, $params);
    
    /* * --- BLOCK REMOVED (FIX) ---
     * The grouping logic below was causing the error.
     * The JavaScript in raw_stocktake.php expects a flat array (response.data)
     * but this code was creating an object (response.data = {"CategoryName": [...]})
     * which caused response.data.length to fail.
     *
    // Group by category for <optgroup>
    $grouped_items = [];
    foreach ($items_raw as $item) {
        $category = $item['CategoryName'] ?? 'سایر';
        if (!isset($grouped_items[$category])) {
            $grouped_items[$category] = [];
        }
        $grouped_items[$category][] = [
            'ItemID' => $item['ItemID'],
            'ItemName' => $item['ItemName'],
            'UnitSymbol' => $item['UnitSymbol']
        ];
    }
    */

    $response['success'] = true;
    // (FIX) Assign the flat array ($items_raw) directly to the data property.
    $response['data'] = $items_raw; 

} catch (Exception $e) {
    error_log("API Get Raw Materials (BOM) Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>