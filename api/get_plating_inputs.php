<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$mode = $_GET['mode'] ?? 'plating'; // plating, washing, rework

// [!!!] دریافت تاریخ برای محاسبه موجودی برنامه‌ریزی شده
$planned_date_jalali = $_GET['planned_date'] ?? null;
$planned_date_gregorian = $planned_date_jalali ? to_gregorian($planned_date_jalali) : date('Y-m-d');

// [FIX] بازگشت به ساختار زیرکوئری (پایدارتر) برای جلوگیری از خطای فیلتر NULL
$base_sql = "
    SELECT 
        wip.PartID, 
        wip.PartName,
        wip.FamilyID,
        pf.FamilyName,
        wip.CurrentStatusID, 
        wip.StatusName,
        wip.StationName,
        wip.TotalWeightKG,
        IFNULL(planned.TotalPlannedKG, 0) AS PlannedTodayKG  -- [!!!] ستون جدید
    FROM (
        -- ابتدا تمام موجودی انبار را با جزئیات محاسبه می‌کنیم
        SELECT 
            t.PartID, 
            p.PartName,
            p.FamilyID,
            t.StatusAfterID AS CurrentStatusID, 
            ps.StatusName AS StatusName,
            s.StationName,
            SUM(t.NetWeightKG) AS TotalWeightKG
        FROM tbl_stock_transactions t
        JOIN tbl_parts p ON t.PartID = p.PartID
        LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
        LEFT JOIN tbl_stations s ON t.ToStationID = s.StationID
        WHERE 
            t.ToStationID IN (8, 9) -- انبارهای نیمه ساخته و نهایی
        GROUP BY t.PartID, p.PartName, p.FamilyID, t.StatusAfterID, ps.StatusName, s.StationName
        HAVING TotalWeightKG > 0.01
    ) AS wip
    -- سپس خانواده‌ها را برای فیلتر کردن جوین می‌کنیم
    JOIN tbl_part_families pf ON wip.FamilyID = pf.FamilyID
    
    -- [!!!] جوین کردن برنامه‌ریزی‌های قبلی در همان روز
    LEFT JOIN (
        SELECT 
            PartID, 
            SUM(Quantity) AS TotalPlannedKG 
        FROM tbl_planning_work_orders
        WHERE PlannedDate = ? -- پارامتر تاریخ
        GROUP BY PartID
    ) AS planned ON wip.PartID = planned.PartID
";

$params = [$planned_date_gregorian]; // [!!!] اولین پارامتر همیشه تاریخ است
$where_clause = ""; // این WHERE بر روی نتایج زیرکوئری اعمال می‌شود

try {
    if ($mode === 'plating') {
        // حالت آبکاری: [FIX] فقط فیلتر خانواده (فیلتر وضعیت حذف شد)
        $family_names = ['محفظه کوچک', 'پیچ کوچک', 'تسمه کوچک', 'بست بزرگ', 'بست کوچک'];
        $placeholders = implode(',', array_fill(0, count($family_names), '?'));
        
        $where_clause = " WHERE pf.FamilyName IN ($placeholders)";
        $params = array_merge($params, $family_names); // [!!!] ادغام پارامتر تاریخ با پارامتر خانواده‌ها

    } elseif ($mode === 'washing') {
        // حالت شستشو: [FIX] طبق درخواست: بدون فیلتر خانواده و بدون فیلتر وضعیت
        $where_clause = ""; // بدون فیلتر
        // $params فقط شامل تاریخ باقی می‌ماند

    } elseif ($mode === 'rework') {
        // حالت دوباره‌کاری: بدون فیلتر خانواده و بدون فیلتر وضعیت
        $where_clause = ""; // بدون فیلتر
        // $params فقط شامل تاریخ باقی می‌ماند
    } else {
        throw new Exception('حالت (mode) نامعتبر است.');
    }
    
    // ترکیب کوئری نهایی
    $sql = $base_sql . $where_clause . " ORDER BY pf.FamilyName, wip.PartName";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // [!!!] اجرا با پارامترهای جدید
    $wip_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $wip_items, 'mode' => $mode, 'date_used' => $planned_date_gregorian]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Get Plating Inputs Error (Mode: $mode): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در واکشی اطلاعات: ' . $e->getMessage()]);
}
?>
