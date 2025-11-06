<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

try {
    $partId = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT);

    if (!$partId) {
        throw new Exception("Part ID نامعتبر است.");
    }

    // 1. یافتن خانواده قطعه
    // [FIX] آرگومان چهارم (کلید اصلی) به تابع اضافه شد
    $part = find_by_id($pdo, 'tbl_parts', $partId, 'PartID');
    if (!$part || empty($part['FamilyID'])) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'خانواده قطعه یافت نشد.']);
        exit;
    }
    $partFamilyId = $part['FamilyID'];

    // 2. یافتن نام خانواده برای تشخیص نوع (پیچ یا پرس)
    // [FIX] آرگومان چهارم (کلید اصلی) به تابع اضافه شد
    $family = find_by_id($pdo, 'tbl_part_families', $partFamilyId, 'FamilyID');
    $familyName = $family['FamilyName'] ?? '';

    // 3. [منطق جدید] تعیین نوع دستگاه بر اساس نام خانواده
    $machineTypeToFind = '';
    if (strpos($familyName, 'پیچ') !== false) {
        $machineTypeToFind = 'پیچ سازی';
    } else {
        // اگر پیچ نبود، فرض می‌کنیم پرس است
        $machineTypeToFind = 'پرس';
    }

    // 4. یافتن تمام ماشین‌های فعال از آن نوع
    $sql = "
        SELECT 
            m.MachineID,
            m.MachineName,
            m.MachineType,
            CASE 
                WHEN m.MachineType = 'پرس' THEN 2
                WHEN m.MachineType = 'پیچ سازی' THEN 6
                ELSE NULL 
            END AS StationID
        FROM tbl_machines m
        WHERE m.MachineType = ? AND m.Status = 'Active' -- [FIX] فقط ماشین‌های فعال
        ORDER BY m.MachineName
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$machineTypeToFind]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($machines)) {
        echo json_encode(['success' => true, 'data' => [], 'message' => "هیچ ماشینی از نوع '$machineTypeToFind' یافت نشد."]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $machines]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

