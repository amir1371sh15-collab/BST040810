<?php
require_once __DIR__ . '/../../../config/init.php';

// Permission Check
if (!has_permission('production.assembly_hall.view')) { // Assuming view is enough for reports
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "گزارش فرآیند مونتاژ";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">گزارش فرآیند مونتاژ</h1>
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
                 <h2 id="report-main-title" class="report-title-print">گزارش فرآیند مونتاژ</h2>
                 <p id="report-filter-summary" class="text-muted small"></p>
                 <p id="report-avg-summary" class="text-muted small"></p>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered text-center report-table" id="report-table">
                    <thead>
                         <tr>
                            <th rowspan="2" class="align-middle col-date">تاریخ</th>
                            <th colspan="7">تولید (تعداد)</th>
                            <th colspan="3">زمان (ساعت)</th>
                            <th colspan="2">عملکرد</th>
                        </tr>
                         <tr>
                            <th class="col-actual-count">تعداد تولید</th>
                            <th class="col-avg-count">میانگین دوره</th>
                            <th class="col-plan-count">برنامه تولید</th>
                            <th class="col-active-machines">تعداد دستگاه</th>
                            <th class="col-cumulative-actual">تجمعی تولید</th>
                            <th class="col-cumulative-plan">تجمعی برنامه</th>
                            <th class="col-deviation">انحراف از برنامه</th>
                            <th class="col-available-time">زمان در دسترس</th>
                            <th class="col-active-time">زمان اکتیو واقعی</th>
                            <th class="col-lost-time">زمان از دست رفته</th>
                            <th class="col-lost-production">تولید از دست رفته (تعداد)</th>
                            <th class="col-efficiency">بهره وری (تعداد/ساعت)</th>
                        </tr>
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

<style>
#a4-wrapper { background: #525659; padding: 30px 0; }
.a4-page { background: white; width: 29.7cm; /* A4 Landscape width */ min-height: 21cm; /* A4 Landscape height */ display: block; margin: 0 auto; padding: 1cm; box-shadow: 0 0 0.5cm rgba(0,0,0,0.5); }
.report-title-print { font-size: 14pt; font-weight: bold; }
.report-table { font-size: 0.75rem; } /* Smaller font for table */
.report-table th, .report-table td { padding: 0.2rem 0.3rem !important; vertical-align: middle; white-space: nowrap; }
.report-table thead th { background-color: #f8f9fa; }
.deviation-negative { color: #dc3545; } /* Red for negative deviation */
.deviation-positive { color: #198754; } /* Green for positive deviation */
.lost-time-value, .lost-prod-value { color: #fd7e14; } /* Orange for lost values */

@media print {
    body * { visibility: hidden; }
    #report-container, #report-container * { visibility: visible; }
    #report-container { position: absolute; left: 0; top: 0; width: 100%; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    .navbar, .page-header, #filter-card, #loader, .btn { display: none !important; visibility: hidden !important; }
    #a4-wrapper { background: white !important; padding: 0 !important; box-shadow: none !important; margin: 0 !important; width: 100% !important; }
    .a4-page { width: 100% !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
    #report-header { display: block !important; visibility: visible !important; margin-bottom: 10px !important; page-break-after: avoid; }
    #report-filter-summary, #report-avg-summary { font-size: 8pt !important; margin-bottom: 5px !important; color: #666 !important; }
    .report-table { font-size: 7pt !important; margin: 0 !important; width: 100% !important; table-layout: auto; }
    .report-table th, .report-table td { padding: 3px 4px !important; border: 1px solid #ddd !important; white-space: nowrap; }
    .report-table thead th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    .report-table tfoot td { background-color: #f8f8f8 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    .deviation-negative, .deviation-positive, .lost-time-value, .lost-prod-value { color: #000 !important; /* Remove colors for print */ }
    @page { size: A4 landscape; margin: 1cm; }
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {

    // Helper functions
    function formatNumber(num) {
        return num.toLocaleString('fa-IR'); // Use Persian locale for formatting
    }
    function formatHours(minutes) {
        if (minutes === null || minutes === undefined || isNaN(minutes) || minutes <= 0) return 0.0;
        return (minutes / 60).toFixed(1);
    }

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        const reportContainer = $('#report-container');
        const loader = $('#loader');
        const tableHeader = $('#report-table-header'); // Not used directly, header is static
        const tableBody = $('#report-table-body');
        const tableFooter = $('#report-table-footer');

        reportContainer.show();
        $('#report-content').hide();
        loader.show();
        tableBody.empty();
        tableFooter.empty();
        $('#report-avg-summary').text(''); // Clear previous average

        let summary = `گزارش از تاریخ ${$('#start_date').val()} تا ${$('#end_date').val()}`;
        $('#report-filter-summary').text(summary);

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_assembly_log_report.php', $(this).serialize())
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    let reportData = response.data;
                    let adjustedData = [];
                    let carryOver = { count: 0, plan: 0, activeHours: 0 }; // To carry over Friday to Saturday

                    // --- Adjust for Friday -> Saturday carry-over ---
                    reportData.forEach((dayData, index) => {
                        let currentData = { ...dayData }; // Clone day data

                        // If it's Saturday (dayOfWeek == 6) and there was a carryOver from Friday
                        if (currentData.dayOfWeek === 6 && carryOver.count > 0) {
                            currentData.ActualCount += carryOver.count;
                            // Assuming plan and active hours for Friday should also add to Saturday
                            currentData.DailyProductionPlan += carryOver.plan;
                            currentData.ActualActiveHours += carryOver.activeHours;
                            carryOver = { count: 0, plan: 0, activeHours: 0 }; // Reset carryOver
                        }

                        // If it's Friday (dayOfWeek == 5), store its data in carryOver
                        if (currentData.dayOfWeek === 5) {
                            carryOver.count = currentData.ActualCount;
                            carryOver.plan = currentData.DailyProductionPlan;
                            carryOver.activeHours = currentData.ActualActiveHours;
                            // Add a placeholder or skip Friday if you don't want to show it
                            // adjustedData.push(null); // Option 1: Add null placeholder
                            // continue; // Option 2: Skip Friday row entirely
                            // Option 3 (Chosen): Show Friday as is, Saturday will show combined
                            adjustedData.push(currentData);
                        } else {
                            adjustedData.push(currentData);
                        }
                    });

                    // Remove null placeholders if used (Option 1)
                    // adjustedData = adjustedData.filter(d => d !== null);

                    // --- Calculate overall average and build table rows ---
                    const totalDays = adjustedData.length;
                    const totalActualCount = adjustedData.reduce((sum, day) => sum + (day.ActualCount || 0), 0);
                    const averageCount = totalDays > 0 ? Math.round(totalActualCount / totalDays) : 0;
                    $('#report-avg-summary').text(`میانگین تولید روزانه دوره: ${formatNumber(averageCount)} عدد`);

                    let cumulativeActual = 0;
                    let cumulativePlan = 0;
                    let totalAvailableMinutes = 0;
                    let totalActiveHours = 0;
                    let totalLostMinutes = 0;
                    let totalLostProduction = 0;
                    let totalPlanSum = 0; // Sum of daily plans

                    adjustedData.forEach(dayData => {
                        cumulativeActual += dayData.ActualCount;
                        // *** Ensure DailyProductionPlan is read correctly ***
                        const dailyPlan = dayData.DailyProductionPlan || 0;
                        cumulativePlan += dailyPlan;
                        const deviation = dayData.ActualCount - dailyPlan;

                        const availableMinutes = dayData.AvailableTimeMinutes || 0;
                        const availableHours = availableMinutes / 60;
                        const activeHours = dayData.ActualActiveHours || 0;
                        // Ensure lost time is not negative
                        const lostMinutes = Math.max(0, availableMinutes - (activeHours * 60));
                        const lostHours = lostMinutes / 60;

                        // Calculate lost production (only if plan and available time exist)
                        let lostProduction = 0;
                        if (availableMinutes > 0 && dailyPlan > 0) {
                            lostProduction = Math.round((dailyPlan / availableMinutes) * lostMinutes);
                        }

                        // Calculate efficiency (count per actual active hour)
                        const efficiency = activeHours > 0 ? Math.round(dayData.ActualCount / activeHours) : 0;

                        // Add to totals
                        totalAvailableMinutes += availableMinutes;
                        totalActiveHours += activeHours;
                        totalLostMinutes += lostMinutes;
                        totalLostProduction += lostProduction;
                        totalPlanSum += dailyPlan;

                        const deviationClass = deviation < 0 ? 'deviation-negative' : (deviation > 0 ? 'deviation-positive' : '');
                        const lostTimeClass = lostMinutes > 0 ? 'lost-time-value' : '';
                        const lostProdClass = lostProduction > 0 ? 'lost-prod-value' : '';

                        let rowHtml = `<tr>
                            <td class="col-date">${dayData.LogDateJalali}</td>
                            <td class="col-actual-count">${formatNumber(dayData.ActualCount)}</td>
                            <td class="col-avg-count">${formatNumber(averageCount)}</td>
                            <td class="col-plan-count">${formatNumber(dailyPlan)}</td>
                            <td class="col-active-machines">${dayData.ActiveMachines}</td>
                            <td class="col-cumulative-actual">${formatNumber(cumulativeActual)}</td>
                            <td class="col-cumulative-plan">${formatNumber(cumulativePlan)}</td>
                            <td class="col-deviation ${deviationClass}">${formatNumber(deviation)}</td>
                            <td class="col-available-time">${formatHours(availableMinutes)}</td>
                            <td class="col-active-time">${activeHours.toFixed(1)}</td>
                            <td class="col-lost-time ${lostTimeClass}">${formatHours(lostMinutes)}</td>
                            <td class="col-lost-production ${lostProdClass}">${formatNumber(lostProduction)}</td>
                            <td class="col-efficiency">${formatNumber(efficiency)}</td>
                        </tr>`;
                        tableBody.append(rowHtml);
                    });

                    // --- Build Footer ---
                    const overallDeviation = totalActualCount - totalPlanSum;
                    const overallEfficiency = totalActiveHours > 0 ? Math.round(totalActualCount / totalActiveHours) : 0;
                    const overallDeviationClass = overallDeviation < 0 ? 'deviation-negative' : (overallDeviation > 0 ? 'deviation-positive' : '');
                     const overallLostTimeClass = totalLostMinutes > 0 ? 'lost-time-value' : '';
                     const overallLostProdClass = totalLostProduction > 0 ? 'lost-prod-value' : '';

                    let footerHtml = `<tr>
                        <td class="col-date">مجموع / میانگین</td>
                        <td class="col-actual-count">${formatNumber(totalActualCount)}</td>
                        <td class="col-avg-count">${formatNumber(averageCount)}</td>
                        <td class="col-plan-count">${formatNumber(totalPlanSum)}</td>
                        <td class="col-active-machines">-</td>
                        <td class="col-cumulative-actual">${formatNumber(cumulativeActual)}</td>
                        <td class="col-cumulative-plan">${formatNumber(cumulativePlan)}</td>
                        <td class="col-deviation ${overallDeviationClass}">${formatNumber(overallDeviation)}</td>
                        <td class="col-available-time">${formatHours(totalAvailableMinutes)}</td>
                        <td class="col-active-time">${totalActiveHours.toFixed(1)}</td>
                        <td class="col-lost-time ${overallLostTimeClass}">${formatHours(totalLostMinutes)}</td>
                        <td class="col-lost-production ${overallLostProdClass}">${formatNumber(totalLostProduction)}</td>
                        <td class="col-efficiency">${formatNumber(overallEfficiency)}</td>
                    </tr>`;
                    tableFooter.html(footerHtml);

                    $('#report-content').show();
                } else {
                    tableBody.html('<tr><td colspan="13" class="text-center text-muted p-3">هیچ داده‌ای در این بازه زمانی یافت نشد.</td></tr>'); // Updated colspan
                    $('#report-content').show();
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                 alert('خطا در برقراری ارتباط با سرور.');
            })
            .always(function() {
                loader.hide();
            });
    });

    $('#print-btn').on('click', function() {
        if ($('#report-table-body').is(':empty') || $('#report-table-body').find('td').length <= 1 ) {
            alert('ابتدا گزارش را با داده‌های معتبر ایجاد کنید.');
            return;
        }
        window.print();
    });

    // Trigger form submit on initial load if needed
    // $('#filter-form').trigger('submit');

});
</script>

