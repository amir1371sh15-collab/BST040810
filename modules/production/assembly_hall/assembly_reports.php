<?php
require_once __DIR__ . '/../../../config/init.php';

// Permission Check: User needs at least view access
if (!has_permission('production.assembly_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "داشبورد تحلیلی مونتاژ";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">داشبورد تحلیلی مونتاژ</h1>
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
                <!-- Add other filters if needed (e.g., machine, part) -->
            </div>
            <hr>
            <label class="form-label fw-bold">انتخاب گزارش‌ها:</label>
            <div class="row">
                 <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_prod_trend" checked data-target="section_prod_trend"><label class="form-check-label" for="show_prod_trend">نمودار روند تولید</label></div></div>
                 <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_productivity_trend" checked data-target="section_productivity_trend"><label class="form-check-label" for="show_productivity_trend">نمودار روند بهره وری</label></div></div>
                 <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_cumulative_trend" checked data-target="section_cumulative_trend"><label class="form-check-label" for="show_cumulative_trend">نمودار و جدول تجمعی/انحراف</label></div></div>
                 <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_active_machines_trend" checked data-target="section_active_machines_trend"><label class="form-check-label" for="show_active_machines_trend">نمودار تعداد دستگاه فعال</label></div></div>
                 <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_final_summary" checked data-target="section_final_summary"><label class="form-check-label" for="show_final_summary">جدول خلاصه نهایی</label></div></div>
                 <div class="col-12 mt-2">
                     <button type="button" class="btn btn-sm btn-outline-primary" id="select-all">انتخاب همه</button>
                     <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="deselect-all">حذف همه</button>
                 </div>
            </div>

            <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-bar-chart-line"></i> ایجاد گزارش</button>
                <button type="button" id="print-btn" class="btn btn-secondary"><i class="bi bi-printer"></i> چاپ</button>
            </div>
        </form>
    </div>
</div>

<div id="report-container" class="mt-4" style="display:none;">
    <div class="text-center" id="loader"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
    <div id="a4-wrapper">
        <div id="report-content" class="a4-page">
            <div id="report-header" class="mb-4 text-center" style="display:none;">
                <h2 id="report-main-title"></h2>
                <p id="report-filter-summary" class="text-muted small"></p>
                <p id="report-avg-summary" class="text-muted small"></p>
            </div>

            <!-- Chart 1: Production Trend -->
            <div class="card content-card mb-3 report-section" id="section_prod_trend" data-checkbox-id="show_prod_trend">
                <div class="card-header"><h5>نمودار روند تولید روزانه (تعداد)</h5></div>
                <div class="card-body"><div class="chart-container-large"><canvas id="productionTrendChart"></canvas></div></div>
            </div>

            <!-- Chart 2: Productivity Trend -->
            <div class="card content-card mb-3 report-section" id="section_productivity_trend" data-checkbox-id="show_productivity_trend">
                 <div class="card-header"><h5>نمودار روند بهره وری (تعداد / ساعت واقعی)</h5></div>
                 <div class="card-body"><div class="chart-container-large"><canvas id="productivityTrendChart"></canvas></div></div>
            </div>

            <!-- Chart 3 & Table: Cumulative Plan vs Actual & Deviation -->
            <div class="report-section" id="section_cumulative_trend" data-checkbox-id="show_cumulative_trend">
                <div class="card content-card mb-3">
                    <div class="card-header"><h5>نمودار تجمعی برنامه و تولید (تعداد)</h5></div>
                    <div class="card-body"><div class="chart-container-large"><canvas id="cumulativeTrendChart"></canvas></div></div>
                </div>
                 <div class="card content-card mb-3">
                     <div class="card-header"><h5>جدول انحراف روزانه از برنامه (تعداد)</h5></div>
                     <div class="card-body p-0">
                         <div class="table-responsive">
                             <table class="table table-sm table-bordered text-center mb-0 deviation-table">
                                 <thead id="deviation-table-head"></thead>
                                 <tbody id="deviation-table-body"></tbody>
                             </table>
                         </div>
                     </div>
                </div>
            </div>

            <!-- Chart 4: Active Machines Trend -->
            <div class="card content-card mb-3 report-section" id="section_active_machines_trend" data-checkbox-id="show_active_machines_trend">
                 <div class="card-header"><h5>نمودار تعداد دستگاه فعال روزانه</h5></div>
                 <div class="card-body"><div class="chart-container-large"><canvas id="activeMachinesChart"></canvas></div></div>
            </div>

            <!-- Table 5: Final Summary -->
            <div class="card content-card mb-3 report-section" id="section_final_summary" data-checkbox-id="show_final_summary">
                 <div class="card-header"><h5>خلاصه وضعیت نهایی دوره</h5></div>
                 <div class="card-body">
                     <table class="table table-sm table-bordered summary-table">
                         <tbody>
                             <tr><th scope="row">برنامه تجمعی کل</th><td id="summary_cumulative_plan"></td></tr>
                             <tr><th scope="row">تولید تجمعی کل</th><td id="summary_cumulative_actual"></td></tr>
                             <tr><th scope="row">انحراف نهایی از برنامه</th><td id="summary_final_deviation"></td></tr>
                         </tbody>
                     </table>
                 </div>
            </div>

        </div>
    </div>
</div>

<style>
#a4-wrapper { background: #525659; padding: 30px 0; }
.a4-page { background: white; width: 21cm; min-height: 29.7cm; display: block; margin: 0 auto; padding: 1.5cm; box-shadow: 0 0 0.5cm rgba(0,0,0,0.5); }
.chart-container { position: relative; height: 300px; width: 100%; }
.chart-container-large { position: relative; height: 250px; width: 100%; }
.report-section { display: none; }
.report-section.active { display: block; }
.deviation-table th, .deviation-table td { font-size: 0.75rem; padding: 0.2rem 0.4rem !important; white-space: nowrap;}
.summary-table th { width: 40%;}
.deviation-negative { color: #dc3545; font-weight: bold;}
.deviation-positive { color: #198754; font-weight: bold;}

@media print {
    body * { visibility: hidden; }
    #report-container, #report-container * { visibility: visible; }
    #report-container { position: absolute; left: 0; top: 0; width: 100%; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    .navbar, .page-header, #filter-card, #loader, .btn { display: none !important; }
    #a4-wrapper { background: white !important; padding: 0 !important; box-shadow: none !important; }
    .a4-page { width: 100% !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 1cm !important; box-shadow: none !important; border: none !important; }
    #report-header { display: block !important; page-break-after: avoid; text-align: center; }
    h2 { font-size: 14pt !important; margin-bottom: 5px !important;}
    #report-filter-summary, #report-avg-summary { font-size: 8pt !important; margin-bottom: 8px !important; color: #666 !important; }
    .report-section { page-break-inside: avoid; margin-bottom: 15px !important; } /* Add margin between sections for print */
    .card { border: 1px solid #ccc !important; box-shadow: none !important; }
    .card-header h5 { font-size: 10pt !important; font-weight: bold; }
    .chart-container, .chart-container-large { height: 200px !important; width: 100% !important; } /* Adjust height for print */
    canvas { display: none !important; }
    .print-image { display: block !important; max-width: 100%; height: auto; }
    .table { font-size: 7pt !important; } /* Smaller font for tables */
    .table th, .table td { padding: 3px 4px !important; border: 1px solid #ccc !important; }
    .deviation-table th, .deviation-table td { padding: 2px 3px !important; }
    .deviation-negative, .deviation-positive { color: black !important; font-weight: normal !important;} /* Remove color for print */
    @page { size: A4 portrait; margin: 1.5cm; }
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    Chart.defaults.font.family = "'Vazirmatn', sans-serif";
    Chart.defaults.font.size = 10;
    Chart.register(ChartDataLabels);
    Chart.defaults.set('plugins.datalabels', { display: false }); // Disable globally first
    let charts = {};

    // --- UI Helpers ---
    $('#select-all').on('click', function() { $('.report-selector').prop('checked', true); });
    $('#deselect-all').on('click', function() { $('.report-selector').prop('checked', false); });

    function destroyCharts() {
        Object.values(charts).forEach(chart => chart && chart.destroy());
        charts = {};
        $('.print-image').remove();
    }

    function updateSectionVisibility() {
        $('.report-section').each(function() {
            $(this).toggleClass('active', $('#' + $(this).data('checkbox-id')).is(':checked'));
        });
    }

    function formatNumber(num) {
        return num.toLocaleString('fa-IR');
    }

    // --- Form Submission ---
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        if ($('.report-selector:checked').length === 0) {
            alert('لطفاً حداقل یک گزارش را برای نمایش انتخاب کنید.'); return;
        }
        $('#report-container').show();
        $('#report-content').hide();
        $('#loader').show();
        destroyCharts();
        $('#deviation-table-head').empty(); // Clear deviation table head/body
        $('#deviation-table-body').empty();
        $('#report-avg-summary').text(''); // Clear average summary

        let summaryParts = [];
        if ($('#start_date').val() && $('#end_date').val()) summaryParts.push(`بازه: ${$('#start_date').val()} تا ${$('#end_date').val()}`);
        // Add other filters to summary if needed

        $('#report-main-title').text('گزارش تحلیلی سالن مونتاژ');
        $('#report-filter-summary').text(summaryParts.join(' | '));
        $('#report-header').show();

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_assembly_analytics.php', $(this).serialize())
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    renderReports(response.data);
                    updateSectionVisibility();
                    $('#report-content').show();
                } else if (response.success) {
                     $('#report-content').html('<div class="alert alert-warning text-center">داده‌ای برای نمایش در بازه زمانی و با فیلترهای انتخابی یافت نشد.</div>').show();
                } else {
                    alert('خطا در دریافت داده‌ها: ' + (response.message || 'خطای نامشخص'));
                }
            })
            .fail(function(jqXHR) {
                 console.error("AJAX Error:", jqXHR.responseText);
                 alert('خطا در ارتباط با سرور.');
            })
            .always(function() {
                $('#loader').hide();
            });
    });

    // --- Print Logic ---
    $('#print-btn').on('click', function() {
        if ($('#report-content').is(':hidden') || !Object.keys(charts).length) {
             alert('ابتدا گزارش را ایجاد کنید.'); return;
        }
        updateSectionVisibility();
        $('.chart-container, .chart-container-large').each(function() {
            if ($(this).closest('.report-section').hasClass('active')) {
                const canvas = $(this).find('canvas')[0];
                if (canvas && charts[canvas.id]) {
                    try {
                        const imageUrl = charts[canvas.id].toBase64Image('image/png', 1.0);
                        $(this).append($('<img>', { class: 'print-image', src: imageUrl, alt: 'Chart Image' }));
                    } catch (e) { console.error("Error converting chart to image:", e); }
                }
            }
        });
        setTimeout(() => window.print(), 300);
     });
    window.onafterprint = () => { $('.print-image').remove(); };

    // --- Chart/Table Rendering ---
    const chartBaseOptions = { responsive: true, maintainAspectRatio: false, animation: false };
    const lineChartOptions = { ...chartBaseOptions, scales: { y: { beginAtZero: true } }, elements: { line: { tension: 0.1 } } };
    const barChartOptions = { ...chartBaseOptions, scales: { y: { beginAtZero: true } } };
     const averageLineDataset = {
        type: 'line',
        label: 'میانگین',
        borderColor: 'rgba(255, 99, 132, 0.7)',
        borderWidth: 1,
        borderDash: [5, 5],
        pointRadius: 0,
        fill: false,
        data: [], // To be filled
        datalabels: { // Custom datalabels for average line
             display: (context) => context.dataIndex === context.chart.data.labels.length - 1, // Show only for the last point
             align: 'end',
             anchor: 'end',
             color: 'rgba(255, 99, 132, 1)',
             font: { weight: 'bold', size: 9 },
             formatter: (value) => 'میانگین: ' + formatNumber(value),
             offset: 8,
             padding: 0
        }
    };
    const planLineDataset = {
        type: 'line',
        label: 'برنامه روزانه',
        borderColor: 'rgba(255, 159, 64, 0.7)',
        borderWidth: 1.5,
        pointRadius: 1,
        pointStyle: 'crossRot',
        fill: false,
        data: [] // To be filled
    };
    const plannedProductivityLine = {
         type: 'line',
        label: 'بهره وری برنامه',
        borderColor: 'rgba(75, 192, 192, 0.7)',
        borderWidth: 1,
        borderDash: [3, 3],
        pointRadius: 0,
        fill: false,
        data: [], // To be filled
        datalabels: { // Custom datalabels for average line
             display: (context) => context.dataIndex === context.chart.data.labels.length - 1, // Show only for the last point
             align: 'end',
             anchor: 'end',
             color: 'rgba(75, 192, 192, 1)',
             font: { weight: 'bold', size: 9 },
             formatter: (value) => 'برنامه: ' + formatNumber(value),
             offset: -15, // Adjust offset to avoid overlap
             padding: 0
        }
    };


    function renderReports(data) {
        const labels = data.map(d => d.LogDateJalali);
        const actualCounts = data.map(d => d.ActualCount || 0);
        const dailyPlans = data.map(d => d.DailyProductionPlan || 0);
        const activeMachines = data.map(d => d.ActiveMachines || 0);
        const actualActiveHours = data.map(d => d.ActualActiveHours || 0);
        const availableMinutes = data.map(d => d.AvailableTimeMinutes || 0);

        // Calculate average production
        const totalActual = actualCounts.reduce((sum, count) => sum + count, 0);
        const averageProduction = data.length > 0 ? Math.round(totalActual / data.length) : 0;
        $('#report-avg-summary').text(`میانگین تولید روزانه دوره: ${formatNumber(averageProduction)} عدد`);

        // 1. Production Trend Chart
        if ($('#show_prod_trend').is(':checked')) {
             // Create copies of template datasets
             let avgLine = {...averageLineDataset, data: Array(labels.length).fill(averageProduction)};
             let planLine = {...planLineDataset, data: dailyPlans};

            charts.productionTrendChart = new Chart($('#productionTrendChart'), {
                type: 'line', // Base type
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'تولید واقعی', data: actualCounts, borderColor: '#36A2EB', backgroundColor: 'rgba(54, 162, 235, 0.1)', fill: true, tension: 0.1, order: 1 }, // Ensure lines are drawn on top
                        avgLine,
                        planLine
                    ]
                },
                options: lineChartOptions
            });
        }

        // 2. Productivity Trend Chart
        if ($('#show_productivity_trend').is(':checked')) {
            const productivityActual = actualCounts.map((count, i) => actualActiveHours[i] > 0 ? Math.round(count / actualActiveHours[i]) : 0);
            const productivityPlanned = dailyPlans.map((plan, i) => availableMinutes[i] > 0 ? Math.round(plan / (availableMinutes[i] / 60)) : 0);

            let plannedProdLine = {...plannedProductivityLine, data: productivityPlanned};

            charts.productivityTrendChart = new Chart($('#productivityTrendChart'), {
                type: 'line',
                 data: {
                    labels: labels,
                    datasets: [
                        { label: 'بهره وری واقعی', data: productivityActual, borderColor: '#4BC0C0', fill: false, tension: 0.1, order: 1 },
                        plannedProdLine
                    ]
                },
                options: lineChartOptions
            });
        }

        // 3. Cumulative Trend Chart & Deviation Table
        if ($('#show_cumulative_trend').is(':checked')) {
            let cumulativeActual = 0;
            let cumulativePlan = 0;
            const cumulativeActualData = [];
            const cumulativePlanData = [];
            const deviationTableHead = $('#deviation-table-head');
            const deviationTableBody = $('#deviation-table-body');
            deviationTableHead.empty();
            deviationTableBody.empty();

            let headRow = '<tr><th>تاریخ</th>';
            let bodyRow = '<tr><td>انحراف</td>';

            data.forEach((day, i) => {
                cumulativeActual += actualCounts[i];
                cumulativePlan += dailyPlans[i];
                cumulativeActualData.push(cumulativeActual);
                cumulativePlanData.push(cumulativePlan);

                const deviation = actualCounts[i] - dailyPlans[i];
                const deviationClass = deviation < 0 ? 'deviation-negative' : (deviation > 0 ? 'deviation-positive' : '');

                headRow += `<th>${labels[i]}</th>`;
                bodyRow += `<td class="${deviationClass}">${formatNumber(deviation)}</td>`;
            });
             headRow += '</tr>';
             bodyRow += '</tr>';
             deviationTableHead.html(headRow);
             deviationTableBody.html(bodyRow);


            charts.cumulativeTrendChart = new Chart($('#cumulativeTrendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'تولید تجمعی', data: cumulativeActualData, borderColor: '#36A2EB', backgroundColor: 'rgba(54, 162, 235, 0.1)', fill: true, tension: 0.1 },
                        { label: 'برنامه تجمعی', data: cumulativePlanData, borderColor: '#FF9F40', fill: false, tension: 0.1 }
                    ]
                },
                options: lineChartOptions
            });

            // Populate Final Summary Table (part of this section)
            if ($('#show_final_summary').is(':checked')) {
                 const finalDeviation = cumulativeActual - cumulativePlan;
                 const finalDeviationClass = finalDeviation < 0 ? 'deviation-negative' : (finalDeviation > 0 ? 'deviation-positive' : '');
                 $('#summary_cumulative_plan').text(formatNumber(cumulativePlan));
                 $('#summary_cumulative_actual').text(formatNumber(cumulativeActual));
                 $('#summary_final_deviation').text(formatNumber(finalDeviation)).removeClass('deviation-negative deviation-positive').addClass(finalDeviationClass);
             }
        } else {
             // Clear summary if cumulative chart is not shown
             $('#summary_cumulative_plan').text('-');
             $('#summary_cumulative_actual').text('-');
             $('#summary_final_deviation').text('-').removeClass('deviation-negative deviation-positive');
        }


        // 4. Active Machines Chart
        if ($('#show_active_machines_trend').is(':checked')) {
            charts.activeMachinesChart = new Chart($('#activeMachinesChart'), {
                type: 'bar',
                 data: {
                    labels: labels,
                    datasets: [{ label: 'تعداد دستگاه فعال', data: activeMachines, backgroundColor: '#FF6384' }]
                },
                options: barChartOptions
            });
        }
    } // End of renderReports
});
</script>
