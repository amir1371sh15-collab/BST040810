<?php
require_once __DIR__ . '/../../config/init.php';


$pageTitle = "گزارش پیگیری و تحقق برنامه‌ها";
include_once __DIR__ . '/../../templates/header.php';

// --- مدیریت فیلترهای تاریخ ---
$today = new DateTime();
$dayOfWeek = $today->format('w'); // 0 (Sunday) to 6 (Saturday)
$startDateDefault = (clone $today)->modify('-' . (($dayOfWeek + 1) % 7) . ' days')->format('Y-m-d'); // شنبه
$endDateDefault = (clone $today)->modify('+' . (5 - $dayOfWeek) . ' days')->format('Y-m-d'); // جمعه

// [FIX] بررسی امن با isset
$start_date_gregorian = isset($_GET['start_date']) && !empty($_GET['start_date']) ? to_gregorian($_GET['start_date']) : $startDateDefault;
$end_date_gregorian = isset($_GET['end_date']) && !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : $endDateDefault;

$start_date_jalali = to_jalali($start_date_gregorian);
$end_date_jalali = to_jalali($end_date_gregorian);

// --- کوئری اصلی برای مقایسه برنامه با واقعیت ---
// [FIX] واکشی وزن واحد (WeightGR) و وضعیت فعلی (Status)
$sql = "
    SELECT 
        wo.WorkOrderID,
        wo.PlannedDate,
        wo.Quantity AS Planned_Qty,
        wo.Unit AS Planned_Unit,
        wo.Status AS CurrentStatus, -- [!!!] NEW: واکشی وضعیت فعلی
        wo.StationID,
        p.PartName,
        s.StationName,
        pw.WeightGR, -- [!!!] NEW: واکشی وزن واحد
        
        -- Method 1: Warehouse Data (Sum of NET output from that station AFTER planned date)
        (
            SELECT SUM(st.NetWeightKG) 
            FROM tbl_stock_transactions st 
            WHERE st.FromStationID = wo.StationID
              AND st.PartID = wo.PartID 
              AND DATE(st.TransactionDate) >= wo.PlannedDate
              AND st.NetWeightKG > 0 
        ) AS Actual_Warehouse_KG,
        
        -- Method 2: Production Data (Sum from specific station log tables AFTER planned date)
        CASE 
            WHEN wo.StationID = 2 THEN (
                SELECT SUM(plog.ProductionKG) 
                FROM tbl_prod_daily_log_details plog
                JOIN tbl_prod_daily_log_header h ON plog.HeaderID = h.HeaderID
                WHERE plog.PartID = wo.PartID AND h.LogDate >= wo.PlannedDate
            )
            WHEN wo.StationID = 4 THEN (
                SELECT SUM(plog.PlatedKG) 
                FROM tbl_plating_log_details plog
                JOIN tbl_plating_log_header h ON plog.PlatingHeaderID = h.PlatingHeaderID -- [FIX]
                WHERE plog.PartID = wo.PartID AND h.LogDate >= wo.PlannedDate
            )
            WHEN wo.StationID = 12 THEN (
                SELECT SUM(alog.ProductionKG)
                FROM tbl_assembly_log_entries alog
                JOIN tbl_assembly_log_header h ON alog.AssemblyHeaderID = h.AssemblyHeaderID
                WHERE alog.PartID = wo.PartID AND h.LogDate >= wo.PlannedDate
            )
            WHEN wo.StationID = 5 THEN (
                SELECT SUM(rlog.ProductionKG)
                FROM tbl_rolling_log_entries rlog
                JOIN tbl_rolling_log_header h ON rlog.RollingHeaderID = h.RollingHeaderID
                WHERE rlog.PartID = wo.PartID AND h.LogDate >= wo.PlannedDate
            )
            WHEN wo.StationID = 1 THEN (
                 SELECT SUM(plog.WashedKG) 
                FROM tbl_plating_log_details plog
                JOIN tbl_plating_log_header h ON plog.PlatingHeaderID = h.PlatingHeaderID -- [FIX]
                WHERE plog.PartID = wo.PartID AND h.LogDate >= wo.PlannedDate
            )
             WHEN wo.StationID = 7 THEN (
                 SELECT SUM(plog.ReworkedKG) 
                FROM tbl_plating_log_details plog
                JOIN tbl_plating_log_header h ON plog.PlatingHeaderID = h.PlatingHeaderID -- [FIX]
                WHERE plog.PartID = wo.PartID AND h.LogDate >= wo.PlannedDate
            )
            -- (برای ایستگاه‌های دیگر مانند پیچ‌سازی و دنده‌زنی باید لاگ‌های مربوطه اضافه شوند)
            ELSE 0 
        END AS Actual_Production_KG

    FROM tbl_planning_work_orders wo
    JOIN tbl_parts p ON wo.PartID = p.PartID
    JOIN tbl_stations s ON wo.StationID = s.StationID
    -- [FIX] واکشی وزن واحد
    LEFT JOIN tbl_part_weights pw ON p.PartID = pw.PartID AND pw.EffectiveTo IS NULL
    WHERE wo.PlannedDate BETWEEN ? AND ?
    AND wo.Unit IN ('KG', 'عدد') 
    ORDER BY wo.PlannedDate, s.StationName, p.PartName
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date_gregorian, $end_date_gregorian]);
$report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for progress bar
function calculate_percentage($actual, $planned) {
    if ($planned <= 0) {
        return ['percent' => 0, 'class' => 'bg-secondary', 'suggested_status' => 'Generated'];
    }
    $percent = ($actual / $planned) * 100;
    
    // [!!!] NEW: منطق پیشنهاد وضعیت
    $suggested_status = 'Generated';
    if ($percent >= 100) {
        $class = 'bg-success';
        $suggested_status = 'Completed';
    } elseif ($percent >= 80) {
        $class = 'bg-success'; // سبز برای بالای 80
        $suggested_status = 'InProgress'; // پیشنهادی: در حال انجام
    } elseif ($percent > 1) { // اگر حتی 1 درصد انجام شده
        $class = 'bg-warning text-dark';
        $suggested_status = 'InProgress';
    } else {
        $class = 'bg-danger'; // صفر درصد
    }
    
    return ['percent' => min(100, $percent), 'class' => $class, 'suggested_status' => $suggested_status];
}

