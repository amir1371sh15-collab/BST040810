<?php
include_once '../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Access Denied. Please login.']);
    exit;
}
// check_permission('planning_constraints.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$station_id = isset($_POST['station_id']) ? intval($_POST['station_id']) : 0;
$form_type = isset($_POST['form_type']) ? $_POST['form_type'] : '';
$rules = isset($_POST['rules']) ? $_POST['rules'] : [];
$rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0; // برای مودال ویرایش

if ($station_id === 0 || empty($form_type)) {
    echo json_encode(['success' => false, 'error' => 'اطلاعات ارسالی ناقص است.']);
    exit;
}

try {
    $pdo->beginTransaction();

    switch ($form_type) {
        
        case 'OEE_Machine': // پرسکاری و پیچ سازی (بدون تغییر)
            $stmt_delete = $pdo->prepare("DELETE FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'OEE'");
            $stmt_delete->execute([$station_id]);
            
            $stmt_insert = $pdo->prepare("
                INSERT INTO tbl_planning_station_capacity_rules 
                (StationID, CalculationMethod, MachineID, StandardValue, FinalCapacity, CapacityUnit) 
                VALUES (?, 'OEE', ?, ?, ?, ?)
            ");
            
            foreach ($rules as $machine_id => $rule) {
                $oee = !empty($rule['oee']) ? floatval($rule['oee']) : 80;
                $available_time = !empty($rule['available_time']) ? floatval($rule['available_time']) : 480;
                
                $params = [$station_id, $machine_id, $oee, $available_time, 'Pieces/Day'];
                $stmt_insert->execute($params);
            }
            break;

        case 'Assembly': // مونتاژ (بدون تغییر)
            $stmt_delete = $pdo->prepare("DELETE FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND (CalculationMethod = 'AssemblySmall' OR CalculationMethod = 'AssemblyLarge')");
            $stmt_delete->execute([$station_id]);

            if (isset($rules['AssemblySmall']['capacity'])) {
                $capacity_small = floatval($rules['AssemblySmall']['capacity']);
                $stmt_insert_small = $pdo->prepare("INSERT INTO tbl_planning_station_capacity_rules (StationID, CalculationMethod, FinalCapacity, CapacityUnit) VALUES (?, 'AssemblySmall', ?, 'Pieces/Day')");
                $stmt_insert_small->execute([$station_id, $capacity_small]);
            }
            if (isset($rules['AssemblyLarge']['capacity'])) {
                $capacity_large = floatval($rules['AssemblyLarge']['capacity']);
                $stmt_insert_large = $pdo->prepare("INSERT INTO tbl_planning_station_capacity_rules (StationID, CalculationMethod, FinalCapacity, CapacityUnit) VALUES (?, 'AssemblyLarge', ?, 'Pieces/Day')");
                $stmt_insert_large->execute([$station_id, $capacity_large]);
            }
            break;
        
        case 'Gearing_Part_Add': // افزودن/ویرایش ردیف دنده زنی (از مودال)
        case 'Rolling_Part_Add': // افزودن/ویرایش ردیف رول (از مودال)
            $method = ($form_type == 'Gearing_Part_Add') ? 'Gearing' : 'Rolling';
            $part_id = isset($_POST['part_id']) ? intval($_POST['part_id']) : 0;
            $capacity = isset($_POST['capacity']) ? floatval($_POST['capacity']) : 0;

            if ($part_id === 0 || $capacity <= 0) {
                throw new Exception("اطلاعات ورودی (محصول و ظرفیت) ناقص است.");
            }
            
            if ($rule_id > 0) {
                // --- حالت ویرایش ---
                // بررسی تکراری (فقط اگر پارت تغییر کرده)
                $stmt_check = $pdo->prepare("SELECT RuleID FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = ? AND PartID = ? AND RuleID != ?");
                $stmt_check->execute([$station_id, $method, $part_id, $rule_id]);
                if ($stmt_check->fetch()) {
                    throw new Exception("یک قانون مشابه برای این محصول قبلاً ثبت شده است.");
                }

                $stmt_update = $pdo->prepare("UPDATE tbl_planning_station_capacity_rules SET PartID = ?, FinalCapacity = ? WHERE RuleID = ? AND StationID = ?");
                $stmt_update->execute([$part_id, $capacity, $rule_id, $station_id]);

            } else {
                // --- حالت افزودن ---
                // بررسی تکراری
                $stmt_check = $pdo->prepare("SELECT RuleID FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = ? AND PartID = ?");
                $stmt_check->execute([$station_id, $method, $part_id]);
                if ($stmt_check->fetch()) {
                    throw new Exception("یک قانون مشابه برای این محصول قبلاً ثبت شده است.");
                }

                $stmt_insert = $pdo->prepare("
                    INSERT INTO tbl_planning_station_capacity_rules 
                    (StationID, CalculationMethod, PartID, FinalCapacity, CapacityUnit) 
                    VALUES (?, ?, ?, ?, 'KG/Day')
                ");
                $stmt_insert->execute([$station_id, $method, $part_id, $capacity]);
            }
            break;

        case 'Gearing_Part': // ذخیره جدول اصلی دنده زنی
        case 'Rolling_Part': // ذخیره جدول اصلی رول
            // این بخش فقط ردیف‌های موجود را *به‌روزرسانی* می‌کند
            $stmt_update = $pdo->prepare("UPDATE tbl_planning_station_capacity_rules SET FinalCapacity = ? WHERE RuleID = ? AND StationID = ?");
            foreach ($rules as $rule_id_key => $rule) {
                if (isset($rule['capacity'])) {
                    $stmt_update->execute([floatval($rule['capacity']), $rule_id_key, $station_id]);
                }
            }
            break;

        case 'Plating': // آبکاری (بدون تغییر)
        case 'Packaging': // بسته بندی (بدون تغییر)
        case 'FixedAmount': // حالت پیش‌فرض (بدون تغییر)
            $rule_key = array_key_first($rules); 
            $rule = $rules[$rule_key];
            
            $method = $rule_key;
            $capacity = floatval($rule['capacity']);
            $unit = $rule['unit'];

            $stmt_delete = $pdo->prepare("DELETE FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = ?");
            $stmt_delete->execute([$station_id, $method]);

            $stmt_insert = $pdo->prepare("INSERT INTO tbl_planning_station_capacity_rules (StationID, CalculationMethod, FinalCapacity, CapacityUnit) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$station_id, $method, $capacity, $unit]);
            break;

        default:
            throw new Exception("نوع فرم ناشناخته: " . $form_type);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'ظرفیت با موفقیت ذخیره شد.']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in save_capacity_rules.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

