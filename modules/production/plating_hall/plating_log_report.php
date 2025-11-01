<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.plating_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$pageTitle = "گزارش چاپی سالن آبکاری";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">گزارش فرآیند ثبت آبکاری</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header"><h5 class="mb-0">فیلتر گزارش</h5></div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="text" id="start_date" name="start_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-01')); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="text" id="end_date" name="end_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="part_family_id" class="form-label">خانواده محصول</label>
                    <select id="part_family_id" name="part_family_id" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($part_families as $family): ?>
                            <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="part_id" class="form-label">نوع محصول</label>
                    <select id="part_id" name="part_id" class="form-select" disabled>
                        <option value="">همه (ابتدا خانواده را انتخاب کنید)</option>
                    </select>
                </div>
            </div>
            <hr>
            <label class="form-label fw-bold">انتخاب ستون‌ها:</label>
            <div class="row">
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_date" checked><label class="form-check-label" for="col_toggle_date">تاریخ</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_barrels" checked><label class="form-check-label" for="col_toggle_barrels">تعداد بارل</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_washed" checked><label class="form-check-label" for="col_toggle_washed">شستشو</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_plated" checked><label class="form-check-label" for="col_toggle_plated">آبکاری</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_reworked" checked><label class="form-check-label" for="col_toggle_reworked">دوباره کاری</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_hours" checked><label class="form-check-label" for="col_toggle_hours">نفر ساعت</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_staff" checked><label class="form-check-label" for="col_toggle_staff">پرسنل</label></div></div>
            </div>
            
            <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> ایجاد گزارش</button>
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
                <h2 id="report-main-title" class="report-title-print">جدول فرآیند ثبت آبکاری</h2>
                <p id="report-filter-summary" class="text-muted small"></p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-sm table-bordered text-center" id="report-table">
                    <thead id="report-table-header">
                        <!-- Header will be built by JS -->
                    </thead>
                    <tbody id="report-table-body">
                        <!-- Data will be injected here by JS -->
                    </tbody>
                    <tfoot id="report-table-footer" class="table-light fw-bold">
                        <!-- Footer will be built by JS -->
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<style id="print-styles"></style>
<style>
#a4-wrapper { background: #525659; padding: 30px 0; }
.a4-page { background: white; width: 21cm; min-height: 29.7cm; display: block; margin: 0 auto; padding: 1.5cm; box-shadow: 0 0 0.5cm rgba(0,0,0,0.5); }
.report-title-print { font-size: 16pt; font-weight: bold; }
.table { font-size: 0.8rem; }
.table th, .table td { padding: 0.25rem 0.4rem; vertical-align: middle; }
.table .col-staff { font-size: 0.7rem; min-width: 150px; white-space: normal; }

