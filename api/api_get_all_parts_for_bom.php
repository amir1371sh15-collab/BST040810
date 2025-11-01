<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!has_permission('planning.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

$exclude_part_id = filter_input(INPUT_GET, 'exclude_part_id', FILTER_VALIDATE_INT) ?: null;
$family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT) ?: null; // New filter

try {
    $sql = "SELECT p.PartID, p.PartName, pf.FamilyName, p.FamilyID 
            FROM tbl_parts p
            JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID";
    
    $where_clauses = [];
    $params = [];
    
    if ($exclude_part_id) {
        $where_clauses[] = "p.PartID != ?";
        $params[] = $exclude_part_id;
    }
    
    if ($family_id) { // Add new family filter
        $where_clauses[] = "p.FamilyID = ?";
        $params[] = $family_id;
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY pf.FamilyName, p.PartName";
    
    $parts_raw = find_all($pdo, $sql, $params);
    
    // Group by family for <optgroup>
    $grouped_parts = [];
    foreach ($parts_raw as $part) {
        $family = $part['FamilyName'] ?? 'سایر';
        if (!isset($grouped_parts[$family])) {
            $grouped_parts[$family] = [];
        }
        $grouped_parts[$family][] = [
            'PartID' => $part['PartID'],
            'PartName' => $part['PartName'],
            'FamilyID' => $part['FamilyID'] // Send family ID back
        ];
    }

    $response['success'] = true;
    $response['data'] = $grouped_parts;

} catch (Exception $e) {
    error_log("API Get All Parts (BOM) Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

