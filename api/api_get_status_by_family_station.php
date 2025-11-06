<?php
// [اصلاح شد] مسیر صحیح به فایل init
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json');



$family_id = $_GET['family_id'] ?? null;
$station_id = $_GET['station_id'] ?? null;

if (!$family_id || !$station_id) {
    echo json_encode(['success' => false, 'message' => 'ID خانواده یا ایستگاه ارائه نشده است.']);
    exit;
}

try {
    // یافتن وضعیت مورد نیاز برای ورود به این ایستگاه
    // این یک پیاده‌سازی ساده است، ممکن است نیاز به منطق پیچیده‌تر بر اساس tbl_routes باشد
    $stmt = $pdo->prepare("
        SELECT RequiredStatusID 
        FROM tbl_routes 
        WHERE FamilyID = ? AND ToStationID = ? 
        ORDER BY StepNumber ASC -- اولین مرحله‌ای که به این ایستگاه می‌آید
        LIMIT 1
    ");
    $stmt->execute([$family_id, $station_id]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($status && $status['RequiredStatusID']) {
        echo json_encode(['success' => true, 'data' => ['StatusID' => $status['RequiredStatusID']]]);
    } else {
        // اگر روت مستقیمی یافت نشد، شاید وضعیت پیش‌فرض (مثل آبکاری نشده) باشد؟
        // برای سادگی، فعلاً null برمی‌گردانیم
        echo json_encode(['success' => true, 'data' => ['StatusID' => null]]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>