@media print {
    body * { visibility: hidden; }
    #report-container, #report-container * { visibility: visible; }
    #report-container { position: absolute; left: 0; top: 0; width: 100%; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    .navbar, .page-header, #filter-card, #loader, .btn { display: none !important; visibility: hidden !important; }
    #a4-wrapper { background: white !important; padding: 0 !important; box-shadow: none !important; margin: 0 !important; width: 100% !important; }
    .a4-page { width: 100% !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
    #report-header { display: block !important; visibility: visible !important; margin-bottom: 15px !important; page-break-after: avoid; }
    #report-filter-summary { font-size: 9pt !important; margin-bottom: 12px !important; color: #666 !important; }
    .report-section { page-break-inside: avoid; }
    .card { border: none !important; box-shadow: none !important; }
    .table { font-size: 8pt !important; margin: 0 !important; width: 100% !important; table-layout: auto; }
    .table th, .table td { padding: 4px 5px !important; border: 1px solid #ddd !important; }
    .table .col-staff { font-size: 7pt; width: 150px; }
    .table thead th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    .table tfoot td { background-color: #f8f8f8 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    @page { size: A4 landscape; margin: 1cm; } /* Changed to landscape for more columns */

    /* Dynamic print styles will be injected into #print-styles */
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // --- Column Visibility Config ---
    let columnConfig = {};

    function updateColumnConfig() {
        columnConfig = {
            date: $('#col_toggle_date').is(':checked'),
            barrels: $('#col_toggle_barrels').is(':checked'),
            washed: $('#col_toggle_washed').is(':checked'),
            plated: $('#col_toggle_plated').is(':checked'),
            reworked: $('#col_toggle_reworked').is(':checked'),
            hours: $('#col_toggle_hours').is(':checked'),
            staff: $('#col_toggle_staff').is(':checked'),
        };
    }

    function updateColumnVisibility() {
        updateColumnConfig();
        let printCss = "";
        
        Object.keys(columnConfig).forEach(key => {
            const isVisible = columnConfig[key];
            $(`#report-table .col-${key}`).toggle(isVisible);
            if (!isVisible) {
                printCss += `#report_table .col-${key} { display: none !important; } `;
            }
        });
        
        $('#print-styles').html(`<style> @media print { ${printCss} } </style>`);
    }

    $('.col-toggle-checkbox').on('change', updateColumnVisibility);
    updateColumnConfig(); // Set initial config on load

    // --- Dependent Part Filter ---
    $('#part_family_id').on('change', function() {
        const familyId = $(this).val();
        const partSelect = $('#part_id');
        partSelect.prop('disabled', true).html('<option value="">همه</option>'); 
        if (familyId) {
            partSelect.html('<option value="">در حال بارگذاری...</option>');
            $.getJSON('<?php echo BASE_URL; ?>api/api_get_parts_by_family.php', { family_id: familyId })
                .done(function(response) {
                    partSelect.html('<option value="">همه</option>'); 
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(i, part) {
                            partSelect.append($('<option>', { value: part.PartID, text: part.PartName }));
                        });
                        partSelect.prop('disabled', false);
                    } else { partSelect.html('<option value="">قطعه‌ای یافت نشد</option>'); }
                })
                .fail(function() { partSelect.html('<option value="">خطا در بارگذاری</option>'); });
        } else { partSelect.html('<option value="">همه (ابتدا خانواده را انتخاب کنید)</option>'); }
    });

    // --- Form Submission ---
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        const reportContainer = $('#report-container');
        const loader = $('#loader');
        const tableHeader = $('#report-table-header');
        const tableBody = $('#report-table-body');
        const tableFooter = $('#report-table-footer');

        reportContainer.show();
        $('#report-content').hide(); // Hide content until data is rendered
        loader.show();
        tableHeader.empty();
        tableBody.empty();
        tableFooter.empty();

        let summary = `گزارش از تاریخ ${$('#start_date').val()} تا ${$('#end_date').val()}`;
        if ($('#part_family_id').val()) { summary += ` | خانواده: ${$('#part_family_id option:selected').text()}`; }
        if ($('#part_id').val()) { summary += ` | قطعه: ${$('#part_id option:selected').text()}`; }
        $('#report-filter-summary').text(summary);
        
        updateColumnConfig(); // Get the latest checkbox states

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_plating_log_report.php', $(this).serialize())
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    // 1. Build Header
                    let headerHtml = '<tr>';
                    if (columnConfig.date) headerHtml += '<th class="col-date">تاریخ</th>';
                    if (columnConfig.barrels) headerHtml += '<th class="col-barrels">تعداد بارل</th>';
                    if (columnConfig.washed) headerHtml += '<th class="col-washed">وزن شستشو (KG)</th>';
                    if (columnConfig.plated) headerHtml += '<th class="col-plated">وزن آبکاری (KG)</th>';
                    if (columnConfig.reworked) headerHtml += '<th class="col-reworked">وزن دوباره کاری (KG)</th>';
                    if (columnConfig.hours) headerHtml += '<th class="col-hours">نفر ساعت</th>';
                    if (columnConfig.staff) headerHtml += '<th class="col-staff">پرسنل</th>';
                    headerHtml += '</tr>';
                    tableHeader.html(headerHtml);

                    // 2. Build Body and Calculate Totals
                    let totalBarrels = 0, totalWashed = 0, totalPlated = 0, totalReworked = 0, totalHours = 0;
                    $.each(response.data, function(i, row) {
                        let rowHtml = '<tr>';
                        if (columnConfig.date) rowHtml += `<td class="col-date">${row.LogDateJalali}</td>`;
                        if (columnConfig.barrels) rowHtml += `<td class="col-barrels">${row.NumberOfBarrels}</td>`;
                        if (columnConfig.washed) rowHtml += `<td class="col-washed">${row.TotalWashed.toFixed(1)}</td>`;
                        if (columnConfig.plated) rowHtml += `<td class="col-plated">${row.TotalPlated.toFixed(1)}</td>`;
                        if (columnConfig.reworked) rowHtml += `<td class="col-reworked">${row.TotalReworked.toFixed(1)}</td>`;
                        if (columnConfig.hours) rowHtml += `<td class="col-hours">${row.TotalHours.toFixed(2)}</td>`;
                        if (columnConfig.staff) rowHtml += `<td class="col-staff small-text">${row.StaffNames || '-'}</td>`;
                        rowHtml += '</tr>';
                        tableBody.append(rowHtml);

                        totalBarrels += parseInt(row.NumberOfBarrels);
                        totalWashed += row.TotalWashed;
                        totalPlated += row.TotalPlated;
                        totalReworked += row.TotalReworked;
                        totalHours += row.TotalHours;
                    });

                    // 3. Build Footer
                    let footerHtml = '<tr>';
                    if (columnConfig.date) footerHtml += '<td class="col-date">مجموع</td>';
                    if (columnConfig.barrels) footerHtml += `<td class="col-barrels">${totalBarrels}</td>`;
                    if (columnConfig.washed) footerHtml += `<td class="col-washed">${totalWashed.toFixed(1)}</td>`;
                    if (columnConfig.plated) footerHtml += `<td class="col-plated">${totalPlated.toFixed(1)}</td>`;
                    if (columnConfig.reworked) footerHtml += `<td class="col-reworked">${totalReworked.toFixed(1)}</td>`;
                    if (columnConfig.hours) footerHtml += `<td class="col-hours">${totalHours.toFixed(2)}</td>`;
                    if (columnConfig.staff) footerHtml += '<td class="col-staff"></td>';
                    footerHtml += '</tr>';
                    tableFooter.html(footerHtml);

                    updateColumnVisibility(); // Apply visibility rules
                    $('#report-content').show();
                } else if (response.success) {
                    tableHeader.empty();
                    tableBody.html('<tr><td colspan="7" class="text-center text-muted">هیچ داده‌ای در این بازه زمانی یافت نشد.</td></tr>');
                    $('#report-content').show();
                } else {
                    alert('خطا: ' + (response.message || 'خطای نامشخص'));
                }
            })
            .fail(function() {
                alert('خطا در برقراری ارتباط با سرور.');
            })
            .always(function() {
                loader.hide();
            });
    });

    $('#print-btn').on('click', function() {
        if ($('#report-table-body').is(':empty')) {
            alert('ابتدا گزارش را ایجاد کنید.');
            return;
        }
        updateColumnVisibility(); // Ensure print styles are set
        window.print();
    });
});
</script>

