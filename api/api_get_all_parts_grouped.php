<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Includes PDO and helpers

$response = ['success' => false, 'data' => [], 'message' => ''];

// Check permission (e.g., planning or base_info view)
if (!has_permission('planning.view') && !has_permission('base_info.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    $sql = "SELECT p.PartID, p.PartName, pf.FamilyName 
            FROM tbl_parts p
            LEFT JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
            ORDER BY pf.FamilyName, p.PartName";
    
    $parts_raw = find_all($pdo, $sql);
    
    // Group by family for <optgroup>
    $grouped_parts = [];
    foreach ($parts_raw as $part) {
        $family = $part['FamilyName'] ?? 'سایر';
        if (!isset($grouped_parts[$family])) {
            $grouped_parts[$family] = [];
        }
        $grouped_parts[$family][] = [
            'PartID' => $part['PartID'],
            'PartName' => $part['PartName']
        ];
    }

    $response['success'] = true;
    $response['data'] = $grouped_parts;

} catch (Exception $e) {
    error_log("API Get All Parts Grouped Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
