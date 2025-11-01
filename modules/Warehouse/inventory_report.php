<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('warehouse.view')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$statuses_raw = find_all($pdo, "SELECT StatusID, StatusName FROM tbl_part_statuses ORDER BY StatusName"); // Fetch from new table
$statuses = $statuses_raw; // Use the full array

$pageTitle = "گزارش گردش موجودی انبار";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header"><h5 class="mb-0">فیلتر گزارش گردش موجودی</h5></div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">از تاریخ *</label>
                    <input type="text" id="start_date" name="start_date" class="form-control persian-date" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تا تاریخ *</label>
                    <input type="text" id="end_date" name="end_date" class="form-control persian-date" required>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="employee_id" class="form-label">نام عامل (اختیاری)</label>
                    <select id="employee_id" name="employee_id" class="form-select">
                        <option value="">همه عامل‌ها</option>
                        <?php foreach($employees as $employee): ?>
                            <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                     <label for="family_id" class="form-label">خانواده قطعه (اختیاری)</label>
                     <select class="form-select" id="family_id" name="family_id">
                         <option value="">-- همه خانواده‌ها --</option>
                         <?php foreach ($part_families as $family): ?>
                             <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="col-md-3 mb-3">
                    <label for="part_id" class="form-label">نام قطعه (اختیاری)</label>
                    <select id="part_id" name="part_id" class="form-select" disabled>
                        <option value="">-- همه قطعات خانواده --</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status_after" class="form-label">وضعیت خروجی (اختیاری)</label>
                    <select id="status_after" name="status_after" class="form-select">
                        <option value="">-- همه وضعیت‌ها --</option>
                         <?php foreach($statuses as $status): /* Loop through fetched statuses */ ?>
                            <option value="<?php echo $status['StatusID']; /* Use ID as value */ ?>">
                                <?php echo htmlspecialchars($status['StatusName']); /* Display Name */ ?>
                            </option>
                        <?php endforeach; ?>
                         <option value="NULL">-- بدون وضعیت --</option>
                    </select>
                </div>
            </div>

            <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> نمایش گزارش</button>
                <button type="button" id="print-btn" class="btn btn-secondary"><i class="bi bi-printer"></i> چاپ</button>
            </div>
        </form>
    </div>
</div>

<div id="report-container" class="mt-4" style="display:none;">
    <div class="text-center" id="loader"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
    <div id="a4-wrapper">
        <div id="report-content" class="a4-page">
            <div id="report-header" class="mb-4 text-center">
                <h2 id="report-main-title" class="report-title-print">گزارش گردش موجودی انبار</h2>
                <p id="report-filter-summary" class="text-muted small"></p>
                <p id="initial-balance-summary" class="text-muted small"></p>
            </div>

            <div class="table-responsive" id="report-table-container">
                <!-- Table generated by JS -->
            </div>
             <!-- Explanatory note removed as requested -->
        </div>
    </div>
</div>

