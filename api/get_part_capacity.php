<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => ''];

try {
    // 1. دریافت ورودی‌ها
    $partId = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT);
    $machineId = filter_input(INPUT_GET, 'machine_id', FILTER_VALIDATE_INT);
    $unit = $_GET['unit'] ?? 'عدد'; // واحدی که کاربر در حال برنامه‌ریزی بر اساس آن است

    if (!$partId || !$machineId) {
        throw new Exception("ID قطعه یا ماشین نامعتبر است.");
    }

    // 2. واکشی اطلاعات ماشین (SPM)
    // [FIX] آرگومان چهارم 'MachineID' (کلید اصلی) به find_by_id اضافه شد
    $machine = find_by_id($pdo, 'tbl_machines', $machineId, 'MachineID');
    if (!$machine) {
        throw new Exception("مشخصات ماشین یافت نشد.");
    }
    $spm = (float)($machine['strokes_per_minute'] ?? 0);

    // 3. واکشی قانون ظرفیت (OEE و زمان در دسترس) بر اساس MachineID
    // [FIX] به جای StationID، مستقیماً از MachineID برای یافتن قانون استفاده می‌کنیم
    $rule = find_one_by_field($pdo, 'tbl_planning_station_capacity_rules', 'MachineID', $machineId);
    if (!$rule) {
        throw new Exception("قانون ظرفیت (OEE/زمان) برای این دستگاه در 'tbl_planning_station_capacity_rules' تعریف نشده است.");
    }
    
    $oee_percent = (float)($rule['StandardValue'] ?? 80); // OEE (e.g., 80)
    $available_time_minutes = (float)($rule['FinalCapacity'] ?? 480); // Available Time (e.g., 480)

    if ($spm <= 0) {
        throw new Exception("تعداد ضرب در دقیقه (SPM) برای این دستگاه صفر یا تعریف نشده است.");
    }

    // 4. محاسبه ظرفیت به "عدد" (Pieces)
    // ظرفیت (عدد) = (ضرب در دقیقه * دقایق در دسترس) * (OEE / 100)
    $calculated_capacity_pieces = ($spm * $available_time_minutes) * ($oee_percent / 100);

    // 5. تبدیل به واحد درخواستی (KG یا عدد)
    $final_capacity = 0;
    $final_unit = 'عدد';

    if (strtoupper($unit) === 'KG') {
        // اگر واحد درخواستی کیلوگرم است، وزن قطعه را پیدا کن
        $weight_info = find_one_by_field($pdo, 'tbl_part_weights', 'PartID', $partId, " AND (EffectiveTo IS NULL OR EffectiveTo >= CURDATE()) ORDER BY EffectiveFrom DESC");
        if (!$weight_info || !isset($weight_info['WeightGR']) || (float)$weight_info['WeightGR'] <= 0) {
            throw new Exception("وزن (WeightGR) برای این قطعه در 'tbl_part_weights' یافت نشد. امکان محاسبه KG وجود ندارد.");
        }
        $weight_kg = (float)$weight_info['WeightGR'] / 1000.0;
        
        $final_capacity = $calculated_capacity_pieces * $weight_kg;
        $final_unit = 'KG';
    } else {
        // اگر واحد درخواستی "عدد" بود
        $final_capacity = $calculated_capacity_pieces;
        $final_unit = 'عدد';
    }

    $response['success'] = true;
    $response['data'] = [
        'capacity' => round($final_capacity, 2),
        'unit' => $final_unit
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    error_log("Get Part Capacity Error: " . $e->getMessage()); // لاگ کردن خطا
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
