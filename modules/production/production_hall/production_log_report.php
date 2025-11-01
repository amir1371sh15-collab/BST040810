<?php
require_once __DIR__ . '/../../../config/init.php';

// Permission Check: User needs at least view access to the production hall
if (!has_permission('production.production_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// --- Fetch data for filters ---
$machine_types = $pdo->query("SELECT DISTINCT MachineType FROM tbl_machines WHERE MachineType IS NOT NULL AND MachineType != '' ORDER BY MachineType")->fetchAll(PDO::FETCH_COLUMN);
// Fetch machines initially, might be filtered by JS later if needed
$machines = find_all($pdo, "SELECT MachineID, MachineName FROM tbl_machines ORDER BY MachineName");
$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
// Parts are loaded dynamically via JS

$pageTitle = "گزارش چاپی سالن تولید";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">گزارش فرآیند ثبت تولید</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header"><h5 class="mb-0">فیلتر گزارش</h5></div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="text" id="start_date" name="start_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-01')); // Default to start of month ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="text" id="end_date" name="end_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); // Default to today ?>">
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="machine_type" class="form-label">نوع دستگاه</label>
                    <select id="machine_type" name="machine_type" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($machine_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="machine_id" class="form-label">دستگاه</label>
                    <select id="machine_id" name="machine_id" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($machines as $machine): ?>
                            <option value="<?php echo $machine['MachineID']; ?>"><?php echo htmlspecialchars($machine['MachineName']); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_date" data-column-class="col-date" checked><label class="form-check-label" for="col_toggle_date">تاریخ</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_hours" data-column-class="col-hours" checked><label class="form-check-label" for="col_toggle_hours">نفر ساعت روزانه</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_machine" data-column-class="col-machine" checked><label class="form-check-label" for="col_toggle_machine">دستگاه</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_part" data-column-class="col-part" checked><label class="form-check-label" for="col_toggle_part">قطعه</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input col-toggle-checkbox" type="checkbox" id="col_toggle_kg" data-column-class="col-kg" checked><label class="form-check-label" for="col_toggle_kg">تولید (KG)</label></div></div>
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
                <h2 id="report-main-title" class="report-title-print">جدول فرآیند ثبت تولید</h2>
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
.table th, .table td { padding: 0.25rem 0.4rem; vertical-align: middle; white-space: nowrap;}
.table .col-part { white-space: normal; min-width: 150px;} /* Allow part name to wrap */

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
    .table th, .table td { padding: 4px 5px !important; border: 1px solid #ddd !important; white-space: nowrap; vertical-align: middle;} /* Ensure vertical alignment */
    .table .col-part { white-space: normal; } /* Allow part name to wrap in print */
    .table thead th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    .table tfoot td { background-color: #f8f8f8 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    @page { size: A4 landscape; margin: 1cm; } /* Landscape to fit columns */

    /* Dynamic print styles for column visibility */
    #print-styles style { display: block !important; }
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {

    // --- Column Visibility Config ---
    let columnConfig = {};

    function updateColumnConfig() {
        columnConfig = {};
        $('.col-toggle-checkbox').each(function() {
            const columnClass = $(this).data('column-class');
            if (columnClass) {
                columnConfig[columnClass] = $(this).is(':checked');
            }
        });
    }

    function updateColumnVisibility() {
        updateColumnConfig();
        let printCss = "";

        Object.keys(columnConfig).forEach(key => {
            const isVisible = columnConfig[key];
            $(`#report-table .${key}`).toggle(isVisible); // Toggle table cells/headers
            if (!isVisible) {
                printCss += `#report-table .${key} { display: none !important; } `;
            }
        });

        // Ensure the footer columns adjust visibility too
        $('#report-table-footer tr td').each(function() {
            let isVisible = true;
            for (const key in columnConfig) {
                // Check if the td has the class and if that column should be hidden
                if ($(this).hasClass(key) && !columnConfig[key]) {
                    isVisible = false;
                    break;
                }
                 // Special case for the first 'Total' cell, check if date is visible
                 // If date is hidden, the first cell should also be hidden unless hours is visible
                 if ($(this).is(':first-child') && !columnConfig['col-date'] && !columnConfig['col-hours'] ) {
                     isVisible = false;
                     break;
                 }
                 // If date is hidden BUT hours is visible, the first cell (now hours) should show
                 else if ($(this).is(':first-child') && !columnConfig['col-date'] && columnConfig['col-hours']) {
                     isVisible = true; // Keep it visible as it now represents hours
                 }
                 // If date IS visible, the first cell (date) is handled by its own class check

            }
            $(this).toggle(isVisible);
        });

        $('#print-styles').html(`<style media="print"> ${printCss} </style>`);
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

        // Build filter summary string
        let summary = `گزارش از تاریخ ${$('#start_date').val()} تا ${$('#end_date').val()}`;
        if ($('#machine_type').val()) { summary += ` | نوع دستگاه: ${$('#machine_type option:selected').text()}`; }
        if ($('#machine_id').val()) { summary += ` | دستگاه: ${$('#machine_id option:selected').text()}`; }
        if ($('#part_family_id').val()) { summary += ` | خانواده: ${$('#part_family_id option:selected').text()}`; }
        if ($('#part_id').val()) { summary += ` | قطعه: ${$('#part_id option:selected').text()}`; }
        $('#report-filter-summary').text(summary);

        updateColumnConfig(); // Get the latest checkbox states before making the request

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_production_log_report.php', $(this).serialize())
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    // 1. Build Header dynamically based on config
                    let headerHtml = '<tr>';
                    if (columnConfig['col-date']) headerHtml += '<th class="col-date">تاریخ</th>';
                    if (columnConfig['col-hours']) headerHtml += '<th class="col-hours">نفر ساعت روزانه</th>';
                    if (columnConfig['col-machine']) headerHtml += '<th class="col-machine">دستگاه</th>';
                    if (columnConfig['col-part']) headerHtml += '<th class="col-part">قطعه</th>';
                    if (columnConfig['col-kg']) headerHtml += '<th class="col-kg">تولید (KG)</th>';
                    headerHtml += '</tr>';
                    tableHeader.html(headerHtml);

                    // 2. Build Body with Rowspan and Calculate Totals
                    let totalProductionKG = 0;

                    response.data.forEach(dayData => {
                        const entries = dayData.entries;
                        const date = dayData.LogDateJalali;
                        const manHours = parseFloat(dayData.ManHours).toFixed(1);
                        const rowspanValue = entries.length > 0 ? entries.length : 1;

                        if (entries.length > 0) {
                             entries.forEach((entry, index) => {
                                let rowHtml = '<tr>';
                                // Add Date and ManHours cells only for the first row of the date group
                                if (index === 0) {
                                     if (columnConfig['col-date']) rowHtml += `<td class="col-date" rowspan="${rowspanValue}">${date}</td>`;
                                     if (columnConfig['col-hours']) rowHtml += `<td class="col-hours" rowspan="${rowspanValue}">${manHours}</td>`;
                                }
                                // Add Machine, Part, KG for every entry
                                if (columnConfig['col-machine']) rowHtml += `<td class="col-machine">${entry.MachineName}</td>`;
                                if (columnConfig['col-part']) rowHtml += `<td class="col-part">${entry.PartName}</td>`;
                                if (columnConfig['col-kg']) rowHtml += `<td class="col-kg">${parseFloat(entry.ProductionKG).toFixed(1)}</td>`;

                                rowHtml += '</tr>';
                                tableBody.append(rowHtml);
                                totalProductionKG += parseFloat(entry.ProductionKG);
                             });
                        } else {
                            // Handle cases where a date might exist in header but no details
                                let rowHtml = '<tr>';
                                if (columnConfig['col-date']) rowHtml += `<td class="col-date">${date}</td>`;
                                if (columnConfig['col-hours']) rowHtml += `<td class="col-hours">${manHours}</td>`;
                                if (columnConfig['col-machine']) rowHtml += `<td class="col-machine">-</td>`;
                                if (columnConfig['col-part']) rowHtml += `<td class="col-part">-</td>`;
                                if (columnConfig['col-kg']) rowHtml += `<td class="col-kg">0.0</td>`;
                                rowHtml += '</tr>';
                                tableBody.append(rowHtml);
                        }
                    });


                    // 3. Build Footer dynamically
                    let footerHtml = '<tr>';
                    let firstColSpan = 0;
                    if (columnConfig['col-date']) firstColSpan++;
                    if (columnConfig['col-hours']) firstColSpan++;
                    if (columnConfig['col-machine']) firstColSpan++;
                    if (columnConfig['col-part']) firstColSpan++;

                    // Add the "Total" cell spanning appropriate columns
                    if (firstColSpan > 0) {
                       // The 'Total' text should go in the first *visible* column conceptually
                       // We create separate cells for styling flexibility, but only the first visible one gets the text
                       if(columnConfig['col-date']){
                           footerHtml += `<td class="col-date" colspan="${firstColSpan > 1 ? 1 : ''}">${firstColSpan > 1 ? 'مجموع' : 'مجموع'}</td>`;
                       } else if (columnConfig['col-hours']) {
                           footerHtml += `<td class="col-hours" colspan="${firstColSpan > 1 ? 1 : ''}">${firstColSpan > 1 ? 'مجموع' : 'مجموع'}</td>`;
                       } else if (columnConfig['col-machine']) {
                            footerHtml += `<td class="col-machine" colspan="${firstColSpan > 1 ? 1 : ''}">${firstColSpan > 1 ? 'مجموع' : 'مجموع'}</td>`;
                       } else if (columnConfig['col-part']) {
                           footerHtml += `<td class="col-part" colspan="${firstColSpan > 1 ? 1 : ''}">${firstColSpan > 1 ? 'مجموع' : 'مجموع'}</td>`;
                       }
                       // Add empty cells for the remaining spanned columns if needed
                       if(firstColSpan > 1){
                           let addedEmptyCells = 0;
                           if (!columnConfig['col-date'] && columnConfig['col-hours']) addedEmptyCells++; // If hours is first visible, skip 1
                           if (!columnConfig['col-date'] && !columnConfig['col-hours'] && columnConfig['col-machine']) addedEmptyCells++; // If machine is first visible
                           if (!columnConfig['col-date'] && !columnConfig['col-hours'] && !columnConfig['col-machine'] && columnConfig['col-part']) addedEmptyCells++; // If part is first visible

                           if (columnConfig['col-hours'] && addedEmptyCells < firstColSpan -1 ) {footerHtml += '<td class="col-hours"></td>'; addedEmptyCells++;}
                           if (columnConfig['col-machine'] && addedEmptyCells < firstColSpan -1) {footerHtml += '<td class="col-machine"></td>'; addedEmptyCells++;}
                           if (columnConfig['col-part'] && addedEmptyCells < firstColSpan -1) {footerHtml += '<td class="col-part"></td>'; addedEmptyCells++;}
                       }
                    }

                    // Add the Total Production KG cell if visible
                    if (columnConfig['col-kg']) footerHtml += `<td class="col-kg">${totalProductionKG.toFixed(1)}</td>`;

                    footerHtml += '</tr>';
                    tableFooter.html(footerHtml);


                    updateColumnVisibility(); // Apply visibility rules AFTER content is added
                    $('#report-content').show();
                } else if (response.success) {
                    tableHeader.empty();
                    tableBody.html('<tr><td colspan="5" class="text-center text-muted">هیچ داده‌ای در این بازه زمانی با فیلترهای انتخابی یافت نشد.</td></tr>'); // Adjusted colspan
                     tableFooter.empty();
                    $('#report-content').show();
                } else {
                    alert('خطا: ' + (response.message || 'خطای نامشخص در دریافت اطلاعات از سرور.'));
                     tableHeader.empty();
                     tableBody.empty();
                     tableFooter.empty();
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                 alert('خطا در برقراری ارتباط با سرور.');
                 tableHeader.empty();
                 tableBody.empty();
                 tableFooter.empty();
            })
            .always(function() {
                loader.hide();
            });
    });

    $('#print-btn').on('click', function() {
        if ($('#report-table-body').is(':empty') || $('#report-table-body').find('td').length <= 1 ) { // Check if only the "no data" message is present
            alert('ابتدا گزارش را با داده‌های معتبر ایجاد کنید.');
            return;
        }
        updateColumnVisibility(); // Ensure print styles are set based on current checkboxes
        window.print();
    });

    // Initial visibility update on page load
    updateColumnVisibility();
});
</script>

