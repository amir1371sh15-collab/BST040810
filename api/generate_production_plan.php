<?php
// api/generate_production_plan.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// فرض می‌کنیم توابع find_one و find_all در config/crud_helpers.php تعریف شده‌اند.
// فرض می‌کنیم تابع has_permission نیز وجود دارد.

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if (!has_permission('planning.production_schedule.edit')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.');
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $selected_wip_items = $input['wip_items'] ?? [];
    $planning_date = $input['planning_date'] ?? date('Y-m-d');

    if (empty($selected_wip_items)) {
        throw new Exception('هیچ مورد WIP برای برنامه‌ریزی انتخاب نشده است.');
    }

    // --- ۱. جمع‌آوری داده‌های پایه ---
    
    // (A) - مسیرهای تولیدی (Routes): برای یافتن ایستگاه بعدی و وضعیت مورد نیاز
    $routes_raw = find_all($pdo, "
        SELECT 
            PartID, CurrentStatusID, NextStatusID, NextStationID, op.OperationName, NextStationID
        FROM tbl_part_routes pr
        JOIN tbl_operations op ON op.OperationID = pr.NextOperationID
    ");
    $routes = [];
    foreach ($routes_raw as $r) {
        $key = $r['PartID'] . '_' . $r['CurrentStatusID'];
        $routes[$key] = $r;
    }

    // (B) - موجودی WIP برای استخراج مقدار موجود
    // ما باید این مقدار را از دیتابیس به صورت دقیق و تجمیع شده در Station 8 بگیریم.
    // این منطق قبلاً در run_mrp_calculation.php وجود داشت، اما برای سادگی، یک کوئری مستقیم می‌زنیم.
    $wip_supply_map = [];
    // این کوئری باید WIP را بر اساس PartID و CurrentStatusID تجمیع کند
    $wip_query = "
        SELECT 
            PartID, StatusAfterID AS CurrentStatusID, SUM(TotalNetWeightKG) AS TotalWeightKG
        FROM tbl_stock_transactions 
        WHERE ToStationID = 8 -- انبار نیمه‌ساخته
        GROUP BY PartID, StatusAfterID
        HAVING TotalWeightKG > 0
    ";
    $wip_data_raw = find_all($pdo, $wip_query);
    
    foreach ($wip_data_raw as $wip_item) {
        $key = $wip_item['PartID'] . '_' . $wip_item['CurrentStatusID'];
        // نیاز به منطق تبدیل وزن به تعداد (از tbl_part_weights) است، اما فعلاً فقط وزن را نگه می‌داریم.
        $wip_supply_map[$key] = (float)$wip_item['TotalWeightKG'];
    }


    // (C) - ظرفیت ایستگاه‌ها
    $capacity_rules = [];
    // این منطق باید از tbl_capacity_rules یا مشابه آن بارگذاری شود
    $capacity_raw = find_all($pdo, "
        SELECT StationID, DailyCapacityKG, DailyCapacityPcs 
        FROM tbl_station_capacity_rules
    ");
    foreach ($capacity_raw as $c) {
        $capacity_rules[$c['StationID']] = $c;
    }
    
    // (D) - قواعد ناسازگاری (مانند ناسازگاری آبکاری یا ارتعاش)
    $incompatibility_rules_raw = find_all($pdo, "
        SELECT GroupID_A, GroupID_B, RuleDescription
        FROM tbl_incompatibility_rules 
        WHERE RuleType = 'Batch' -- مثال: قوانین دسته بندی
    ");
    
    // فرض می‌کنیم توابعی برای یافتن PartName و UnitName وجود دارد.
    // $all_parts = ... ; $all_units = ... ;

    // --- ۲. محاسبه برنامه ناخالص بر اساس موارد انتخاب شده و فیلتر کردن ---
    $planned_groups = []; // خروجی نهایی: تجمیع شده بر اساس ایستگاه
    $selected_part_group_map = []; // برای بررسی محدودیت‌ها

    foreach ($selected_wip_items as $item) {
        $part_id = $item['part_id'];
        $current_status_id = $item['status_id'];
        $wip_key = $part_id . '_' . $current_status_id;
        $route_key = $wip_key;

        // الف. اطمینان از وجود مسیر و موجودی
        if (!isset($routes[$route_key])) {
            // این مورد نباید در لیست انتخاب ظاهر می‌شد، یا مسیر برای آن تعریف نشده است.
            continue; 
        }

        $route = $routes[$route_key];
        $gross_demand_kg = $wip_supply_map[$wip_key] ?? 0;
        
        if ($gross_demand_kg < 0.01) {
            continue; // موجودی کافی در WIP وجود ندارد.
        }

        $next_station_id = $route['NextStationID'];
        
        // ب. تعیین اطلاعات ایستگاه و ظرفیت
        $station_name = get_station_name($pdo, $next_station_id); // تابع کمکی
        $capacity = $capacity_rules[$next_station_id]['DailyCapacityKG'] ?? 999999;
        $unit_name = 'KG'; // برای سادگی، مبنای واحد را KG در نظر می‌گیریم. (نیاز به منطق تبدیل دقیق‌تر دارد)
        
        // پ. محاسبه مقدار پیشنهادی (حداقل نیاز و حداکثر ظرفیت)
        $suggested_qty_to_plan = min($gross_demand_kg, $capacity);
        
        // ت. تجمیع بر اساس ایستگاه و قطعه (برای نمایش در جدول)
        if (!isset($planned_groups[$next_station_id])) {
            $planned_groups[$next_station_id] = [
                'StationName' => $station_name,
                'Capacity' => $capacity,
                'Unit' => $unit_name,
                'parts' => []
            ];
        }
        
        $part_key = $part_id . '_' . $route['NextStatusID'];
        if (!isset($planned_groups[$next_station_id]['parts'][$part_key])) {
            $planned_groups[$next_station_id]['parts'][$part_key] = [
                'PartID' => $part_id,
                'PartName' => get_part_name($pdo, $part_id), // تابع کمکی
                'NextOperationName' => $route['OperationName'],
                'NextStatusID' => $route['NextStatusID'],
                'GrossDemand' => 0,
                'SuggestedProductionQuantity' => 0,
                'Unit' => $unit_name
            ];
        }
        
        $planned_groups[$next_station_id]['parts'][$part_key]['GrossDemand'] += $gross_demand_kg;
        $planned_groups[$next_station_id]['parts'][$part_key]['SuggestedProductionQuantity'] += $suggested_qty_to_plan;

        // ث. جمع‌آوری اطلاعات برای بررسی محدودیت‌ها
        // فرض می‌کنیم تابعی برای گرفتن گروه دسته‌بندی قطعه (مانند گروه آبکاری یا ارتعاش) وجود دارد
        $part_groups = get_part_planning_groups($pdo, $part_id); // تابع کمکی: [GroupID => GroupName, ...]
        $selected_part_group_map[$part_id] = $part_groups;
    }

    // --- ۳. بررسی محدودیت‌ها (Constraints) ---
    $warnings = [];
    $part_ids_in_plan = array_keys($selected_part_group_map);

    foreach ($incompatibility_rules_raw as $rule) {
        $group_a = $rule['GroupID_A'];
        $group_b = $rule['GroupID_B'];
        $rule_desc = $rule['RuleDescription'];
        
        $parts_with_A = [];
        $parts_with_B = [];
        
        foreach ($selected_wip_items as $item) {
            $part_id = $item['part_id'];
            $groups = $selected_part_group_map[$part_id];

            // چک کردن اینکه آیا گروه A و B در میان قطعات انتخاب شده وجود دارند
            if (array_key_exists($group_a, $groups)) {
                $parts_with_A[] = get_part_name($pdo, $part_id);
            }
            if (array_key_exists($group_b, $groups)) {
                $parts_with_B[] = get_part_name($pdo, $part_id);
            }
        }
        
        // اگر هر دو نوع گروه در یک برنامه قرار گرفته‌اند
        if (!empty($parts_with_A) && !empty($parts_with_B)) {
            $warnings[] = "توجه: قطعات گروه **" . $group_a . "** (مانند: " . implode(', ', array_unique($parts_with_A)) . 
                          ") و قطعات گروه **" . $group_b . "** (مانند: " . implode(', ', array_unique($parts_with_B)) . 
                          ") در برنامه امروز قرار دارند. محدودیت: " . $rule_desc . " **(توصیه می‌شود برنامه لغو یا جدا شود)**.";
        }
    }
    

    $response['success'] = true;
    $response['data'] = [
        'planning_groups' => $planned_groups,
        'warnings' => $warnings
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

// --- توابع کمکی ساختگی (باید در config/functions.php یا مشابه آن پیاده‌سازی شوند) ---

function get_station_name($pdo, $station_id) {
    // منطق دیتابیسی برای بازیابی نام ایستگاه
    return "ایستگاه " . $station_id;
}

function get_part_name($pdo, $part_id) {
    // منطق دیتابیسی برای بازیابی نام قطعه
    return "قطعه " . $part_id; 
}

function get_part_planning_groups($pdo, $part_id) {
    // منطق دیتابیسی برای بازیابی گروه‌های دسته‌بندی قطعه (مثلاً گروه آبکاری)
    // برای مثال: SELECT GroupID, GroupName FROM tbl_part_to_group WHERE PartID = ?
    // خروجی ساختگی:
    if ($part_id % 3 == 0) return [1 => 'آبکاری فسفاته'];
    if ($part_id % 3 == 1) return [2 => 'آبکاری گالوانیزه'];
    return [3 => 'ارتعاش شدید']; 
}

// --------------------------------------------------------------------------

?>