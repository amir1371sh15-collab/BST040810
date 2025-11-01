<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

if (!has_permission('planning_capacity.run')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'شما مجوز دسترسی به این بخش را ندارید.']); exit;
}

$response = ['success' => false, 'message' => '', 'data' => null];
$input = json_decode(file_get_contents('php://input'), true);

// استفاده از POST یا JSON body
$planning_date = $_POST['planning_date'] ?? $input['planning_date'] ?? null;
$station_id = $_POST['station_id'] ?? $input['station_id'] ?? null;

if (empty($station_id) || empty($planning_date)) {
    http_response_code(400);
    $response['message'] = 'تاریخ و ایستگاه الزامی است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

// تبدیل تاریخ جلالی به میلادی برای محاسبات
try {
    $gregorian_date = convert_jalali_to_gregorian($planning_date);
    $gregorian_date_end_of_day = $gregorian_date . ' 23:59:59';
    // تاریخ شروع برای محاسبه OEE (یک هفته گذشته)
    $one_week_ago = date('Y-m-d', strtotime('-7 days', strtotime($gregorian_date)));

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = 'فرمت تاریخ نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    // 1. واکشی قانون محاسبه برای این ایستگاه
    $rule = find_one_by_field($pdo, 'tbl_planning_station_capacity_rules', 'StationID', $station_id);
    if (!$rule) {
        http_response_code(404);
        $response['message'] = 'هیچ قانون محاسبه ظرفیتی برای این ایستگاه تعریف نشده است.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
    }

    $suggested_capacity = 0.0;
    $capacity_unit = '';
    $details = '';
    $final_capacity = 0.0; // مقدار قبلا ذخیره شده
    
    $method = $rule['CalculationMethod'];
    $standard_value = (float)$rule['StandardValue'];
    $capacity_unit = $rule['CapacityUnit']; // واحد پیش‌فرض از قانون خوانده می‌شود

    // 2. بررسی اینکه آیا قبلا مقداری برای این روز ذخیره شده؟
    $override = find_one_by_fields($pdo, 'tbl_planning_capacity_override', [
        'PlanningDate' => $gregorian_date,
        'StationID' => $station_id
    ]);
    if ($override) {
        $final_capacity = (float)$override['FinalCapacity'];
    }

    // 3. اجرای منطق محاسبه بر اساس متد
    switch ($method) {
        case 'OEE': // سالن تولید (پرسکاری)
            $oee_sql = "
                SELECT 
                    COALESCE(SUM(h.AvailableTimeMinutes), 0) as TotalAvailable,
                    (SELECT COALESCE(SUM(d.DowntimeMinutes), 0)
                     FROM tbl_prod_downtime_details d
                     JOIN tbl_prod_daily_log_header dh ON d.HeaderID = dh.HeaderID
                     JOIN tbl_machines m ON dh.MachineID = m.MachineID
                     WHERE m.StationID = :station_id1 AND dh.LogDate BETWEEN :start_date AND :end_date) as TotalDowntime,
                    
                    (SELECT COALESCE(SUM(p.ProductionKG), 0)
                     FROM tbl_prod_daily_log_details p
                     JOIN tbl_prod_daily_log_header dh ON p.HeaderID = dh.HeaderID
                     JOIN tbl_machines m ON dh.MachineID = m.MachineID
                     WHERE m.StationID = :station_id2 AND dh.LogDate BETWEEN :start_date AND :end_date) as TotalProdKG,

                    (SELECT COALESCE(AVG(m.strokes_per_minute), 0) FROM tbl_machines m WHERE m.StationID = :station_id3 AND m.strokes_per_minute > 0) as AvgSPM,
                    
                    (SELECT COALESCE(AVG(pw.WeightGrams), 0) 
                     FROM tbl_part_weights pw
                     JOIN tbl_parts p ON pw.PartID = p.PartID
                     JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID 
                     WHERE pf.StationID = :station_id4 AND pw.WeightGrams > 0) as AvgWeightGrams
                     
                FROM tbl_prod_daily_log_header h
                JOIN tbl_machines m ON h.MachineID = m.MachineID
                WHERE m.StationID = :station_id5 AND h.LogDate BETWEEN :start_date AND :end_date
            ";
            
            $stmt = $pdo->prepare($oee_sql);
            $params = [
                ':station_id1' => $station_id, ':start_date' => $one_week_ago, ':end_date' => $gregorian_date,
                ':station_id2' => $station_id,
                ':station_id3' => $station_id,
                ':station_id4' => $station_id,
                ':station_id5' => $station_id
            ];
            $stmt->execute($params);
            $oee_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oee_data && (float)$oee_data['TotalAvailable'] > 0 && (float)$oee_data['AvgSPM'] > 0 && (float)$oee_data['AvgWeightGrams'] > 0) {
                $total_available_time = (float)$oee_data['TotalAvailable'];
                $total_downtime = (float)$oee_data['TotalDowntime'];
                $total_prod_kg = (float)$oee_data['TotalProdKG'];
                $avg_spm = (float)$oee_data['AvgSPM'];
                $avg_weight_g = (float)$oee_data['AvgWeightGrams'];
                $avg_weight_kg = $avg_weight_g / 1000;

                $run_time = $total_available_time - $total_downtime;
                if ($run_time <= 0) $run_time = $total_available_time; // جلوگیری از تقسیم بر صفر

                // Availability
                $availability = $run_time / $total_available_time;
                
                // Performance
                $total_pieces_produced = $total_prod_kg / $avg_weight_kg;
                $ideal_cycle_time_min = 1 / $avg_spm; // (دقیقه بر قطعه)
                $ideal_run_time = $total_pieces_produced * $ideal_cycle_time_min;
                $performance = ($run_time > 0) ? ($ideal_run_time / $run_time) : 0;
                
                // Quality (فرض 100% فعلا)
                $quality = 1.0; 
                
                $oee = $availability * $performance * $quality;
                
                // ظرفیت استاندارد روزانه (8 ساعت = 480 دقیقه)
                // فرض می‌کنیم 1 دستگاه SPM میانگین را دارد
                $daily_ideal_pieces = $avg_spm * 480; // (قطعه در روز)
                $suggested_capacity = ($daily_ideal_pieces * $oee) * $avg_weight_kg; // (کیلوگرم در روز)
                
                $capacity_unit = 'KG/Day'; // واحد این متد ثابت است
                $details = sprintf("بر اساس OEE هفته گذشته: %.1f%% (راندمان: %.1f%%, دسترسی: %.1f%%) | SPM میانگین: %d", 
                                   $oee * 100, $performance * 100, $availability * 100, $avg_spm);
            } else {
                $suggested_capacity = $standard_value; // استفاده از مقدار ثابت در صورت نبود داده OEE
                $capacity_unit = 'KG/Day';
                $details = 'داده‌ای برای محاسبه OEE یافت نشد. از مقدار استاندارد استفاده شد.';
            }
            break;

        case 'PlatingManHours': // سالن آبکاری
            // بر اساس نفر-ساعت.
            $capacity_per_man_hour = $standard_value > 0 ? $standard_value : 58; // (کیلوگرم بر نفر-ساعت)
            
            // واکشی تعداد پرسنل ثبت شده برای این روز در سالن آبکاری
            $personnel_sql = "SELECT SUM(PersonnelCount) as TotalPersonnel FROM tbl_plating_daily_log WHERE LogDate = ?";
            $personnel_stmt = $pdo->prepare($personnel_sql);
            $personnel_stmt->execute([$gregorian_date]);
            $personnel_count = (int)$personnel_stmt->fetchColumn();
            
            if ($personnel_count == 0) {
                 // اگر پرسنلی ثبت نشده، از یک مقدار پیش‌فرض استفاده کن (مثلاً 3 نفر)
                $personnel_count = 3; 
                $details = sprintf("پرسنلی برای امروز ثبت نشده. بر اساس %d نفر پیش‌فرض: %d نفر * 8 ساعت * %.1f کیلوگرم/ساعت",
                                   $personnel_count, $personnel_count, $capacity_per_man_hour);
            } else {
                $details = sprintf("بر اساس %d پرسنل ثبت شده امروز: %d نفر * 8 ساعت * %.1f کیلوگرم/ساعت",
                                   $personnel_count, $personnel_count, $capacity_per_man_hour);
            }
            
            $standard_man_hours = $personnel_count * 8; // فرض 8 ساعت کاری
            $suggested_capacity = $standard_man_hours * $capacity_per_man_hour;
            $capacity_unit = 'KG/Day'; // واحد این متد ثابت است
            break;

        case 'AssemblySmall':
            $suggested_capacity = $standard_value > 0 ? $standard_value : 10000;
            $capacity_unit = 'Pieces/Day';
            $details = "ظرفیت ثابت روزانه بر اساس قانون.";
            break;
            
        case 'AssemblyLarge':
            $suggested_capacity = $standard_value > 0 ? $standard_value : 7000;
            $capacity_unit = 'Pieces/Day';
            $details = "ظرفیت ثابت روزانه بر اساس قانون.";
            break;
            
        case 'Rolling':
            $suggested_capacity = $standard_value > 0 ? $standard_value : 5000;
            $capacity_unit = 'Pieces/Day';
            $details = "ظرفیت ثابت روزانه (وابسته به محصول).";
            break;

        case 'Packaging':
            $suggested_capacity = $standard_value > 0 ? $standard_value : 20;
            $capacity_unit = 'Carton/Day';
            $details = "ظرفیت ثابت روزانه بر اساس قانون.";
            break;

        case 'ManHours':
        case 'FixedAmount':
        default:
            $suggested_capacity = $standard_value;
            // $capacity_unit از $rule['CapacityUnit'] در بالا خوانده شده است
            $details = "ظرفیت ثابت روزانه بر اساس قانون.";
            break;
    }
    
    $response['success'] = true;
    $response['data'] = [
        'suggested_capacity' => round($suggested_capacity, 2),
        'final_capacity' => round($final_capacity, 2), // مقدار 0.0 اگر قبلا ذخیره نشده
        'capacity_unit' => $capacity_unit,
        'details' => $details
    ];

} catch (Exception $e) {
    error_log("API Get Suggested Capacity Error: " . $e->getMessage() . "\nSQL: " . ($oee_sql ?? 'N/A'));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

