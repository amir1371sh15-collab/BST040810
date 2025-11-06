<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

try {
    // اطمینان از اینکه کاربر مجوز لازم برای مشاهده را دارد
    if (!has_permission('planning.production_schedule.view')) {
        throw new Exception('شما مجوز دسترسی به این اطلاعات را ندارید.');
    }

    $allItems = [];

    // --- بخش ۱: دریافت نیازمندی‌های خالص از آخرین اجرای MRP ---
    // (این بخش بدون تغییر باقی می‌ماند)
    $mrpSql = "
        SELECT
            nr.RunID,
            nr.ItemID AS PartID,
            p.PartName,
            p.FamilyID AS PartFamilyID,
            nr.NetRequirement AS Quantity,
            'MRP' AS Source,
            'کسری' AS CurrentStatusName,
            NULL AS CurrentStatusID,
            nr.Unit AS UnitName
        FROM
            tbl_planning_mrp_results nr
        JOIN
            tbl_parts p ON nr.ItemID = p.PartID
        JOIN
            tbl_part_families pf ON p.FamilyID = pf.FamilyID -- Join برای فیلتر خانواده
        WHERE
            nr.NetRequirement > 0
            AND nr.ItemType != 'ماده اولیه'
            AND pf.FamilyName NOT LIKE '%بست%' -- حذف کردن قطعات 'بست'
            AND nr.RunID = (SELECT MAX(RunID) FROM tbl_planning_mrp_run)
    ";
    
    $mrpStmt = $pdo->query($mrpSql);
    $mrpItems = $mrpStmt->fetchAll(PDO::FETCH_ASSOC);
    $allItems = array_merge($allItems, $mrpItems);


    // --- بخش ۲: دریافت قطعات نیمه ساخته (WIP) با وضعیت 'برش خورده' ---
    // [FIX] این کوئری اصلاح شد تا KG را به 'عدد' تبدیل کند
    $wipSql = "
        SELECT 
            wip_kg.PartID,
            wip_kg.PartName,
            wip_kg.PartFamilyID,
            
            -- [NEW] Logic to convert KG to Pieces if weight is available
            CASE
                WHEN pw.WeightGR IS NOT NULL AND pw.WeightGR > 0
                THEN ROUND((wip_kg.TotalWeightKG * 1000) / pw.WeightGR) -- Convert to pieces and round
                ELSE wip_kg.TotalWeightKG -- Keep KG if no weight found
            END AS Quantity,
            
            wip_kg.StationName AS Source,
            wip_kg.StatusName AS CurrentStatusName,
            wip_kg.CurrentStatusID,
            
            -- [NEW] Logic to set the unit based on conversion
            CASE 
                WHEN pw.WeightGR IS NOT NULL AND pw.WeightGR > 0 
                THEN 'عدد'
                ELSE 'KG'
            END AS UnitName
            
        FROM (
            -- This subquery (wip_kg) finds the total KG for each part
            SELECT 
                t.PartID, 
                p.PartName,
                p.FamilyID AS PartFamilyID,
                t.StatusAfterID AS CurrentStatusID, 
                ps.StatusName,
                t.ToStationID,
                s.StationName,
                SUM(t.NetWeightKG) AS TotalWeightKG
            FROM tbl_stock_transactions t
            JOIN tbl_parts p ON t.PartID = p.PartID
            LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
            LEFT JOIN tbl_stations s ON t.ToStationID = s.StationID
            WHERE 
                t.ToStationID IN (8, 9) -- فقط انبارهای نیمه ساخته و نهایی
            GROUP BY t.PartID, p.PartName, p.FamilyID, t.StatusAfterID, ps.StatusName, t.ToStationID, s.StationName
            HAVING TotalWeightKG > 0.01
        ) AS wip_kg
        
        -- [NEW] Join with part weights to find the LATEST active weight
        LEFT JOIN tbl_part_weights pw ON pw.PartWeightID = (
            SELECT PartWeightID 
            FROM tbl_part_weights
            WHERE PartID = wip_kg.PartID
              AND (EffectiveTo IS NULL OR EffectiveTo >= CURDATE())
            ORDER BY EffectiveFrom DESC
            LIMIT 1
        )
        
        WHERE
            wip_kg.StatusName = 'برش خورده' -- فیلتر کردن فقط برای وضعیت 'برش خورده'
    ";

    $wipStmt = $pdo->query($wipSql);
    $wipItems = $wipStmt->fetchAll(PDO::FETCH_ASSOC);
    $allItems = array_merge($allItems, $wipItems);

    // --- ارسال نتایج --
    if (empty($allItems)) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'هیچ نیازمندی MRP یا قطعه برش خورده‌ای یافت نشد.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $allItems]);

} catch (PDOException $e) {
    // خطای SQL
    http_response_code(500);
    error_log("Pressing Inputs API Error (PDO): " . $e->getMessage()); // لاگ کردن خطا
    echo json_encode(['success' => false, 'message' => 'خطا در پایگاه داده: ' . $e->getMessage()]);
} catch (Exception $e) {
    // سایر خطاها
    http_response_code(500);
    error_log("Pressing Inputs API Error (General): " . $e->getMessage()); // لاگ کردن خطا
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت داده‌های ورودی: ' . $e->getMessage()]);
}
?>
