<?php
require_once __DIR__ . '/../../../config/init.php';

// Permission Check: User needs at least view access to the assembly hall
if (!has_permission('production.assembly_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "گزارش خلاصه روزانه مونتاژ";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center no-print">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card no-print" id="filter-card">
    <div class="card-header"><h5 class="mb-0">انتخاب تاریخ گزارش</h5></div>
    <div class="card-body">
        <form id="filter-form" class="row align-items-end">
            <div class="col-md-4">
                <label class="form-label">تاریخ گزارش</label>
                <input type="text" id="report_date" name="report_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> نمایش گزارش</button>
            </div>
            <div class="col-md-auto">
                <button type="button" id="print-btn" class="btn btn-secondary"><i class="bi bi-printer"></i> چاپ</button>
            </div>
        </form>
    </div>
</div>

<div id="report-container" class="mt-4" style="display:none;">
    <div class="text-center" id="loader"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
    <div id="a4-wrapper">
        <div id="report-content" class="a4-page">
            <div id="report-header" class="mb-3 text-center">
                 <h2 id="report-main-title" class="report-title-print">خلاصه گزارش روزانه سالن مونتاژ</h2>
                 <p id="report-date-display" class="text-muted small"></p>
            </div>

            <!-- Report Sections -->
            <div id="assembly-section">
                <h3 class="section-title">بخش مونتاژ</h3>
                <div id="assembly-details-table-container"></div>
                <div id="assembly-operator-summary-container" class="mt-3"></div>
                <div id="assembly-overall-summary" class="summary-line mt-2"></div>
            </div>
            <hr class="section-divider">
            <div id="rolling-section">
                 <h3 class="section-title">بخش رول</h3>
                 <div id="rolling-details-table-container"></div>
                 <div id="rolling-overall-summary" class="summary-line mt-2"></div>
            </div>
            <hr class="section-divider">
            <div id="packaging-section"></div>
            <hr class="section-divider">
            <div id="description-section"></div>

        </div>
    </div>
</div>

<style>
/* Styles remain mostly the same, adjusted font sizes slightly */
#a4-wrapper { background: #525659; padding: 20px 0; }
.a4-page { background: white; width: 21cm; min-height: 29.7cm; display: block; margin: 0 auto; padding: 1.5cm; box-shadow: 0 0 0.5cm rgba(0,0,0,0.5); font-size: 9pt; }
.report-title-print { font-size: 14pt; font-weight: bold; }
.section-title { font-size: 11pt; font-weight: bold; margin-bottom: 0.5rem; padding-bottom: 0.2rem; border-bottom: 1px solid #dee2e6;}
.report-table { width: 100%; border-collapse: collapse; margin-bottom: 0.8rem; font-size: 8pt; } /* Slightly larger table font */
.report-table th, .report-table td { border: 1px solid #dee2e6; padding: 4px 6px; text-align: center; vertical-align: middle; }
.report-table thead th { background-color: #f8f9fa; font-weight: bold; white-space: nowrap; }
.report-table tbody td { white-space: nowrap; }
.report-table .left-align { text-align: right; } /* Changed to right-align for Persian */
.report-table .personnel-list { list-style: none; padding: 0; margin: 0; font-size: 7.5pt; text-align: right; }
.report-table .personnel-list li { margin-bottom: 2px; }
.section-divider { border-top: 1px dashed #adb5bd; margin: 1rem 0; }
.description-box { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 0.5rem; border-radius: 4px; min-height: 40px; font-size: 8pt; white-space: pre-wrap; } /* Slightly smaller min-height */
.summary-line { font-weight: bold; margin-top: 0.5rem; font-size: 8.5pt;}
.subtle-split { border-top: 1px dotted #ccc; } /* For splitting rows within a machine group */
.machine-summary-row td { background-color: #e9ecef; font-weight: bold; font-size: 7.5pt; } /* Style for machine summary row */

@media print {
    body * { visibility: hidden; }
    #report-container, #report-container * { visibility: visible; }
    #report-container { position: absolute; left: 0; top: 0; width: 100%; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    .no-print { display: none !important; visibility: hidden !important; }
    #a4-wrapper { background: white !important; padding: 0 !important; box-shadow: none !important; margin: 0 !important; width: 100% !important; }
    .a4-page { width: 100% !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; font-size: 8pt; }
    #report-header { display: block !important; visibility: visible !important; margin-bottom: 10px !important; page-break-after: avoid; }
    .report-title-print { font-size: 12pt !important; }
    #report-date-display { font-size: 9pt !important; }
    .section-title { font-size: 10pt !important; page-break-after: avoid; }
    .report-table { font-size: 7pt !important; margin-bottom: 0.5rem; }
    .report-table th, .report-table td { padding: 3px 4px !important; }
    .report-table .personnel-list { font-size: 6.5pt; }
    .description-box { font-size: 7pt; min-height: 30px;}
    .summary-line { font-size: 7.5pt;}
    .section-divider { margin: 0.5rem 0; }
    .machine-summary-row td { background-color: #e9ecef !important; -webkit-print-color-adjust: exact; font-size: 7pt !important;} /* Ensure background prints */
    div, table, tr, td, th { page-break-inside: avoid !important; } /* More aggressive page break avoidance */
    @page { size: A4 portrait; margin: 1cm; }
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiReportUrl = '<?php echo BASE_URL; ?>api/api_get_assembly_daily_summary_report.php';

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        const reportDate = $('#report_date').val();
        const reportContainer = $('#report-container');
        const loader = $('#loader');
        const reportContent = $('#report-content');
        // Get containers for each section
        const assemblyDetailsContainer = $('#assembly-details-table-container');
        const assemblyOpSummaryContainer = $('#assembly-operator-summary-container');
        const assemblyOverallSummary = $('#assembly-overall-summary');
        const rollingDetailsContainer = $('#rolling-details-table-container'); // Corrected variable name
        const rollingOverallSummary = $('#rolling-overall-summary');
        const packagingSection = $('#packaging-section');
        const descriptionSection = $('#description-section');

        if (!reportDate) {
            alert('لطفاً تاریخ گزارش را انتخاب کنید.');
            return;
        }

        reportContainer.show();
        reportContent.hide();
        loader.show();
        // Clear containers
        assemblyDetailsContainer.empty();
        assemblyOpSummaryContainer.empty();
        assemblyOverallSummary.empty();
        rollingDetailsContainer.empty(); // Clear rolling details container
        rollingOverallSummary.empty();   // Clear rolling summary container
        packagingSection.empty();
        descriptionSection.empty();
        $('#report-date-display').text(`تاریخ: ${reportDate}`);

        $.getJSON(apiReportUrl, { report_date: reportDate })
            .done(function(response) {
                loader.hide();
                if (response.success && response.data) {
                    const data = response.data;

                    // --- Render Assembly Section ---
                    if (data.assembly && data.assembly.details && data.assembly.details.length > 0) {
                        let assemblyDetailsHtml = `<table class="report-table">
                            <thead><tr><th>دستگاه</th><th>مونتاژکار</th><th>پیچ انداز</th><th>محصول</th><th>تولید (KG)</th></tr></thead>
                            <tbody>`;
                        let currentMachineId = null;
                        let machineTotalKg = 0;
                        let currentMachineName = ''; // Store the name for the summary row
                        const assemblyEntries = data.assembly.details;

                        assemblyEntries.forEach((entry, index) => {
                             // --- Machine Group Logic ---
                            if (entry.MachineID !== currentMachineId) {
                                // If not the first machine, add summary row for the previous one
                                if (currentMachineId !== null) {
                                    // *** FIX: Use stored currentMachineName ***
                                    assemblyDetailsHtml += `<tr class="machine-summary-row"><td colspan="4" class="left-align">مجموع دستگاه (${currentMachineName || '-'}):</td><td>${machineTotalKg.toFixed(1)}</td></tr>`;
                                }
                                currentMachineId = entry.MachineID;
                                currentMachineName = entry.MachineName; // Store the new name
                                machineTotalKg = 0; // Reset for the new machine
                                // Add a separator only between different machines
                                if (index > 0) {
                                     assemblyDetailsHtml += `<tr class="subtle-split"><td colspan="5"></td></tr>`; // Separator
                                }
                            }

                            // --- Add Entry Row ---
                            assemblyDetailsHtml += `<tr>`;
                            assemblyDetailsHtml += `<td class="left-align">${entry.MachineName || '-'}</td>`;
                            assemblyDetailsHtml += `<td class="left-align">${entry.Operator1Name || '-'}</td>`;
                            assemblyDetailsHtml += `<td class="left-align">${entry.Operator2Name || '-'}</td>`;
                            assemblyDetailsHtml += `<td class="left-align">${entry.PartName || '-'}</td>`;
                            const entryKg = parseFloat(entry.ProductionKG || 0);
                            assemblyDetailsHtml += `<td>${entryKg.toFixed(1)}</td>`;
                            assemblyDetailsHtml += `</tr>`;

                            machineTotalKg += entryKg; // Add to current machine's total

                             // --- Add Summary for the very last machine after the loop ---
                            if (index === assemblyEntries.length - 1) {
                                 // *** FIX: Use stored currentMachineName ***
                                 assemblyDetailsHtml += `<tr class="machine-summary-row"><td colspan="4" class="left-align">مجموع دستگاه (${currentMachineName || '-'}):</td><td>${machineTotalKg.toFixed(1)}</td></tr>`;
                            }
                        });
                        assemblyDetailsHtml += `</tbody></table>`;
                        assemblyDetailsContainer.html(assemblyDetailsHtml);

                        if (data.assembly.operator_summary && data.assembly.operator_summary.length > 0) {
                            let opSummaryHtml = `<h4 class="section-title" style="font-size: 9pt; margin-top: 1rem;">خلاصه عملکرد مونتاژکاران</h4>
                                                 <table class="report-table"><thead><tr><th>مونتاژکار</th><th>جمع ساعت کاری</th><th>جمع تولید (KG)</th></tr></thead><tbody>`;
                            data.assembly.operator_summary.forEach(op => { opSummaryHtml += `<tr><td class="left-align">${op.OperatorName}</td><td>${op.TotalHours.toFixed(1)}</td><td>${op.TotalKG.toFixed(1)}</td></tr>`; });
                            opSummaryHtml += `</tbody></table>`;
                            assemblyOpSummaryContainer.html(opSummaryHtml);
                        } else { assemblyOpSummaryContainer.empty(); }
                        assemblyOverallSummary.html(`مجموع تولید مونتاژ: ${parseFloat(data.assembly.summary?.TotalKG || 0).toFixed(1)} KG | دستگاه فعال: ${data.assembly.summary?.ActiveMachines || 0} | نفر ساعت کل: ${parseFloat(data.assembly.summary?.TotalManHours || 0).toFixed(1)}`);
                    } else {
                        assemblyDetailsContainer.html(`<p class="text-muted small">داده‌ای برای مونتاژ در این روز ثبت نشده است.</p>`);
                        assemblyOpSummaryContainer.empty(); assemblyOverallSummary.empty();
                    }


                    // --- Render Rolling Section ---
                    rollingDetailsContainer.empty();
                    rollingOverallSummary.empty();
                    if (data.rolling && data.rolling.details && data.rolling.details.length > 0) {
                        let rollingDetailsHtml = `<table class="report-table">
                            <thead><tr><th>دستگاه</th><th>اپراتور</th><th>محصول</th><th>شروع</th><th>پایان</th><th>تولید (KG)</th></tr></thead>
                            <tbody>`;
                        data.rolling.details.forEach(entry => {
                             rollingDetailsHtml += `<tr>`;
                             rollingDetailsHtml += `<td class="left-align">${entry.MachineName || '-'}</td>`;
                             rollingDetailsHtml += `<td class="left-align">${entry.OperatorName || '-'}</td>`;
                             rollingDetailsHtml += `<td class="left-align">${entry.PartName || '-'}</td>`;
                             rollingDetailsHtml += `<td>${entry.StartTime ? entry.StartTime.substring(0, 5) : '-'}</td>`;
                             rollingDetailsHtml += `<td>${entry.EndTime ? entry.EndTime.substring(0, 5) : '-'}</td>`;
                             rollingDetailsHtml += `<td>${parseFloat(entry.ProductionKG || 0).toFixed(1)}</td>`;
                             rollingDetailsHtml += `</tr>`;
                         });
                         rollingDetailsHtml += `</tbody></table>`;
                         rollingDetailsContainer.html(rollingDetailsHtml);

                        rollingOverallSummary.html(`مجموع تولید رول: ${parseFloat(data.rolling.summary?.TotalKG || 0).toFixed(1)} KG | نفر ساعت کل: ${parseFloat(data.rolling.summary?.TotalManHours || 0).toFixed(1)}`);

                    } else {
                        rollingDetailsContainer.html(`<p class="text-muted small">داده‌ای برای رول در این روز ثبت نشده است.</p>`);
                         if (data.rolling) {
                            rollingOverallSummary.html(`مجموع تولید رول: 0.0 KG | نفر ساعت کل: ${parseFloat(data.rolling.summary?.TotalManHours || 0).toFixed(1)}`);
                         } else {
                            rollingOverallSummary.empty();
                         }
                    }

                    // --- Render Packaging Section ---
                    // ... (Packaging rendering remains the same) ...
                     packagingSection.empty();
                    let packagingHtml = `<h3 class="section-title">بخش بسته‌بندی</h3>`;
                     if (data.packaging && data.packaging.details && data.packaging.details.length > 0) {
                         packagingHtml += `<table class="report-table"><thead><tr><th>محصول</th><th>تعداد کارتن</th></tr></thead><tbody>`;
                         data.packaging.details.forEach(entry => { packagingHtml += `<tr><td class="left-align">${entry.PartName || '-'}</td><td>${parseInt(entry.TotalCartonsPackaged || 0)}</td></tr>`; });
                         packagingHtml += `</tbody></table>`;
                         packagingHtml += `<p class="summary-line">پرسنل بسته‌بندی:</p><ul class="personnel-list">`;
                         if(data.packaging.operators && data.packaging.operators.length > 0) { data.packaging.operators.forEach(op => { packagingHtml += `<li>${op.EmployeeName || '-'} (${op.StartTimeFmt || '?'} - ${op.EndTimeFmt || '?'})</li>`; }); } else { packagingHtml += `<li>-</li>`; }
                         packagingHtml += `</ul>`;
                         packagingHtml += `<div class="summary-line">مجموع کارتن: ${data.packaging.summary?.TotalCartons || 0} | نفر ساعت: ${parseFloat(data.packaging.summary?.TotalManHours || 0).toFixed(1)}</div>`;
                    } else {
                        packagingHtml += `<p class="text-muted small">داده‌ای برای بسته‌بندی در این روز ثبت نشده است.</p>`;
                         if (data.packaging) {
                             packagingHtml += `<p class="summary-line">پرسنل بسته‌بندی:</p><ul class="personnel-list">`;
                            if(data.packaging.operators && data.packaging.operators.length > 0) { data.packaging.operators.forEach(op => { packagingHtml += `<li>${op.EmployeeName || '-'} (${op.StartTimeFmt || '?'} - ${op.EndTimeFmt || '?'})</li>`; }); } else { packagingHtml += `<li>-</li>`; }
                            packagingHtml += `</ul>`;
                            packagingHtml += `<div class="summary-line">مجموع کارتن: 0 | نفر ساعت: ${parseFloat(data.packaging.summary?.TotalManHours || 0).toFixed(1)}</div>`;
                         }
                    }
                    packagingSection.html(packagingHtml);


                    // --- Render Description Section ---
                    // ... (Description rendering remains the same) ...
                    descriptionSection.empty();
                    let descriptionHtml = `<h3 class="section-title">توضیحات</h3>`;
                    descriptionHtml += `<p class="small mb-1"><strong>مونتاژ:</strong></p><div class="description-box mb-2">${data.descriptions.assembly || '-'}</div>`;
                    descriptionHtml += `<p class="small mb-1"><strong>رول:</strong></p><div class="description-box mb-2">${data.descriptions.rolling || '-'}</div>`;
                    descriptionHtml += `<p class="small mb-1"><strong>بسته‌بندی:</strong></p><div class="description-box mb-2">${data.descriptions.packaging || '-'}</div>`;
                    descriptionHtml += `<p class="small mb-1"><strong>توضیحات سرپرست:</strong></p><div class="description-box mb-2" contenteditable="true" style="min-height: 70px;"></div>`;
                    descriptionSection.html(descriptionHtml);

                    reportContent.show(); // Show content after rendering all sections
                } else {
                    reportContainer.show(); reportContent.hide(); $('#report-date-display').text(`تاریخ: ${reportDate}`);
                    assemblySection.html(`<div class="alert alert-warning text-center p-3">${response.message || 'داده‌ای برای این روز یافت نشد.'}</div>`);
                    rollingSection.empty(); packagingSection.empty(); descriptionSection.empty();
                }
            })
            .fail(function(jqXHR) {
                 loader.hide(); console.error("AJAX Error:", jqXHR.status, jqXHR.statusText, jqXHR.responseText);
                 alert('خطا در دریافت اطلاعات گزارش. لطفاً کنسول مرورگر (F12) را بررسی کنید.');
                 reportContainer.show(); reportContent.hide(); $('#report-date-display').text(`تاریخ: ${reportDate}`);
                 assemblySection.html('<p class="text-center text-danger p-3">خطا در بارگذاری گزارش.</p>');
                 rollingSection.empty(); packagingSection.empty(); descriptionSection.empty();
            });
    });

    $('#print-btn').on('click', function() {
        if ($('#report-content').is(':hidden') || $('#assembly-details-table-container').is(':empty')) {
            alert('ابتدا گزارش را برای یک تاریخ معتبر ایجاد کنید.');
            return;
        }
        window.print();
    });

});
</script>

