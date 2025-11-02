<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

if (!has_permission('planning_capacity.run')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'شما مجوز دسترسی به این بخش را ندارید.']); exit;
}

$response = ['success' => false, 'message' => ''];
$input = json_decode(file_get_contents('php://input'), true);

// استفاده از POST یا JSON body
$planning_date = $_POST['planning_date'] ?? $input['planning_date'] ?? null;
$station_id = $_POST['station_id'] ?? $input['station_id'] ?? null;
// suggested_capacity از فرم دریافت می‌شود اما در محاسبه واحد استفاده می‌شود
$suggested_capacity_raw = $_POST['suggested_capacity'] ?? $input['suggested_capacity'] ?? 0;
// این مهم است که فرمت عددی را از رشته جدا کنیم
$suggested_capacity = (float)str_replace(',', '', $suggested_capacity_raw); 
$final_capacity = $_POST['final_capacity'] ?? $input['final_capacity'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (empty($station_id) || empty($planning_date) || $final_capacity === null) {
    http_response_code(400);
    $response['message'] = 'داده‌های ارسالی ناقص است (تاریخ، ایستگاه، ظرفیت نهایی).';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    $gregorian_date = convert_jalali_to_gregorian($planning_date);
    
    // 1. واکشی قانون برای دریافت واحد صحیح
    $rule = find_one_by_field($pdo, 'tbl_planning_station_capacity_rules', 'StationID', $station_id);
    if (!$rule) {
        http_response_code(404);
        $response['message'] = 'هیچ قانون محاسبه ظرفیتی برای این ایستگاه تعریف نشده است.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
    }
    
    // تعیین واحد بر اساس منطق مشابه API محاسبه
    $capacity_unit = $rule['CapacityUnit']; // واحد پیش‌فرض
    switch ($rule['CalculationMethod']) {
        case 'OEE': $capacity_unit = 'KG/Day'; break;
        case 'PlatingManHours': $capacity_unit = 'KG/Day'; break;
        case 'AssemblySmall': $capacity_unit = 'Pieces/Day'; break;
        case 'AssemblyLarge': $capacity_unit = 'Pieces/Day'; break;
        case 'Rolling': $capacity_unit = 'Pieces/Day'; break;
        case 'Packaging': $capacity_unit = 'Carton/Day'; break;
    }

    // 2. بررسی اینکه آیا رکوردی برای این روز و ایستگاه وجود دارد؟
    $existing_override = find_one_by_fields($pdo, 'tbl_planning_capacity_override', [
        'PlanningDate' => $gregorian_date,
        'StationID' => $station_id
    ]);

    $data = [
        'PlanningDate' => $gregorian_date,
        'StationID' => (int)$station_id,
        'SuggestedCapacity' => (float)$suggested_capacity,
        'FinalCapacity' => (float)$final_capacity,
        'CapacityUnit' => $capacity_unit,
        'LastUpdatedBy' => $user_id
    ];

    if ($existing_override) {
        // Update
        $result = update_record($pdo, 'tbl_planning_capacity_override', $existing_override['OverrideID'], $data, 'OverrideID');
    } else {
        // Insert
        $result = create_record($pdo, 'tbl_planning_capacity_override', $data);
    }

    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'ظرفیت نهایی با موفقیت ذخیره شد.';
    } else {
        http_response_code(500);
        $response['message'] = 'خطا در ذخیره‌سازی: ' . $result['message'];
    }

} catch (Exception $e) {
    error_log("API Save Capacity Override Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

