<?php
// api/save_production_schedule.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// پاسخ پیش‌فرض
$response = ['success' => false, 'message' => '', 'data' => ['work_order_count' => 0]];

try {
    // 1. بررسی مجوز
    if (!has_permission('planning.production_schedule.save')) {
        throw new Exception('شما مجوز نهایی‌سازی برنامه تولید را ندارید.');
    }

    // 2. خواندن ورودی
    $input = json_decode(file_get_contents("php://input"), true);
    $planned_items = $input['planned_items'] ?? [];
    $planning_date_jalali = $input['planning_date_jalali'] ?? null; 

    if (empty($planned_items)) {
        throw new Exception('لیست اقلام برنامه‌ریزی شده خالی است.');
    }
    
    // 3. اعتبارسنجی تاریخ
    $planning_date_gregorian = to_gregorian($planning_date_jalali); 
    if (!$planning_date_gregorian) {
        throw new Exception('تاریخ اجرای برنامه نامعتبر است.');
    }
    
    $pdo->beginTransaction();
    $work_order_count = 0;

    // 4. آماده‌سازی کوئری‌ها
    
    // [FIX] کوئری INSERT اصلاح شد تا MachineID را در ستون جدیدی که ایجاد کردید ذخیره کند
    $work_order_insert_sql = "
        INSERT INTO tbl_planning_work_orders 
            (RunID, StationID, PartID, RequiredStatusID, TargetStatusID, Quantity, Unit, PlannedDate, CreationDate, Priority, Status, MachineID)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, 'Generated', ?)
    ";
    $stmt_insert = $pdo->prepare($work_order_insert_sql);
    
    // کوئری کمکی برای یافتن وضعیت خروجی (TargetStatusID)
    $route_sql = "
        SELECT NewStatusID 
        FROM tbl_routes 
        WHERE FamilyID = ? 
          AND FromStationID = ? 
          AND (RequiredStatusID = ? OR (RequiredStatusID IS NULL AND ? IS NULL))
        LIMIT 1
    ";
    $stmt_route = $pdo->prepare($route_sql);

    // کوئری کمکی برای یافتن FamilyID (برای رفع هشدار قبلی)
    $part_sql = "SELECT FamilyID FROM tbl_parts WHERE PartID = ? LIMIT 1";
    $stmt_part = $pdo->prepare($part_sql);

    // 5. پیمایش آیتم‌ها و درج در دیتابیس
    foreach ($planned_items as $item) {
        $part_id = (int)$item['part_id'];
        
        // 5a. واکشی FamilyID از دیتابیس
        $stmt_part->execute([$part_id]);
        $part_info = $stmt_part->fetch(PDO::FETCH_ASSOC);
        $family_id = $part_info ? (int)$part_info['FamilyID'] : null;

        if (!$family_id) {
             error_log("Save WO Warning: Could not find FamilyID for PartID: $part_id. Skipping item.");
             continue; 
        }

        $required_status_id = (empty($item['required_status_id'])) ? null : (int)$item['required_status_id'];
        $station_id = (int)$item['station_id'];
        
        // [FIX] دریافت MachineID از ورودی JS
        $machine_id = (int)$item['machine_id']; 
        
        $planned_qty = (float)$item['planned_quantity'];
        $unit = $item['unit'];
        $run_id = !empty($item['mrp_run_id']) ? (int)$item['mrp_run_id'] : null;

        if ($planned_qty <= 0) {
            continue; 
        }

        // 5b. یافتن وضعیت خروجی (TargetStatusID)
        $stmt_route->execute([$family_id, $station_id, $required_status_id, $required_status_id]);
        $target_status_id_raw = $stmt_route->fetchColumn(); 
        $target_status_id = ($target_status_id_raw === false) ? null : (int)$target_status_id_raw;
        
        if ($target_status_id_raw === false) {
             error_log("Save WO Warning: Could not find TargetStatusID for Family: $family_id, FromStation (WorkStation): $station_id, ReqStatus: " . ($required_status_id ?? 'NULL'));
        }
        
        // 5c. [FIX] اجرای کوئری INSERT با MachineID
        $stmt_insert->execute([
            $run_id, 
            $station_id, 
            $part_id, 
            $required_status_id, 
            $target_status_id,
            $planned_qty, 
            $unit,
            $planning_date_gregorian,
            $machine_id // [FIX] افزودن machine_id به پارامترها
        ]);
        
        $work_order_count++;
    }
    
    // 6. تایید تراکنش
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = "تعداد $work_order_count دستور کار تولیدی برای تاریخ $planning_date_jalali با موفقیت ایجاد شد.";
    $response['data']['work_order_count'] = $work_order_count;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Save Production Schedule Error: " . $e->getMessage() . " | Input: " . json_encode($input));
    $response['message'] = 'خطا در نهایی‌سازی برنامه: ' . $e->getMessage();
}

// 7. ارسال پاسخ
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

