<?php
// api/check_production_constraints.php
// API برای گام ۴: بررسی محدودیت‌ها و توصیه‌ها

header('Content-Type: application/json');
include_once __DIR__ . '/../config/init.php';

// TODO: این تابع باید با منطق واقعی شما جایگزین شود
// لیست شناسه قطعات را می‌گیرد و قوانین ناسازگاری را چک می‌کند
function check_constraints($db, $part_ids) {
    $warnings = [];
    
    // شبیه‌سازی داده‌های ناسازگاری (مثلا از جدول 'batch_compatibility')
    // قانون: قطعه 101 (X) نباید با قطعه 104 (C) همزمان باشد
    $incompatible_pairs = [
        [101, 104] 
    ];

    // TODO: این منطق باید با کوئری واقعی جایگزین شود
    foreach ($incompatible_pairs as $pair) {
        if (in_array($pair[0], $part_ids) && in_array($pair[1], $part_ids)) {
            // TODO: نام قطعات را از دیتابیس بگیرید
            $part_name_1 = "قطعه X - مدل A (101)"; 
            $part_name_2 = "قطعه C - ناسازگار (104)";
            $warnings[] = "توصیه: $part_name_1 و $part_name_2 نباید در یک روز تولید شوند (محدودیت هم‌زمانی).";
        }
    }
    
    // قانون: قطعه 102 (Y) محدودیت ظرفیت خاص دارد
    if (in_array(102, $part_ids)) {
         $warnings[] = "توجه: قطعه Y - مدل B (102) نیاز به تنظیمات خاص دستگاه آبکاری دارد. از صحت تنظیمات اطمینان حاصل کنید.";
    }

    return $warnings;
}

// --- اجرای API ---
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $part_ids = isset($input['part_ids']) ? $input['part_ids'] : [];

    if (empty($part_ids)) {
        throw new Exception("هیچ قطعه‌ای انتخاب نشده است.");
    }

    $warnings = check_constraints($db, $part_ids);

    echo json_encode([
        'success' => true,
        'warnings' => $warnings
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
