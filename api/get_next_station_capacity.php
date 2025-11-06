<?php
// api/get_next_station_capacity.php
// API برای گام ۳: پیدا کردن ایستگاه بعدی و ظرفیت آن

header('Content-Type: application/json');
include_once __DIR__ . '/../config/init.php';

// TODO: این تابع باید با منطق واقعی شما جایگزین شود
// بر اساس شناسه قطعه و شناسه ایستگاه فعلی، ایستگاه بعدی را از جدول 'routes' پیدا می‌کند
function find_next_station($db, $part_id, $current_wip_location_id) {
    // منطق شبیه‌سازی شده:
    // اگر 0 (انبار) بود، ایستگاه بعدی 1 (برش) است
    // اگر 1 (برش) بود، ایستگاه بعدی 2 (پرس) است
    // اگر 2 (پرس) بود، ایستگاه بعدی 3 (آبکاری) است
    
    // TODO: اینجا کوئری واقعی به جدول 'routes' یا 'bom' زده شود
    $next_station_id = $current_wip_location_id + 1; 
    
    // شبیه‌سازی نام ایستگاه
    $stations = [1 => 'برشکاری', 2 => 'پرس‌کاری', 3 => 'آبکاری', 4 => 'مونتاژ'];
    $station_name = isset($stations[$next_station_id]) ? $stations[$next_station_id] : 'انبار محصول';

    if ($next_station_id > 4) {
        return ['id' => 99, 'name' => 'انبار محصول'];
    }
    
    return ['id' => $next_station_id, 'name' => $station_name];
}

// TODO: این تابع باید با منطق واقعی شما جایگزین شود
// ظرفیت روزانه ایستگاه را بر اساس شناسه ایستگاه و شاید شناسه قطعه برمی‌گرداند
function get_station_capacity($db, $station_id, $part_id) {
    // منطق شبیه‌سازی شده:
    // TODO: کوئری واقعی به جدول 'station_capacity'
    $capacities = [
        1 => 5000,  // ظرفیت برش
        2 => 3000,  // ظرفیت پرس
        3 => 1000,  // ظرفیت آبکاری
        4 => 1500,  // ظرفیت مونتاژ
    ];
    return isset($capacities[$station_id]) ? $capacities[$station_id] : 0;
}


// --- اجرای API ---
try {
    if (!isset($_GET['part_id']) || !isset($_GET['wip_id'])) {
        throw new Exception("ورودی نامعتبر");
    }

    $part_id = (int)$_GET['part_id'];
    $current_wip_location_id = (int)$_GET['wip_id'];

    // ۱. پیدا کردن ایستگاه بعدی
    $station = find_next_station($db, $part_id, $current_wip_location_id);

    // ۲. پیدا کردن ظرفیت ایستگاه
    $capacity = 0;
    if ($station['id'] != 99) { // 99 یعنی انبار محصول
        $capacity = get_station_capacity($db, $station['id'], $part_id);
    }

    echo json_encode([
        'success' => true,
        'station' => $station,
        'capacity' => $capacity
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
