<?php
require_once __DIR__ . '/../../config/init.php';
// ... (rest of the file includes)
if (!has_permission('planning.mrp.run')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

// --- UPDATED LOGIC ---
// Fetch *all completed* MRP runs to select from
$all_runs = find_all($pdo, "
    SELECT 
        r.RunID, 
        r.RunDate, 
        u.Username,
        (SELECT COUNT(*) FROM tbl_planning_mrp_results res WHERE res.RunID = r.RunID AND res.NetRequirement > 0) as NetItemCount
    FROM tbl_planning_mrp_run r
    LEFT JOIN tbl_users u ON r.RunByUserID = u.UserID
    WHERE r.Status = 'Completed'
    ORDER BY r.RunDate DESC
    LIMIT 50
");

// Fetch WIP Inventory (from Station 8)
$wip_inventory = find_all($pdo, "
    SELECT 
        t.PartID, p.PartName, t.StatusAfterID, s.StatusName,
        SUM(t.NetWeightKG) AS TotalNetWeightKG,
        SUM(t.CartonQuantity) AS TotalCartonQuantity
    FROM tbl_stock_transactions t
    JOIN tbl_parts p ON t.PartID = p.PartID
    LEFT JOIN tbl_part_statuses s ON t.StatusAfterID = s.StatusID
    WHERE t.ToStationID = 8 -- (8 = Anbar Monfaseleh)
    GROUP BY t.PartID, t.StatusAfterID
    HAVING TotalNetWeightKG > 0.01 OR TotalCartonQuantity > 0
    ORDER BY p.PartName, s.StatusName
");

$page_title = "فاز ۲: برنامه‌ریزی تولید و ایجاد دستور کار";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <!-- Global Alert for delete/save -->
            <div id="plan-result-alert" style="display: none;"></div>


            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                <b>راهنما:</b>
                <br>
                ۱. از لیست "اجراهای MRP (فاز ۱)"، یک یا چند مورد از نیازسنجی‌های قبلی را برای برنامه‌ریزی انتخاب کنید.
                <br>
                ۲. از لیست "موجودی در حال کار (WIP)"، مواردی را که می‌خواهید به مرحله بعد ارسال شوند، انتخاب کنید.
                <br>
                ۳. شما می‌توانید **فقط بر اساس WIP** یا **فقط بر اساس MRP** یا **ترکیبی از هر دو** دستور کار ایجاد کنید.
            </div>


            <!-- Card 1: Select MRP Runs (NEW) -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">۱. انتخاب از نتایج MRP (نیازمندی‌های خالص)</h5>
                    <button id="select-all-runs" class="btn btn-light btn-sm">انتخاب/لغو همه</button>
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;"><input type="checkbox" id="runs-select-all-header"></th>
                                <th>شناسه اجرا</th>
                                <th>تاریخ اجرا</th>
                                <th>کاربر</th>
                                <th>اقلام کسری</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="mrp-runs-tbody">
                            <?php if (empty($all_runs)): ?>
                                <tr><td colspan="6" class="text-center">هیچ اجرای MRP (فاز ۱) تکمیل شده‌ای یافت نشد.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($all_runs as $run): ?>
                                <tr id="run-row-<?php echo $run['RunID']; ?>">
                                    <td><input type="checkbox" class="run-checkbox" value="<?php echo $run['RunID']; ?>"></td>
                                    <td><?php echo $run['RunID']; ?></td>
                                    <td><?php echo to_jalali($run['RunDate']); ?></td>
                                    <td><?php echo htmlspecialchars($run['Username'] ?? '---'); ?></td>
                                    <td><?php echo $run['NetItemCount']; ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm py-0 px-1 view-run-results" data-run-id="<?php echo $run['RunID']; ?>" data-bs-toggle="modal" data-bs-target="#runResultsModal" title="مشاهده نتایج">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                        <!-- NEW DELETE BUTTON -->
                                        <button class="btn btn-danger btn-sm py-0 px-1 delete-run-btn" data-run-id="<?php echo $run['RunID']; ?>" data-run-date="<?php echo to_jalali($run['RunDate']); ?>" data-bs-toggle="modal" data-bs-target="#deleteRunModal" title="حذف این اجرا">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 2: WIP Processing (Parallel Demand) -->
            <div class="card mb-4">
                <div class="card-header bg-info text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">۲. انتخاب از موجودی در حال کار (WIP انبار منفصله)</h5>
                    <button id="select-all-wip" class="btn btn-light btn-sm">انتخاب/لغو همه</button>
                </div>
                 <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;"><input type="checkbox" id="wip-select-all-header"></th>
                                <th>نام قطعه</th>
                                <th>وضعیت فعلی</th>
                                <th>موجودی (KG)</th>
                                <th>موجودی (کارتن)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($wip_inventory)): ?>
                                <tr><td colspan="5" class="text-center">هیچ موجودی در انبار منفصله یافت نشد.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($wip_inventory as $item): ?>
                                <tr>
                                    <!-- Use composite key, handle NULL status -->
                                    <td><input type="checkbox" class="wip-checkbox" value="<?php echo $item['PartID'] . ':' . ($item['StatusAfterID'] ?? 'NULL'); ?>"></td>
                                    <td><?php echo htmlspecialchars($item['PartName']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['StatusName'] ?? '-- بدون وضعیت --'); ?></span></td>
                                    <td><?php echo number_format($item['TotalNetWeightKG'], 2); ?></td>
                                    <td><?php echo number_format($item['TotalCartonQuantity'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generate Plan Button -->
            <div class="text-center">
                <button id="generate-plan-btn" class="btn btn-success btn-lg">
                    <i class="bi bi-play-circle-fill"></i> ایجاد دستور کارهای اولیه (فاز ۲)
                </button>
            </div>
            
            <div id="plan-results-container" class="mt-4" style="display: none;">
                <h4>نتایج برنامه‌ریزی:</h4>
                <div id="plan-results-output" class="alert alert-info"></div>
            </div>

        </main>
    </div>
</div>

<!-- Modal for Run Results -->
<div class="modal fade" id="runResultsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="runResultsModalLabel">نتایج اجرای MRP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="runResultsModalBody">
                <!-- Content Loaded by AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<!-- NEW Modal for Delete Confirmation -->
<div class="modal fade" id="deleteRunModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأیید حذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>آیا از حذف اجرای MRP با شناسه <strong id="delete-run-id-display"></strong> (تاریخ: <span id="delete-run-date-display"></span>) مطمئن هستید؟</p>
                <p class="text-danger">توجه: با حذف این اجرا، تمام نتایج و دستور کارهای مرتبط با آن نیز حذف خواهند شد.</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="run-id-to-delete" value="">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-run-btn">بله، حذف کن</button>
            </div>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // --- Select All Logic ---
    $('#select-all-runs, #runs-select-all-header').on('click', function(e) {
        e.stopPropagation();
        let isChecked = $(this).is('input') ? $(this).prop('checked') : !$('.run-checkbox:first').prop('checked');
        $('.run-checkbox').prop('checked', isChecked);
        $('#runs-select-all-header').prop('checked', isChecked);
    });

    $('#select-all-wip, #wip-select-all-header').on('click', function(e) {
        e.stopPropagation();
        let isChecked = $(this).is('input') ? $(this).prop('checked') : !$('.wip-checkbox:first').prop('checked');
        $('.wip-checkbox').prop('checked', isChecked);
        $('#wip-select-all-header').prop('checked', isChecked);
    });

    // --- View Run Results Modal ---
    $('.view-run-results').on('click', function(e) {
        e.stopPropagation(); // Prevent row checkbox from toggling
        const runId = $(this).data('run-id');
        const modalBody = $('#runResultsModalBody');
        const modalTitle = $('#runResultsModalLabel');
        
        modalTitle.text(`نتایج اجرای MRP (RunID: ${runId})`);
        modalBody.html('<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>');
        
        // --- AJAX Call to the NEW API ---
        $.ajax({
            url: '../../api/get_mrp_results.php', // *** FIXED API URL ***
            type: 'GET',
            data: { run_id: runId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let table = '<table class="table table-sm table-striped"><thead><tr><th>نوع</th><th>نام</th><th>کسری (Net)</th><th>واحد</th></tr></thead><tbody>';
                    response.data.forEach(item => {
                        table += `
                            <tr>
                                <td>${item.ItemType}</td>
                                <td>${item.ItemName}</td>
                                <td class="text-danger fw-bold">${parseFloat(item.NetRequirement).toLocaleString()}</td>
                                <td>${item.Unit}</td>
                            </tr>
                        `;
                    });
                    table += '</tbody></table>';
                    modalBody.html(table);
                } else if (response.success) {
                    modalBody.html('<div class="alert alert-info">هیچ نیازمندی خالصی (کسری) برای این اجرا ثبت نشده است.</div>');
                } else {
                    modalBody.html(`<div class="alert alert-danger">${response.message || 'خطا در واکشی نتایج.'}</div>`);
                }
            },
            error: function() {
                modalBody.html('<div class="alert alert-danger">خطای شبکه در واکشی نتایج.</div>');
            }
        });
    });

    // --- Handle Generate Plan Button ---
    $('#generate-plan-btn').on('click', function() {
        const btn = $(this);
        const alertBox = $('#plan-result-alert');
        const resultsContainer = $('#plan-results-container');
        const resultsOutput = $('#plan-results-output');

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال ایجاد برنامه...');
        alertBox.hide().removeClass('alert-danger alert-success alert-warning');
        resultsContainer.hide();

        const selectedRuns = $('.run-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        const selectedWip = $('.wip-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedRuns.length === 0 && selectedWip.length === 0) {
            alertBox.addClass('alert-warning').html('لطفاً حداقل یک مورد را برای برنامه‌ریزی انتخاب کنید.').show();
            btn.prop('disabled', false).html('<i class="bi bi-play-circle-fill"></i> ایجاد دستور کارهای اولیه (فاز ۲)');
            return;
        }

        // Send data to the Phase 2 API
        fetch('../../api/generate_production_plan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                run_ids: selectedRuns, // Changed from run_id
                wip_items: selectedWip
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alertBox.addClass('alert-success').html(data.message || 'برنامه‌ریزی با موفقیت انجام شد.').show();
                resultsContainer.show();
                
                // Build a summary of the plan
                let html = '<h5>دستور کارهای ایجاد شده:</h5><ul>';
                if (data.data && data.data.work_orders_by_station && Object.keys(data.data.work_orders_by_station).length > 0) {
                    for (const [station, items] of Object.entries(data.data.work_orders_by_station)) {
                        html += `<li><strong>${station}:</strong> ${items.length} دستور کار</li>`;
                    }
                } else {
                    html += '<li>هیچ دستور کاری ایجاد نشد.</li>';
                }
                html += '</ul><p>اکنون می‌توانید دستور کارها را در صفحه <a href="work_order_list.php" class="alert-link">"مشاهده دستور کارها"</a> ببینید.</p>';
                resultsOutput.html(html);

                btn.prop('disabled', false).html('<i class="bi bi-check-all"></i> انجام شد (اجرای مجدد)');
            } else {
                throw new Error(data.message || 'خطای ناشناخته‌ای در سرور رخ داد.');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alertBox.addClass('alert-danger').html('<strong>خطای سیستمی:</strong> ' + error.message).show();
            btn.prop('disabled', false).html('<i class="bi bi-play-circle-fill"></i> ایجاد دستور کارهای اولیه (فاز ۲)');
        });
    });

    // --- NEW: Delete Run Logic ---
    let runIdToDelete = null;
    $('.delete-run-btn').on('click', function(e) {
        e.stopPropagation();
        runIdToDelete = $(this).data('run-id');
        const runDate = $(this).data('run-date');
        $('#delete-run-id-display').text(runIdToDelete);
        $('#delete-run-date-display').text(runDate);
        $('#run-id-to-delete').val(runIdToDelete);
    });

    $('#confirm-delete-run-btn').on('click', function() {
        if (!runIdToDelete) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: '../../api/delete_mrp_run.php',
            type: 'POST',
            data: { run_id: runIdToDelete },
            dataType: 'json',
            success: function(response) {
                const alertBox = $('#plan-result-alert');
                if (response.success) {
                    alertBox.removeClass('alert-danger').addClass('alert-success').html(response.message).show();
                    // Remove row from table
                    $(`#run-row-${runIdToDelete}`).fadeOut(500, function() { $(this).remove(); });
                } else {
                    alertBox.removeClass('alert-success').addClass('alert-danger').html(response.message).show();
                }
            },
            error: function() {
                $('#plan-result-alert').removeClass('alert-success').addClass('alert-danger').html('خطای شبکه هنگام حذف.').show();
            },
            complete: function() {
                bootstrap.Modal.getInstance($('#deleteRunModal')).hide();
                btn.prop('disabled', false).text('بله، حذف کن');
                runIdToDelete = null;
            }
        });
    });
});
</script>

