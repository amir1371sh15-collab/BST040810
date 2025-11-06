<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks
if (!has_permission('planning.production_schedule.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// --- [NEW] Handle Delete Work Order ---
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!has_permission('planning.production_schedule.save')) { // یا دسترسی مجزا
        $_SESSION['message'] = 'شما مجوز حذف دستور کار را ندارید.';
        $_SESSION['message_type'] = 'danger';
     } else {
        $delete_id = (int)$_POST['delete_id'];
        $pdo->beginTransaction();
        try {
            // 1. Delete from link table
            // [FIX] این خط حذف شد چون جدول tbl_planning_work_order_machines وجود ندارد.
            // $stmt_link = $pdo->prepare("DELETE FROM tbl_planning_work_order_machines WHERE WorkOrderID = ?");
            // $stmt_link->execute([$delete_id]);
            
            // 2. Delete from main table
            $result = delete_record($pdo, 'tbl_planning_work_orders', $delete_id, 'WorkOrderID');
            
            $pdo->commit();
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'خطا در حذف: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    }
    header("Location: pressing_schedule.php"); // Reload the page
    exit;
}

// --- [NEW] Fetch Work Order History (Last 20) ---
$work_orders_history = find_all($pdo, "
    SELECT 
        wo.WorkOrderID, wo.PlannedDate, wo.Quantity, wo.Unit, wo.Status,
        p.PartName, s.StationName, m.MachineName
    FROM tbl_planning_work_orders wo
    JOIN tbl_parts p ON wo.PartID = p.PartID
    JOIN tbl_stations s ON wo.StationID = s.StationID
    LEFT JOIN tbl_machines m ON wo.MachineID = m.MachineID
    ORDER BY wo.WorkOrderID DESC 
    LIMIT 20
");


$pageTitle = "برنامه‌ریزی پرسکاری و پیچ‌سازی";
include_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <!-- [NEW] Display delete/edit messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- راهنما -->
            <div class="alert alert-info">
                <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>راهنمای برنامه‌ریزی پرسکاری</h5>
                <p>
                    ۱. **بارگذاری نیازمندی‌ها:** دکمه زیر را بزنید تا نیازمندی‌های خالص (MRP) و قطعات نیمه‌ساخته (WIP) با وضعیت "برش خورده" بارگذاری شوند.
                    <br>
                    ۲. **انتخاب دستگاه:** برای هر قطعه، دستگاه مورد نظر را از لیست انتخاب کنید.
                    <br>
                    ۳. **بررسی ظرفیت:** با انتخاب دستگاه، ظرفیت تولید روزانه آن به صورت خودکار نمایش داده می‌شود.
                    <br>
                    ۴. **ثبت برنامه:** مقدار نهایی تولید را بر اساس ظرفیت نمایش داده شده وارد کرده و برنامه را ذخیره کنید.
                </p>
            </div>

            <!-- گام ۱: دکمه بارگذاری -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button id="load-inputs-btn" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-seam me-2"></i> ۱. بارگذاری نیازمندی‌های پرسکاری
                </button>
                <div class="col-md-3">
                    <label for="planning-date" class="form-label">تاریخ اجرای برنامه *</label>
                    <!-- [FIX] Set value to Jalali date -->
                    <input type="text" id="planning-date" class="form-control text-center persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required>
                </div>
            </div>

            <!-- گام ۲: جدول برنامه‌ریزی -->
            <div id="planning-grid-container" class="mt-4" style="display: none;">
                <div class="card content-card">
                    <div class="card-header bg-info text-dark">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>۲. تخصیص دستگاه و تعیین مقدار تولید</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="p-3">قطعه</th>
                                        <th class="p-3">منبع</th>
                                        <th class="p-3">وضعیت فعلی</th>
                                        <th class="p-3">مقدار مورد نیاز</th>
                                        <th class="p-3" style="width: 20%;">دستگاه *</th>
                                        <th class="p-3" style="width: 15%;">ظرفیت روزانه (دستگاه)</th>
                                        <th class="p-3 text-success" style="width: 150px;">مقدار برنامه‌ریزی *</th>
                                        <th class="p-3">واحد</th>
                                    </tr>
                                </thead>
                                <tbody id="planning-tbody">
                                    <!-- Rows loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mt-3">
                            <button id="save-plan-btn" class="btn btn-danger btn-lg"
                                    <?php echo has_permission('planning.production_schedule.save') ? '' : 'disabled title="مجوز ذخیره برنامه را ندارید"'; ?>>
                                <i class="bi bi-save me-2"></i> ۳. نهایی‌سازی و ایجاد دستور کار
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- [NEW] گام ۴: تاریخچه دستور کارها -->
            <div class="card content-card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>تاریخچه آخرین دستور کارهای صادر شده (پرسکاری/پیچ‌سازی)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th class="p-2">ID</th>
                                    <th class="p-2">تاریخ برنامه</th>
                                    <th class="p-2">قطعه</th>
                                    <th class="p-2">ایستگاه</th>
                                    <th class="p-2">دستگاه</th>
                                    <th class="p-2">تعداد</th>
                                    <th class="p-2">واحد</th>
                                    <th class="p-2">وضعیت</th>
                                    <th class="p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($work_orders_history)): ?>
                                    <tr><td colspan="9" class="text-center text-muted p-3">دستور کاری یافت نشد.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($work_orders_history as $wo): ?>
                                        <tr>
                                            <td class="p-2"><?php echo $wo['WorkOrderID']; ?></td>
                                            <td class="p-2"><?php echo to_jalali($wo['PlannedDate']); ?></td>
                                            <td class="p-2"><?php echo htmlspecialchars($wo['PartName']); ?></td>
                                            <td class="p-2"><?php echo htmlspecialchars($wo['StationName']); ?></td>
                                            <td class="p-2"><?php echo htmlspecialchars($wo['MachineName'] ?? '---'); ?></td>
                                            <td class="p-2"><?php echo number_format($wo['Quantity'], 2); ?></td>
                                            <td class="p-2"><?php echo htmlspecialchars($wo['Unit']); ?></td>
                                            <td class="p-2">
                                                <?php
                                                $status_class = 'bg-secondary';
                                                if ($wo['Status'] == 'InProgress') $status_class = 'bg-info text-dark';
                                                if ($wo['Status'] == 'Completed') $status_class = 'bg-success';
                                                echo "<span class='badge {$status_class}'>{$wo['Status']}</span>";
                                                ?>
                                            </td>
                                            <td class="p-2 text-nowrap">
                                                <a href="edit_work_order.php?edit_id=<?php echo $wo['WorkOrderID']; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <form method="POST" action="pressing_schedule.php" class="d-inline" onsubmit="return confirm('آیا از حذف این دستور کار مطمئن هستید؟');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $wo['WorkOrderID']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm py-0 px-1" title="حذف">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiGetInputsUrl = '../../api/get_pressing_inputs.php';
    const apiGetMachinesUrl = '../../api/get_compatible_machines_for_part.php';
    const apiGetCapacityUrl = '../../api/get_part_capacity.php';
    const apiSavePlanUrl = '../../api/save_production_schedule.php';

    // --- (Load Input Data - STEP 1) ---
    $('#load-inputs-btn').on('click', function() {
        const $this = $(this);
        $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>در حال بارگذاری داده‌ها...');
        $('#planning-tbody').empty();
        $('#planning-grid-container').slideUp(); 

        $.ajax({
            url: apiGetInputsUrl, 
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let itemsProcessed = 0;
                    
                    $.each(response.data, function(i, item) {
                        const rowId = `plan_row_${i}`;
                        const isMRP = item.Source === 'MRP';
                        const sourceBadge = isMRP ? `<span class="badge bg-danger">MRP</span>` : `<span class="badge bg-primary">WIP</span>`;
                        const statusBadgeClass = isMRP ? 'bg-danger' : 'bg-secondary';
                        const step = (item.UnitName && item.UnitName.toUpperCase() === 'KG') ? 0.01 : 1;
                        const neededQty = parseFloat(item.Quantity).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        
                        $('#planning-tbody').append(`
                            <tr id="${rowId}" 
                                data-part-id="${item.PartID}" 
                                data-status-id="${item.CurrentStatusID || 0}" 
                                data-unit="${item.UnitName}"
                                data-source="${item.Source}"
                                data-mrp-run-id="${item.RunID || ''}">

                                <td class="p-2">${item.PartName}</td>
                                <td class="p-2">${sourceBadge}</td>
                                <td class="p-2"><span class="badge ${statusBadgeClass}">${item.CurrentStatusName}</span></td>
                                <td class="p-2">${neededQty}</td>
                                
                                <td class="p-2">
                                    <select class="form-select form-select-sm machine-select" data-row-id="${rowId}">
                                        <option value="">درحال بارگذاری...</option>
                                    </select>
                                </td>
                                
                                <td class="p-2 text-center">
                                    <span class="capacity-display fw-bold text-primary" id="capacity-display-${rowId}">-</span>
                                </td>

                                <td class="p-2">
                                    <input type="number" 
                                           class="form-control form-control-sm planned-qty-input" 
                                           step="${step}"
                                           value="${parseFloat(item.Quantity).toFixed(step == 1 ? 0 : 2)}"
                                           min="0">
                                </td>
                                
                                <td class="p-2">${item.UnitName}</td>
                            </tr>
                        `);

                        // Asynchronously load machines for this part
                        $.ajax({
                            url: apiGetMachinesUrl,
                            type: 'GET',
                            data: { part_id: item.PartID },
                            dataType: 'json',
                            success: function(machineResponse) {
                                const $select = $(`#${rowId} .machine-select`);
                                $select.empty().append('<option value="">-- انتخاب دستگاه --</option>');
                                if (machineResponse.success && machineResponse.data.length > 0) {
                                    $.each(machineResponse.data, function(j, machine) {
                                        // [FIX] ذخیره کردن StationID از API در data attribute
                                        $select.append(`<option value="${machine.MachineID}" data-station-id="${machine.StationID}">${machine.MachineName}</option>`);
                                    });
                                } else {
                                    $select.append('<option value="" disabled>دستگاه سازگار یافت نشد</option>');
                                    $select.prop('disabled', true);
                                    // [FIX] نمایش خطا در بخش ظرفیت
                                    $(`#capacity-display-${rowId}`).text('خطا: ماشین').addClass('text-danger');
                                }
                            },
                            error: function(xhr) {
                                console.error("Machine load error:", xhr.responseText);
                                $(`#${rowId} .machine-select`).empty().append('<option value="" disabled>خطا در بارگذاری</option>');
                                $(`#capacity-display-${rowId}`).text('خطا').addClass('text-danger');
                            },
                            complete: function() {
                                itemsProcessed++;
                                if (itemsProcessed === response.data.length) {
                                    $('#planning-grid-container').slideDown();
                                    $this.prop('disabled', false).html('<i class="bi bi-box-seam me-2"></i> ۱. بارگذاری مجدد نیازمندی‌ها');
                                }
                            }
                        });
                    });

                } else {
                    $('#planning-tbody').html('<tr><td colspan="8" class="text-center text-muted p-4">نیازمندی (MRP) یا قطعه برش خورده (WIP) یافت نشد.</td></tr>');
                    $('#planning-grid-container').slideDown();
                    $this.prop('disabled', false).html('<i class="bi bi-box-seam me-2"></i> ۱. بارگذاری نیازمندی‌های پرسکاری');
                }
            },
            error: function(xhr) {
                alert('خطا در بارگذاری داده‌های ورودی: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
                $this.prop('disabled', false).html('<i class="bi bi-box-seam me-2"></i> ۱. بارگذاری نیازمندی‌های پرسکاری');
            }
        });
    });

    // --- (STEP 2: Machine Selection & Capacity Check) ---
    $(document).on('change', '.machine-select', function() {
        const machineId = $(this).val();
        const rowId = $(this).data('row-id');
        const $capacityDisplay = $(`#capacity-display-${rowId}`);
        const $row = $(this).closest('tr');
        const partId = $row.data('part-id');
        const unit = $row.data('unit'); // [FIX] واحد را از ردیف بخوان

        if (!machineId) {
            $capacityDisplay.text('-');
            return;
        }

        $capacityDisplay.html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: apiGetCapacityUrl,
            type: 'GET',
            // [FIX] ارسال واحد به API
            data: { part_id: partId, machine_id: machineId, unit: unit }, 
            dataType: 'json',
            success: function(capacityResponse) {
                if (capacityResponse.success) {
                    const capacity = parseFloat(capacityResponse.data.capacity).toLocaleString();
                    $capacityDisplay.text(`${capacity} ${capacityResponse.data.unit || 'عدد'}`);
                } else {
                    $capacityDisplay.text('N/A').addClass('text-danger');
                    console.error("Capacity Error:", capacityResponse.message);
                }
            },
            error: function(xhr) {
                $capacityDisplay.text('خطا').addClass('text-danger');
                console.error("Capacity API Error:", xhr.responseText);
            }
        });
    });

    // --- (STEP 3: Save Final Plan) ---
    $('#save-plan-btn').on('click', function() {
        if ($(this).is(':disabled')) return;

        const $this = $(this);
        const finalPlan = [];
        const planningDate = $('#planning-date').val();
        let hasError = false;

        if (!planningDate) {
            alert('لطفاً تاریخ اجرای برنامه را انتخاب کنید.');
            $('#planning-date').addClass('is-invalid');
            return;
        } else {
             $('#planning-date').removeClass('is-invalid');
        }

        $('#planning-tbody tr').each(function() {
            const $row = $(this);
            const quantity = $row.find('.planned-qty-input').val();
            const $machineSelect = $row.find('.machine-select');
            const machineId = $machineSelect.val();
            // [FIX] خواندن StationID از data attribute سلکت ماشین
            const stationId = $machineSelect.find('option:selected').data('station-id');

            // فقط ردیف‌هایی که مقدار دارند را بررسی کن
            if (parseFloat(quantity) > 0) {
                if (!machineId || !stationId) {
                    $machineSelect.addClass('is-invalid');
                    hasError = true;
                } else {
                    $machineSelect.removeClass('is-invalid');
                    
                    finalPlan.push({
                        part_id: $row.data('part-id'),
                        required_status_id: $row.data('status-id'), 
                        station_id: parseInt(stationId),
                        machine_id: parseInt(machineId),
                        planned_quantity: parseFloat(quantity),
                        unit: $row.data('unit'),
                        source: $row.data('source'), // [FIX] ارسال منبع
                        mrp_run_id: $row.data('mrp-run-id') // [FIX] ارسال RunID
                    });
                }
            } else {
                 $machineSelect.removeClass('is-invalid');
            }
        });

        if (hasError) {
            alert("لطفاً برای تمام ردیف‌هایی که مقدار برنامه‌ریزی دارند، دستگاه را انتخاب کنید.");
            return;
        }

        if (finalPlan.length === 0) {
            alert("هیچ مقداری برای تولید برنامه‌ریزی نشده است.");
            return;
        }

        $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>در حال نهایی‌سازی و ایجاد دستور کار...');
        
        $.ajax({
            url: apiSavePlanUrl, 
            type: 'POST',
            data: JSON.stringify({ 
                planned_items: finalPlan,
                planning_date_jalali: planningDate // [FIX] ارسال تاریخ با کلید صحیح
            }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(`دستور کارهای تولیدی با موفقیت ایجاد شدند. ${response.message}`);
                    location.reload(); // [FIX] صفحه را کامل ریلود کن تا تاریخچه آپدیت شود
                } else {
                    alert('خطا در ذخیره برنامه تولید: ' + response.message);
                }
                $this.prop('disabled', false).html('<i class="bi bi-save me-2"></i> ۳. نهایی‌سازی و ایجاد دستور کار');
            },
            error: function(xhr) {
                let errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'پاسخ معتبری از سرور دریافت نشد.';
                alert('خطای سیستمی در ذخیره برنامه. ' + errorMsg);
                $this.prop('disabled', false).html('<i class="bi bi-save me-2"></i> ۳. نهایی‌سازی و ایجاد دستور کار');
            }
        });
    });

});
</script>

