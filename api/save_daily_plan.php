<?php
// api/save_daily_plan.php
// API برای گام ۵: ذخیره کردن برنامه نهایی در دیتابیس

header('Content-Type: application/json');
include_once __DIR__ . '/../config/init.php';

/*
  TODO: قبل از اجرا، این جدول را در دیتابیس خود ایجاد کنید:
  
  CREATE TABLE daily_production_plan (
      id INT AUTO_INCREMENT PRIMARY KEY,
      plan_date DATE NOT NULL,              -- تاریخ برنامه‌ریزی
      part_id INT NOT NULL,                 -- شناسه قطعه
      station_id INT NOT NULL,              -- شناسه ایستگاه مقصد
      planned_qty INT NOT NULL,             -- تعداد برنامه‌ریزی شده
      produced_qty INT NOT NULL DEFAULT 0,  -- تعداد تولید شده (برای ردیابی)
      status VARCHAR(50) NOT NULL DEFAULT 'Pending', -- وضعیت: Pending, InProgress, Completed
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(plan_date),
      FOREIGN KEY (part_id) REFERENCES parts(id),
      FOREIGN KEY (station_id) REFERENCES stations(id) -- اطمینان حاصل کنید که جدول 'stations' وجود دارد
  );

*/

// --- اجرای API ---
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("درخواست نامعتبر.");
    }

    if (!isset($_POST['final_qty']) || !isset($_POST['station_id'])) {
        throw new Exception("داده‌های فرم ناقص است.");
    }

    $final_quantities = $_POST['final_qty'];
    $station_ids = $_POST['station_id'];
    $plan_date = date('Y-m-d'); // برنامه‌ریزی برای امروز (می‌تواند قابل انتخاب باشد)

    $db->beginTransaction();

    $inserted_count = 0;

    foreach ($final_quantities as $part_id => $qty) {
        $qty = (int)$qty;
        // فقط مواردی که تعداد نهایی آنها بزرگتر از صفر است را ذخیره کن
        if ($qty > 0) {
            if (!isset($station_ids[$part_id])) {
                throw new Exception("خطای سیستمی: شناسه ایستگاه برای قطعه $part_id یافت نشد.");
            }
            
            $part_id_int = (int)$part_id;
            $station_id_int = (int)$station_ids[$part_id];

            // اگر شناسه ایستگاه 99 (انبار محصول) بود، یعنی این قطعه نیازی به تولید ندارد و باید نادیده گرفته شود
            if ($station_id_int == 99) {
                continue; // برو سراغ قطعه بعدی
            }

            // درج در جدول برنامه‌ریزی
            $stmt = $db->prepare(
                "INSERT INTO daily_production_plan (plan_date, part_id, station_id, planned_qty, status) 
                 VALUES (?, ?, ?, ?, 'Pending')"
            );
            $stmt->execute([$plan_date, $part_id_int, $station_id_int, $qty]);
            $inserted_count++;
        }
    }

    if ($inserted_count == 0 && count($final_quantities) > 0) {
         throw new Exception("هیچ موردی برای ذخیره‌سازی ثبت نشد (مقادیر صفر یا انبار محصول بودند).");
    }

    // اگر همه چیز موفق بود
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "$inserted_count دستور کار با موفقیت ایجاد شد."
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