// لیست وضعیت‌ها
$status_options = ['Generated', 'InProgress', 'Completed', 'Cancelled'];
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom d-print-none">
                <h1 class="h2"><i class="bi bi-check-all me-2"></i><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <!-- Filter Form -->
            <div class="card content-card mb-3 d-print-none">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-filter me-2"></i>انتخاب بازه زمانی</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start-date" class="form-label">از تاریخ</label>
                            <input type="text" id="start-date" name="start_date" class="form-control text-center persian-date" value="<?php echo $start_date_jalali; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="end-date" class="form-label">تا تاریخ</label>
                            <input type="text" id="end-date" name="end_date" class="form-control text-center persian-date" value="<?php echo $end_date_jalali; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>اعمال فیلتر
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card content-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        گزارش تحقق از <span class="text-primary"><?php echo $start_date_jalali; ?></span> تا <span class="text-primary"><?php echo $end_date_jalali; ?></span>
                    </h5>
                    <button onclick="window.print()" class="btn btn-outline-success btn-sm d-print-none">
                        <i class="bi bi-printer-fill me-2"></i>چاپ گزارش
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" class="align-middle text-center">تاریخ</th>
                                    <th rowspan="2" class="align-middle text-center">ایستگاه</th>
                                    <th rowspan="2" class="align-middle text-center">قطعه</th>
                                    <th rowspan="2" class="align-middle text-center">برنامه</th>
                                    <th colspan="2" class="text-center" style="background-color: #e0e7ff;">۱. تحقق (آمار تولید)</th>
                                    <th colspan="2" class="text-center" style="background-color: #d4f8e0;">۲. تحقق (خروجی انبار)</th>
                                    <th rowspan="2" class="align-middle text-center d-print-none" style="width: 180px;">تغییر وضعیت</th> <!-- [!!!] NEW -->
                                </tr>
                                <tr>
                                    <th style="background-color: #e0e7ff;">مقدار (KG)</th>
                                    <th style="background-color: #e0e7ff; width: 15%;">درصد</th>
                                    <th style="background-color: #d4f8e0;">مقدار (KG)</th>
                                    <th style="background-color: #d4f8e0; width: 15%;">درصد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report_data)): ?>
                                    <tr><td colspan="9" class="text-center text-muted p-3">دستور کاری برای این بازه زمانی یافت نشد.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($report_data as $row): ?>
                                        <?php
                                        $planned_qty_orig = (float)$row['Planned_Qty'];
                                        $unit = $row['Planned_Unit'];
                                        $weight_gr = (float)($row['WeightGR'] ?? 0);
                                        
                                        // [!!!] NEW: تبدیل برنامه به کیلوگرم
                                        $planned_kg = 0;
                                        $planned_display = "";
                                        
                                        if ($unit == 'KG') {
                                            $planned_kg = $planned_qty_orig;
                                            $planned_display = "<strong>" . number_format($planned_kg, 2) . "</strong> <small>KG</small>";
                                        } elseif ($unit == 'عدد' && $weight_gr > 0) {
                                            $planned_kg = ($planned_qty_orig * $weight_gr) / 1000;
                                            $planned_display = "<strong>" . number_format($planned_qty_orig) . "</strong> <small>عدد</small><br><small class='text-muted'>(" . number_format($planned_kg, 2) . " KG)</small>";
                                        } else {
                                            $planned_display = "<strong>" . number_format($planned_qty_orig) . "</strong> <small>$unit</small><br><small class='text-danger'>(بدون وزن)</small>";
                                        }
                                        
                                        // محاسبه تحقق تولید (بر اساس کیلوگرم)
                                        $actual_prod = (float)($row['Actual_Production_KG'] ?? 0);
                                        $prod_stats = calculate_percentage($actual_prod, $planned_kg);

                                        // محاسبه تحقق انبار (بر اساس کیلوگرم)
                                        $actual_wh = (float)($row['Actual_Warehouse_KG'] ?? 0);
                                        $wh_stats = calculate_percentage($actual_wh, $planned_kg);
                                        
                                        // [!!!] NEW: تعیین وضعیت پیشنهادی بر اساس تحقق *تولید*
                                        $suggested_status = $prod_stats['suggested_status'];
                                        $current_status = $row['CurrentStatus'];
                                        ?>
                                        <tr>
                                            <td class="text-center small"><?php echo to_jalali($row['PlannedDate']); ?></td>
                                            <td><?php echo htmlspecialchars($row['StationName']); ?></td>
                                            <td><?php echo htmlspecialchars($row['PartName']); ?></td>
                                            <td class="text-center small"><?php echo $planned_display; ?></td>
                                            
                                            <!-- آمار تولید -->
                                            <td class="text-center"><?php echo number_format($actual_prod, 2); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $prod_stats['class']; ?>" role="progressbar" style="width: <?php echo $prod_stats['percent']; ?>%;" aria-valuenow="<?php echo $prod_stats['percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo number_format($prod_stats['percent'], 0); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- آمار انبار -->
                                            <td class="text-center"><?php echo number_format($actual_wh, 2); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $wh_stats['class']; ?>" role="progressbar" style="width: <?php echo $wh_stats['percent']; ?>%;" aria-valuenow="<?php echo $wh_stats['percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo number_format($wh_stats['percent'], 0); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- [!!!] NEW: ستون تغییر وضعیت -->
                                            <td class="d-print-none">
                                                <div class="input-group input-group-sm">
                                                    <select class="form-select status-select" data-wo-id="<?php echo $row['WorkOrderID']; ?>">
                                                        <?php
                                                        $is_suggested = false;
                                                        foreach ($status_options as $status) {
                                                            $selected = ($status == $current_status) ? 'selected' : '';
                                                            $label = $status;
                                                            
                                                            // اگر وضعیت فعلی نیست و وضعیت پیشنهادی است
                                                            if ($status == $suggested_status && $status != $current_status) {
                                                                $label .= " (پیشنهادی)";
                                                                $is_suggested = true;
                                                            }
                                                            echo "<option value='{$status}' {$selected}>{$label}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="btn btn-outline-success change-status-btn" title="ذخیره وضعیت" <?php echo ($current_status == $suggested_status || $current_status == 'Completed' || $current_status == 'Cancelled') ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </div>
                                                <?php if ($is_suggested && $current_status != $suggested_status): ?>
                                                    <small class="text-success d-block text-center mt-1">پیشنهاد جدید!</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="status-alert-container" class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index: 1100">
                <!-- Alerts will be injected here -->
            </div>

        </main>
    </div>
</div>

<!-- استایل‌های مخصوص چاپ -->
<style>
@media print {
    body { background-color: #fff; }
    .card-header, .card-body { border-width: 1px !important; }
    .table-bordered th, .table-bordered td { border-width: 1px !important; }
    .progress { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .progress-bar { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .bg-success { background-color: #d1e7dd !important; color: #0f5132 !important; }
    .bg-warning { background-color: #fff3cd !important; color: #664d03 !important; }
    .bg-danger { background-color: #f8d7da !important; color: #842029 !important; }
}
</style>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>

<!-- [!!!] NEW: JS for Status Update -->
<script>
$(document).ready(function() {
    
    // API endpoint for updating status
    const UPDATE_STATUS_API = '<?php echo BASE_URL; ?>api/update_work_order_status.php';

    // Handle status change
    $('.change-status-btn').on('click', function() {
        const $this = $(this);
        const $select = $this.closest('.input-group').find('.status-select');
        const workOrderId = $select.data('wo-id');
        const newStatus = $select.val();
        
        $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        $.ajax({
            url: UPDATE_STATUS_API,
            type: 'POST',
            data: {
                work_order_id: workOrderId,
                new_status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showStatusAlert(`وضعیت دستور کار ${workOrderId} به ${newStatus} تغییر یافت.`, 'success');
                    $this.removeClass('btn-outline-success').addClass('btn-success')
                         .html('<i class="bi bi-check-all"></i>');
                    
                    // Remove " (پیشنهادی)" label if it exists
                    $select.find('option:selected').text(newStatus);
                    
                    // Disable if completed or cancelled
                    if(newStatus === 'Completed' || newStatus === 'Cancelled') {
                        $select.prop('disabled', true);
                    }
                } else {
                    showStatusAlert('خطا: ' + response.message, 'danger');
                    $this.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
                }
            },
            error: function(xhr) {
                showStatusAlert('خطای سرور: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText), 'danger');
                $this.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
            }
        });
    });

    // Show a floating alert at the bottom of the screen
    function showStatusAlert(message, type = 'success') {
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show shadow-lg" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#status-alert-container').append(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            $('#' + alertId).alert('close');
        }, 5000);
    }
});
</script>

