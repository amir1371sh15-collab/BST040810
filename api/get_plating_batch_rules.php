<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';



$input = json_decode(file_get_contents("php://input"), true);
$part_ids = $input['part_ids'] ?? [];

if (empty($part_ids) || !is_array($part_ids)) {
    echo json_encode(['success' => false, 'message' => 'هیچ ID قطعه‌ای ارسال نشده است.']);
    exit;
}

// پاکسازی ID ها
$part_ids = array_map('intval', $part_ids);
$placeholders = implode(',', array_fill(0, count($part_ids), '?'));

$response = [
    'success' => true,
    'data' => [
        'part_details' => [],
        'compatibility_rules' => [],
        'vibration_rules' => []
    ]
];

try {
    // 1. واکشی جزئیات قطعات (شامل وزن بارل تکی)
    $stmt_parts = $pdo->prepare("
        SELECT PartID, PartName, BarrelWeight_Solo_KG 
        FROM tbl_parts 
        WHERE PartID IN ($placeholders)
    ");
    $stmt_parts->execute($part_ids);
    $parts = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);
    foreach ($parts as $part) {
        $response['data']['part_details'][$part['PartID']] = $part;
    }

    // 2. واکشی قوانین سازگاری بچینگ (که هر دو قطعه در لیست انتخابی ما باشند)
    $stmt_compat = $pdo->prepare("
        SELECT PrimaryPartID, CompatiblePartID, PrimaryPartWeight_KG, CompatiblePartWeight_KG 
        FROM tbl_planning_batch_compatibility
        WHERE PrimaryPartID IN ($placeholders) AND CompatiblePartID IN ($placeholders)
    ");
    $stmt_compat->execute(array_merge($part_ids, $part_ids));
    // استفاده از (FETCH_NUM) برای ارسال آرایه عددی فشرده به JS
    $response['data']['compatibility_rules'] = $stmt_compat->fetchAll(PDO::FETCH_NUM); 

    // 3. واکشی قوانین ناسازگاری ویبره (که هر دو قطعه در لیست انتخابی ما باشند)
    $stmt_vibrate = $pdo->prepare("
        SELECT PrimaryPartID, IncompatiblePartID
        FROM tbl_planning_vibration_incompatibility
        WHERE PrimaryPartID IN ($placeholders) AND IncompatiblePartID IN ($placeholders)
    ");
    $stmt_vibrate->execute(array_merge($part_ids, $part_ids));
    $response['data']['vibration_rules'] = $stmt_vibrate->fetchAll(PDO::FETCH_NUM);

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Get Plating Batch Rules Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در واکشی قوانین بچینگ: ' . $e->getMessage()]);
}
?>