<style id="print-styles"></style>
<style>
#a4-wrapper { background: #525659; padding: 30px 0; }
.a4-page { background: white; width: 21cm; min-height: 29.7cm; display: block; margin: 0 auto; padding: 1.5cm; box-shadow: 0 0 0.5cm rgba(0,0,0,0.5); }
.report-title-print { font-size: 16pt; font-weight: bold; }
.table { font-size: 0.8rem; }
.table th, .table td { padding: 0.3rem 0.5rem; vertical-align: middle; white-space: nowrap;}
.table tfoot td { font-weight: bold;}
.table tfoot tr:last-child td { border-top: 2px solid #adb5bd; } /* Separator for carton total */


.summary-table { width: 60%; margin: 1rem auto; font-size: 0.9rem; }
.summary-table th { width: 40%; text-align: left; padding-right: 10px; }
.summary-table td { font-weight: bold; }

@media print {
    body * { visibility: hidden; }
    #report-container, #report-container * { visibility: visible; }
    #report-container { position: absolute; left: 0; top: 0; width: 100%; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    .navbar, .page-header, #filter-card, #loader, .btn { display: none !important; visibility: hidden !important; }
    #a4-wrapper { background: white !important; padding: 0 !important; box-shadow: none !important; margin: 0 !important; width: 100% !important; }
    .a4-page { width: 100% !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 1cm !important; box-shadow: none !important; border: none !important; }
    #report-header { display: block !important; visibility: visible !important; margin-bottom: 15px !important; page-break-after: avoid; text-align: center;}
    #report-filter-summary, #initial-balance-summary { font-size: 8pt !important; margin-bottom: 5px !important; color: #666 !important; }
    .report-section { page-break-inside: avoid; }
    .table { font-size: 8pt !important; margin: 0 !important; width: 100% !important; table-layout: auto; }
    .table th, .table td { padding: 3px 4px !important; border: 1px solid #ddd !important; white-space: nowrap;}
    .table thead th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    .table tfoot td { background-color: #f8f8f8 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    .summary-table { font-size: 9pt !important; }
    .small.text-muted { display: none !important; } /* Hide the note in print */
    @page { size: A4 portrait; margin: 1cm; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiReportUrl = '<?php echo BASE_URL; ?>api/api_get_inventory_report.php';
    const familySelect = $('#family_id');
    const partSelect = $('#part_id');

    familySelect.on('change', function() {
        const familyId = $(this).val();
        partSelect.prop('disabled', true).html('<option value="">-- همه قطعات خانواده --</option>');
        if (familyId) {
            partSelect.html('<option value="">در حال بارگذاری...</option>');
            $.getJSON('<?php echo BASE_URL; ?>api/api_get_parts_by_family.php', { family_id: familyId })
                .done(function(response) {
                    partSelect.html('<option value="">-- همه قطعات خانواده --</option>');
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(i, part) {
                            partSelect.append($('<option>', { value: part.PartID, text: part.PartName }));
                        });
                        partSelect.prop('disabled', false);
                    } else {
                        partSelect.append('<option value="" disabled>قطعه‌ای یافت نشد</option>');
                    }
                })
                .fail(function() { partSelect.html('<option value="">خطا در بارگذاری</option>'); });
        } else { partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>'); }
    });

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        const reportContainer = $('#report-container');
        const loader = $('#loader');
        const reportTableContainer = $('#report-table-container');
        const initialBalanceSummary = $('#initial-balance-summary');
        const startDate = $('#start_date').val();
        const familyId = $('#family_id').val();
        const statusAfter = $('#status_after').val();
        const partId = $('#part_id').val(); // Get selected partId

        if (!startDate || !$('#end_date').val()) {
            alert('لطفاً بازه زمانی را انتخاب کنید.');
            return;
        }

        reportContainer.show();
        $('#report-content').hide();
        loader.show();
        reportTableContainer.empty();
        initialBalanceSummary.empty();

        // Update summary text
        let summary = `بازه: ${startDate} تا ${$('#end_date').val()}`;
        if (familyId) {
            summary = `خانواده: ${$('#family_id option:selected').text()}`;
            if (partId) { summary = `قطعه: ${$('#part_id option:selected').text()}`; }
        }
        // Handle optional status
        const statusSelectedText = $('#status_after option:selected').text();
        if (statusAfter && statusSelectedText !== '-- همه وضعیت‌ها --') {
             summary += ` | وضعیت: ${statusSelectedText}`;
        }
        if ($('#employee_id').val()) { summary += ` | عامل: ${$('#employee_id option:selected').text()}`; }
        $('#report-filter-summary').text(summary);


        $.getJSON(apiReportUrl, {
            ...$(this).serializeObject() // Use serializeObject helper if available, or $(this).serialize()
        })
            .done(function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    initialBalanceSummary.text(`موجودی اولیه (قبل از ${startDate}): ${parseFloat(data.initial_balance_kg).toFixed(3)} KG`);

                    // --- Render Table ---
                    let tableHtml = `<table class="table table-sm table-bordered text-center" id="report-table">
                        <thead>
                            <tr class="table-light">
                                <th>تاریخ</th>
                                ${partId ? '' : '<th>قطعه</th>'}
                                <th>از ایستگاه</th>
                                <th>به ایستگاه</th>
                                <th>عامل</th>
                                <th>تحویل گیرنده</th>
                                <th>ورود</th>
                                <th>خروج</th>
                                <th>واحد</th>
                                <th>مانده (KG)</th>
                            </tr>
                        </thead>
                        <tbody>`;

                    let currentBalance = parseFloat(data.initial_balance_kg); // Start with initial balance (KG)

                    if (data.transactions && data.transactions.length > 0) {
                        data.transactions.forEach(tx => {
                            // API now sends inflow_kg_calc and outflow_kg_calc (always KG)
                            const inflowKg = parseFloat(tx.inflow_kg_calc);
                            const outflowKg = parseFloat(tx.outflow_kg_calc);

                            // Running balance is always KG
                            currentBalance += inflowKg;
                            currentBalance -= outflowKg;

                            tableHtml += `<tr>
                                <td>${tx.transaction_date_jalali}</td>
                                ${partId ? '' : `<td>${tx.part_name || '-'}</td>`}
                                <td>${tx.from_station_name || '-'}</td>
                                <td>${tx.to_station_name || '-'}</td>
                                <td>${tx.operator_name || '-'}</td>
                                <td>${tx.receiver_name || '-'}</td>
                                <td>${tx.inflow_display}</td>
                                <td>${tx.outflow_display}</td>
                                <td>${tx.unit_display}</td>
                                <td>${currentBalance.toFixed(3)}</td>
                            </tr>`;
                        });
                    } else {
                         const colspanValue = partId ? 9 : 10; // Adjust colspan
                        tableHtml += `<tr><td colspan="${colspanValue}" class="text-center text-muted p-3">هیچ تراکنشی در این بازه زمانی یافت نشد.</td></tr>`;
                    }

                    const footerColspan = (partId ? 5 : 6); // Adjust colspan
                    tableHtml += `</tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                 <td colspan="${footerColspan}" class="text-end pe-3">جمع / مانده نهایی (KG):</td>
                                 <td>${parseFloat(data.total_inflow_kg).toFixed(3)}</td>
                                 <td>${parseFloat(data.total_outflow_kg).toFixed(3)}</td>
                                 <td>KG</td>
                                 <td>${parseFloat(data.final_balance_kg).toFixed(3)}</td>
                             </tr>`;
                    
                    // *** NEW: Add Carton Total Row if data exists ***
                    if (data.total_inflow_cartons > 0 || data.total_outflow_cartons > 0) {
                         tableHtml += `
                             <tr>
                                 <td colspan="${footerColspan}" class="text-end pe-3">جمع / مانده نهایی (کارتن):</td>
                                 <td>${data.total_inflow_cartons}</td>
                                 <td>${data.total_outflow_cartons}</td>
                                 <td>کارتن</td>
                                 <td>-</td>
                             </tr>`;
                    }
                    
                    tableHtml += `</tfoot></table>`;
                    reportTableContainer.html(tableHtml);

                    $('#report-content').show();
                } else {
                    reportTableContainer.html(`<div class="alert alert-warning text-center p-3">${response.message || 'خطایی رخ داد.'}</div>`);
                    initialBalanceSummary.empty();
                    $('#report-content').show();
                }
            })
            .fail(function(jqXHR) {
                 console.error("AJAX Error:", jqXHR.status, jqXHR.statusText, jqXHR.responseText);
                 reportTableContainer.html('<div class="alert alert-danger text-center p-3">خطا در برقراری ارتباط با سرور.</div>');
                 initialBalanceSummary.empty();
                 $('#report-content').show();
            })
            .always(function() {
                loader.hide();
            });
    });
    
    // Helper function to serialize form to object (if not already globally available)
    if (!$.fn.serializeObject) {
        $.fn.serializeObject = function() {
            var o = {};
            var a = this.serializeArray();
            $.each(a, function() {
                if (o[this.name]) {
                    if (!o[this.name].push) {
                        o[this.name] = [o[this.name]];
                    }
                    o[this.name].push(this.value || '');
                } else {
                    o[this.name] = this.value || '';
                }
            });
            return o;
        };
    }


    $('#print-btn').on('click', function() {
        if ($('#report-content').is(':hidden')) {
            alert('ابتدا گزارش را ایجاد کنید.');
            return;
        }
        window.print();
    });
});
</script>


