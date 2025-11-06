<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';


$man_hours = filter_input(INPUT_GET, 'man_hours', FILTER_VALIDATE_FLOAT);
$plating_station_id = 4; // ایستگاه آبکاری

if (!$man_hours || $man_hours <= 0) {
    echo json_encode(['success' => false, 'message' => 'نفر-ساعت نامعتبر است.']);
    exit;
}

try {
    // واکشی قانون ظرفیت برای ایستگاه آبکاری
    // فرض: 'CalculationMethod' = 'PlatingManHours' و 'StandardValue' = 'Barrels per ManHour'
    $sql = "
        SELECT StandardValue 
        FROM tbl_planning_station_capacity_rules 
        WHERE StationID = ? AND CalculationMethod = 'PlatingManHours'
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plating_station_id]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        throw new Exception("قانون ظرفیت (PlatingManHours) برای ایستگاه آبکاری (ID: $plating_station_id) در جدول 'tbl_planning_station_capacity_rules' تعریف نشده است.");
    }

    $barrels_per_man_hour = (float)$rule['StandardValue'];
    if ($barrels_per_man_hour <= 0) {
        throw new Exception("مقدار استاندارد (بارل بر نفر-ساعت) در قانون ظرفیت صفر یا نامعتبر است.");
    }

    $total_capacity = $man_hours * $barrels_per_man_hour;

    echo json_encode([
        'success' => true,
        'data' => [
            'man_hours' => $man_hours,
            'barrels_per_man_hour' => $barrels_per_man_hour,
            'total_capacity_barrels' => round($total_capacity)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Get Plating Capacity Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در محاسبه ظرفیت: ' . $e->getMessage()]);
}
?>
