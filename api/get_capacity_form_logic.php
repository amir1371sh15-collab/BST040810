<?php
include_once '../config/init.php';

// [FIX]: این بخش برای جلوگیری از خطای 'Invalid Token' اضافه شده است
// 1. پاک کردن هرگونه خروجی ناخواسته (مثل هشدارها یا فاصله‌های خالی)
if (ob_get_level() > 0) {
    ob_clean();
}
// 2. تنظیم هدر به صورت صریح، تا مرورگر بداند این یک پاسخ JSON است
header('Content-Type: application/json');

// اطمینان از اینکه کاربر لاگین کرده و دسترسی دارد
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Access Denied. Please login.']);
    exit;
}
// check_permission('planning_constraints.manage');

$station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : 0;
if ($station_id === 0) {
    echo json_encode(['html' => '<div class="alert alert-danger">شناسه ایستگاه نامعتبر است.</div>']);
    exit;
}

$html = '<form id="capacity-form">';
$html .= '<input type="hidden" name="station_id" value="' . $station_id . '">';

try {
    switch ($station_id) {
        
        // --- ایستگاه پرسکاری (2) و پیچ‌سازی (6) ---
        // [UPDATE]: ایستگاه 6 (پیچ سازی) به این منطق اضافه شد
        case 2: // پرسکاری
        case 6: // پیچ سازی
            $machine_type = ($station_id == 2) ? 'پرس' : 'پیچ سازی';
            $html .= '<input type="hidden" name="form_type" value="OEE_Machine">';
            $html .= '<h3>تنظیمات ظرفیت مبتنی بر OEE (راندمان)</h3>';
            $html .= '<p>ظرفیت پیشنهادی بر اساس راندمان واقعی و ضرب دستگاه محاسبه می‌شود. شما می‌توانید راندمان یا ظرفیت نهایی را دستی تنظیم کنید.</p>';
            
            // واکشی ماشین‌های مربوطه
            $stmt_machines = $pdo->prepare("SELECT MachineID, MachineName, strokes_per_minute FROM tbl_machines WHERE MachineType = ? AND Status = 'Active' ORDER BY MachineName");
            $stmt_machines->execute([$machine_type]);
            $machines = $stmt_machines->fetchAll(PDO::FETCH_ASSOC);

            // واکشی قوانین ذخیره شده قبلی
            $stmt_rules = $pdo->prepare("SELECT MachineID, StandardValue, FinalCapacity FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'OEE'");
            $stmt_rules->execute([$station_id]);
            
            // [FIX]: ترکیب PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP معتبر نیست و باعث ایجاد هشدار PHP می‌شود.
            // این هشدار، JSON را خراب کرده و باعث خطای 'Invalid token' در مرورگر می‌شود.
            // حالت واکشی به PDO::FETCH_GROUP تغییر یافت.
            $rules = $stmt_rules->fetchAll(PDO::FETCH_GROUP); // [MachineID => [Array of Rules]]
            
            $html .= '<table class="table table-striped table-bordered">';
            $html .= '<thead><tr><th>نام دستگاه</th><th>ضرب در دقیقه (SPM)</th><th>راندمان پیشنهادی (OEE) %</th><th>ظرفیت نهایی (قطعه/روز)</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($machines as $machine) {
                $machine_id = $machine['MachineID'];
                
                // بازیابی داده‌های ذخیره شده
                // دسترسی به داده‌ها بر اساس حالت FETCH_GROUP (که یک آرایه برمی‌گرداند) صحیح است
                $existing_oee = isset($rules[$machine_id][0]['StandardValue']) ? htmlspecialchars($rules[$machine_id][0]['StandardValue']) : '80'; // پیش‌فرض 80%
                $existing_capacity = isset($rules[$machine_id][0]['FinalCapacity']) ? htmlspecialchars($rules[$machine_id][0]['FinalCapacity']) : '';
                
                // TODO: در اینجا باید منطق محاسبه OEE واقعی فراخوانی شود
                // $calculated_oee = calculate_actual_oee($pdo, $machine_id);
                // $suggested_capacity = ($machine['strokes_per_minute'] * 60 * 8) * ($calculated_oee / 100);

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($machine['MachineName']) . '</td>';
                $html .= '<td>' . htmlspecialchars($machine['strokes_per_minute'] ?? 'N/A') . '</td>';
                $html .= '<td><input type="number" class="form-control" name="rules[' . $machine_id . '][oee]" value="' . $existing_oee . '" placeholder="مثلاً: 85"></td>';
                $html .= '<td><input type="number" class="form-control" name="rules[' . $machine_id . '][final_capacity]" value="' . $existing_capacity . '" placeholder="محاسبه خودکار یا ورود دستی"></td>';
                $html .= '<input type="hidden" name="rules[' . $machine_id . '][spm]" value="' . $machine['strokes_per_minute'] . '">';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            break;

        // --- ایستگاه مونتاژ (12) ---
        case 12: // مونتاژ
            $html .= '<input type="hidden" name="form_type" value="Assembly">';
            $html .= '<h3>تنظیمات ظرفیت مونتاژ</h3>';
            $html .= '<p>ظرفیت را برای دو نوع دستگاه مونتاژ (کوچک و بزرگ) به صورت مجزا وارد کنید.</p>';

            // واکشی قوانین ذخیره شده
            $stmt_rules = $pdo->prepare("SELECT CalculationMethod, FinalCapacity FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND (CalculationMethod = 'AssemblySmall' OR CalculationMethod = 'AssemblyLarge')");
            $stmt_rules->execute([$station_id]);
            $rules = $stmt_rules->fetchAll(PDO::FETCH_KEY_PAIR); // [CalculationMethod => FinalCapacity]

            $capacity_small = isset($rules['AssemblySmall']) ? htmlspecialchars($rules['AssemblySmall']) : '70000';
            $capacity_large = isset($rules['AssemblyLarge']) ? htmlspecialchars($rules['AssemblyLarge']) : '';

            $html .= '<div class="row">';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">ظرفیت دستگاه‌های مونتاژ کوچک (قطعه/روز)</label>';
            $html .= '<input type="number" class="form-control" name="rules[AssemblySmall][capacity]" value="' . $capacity_small . '">';
            $html .= '</div>';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">ظرفیت دستگاه‌های مونتاژ بزرگ (قطعه/روز)</label>';
            $html .= '<input type="number" class="form-control" name="rules[AssemblyLarge][capacity]" value="' . $capacity_large . '">';
            $html .= '</div>';
            $html .= '</div>';
            break;

        // --- ایستگاه رول (5) ---
        case 5: // رول
            $html .= '<input type="hidden" name="form_type" value="Rolling_Part_Machine">';
            $html .= '<h3>تنظیمات ظرفیت رول (بر اساس محصول)</h3>';
            $html .= '<p>ظرفیت تولید (کیلوگرم در روز) را برای هر محصول روی هر دستگاه رول کن مشخص کنید.</p>';
            
            // واکشی دستگاه‌های رول کن
            $stmt_machines = $pdo->query("SELECT MachineID, MachineName FROM tbl_machines WHERE MachineType = 'رول کن' AND Status = 'Active'");
            $roll_machines = $stmt_machines->fetchAll(PDO::FETCH_ASSOC);

            // واکشی محصولاتی که رول می‌شوند (این یک مثال است، کوئری باید دقیق‌تر شود)
            // $stmt_parts = $pdo->query("SELECT PartID, PartName FROM tbl_parts WHERE FamilyID IN (SELECT FamilyID FROM tbl_routes WHERE FromStationID = 5 OR ToStationID = 5)");
            // $roll_parts = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);
            // نکته: بهتر است لیست قطعات با JS و بر اساس API موجود (api_get_producible_parts_for_rolling.php) لود شود
            
            $html .= '<div class="alert alert-warning">بخش رول در حال توسعه است. در حال حاضر فقط نمایش قوانین موجود فعال است.</div>';
            // TODO: پیاده‌سازی جدول داینامیک برای افزودن و حذف ردیف‌های (ماشین، محصول، ظرفیت)
            // در اینجا باید ردیف‌های ذخیره شده قبلی را از tbl_planning_station_capacity_rules واکشی و نمایش داد
            
            $html .= '<table class="table table-bordered">';
            $html .= '<thead><tr><th>دستگاه رول</th><th>محصول</th><th>ظرفیت (KG/Day)</th><th>عملیات</th></tr></thead>';
            $html .= '<tbody>';
            
            $stmt_rules = $pdo->prepare("
                SELECT r.RuleID, r.MachineID, r.PartID, r.FinalCapacity, m.MachineName, p.PartName 
                FROM tbl_planning_station_capacity_rules r
                JOIN tbl_machines m ON m.MachineID = r.MachineID
                JOIN tbl_parts p ON p.PartID = r.PartID
                WHERE r.StationID = ? AND r.CalculationMethod = 'Rolling'
            ");
            $stmt_rules->execute([$station_id]);
            $rules = $stmt_rules->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rules as $rule) {
                 $html .= '<tr>';
                 $html .= '<td>' . htmlspecialchars($rule['MachineName']) . '</td>';
                 $html .= '<td>' . htmlspecialchars($rule['PartName']) . '</td>';
                 $html .= '<td><input type="number" class="form-control" name="rules['.$rule['RuleID'].'][capacity]" value="' . htmlspecialchars($rule['FinalCapacity']) . '"></td>';
                 $html .= '<td><button type="button" class="btn btn-sm btn-danger delete-row-btn" data-rule-id="'.$rule['RuleID'].'">حذف</button></td>';
                 $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '<tfoot>';
            $html .= '<tr>';
            $html .= '<td>... (افزودن ردیف جدید) ...</td>';
            // $html .= '<td><button type="button" class="btn btn-sm btn-success add-row-btn">افزودن ردیف</button></td>';
            $html .= '</tr>';
            $html .= '</tfoot>';
            $html .= '</table>';
            
            break;

        // --- ایستگاه آبکاری (4) ---
        case 4: // آبکاری
            $html .= '<input type="hidden" name="form_type" value="Plating">';
            $html .= '<h3>تنظیمات ظرفیت آبکاری</h3>';
            $html .= '<p>ظرفیت کل ایستگاه آبکاری را بر اساس واحد مورد نظر وارد کنید.</p>';

            $stmt_rule = $pdo->prepare("SELECT FinalCapacity, CapacityUnit FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'PlatingManHours' LIMIT 1");
            $stmt_rule->execute([$station_id]);
            $rule = $stmt_rule->fetch(PDO::FETCH_ASSOC);

            $capacity = $rule ? htmlspecialchars($rule['FinalCapacity']) : '';
            $unit = $rule ? htmlspecialchars($rule['CapacityUnit']) : 'KG/ManHour';

            $html .= '<div class="row">';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">ظرفیت</label>';
            $html .= '<input type="number" class="form-control" name="rules[PlatingManHours][capacity]" value="' . $capacity . '">';
            $html .= '</div>';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">واحد ظرفیت</label>';
            $html .= '<select class="form-select" name="rules[PlatingManHours][unit]">';
            $html .= '<option value="KG/ManHour" ' . ($unit == 'KG/ManHour' ? 'selected' : '') . '>کیلوگرم بر نفر ساعت</option>';
            $html .= '<option value="Barrels/ManHour" ' . ($unit == 'Barrels/ManHour' ? 'selected' : '') . '>بارل بر نفر ساعت</option>';
            $html .= '</select>';
            $html .= '</div>';
            $html .= '</div>';
            break;

        // --- ایستگاه بسته‌بندی (10) ---
        case 10: // بسته بندی
            $html .= '<input type="hidden" name="form_type" value="Packaging">';
            $html .= '<h3>تنظیمات ظرفیت بسته‌بندی</h3>';
            $html .= '<p>ظرفیت کل ایستگاه بسته‌بندی را بر اساس "کارتن در روز" وارد کنید.</p>';
            
            $stmt_rule = $pdo->prepare("SELECT FinalCapacity FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'Packaging' LIMIT 1");
            $stmt_rule->execute([$station_id]);
            $capacity = $stmt_rule->fetchColumn();
            
            $html .= '<div class="row">';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">ظرفیت (کارتن در روز)</label>';
            $html .= '<input type="number" class="form-control" name="rules[Packaging][capacity]" value="' . htmlspecialchars($capacity ?: '') . '">';
            $html .= '<input type="hidden" name="rules[Packaging][unit]" value="Cartons/Day">';
            $html .= '</div>';
            $html .= '</div>';
            break;

        // --- سایر ایستگاه‌ها (ظرفیت ثابت) ---
        default:
            $html .= '<input type="hidden" name="form_type" value="FixedAmount">';
            $html .= '<h3>تنظیمات ظرفیت ثابت</h3>';
            $html .= '<p>یک ظرفیت ثابت (پیش‌فرض) برای این ایستگاه وارد کنید.</p>';

            $stmt_rule = $pdo->prepare("SELECT FinalCapacity, CapacityUnit FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'FixedAmount' LIMIT 1");
            $stmt_rule->execute([$station_id]);
            $rule = $stmt_rule->fetch(PDO::FETCH_ASSOC);

            $capacity = $rule ? htmlspecialchars($rule['FinalCapacity']) : '';
            $unit = $rule ? htmlspecialchars($rule['CapacityUnit']) : 'KG/Day';

            $html .= '<div class="row">';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">ظرفیت</label>';
            $html .= '<input type="number" class="form-control" name="rules[FixedAmount][capacity]" value="' . $capacity . '">';
            $html .= '</div>';
            $html .= '<div class="col-md-6 mb-3">';
            $html .= '<label class="form-label">واحد ظرفیت</label>';
            // [FIX]: غلط املایی 'type_exists' به 'type' تصحیح شد
            $html .= '<input type="text" class="form-control" name="rules[FixedAmount][unit]" value="' . $unit . '" placeholder="KG/Day, Pieces/Day, ...">';
            $html .= '</div>';
            $html .= '</div>';
            break;
    }

    $html .= '<hr><button type="submit" class="btn btn-primary">ذخیره تغییرات</button>';
    $html .= '</form>';
    
    echo json_encode(['html' => $html]);

} catch (Exception $e) {
    // لاگ کردن خطا
    error_log("Error in get_capacity_form_logic.php: " . $e->getMessage());
    echo json_encode(['html' => '<div class="alert alert-danger">خطای سیستمی: ' . $e->getMessage() . '</div>']);
}

