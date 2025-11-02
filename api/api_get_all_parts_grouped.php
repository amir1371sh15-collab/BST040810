<?php
// api/api_get_all_parts_grouped.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// --- Permission Check ---
// ما به هر دو مجوز نیاز داریم، چون این API در هر دو صفحه استفاده می شود
if (!has_permission('planning.view') && !has_permission('planning_constraints.manage')) {
    echo json_encode(['success' => false, 'message' => 'شما مجوز دسترسی به این اطلاعات را ندارید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$family_id = $_GET['family_id'] ?? null;
$params = [];

$sql = "SELECT p.PartID, p.PartName, p.PartCode, pf.FamilyName 
        FROM tbl_parts p 
        JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID";

// --- FIX: افزودن فیلتر WHERE ---
if ($family_id) {
    $sql .= " WHERE p.FamilyID = ?";
    $params[] = $family_id;
}
// --- End FIX ---

$sql .= " ORDER BY pf.FamilyName, p.PartName";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedParts = [];
    foreach ($parts as $part) {
        $groupedParts[$part['FamilyName']][] = $part;
    }

    echo json_encode(['success' => true, 'data' => $groupedParts], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطای دیتابیس: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>

