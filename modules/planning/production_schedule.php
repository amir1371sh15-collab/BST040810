<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/crud_helpers.php';
if (!has_permission('planning.mrp.run')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$run_id = $_GET['run_id'] ?? 0;
if (empty($run_id)) {
    die('شناسه اجرای MRP مشخص نشده است.');
}

// Fetch data for the two tables
// 1. Net Requirements (from the saved MRP run)
$net_reqs = find_all($pdo, "SELECT * FROM tbl_planning_mrp_results WHERE RunID = ? AND NetRequirement > 0", [$run_id]);

// 2. WIP Inventory (from Station 8)
$wip_inventory = find_all($pdo, "
    SELECT 
        t.PartID, p.PartName, t.StatusAfterID, s.StatusName,
        SUM(t.NetWeightKG) AS TotalNetWeightKG
    FROM tbl_stock_transactions t
    JOIN tbl_parts p ON t.PartID = p.PartID
    JOIN tbl_part_statuses s ON t.StatusAfterID = s.StatusID
    WHERE t.ToStationID = 8 -- (8 = Anbar Monfaseleh)
    GROUP BY t.PartID, t.StatusAfterID
    HAVING TotalNetWeightKG > 0.01
");

$page_title = "فاز ۲: برنامه‌ریزی تولید (اجرای شماره $run_id)";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h3><?php echo $page_title; ?></h3>
            <a href="mrp_run.php" class="btn btn-secondary">بازگشت به فاز ۱</a>
        </div>
        <div class="card-body">

            <div id="plan-result-alert" class="alert" style="display: none;"></div>

            <!-- Card 1: Net Requirements (Demand) -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">۱. تولید جدید (نیازمندی‌های خالص از فاز ۱)</h5>
                    <button id="select-all-reqs" class="btn btn-light btn-sm">انتخاب همه</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;"><input type="checkbox" id="reqs-select-all-header"></th>
                                <th>نوع</th>
                                <th>نام قطعه / ماده اولیه</th>
                                <th>نیازمندی خالص</th>
                                <th>واحد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($net_reqs)): ?>
                                <tr><td colspan="5" class="text-center">هیچ نیازمندی خالصی برای این اجرا یافت نشد.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($net_reqs as $item): ?>
                                <tr>
                                    <td><input type="checkbox" class="req-checkbox" value="<?php echo $item['ResultID']; ?>" checked></td>
                                    <td>
                                        <?php if($item['ItemType'] == 'ماده اولیه'): echo '<span class="badge bg-success">ماده اولیه</span>';
                                              elseif($item['ItemType'] == 'قطعه'): echo '<span class="badge bg-warning text-dark">قطعه</span>';
                                              else: echo '<span class="badge bg-info">محصول</span>'; endif; ?>
                                    </td>
                                    <td><?php echo $item['ItemName']; // Name already contains status ?></td>
                                    <td><?php echo number_format($item['NetRequirement'], $item['Unit'] == 'KG' ? 2 : 0); ?></td>
                                    <td><?php echo htmlspecialchars($item['Unit']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 2: WIP Processing (Parallel Demand) -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">۲. پردازش موجودی (WIP از انبار منفصله)</h5>
                    <button id="select-all-wip" class="btn btn-light btn-sm">انتخاب همه</button>
                </div>
                 <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;"><input type="checkbox" id="wip-select-all-header"></th>
                                <th>نام قطعه</th>
                                <th>وضعیت فعلی</th>
                                <th>موجودی (KG)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($wip_inventory)): ?>
                                <tr><td colspan="4" class="text-center">هیچ موجودی در انبار منفصله یافت نشد.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($wip_inventory as $item): ?>
                                <tr>
                                    <!-- We use a composite key for the value -->
                                    <td><input type="checkbox" class="wip-checkbox" value="<?php echo $item['PartID'] . ':' . $item['StatusAfterID']; ?>"></td>
                                    <td><?php echo htmlspecialchars($item['PartName']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($item['StatusName']); ?></span></td>
                                    <td><?php echo number_format($item['TotalNetWeightKG'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generate Plan Button -->
            <div class="text-center">
                <button id="generate-plan-btn" class="btn btn-success btn-lg">
                    <i class="bi bi-play-circle-fill"></i> ایجاد دستور کارهای اولیه
                </button>
            </div>
            
            <div id="plan-results-container" class="mt-4" style="display: none;">
                <h4>نتایج برنامه‌ریزی:</h4>
                <div id="plan-results-output" class="alert alert-info"></div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const runId = <?php echo (int)$run_id; ?>;

    // Select All logic
    $('#select-all-reqs, #reqs-select-all-header').on('click', function() {
        let isChecked = $(this).is('input') ? $(this).prop('checked') : !$('.req-checkbox:first').prop('checked');
        $('.req-checkbox').prop('checked', isChecked);
        $('#reqs-select-all-header').prop('checked', isChecked);
    });

    $('#select-all-wip, #wip-select-all-header').on('click', function() {
        let isChecked = $(this).is('input') ? $(this).prop('checked') : !$('.wip-checkbox:first').prop('checked');
        $('.wip-checkbox').prop('checked', isChecked);
        $('#wip-select-all-header').prop('checked', isChecked);
    });

    // Handle Generate Plan Button
    $('#generate-plan-btn').on('click', function() {
        const btn = $(this);
        const alertBox = $('#plan-result-alert');
        const resultsContainer = $('#plan-results-container');
        const resultsOutput = $('#plan-results-output');

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال ایجاد برنامه...');
        alertBox.hide().removeClass('alert-danger alert-success');
        resultsContainer.hide();

        const selectedReqs = $('.req-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        const selectedWip = $('.wip-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedReqs.length === 0 && selectedWip.length === 0) {
            alertBox.addClass('alert-warning').html('لطفاً حداقل یک مورد را برای برنامه‌ریزی انتخاب کنید.').show();
            btn.prop('disabled', false).html('<i class="bi bi-play-circle-fill"></i> ایجاد دستور کارهای اولیه');
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
                run_id: runId,
                net_requirements: selectedReqs, // List of ResultIDs
                wip_items: selectedWip         // List of "PartID:StatusID"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alertBox.addClass('alert-success').html(data.message || 'برنامه‌ریزی با موفقیت انجام شد.').show();
                resultsContainer.show();
                
                // Build a summary of the plan
                let html = '<h5>دستور کارهای ایجاد شده:</h5><ul>';
                for (const [station, items] of Object.entries(data.data.work_orders_by_station)) {
                    html += `<li><strong>${station}:</strong> ${items.length} دستور کار</li>`;
                }
                html += '</ul>';
                resultsOutput.html(html);

                btn.prop('disabled', false).html('<i class="bi bi-check-all"></i> انجام شد (اجرای مجدد)');
            } else {
                throw new Error(data.message || 'خطای ناشناخته‌ای در سرور رخ داد.');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alertBox.addClass('alert-danger').html('<strong>خطای سیستمی:</strong> ' + error.message).show();
            btn.prop('disabled', false).html('<i class="bi bi-play-circle-fill"></i> ایجاد دستور کارهای اولیه');
        });
    });
});
</script>
