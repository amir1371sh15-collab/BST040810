<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.production_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// Fetch filter data
$machine_types = $pdo->query("SELECT DISTINCT MachineType FROM tbl_machines WHERE MachineType IS NOT NULL AND MachineType != '' ORDER BY MachineType")->fetchAll(PDO::FETCH_COLUMN);
$machines = find_all($pdo, "SELECT MachineID, MachineName FROM tbl_machines ORDER BY MachineName");
$molds = find_all($pdo, "SELECT MoldID, MoldName FROM tbl_molds ORDER BY MoldName");

$pageTitle = "داشبورد تحلیلی توقفات";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">داشبورد تحلیلی توقفات</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header">
        <h5 class="mb-0">فیلتر گزارش</h5>
    </div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="text" id="start_date" name="start_date" class="form-control persian-date">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="text" id="end_date" name="end_date" class="form-control persian-date">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">زمان آماده به کار (دقیقه در روز)</label>
                    <input type="number" id="shift_duration" name="shift_duration" class="form-control" placeholder="مثال: 480">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">نوع دستگاه</label>
                    <select id="machine_type" name="machine_type" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($machine_types as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">دستگاه خاص</label>
                    <select id="machine_id" name="machine_id" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($machines as $machine): ?>
                        <option value="<?php echo $machine['MachineID']; ?>"><?php echo $machine['MachineName']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">قالب خاص</label>
                    <select id="mold_id" name="mold_id" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($molds as $mold): ?>
                        <option value="<?php echo $mold['MoldID']; ?>"><?php echo $mold['MoldName']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Report Selection -->
            <div class="row mt-3">
                <div class="col-12">
                    <label class="form-label fw-bold">انتخاب نمودارها و گزارش‌ها:</label>
                    <div class="row">
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_pie" checked><label class="form-check-label" for="show_pie">نمودار دایره‌ای علل توقف</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_pareto_table" checked><label class="form-check-label" for="show_pareto_table">جدول پارتو</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_pareto_chart" checked><label class="form-check-label" for="show_pareto_chart">نمودار پارتو</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_by_machine" checked><label class="form-check-label" for="show_by_machine">توقفات بر اساس دستگاه</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_by_mold" checked><label class="form-check-label" for="show_by_mold">توقفات بر اساس قالب</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_trend" checked><label class="form-check-label" for="show_trend">روند زمانی توقفات</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_efficiency_table"><label class="form-check-label" for="show_efficiency_table">جدول راندمان دستگاه‌ها</label></div></div>
                        <div class="col-md-3 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_efficiency_trend"><label class="form-check-label" for="show_efficiency_trend">نمودار روند راندمان پرس‌ها</label></div></div>
                        <div class="col-12 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="select-all"><i class="bi bi-check-all"></i> انتخاب همه</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all"><i class="bi bi-x-circle"></i> حذف همه</button>
                        </div>
                    </div>
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
                <h2 id="report-main-title" class="report-title-print"></h2>
                <p id="report-filter-summary" class="text-muted"></p>
            </div>
            <div class="row first-row">
                <div class="col-lg-5 mb-3 report-section" id="section-pie"><div class="card content-card h-100"><div class="card-header"><h5 class="mb-0">درصد توقفات بر اساس علت</h5></div><div class="card-body"><div class="chart-container"><canvas id="pieChart"></canvas></div></div></div></div>
                <div class="col-lg-7 mb-3 report-section" id="section-pareto-table"><div class="card content-card h-100"><div class="card-header"><h5 class="mb-0">تحلیل پارتو علل توقفات</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr class="table-light"><th>علت توقف</th><th>زمان (دقیقه)</th><th>درصد</th><th>تجمعی</th></tr></thead><tbody id="pareto-table-body"></tbody></table></div></div></div></div>
            </div>
            <div class="card content-card mb-3 report-section full-width" id="section-pareto-chart"><div class="card-header"><h5 class="mb-0">نمودار پارتو</h5></div><div class="card-body"><div class="chart-container"><canvas id="paretoBarChart"></canvas></div></div></div>
            <div class="row second-row">
                <div class="col-lg-6 mb-3 report-section" id="section-by-machine"><div class="card content-card h-100"><div class="card-header"><h5 class="mb-0">توقفات بر اساس دستگاه</h5></div><div class="card-body"><div class="chart-container"><canvas id="byMachineChart"></canvas></div></div></div></div>
                <div class="col-lg-6 mb-3 report-section" id="section-by-mold"><div class="card content-card h-100"><div class="card-header"><h5 class="mb-0">توقفات بر اساس قالب</h5></div><div class="card-body"><div class="chart-container"><canvas id="byMoldChart"></canvas></div></div></div></div>
            </div>
            <div class="card content-card mb-3 report-section full-width" id="section-trend"><div class="card-header"><h5 class="mb-0">روند زمان توقفات روزانه</h5></div><div class="card-body"><div class="chart-container trend-chart"><canvas id="trendChart"></canvas></div></div></div>
            
            <!-- New Efficiency Table Section -->
            <div class="card content-card mb-3 report-section full-width" id="section-efficiency-table">
                <div class="card-header"><h5 class="mb-0">راندمان دستگاه‌ها</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>دستگاه</th><th>روزهای کاری</th><th>زمان کل آماده به کار (دقیقه)</th><th>کل توقفات (دقیقه)</th><th>توقفات بی‌برنامگی (دقیقه)</th><th>توقفات موثر (دقیقه)</th><th>زمان کارکرد موثر (دقیقه)</th><th>راندمان (%)</th>
                                </tr>
                            </thead>
                            <tbody id="efficiency-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- New Efficiency Trend Chart Section -->
            <div class="card content-card mb-3 report-section full-width" id="section-efficiency-trend">
                <div class="card-header"><h5 class="mb-0">روند راندمان روزانه پرس‌ها</h5></div>
                <div class="card-body">
                    <div class="chart-container trend-chart"><canvas id="efficiencyTrendChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* A4 Page Simulation - Screen View */
#a4-wrapper { background: #525659; padding: 30px 0; }
.a4-page { background: white; width: 21cm; height: auto; min-height: 29.7cm; display: block; margin: 0 auto; padding: 1.5cm; box-shadow: 0 0 0.5cm rgba(0,0,0,0.5); }
.chart-container { position: relative; height: 280px; width: 100%; }
.chart-container.trend-chart { height: 250px; }
.report-title-print { display: none; }
.print-image { display: none; max-width: 100%; height: auto; }
.report-section { display: none; }
.report-section.active { display: block; }
/* Print Styles */
@media print {
    body * { visibility: hidden; }
    #report-container, #report-container * { visibility: visible; }
    #report-container { position: absolute; left: 0; top: 0; width: 100%; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    .navbar, .page-header, #filter-card, #loader { display: none !important; visibility: hidden !important; }
    #a4-wrapper { background: white !important; padding: 0 !important; box-shadow: none !important; margin: 0 !important; width: 100% !important; }
    .a4-page { width: 100% !important; max-width: 100% !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
    #report-header { display: block !important; visibility: visible !important; margin-bottom: 15px !important; page-break-after: avoid; }
    .report-title-print { display: block !important; font-size: 16pt !important; font-weight: bold !important; margin-bottom: 8px !important; color: #000 !important; }
    #report-filter-summary { font-size: 9pt !important; margin-bottom: 12px !important; color: #666 !important; }
    .row { display: block !important; }
    .row > [class*="col-"] { width: 100% !important; max-width: 100% !important; flex: none !important; page-break-inside: avoid; margin-bottom: 15px; }
    .report-section { display: none !important; visibility: hidden !important; }
    .report-section.active { display: block !important; visibility: visible !important; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; page-break-inside: avoid; }
    .card-header { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; padding: 6px 10px !important; border-bottom: 1px solid #ccc !important; }
    .card-header h5 { font-size: 10pt !important; font-weight: bold !important; margin: 0 !important; color: #000 !important; }
    .card-body { padding: 8px !important; }
    .chart-container { height: 250px !important; width: 100% !important; }
    .chart-container.trend-chart { height: 220px !important; }
    .table-responsive { overflow: visible !important; }
    .table { font-size: 8pt !important; margin: 0 !important; width: 100% !important; }
    .table th, .table td { padding: 3px 4px !important; border: 1px solid #ddd !important; }
    .table thead th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact !important; font-weight: bold !important; }
    #report-container, #report-content { display: block !important; visibility: visible !important; }
    canvas { display: none !important; }
    .print-image { display: block !important; visibility: visible !important; width: 100% !important; height: 100% !important; object-fit: contain !important; }
    @page { size: A4 portrait; margin: 1.5cm; }
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    Chart.defaults.font.family = "'Vazirmatn', sans-serif";
    Chart.defaults.font.size = 10;
    let charts = {};

    $('#select-all').on('click', function() { $('input[type="checkbox"][id^="show_"]').prop('checked', true); });
    $('#deselect-all').on('click', function() { $('input[type="checkbox"][id^="show_"]').prop('checked', false); });

    function destroyCharts() {
        Object.values(charts).forEach(chart => chart.destroy());
        charts = {};
        $('.print-image').remove();
    }
    
    function updateSectionVisibility() {
        $('#section-pie').toggleClass('active', $('#show_pie').is(':checked'));
        $('#section-pareto-table').toggleClass('active', $('#show_pareto_table').is(':checked'));
        $('#section-pareto-chart').toggleClass('active', $('#show_pareto_chart').is(':checked'));
        $('#section-by-machine').toggleClass('active', $('#show_by_machine').is(':checked'));
        $('#section-by-mold').toggleClass('active', $('#show_by_mold').is(':checked'));
        $('#section-trend').toggleClass('active', $('#show_trend').is(':checked'));
        $('#section-efficiency-table').toggleClass('active', $('#show_efficiency_table').is(':checked'));
        $('#section-efficiency-trend').toggleClass('active', $('#show_efficiency_trend').is(':checked'));
        
        $('.first-row').toggle($('#section-pie').hasClass('active') || $('#section-pareto-table').hasClass('active'));
        $('.second-row').toggle($('#section-by-machine').hasClass('active') || $('#section-by-mold').hasClass('active'));
    }

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        
        if ($('input[type="checkbox"][id^="show_"]:checked').length === 0) {
            alert('لطفاً حداقل یک نمودار یا گزارش را انتخاب کنید.');
            return;
        }

        if ($('#show_efficiency_table').is(':checked') || $('#show_efficiency_trend').is(':checked')) {
            if (!$('#shift_duration').val() || parseInt($('#shift_duration').val()) <= 0) {
                alert('برای محاسبه راندمان، لطفا زمان آماده به کار (بیشتر از صفر) را وارد کنید.');
                return;
            }
        }
        
        const formData = $(this).serialize();
        $('#report-container').show();
        $('#report-content').hide();
        $('#loader').show();
        destroyCharts();
        
        let summaryParts = [];
        if ($('#start_date').val() && $('#end_date').val()) summaryParts.push(`در بازه زمانی ${$('#start_date').val()} تا ${$('#end_date').val()}`);
        if ($('#shift_duration').val()) summaryParts.push(`زمان شیفت روزانه: ${$('#shift_duration').val()} دقیقه`);
        if ($('#machine_type').val()) summaryParts.push(`نوع دستگاه: ${$('#machine_type option:selected').text()}`);
        if ($('#machine_id').val()) summaryParts.push(`دستگاه: ${$('#machine_id option:selected').text()}`);
        if ($('#mold_id').val()) summaryParts.push(`قالب: ${$('#mold_id option:selected').text()}`);

        $('#report-main-title').text('گزارش تحلیلی توقفات و راندمان');
        $('#report-filter-summary').text(summaryParts.join(' | '));
        $('#report-header').show();

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_downtime_analytics.php', formData)
            .done(function(response) {
                if (response.success) {
                    renderReports(response.data);
                    updateSectionVisibility();
                    $('#report-content').show();
                } else {
                    alert('خطا در دریافت داده‌ها: ' + (response.message || 'خطای نامشخص'));
                }
            })
            .fail(function() { alert('خطا در برقراری ارتباط با سرور.'); })
            .always(function() { $('#loader').hide(); });
    });

    $('#print-btn').on('click', function() {
        updateSectionVisibility();
        $('.chart-container').each(function() {
            if ($(this).closest('.report-section').hasClass('active')) {
                const canvas = $(this).find('canvas')[0];
                if (canvas && charts[canvas.id]) {
                    const imageUrl = charts[canvas.id].toBase64Image('image/png', 1);
                    $(this).append($('<img>', { class: 'print-image', src: imageUrl, alt: 'Chart' }));
                }
            }
        });
        setTimeout(function() { window.print(); }, 300);
    });

    window.onafterprint = () => { $('.print-image').remove(); };

    const chartBaseOptions = { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { labels: { font: { size: 10 } } } } };

    function renderReports(data) {
        // --- Existing Charts ---
        if ($('#show_pie').is(':checked') && data.pareto_by_reason) { charts.pieChart = new Chart(document.getElementById('pieChart'), { type: 'pie', data: { labels: data.pareto_by_reason.map(d => d.reason), datasets: [{ data: data.pareto_by_reason.map(d => d.duration), backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'] }] }, options: { ...chartBaseOptions, plugins: { legend: { position: 'bottom', labels: { font: { size: 9 }, padding: 8 } } } } }); }
        if ($('#show_pareto_table').is(':checked') && data.pareto_by_reason) { const body = $('#pareto-table-body').empty(); data.pareto_by_reason.forEach(item => { body.append(`<tr><td>${item.reason}</td><td>${item.duration}</td><td>${item.percentage}%</td><td>${item.cumulative_percentage}%</td></tr>`); }); }
        if ($('#show_pareto_chart').is(':checked') && data.pareto_by_reason) { charts.paretoBarChart = new Chart(document.getElementById('paretoBarChart'), { type: 'bar', data: { labels: data.pareto_by_reason.map(d => d.reason), datasets: [{ label: 'زمان توقف (دقیقه)', data: data.pareto_by_reason.map(d => d.duration), backgroundColor: 'rgba(54, 162, 235, 0.7)', yAxisID: 'y' }, { label: 'درصد تجمعی', data: data.pareto_by_reason.map(d => d.cumulative_percentage), type: 'line', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 2, pointRadius: 3, yAxisID: 'y1' }] }, options: { ...chartBaseOptions, scales: { y: { beginAtZero: true, position: 'left' }, y1: { max: 100, beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }, x: { ticks: { font: { size: 9 } } } } } }); }
        if ($('#show_by_machine').is(':checked') && data.by_machine) { charts.byMachineChart = new Chart(document.getElementById('byMachineChart'), { type: 'bar', data: { labels: data.by_machine.map(d => d.MachineName), datasets: [{ label: 'زمان توقف (دقیقه)', data: data.by_machine.map(d => d.TotalDuration), backgroundColor: 'rgba(75, 192, 192, 0.7)' }] }, options: { ...chartBaseOptions, indexAxis: 'y', plugins: { legend: { display: false } } } }); }
        if ($('#show_by_mold').is(':checked') && data.by_mold) { charts.byMoldChart = new Chart(document.getElementById('byMoldChart'), { type: 'bar', data: { labels: data.by_mold.map(d => d.MoldName), datasets: [{ label: 'زمان توقف (دقیقه)', data: data.by_mold.map(d => d.TotalDuration), backgroundColor: 'rgba(255, 159, 64, 0.7)' }] }, options: { ...chartBaseOptions, indexAxis: 'y', plugins: { legend: { display: false } } } }); }
        if ($('#show_trend').is(':checked') && data.trend_by_day) { charts.trendChart = new Chart(document.getElementById('trendChart'), { type: 'line', data: { labels: data.trend_by_day.map(d => d.date), datasets: [{ label: 'مجموع زمان توقف (دقیقه)', data: data.trend_by_day.map(d => d.duration), borderColor: 'rgba(153, 102, 255, 1)', backgroundColor: 'rgba(153, 102, 255, 0.1)', borderWidth: 2, tension: 0.3, fill: true, pointRadius: 3 }] }, options: { ...chartBaseOptions, scales: { y: { beginAtZero: true } } } }); }

        // --- New Efficiency Table ---
        if ($('#show_efficiency_table').is(':checked') && data.efficiency_by_machine) {
            const body = $('#efficiency-table-body').empty();
            data.efficiency_by_machine.forEach(item => {
                body.append(`
                    <tr>
                        <td>${item.machine_name}</td>
                        <td>${item.logged_days}</td>
                        <td>${item.total_available_time}</td>
                        <td>${item.total_downtime}</td>
                        <td>${item.no_plan_downtime}</td>
                        <td>${item.effective_downtime}</td>
                        <td>${item.effective_working_time}</td>
                        <td><b>${item.efficiency}%</b></td>
                    </tr>`);
            });
        }
        
        // --- New Efficiency Trend Chart ---
        if ($('#show_efficiency_trend').is(':checked') && data.efficiency_trend) {
            charts.efficiencyTrendChart = new Chart(document.getElementById('efficiencyTrendChart'), {
                type: 'line',
                data: {
                    labels: data.efficiency_trend.labels,
                    datasets: data.efficiency_trend.datasets.map(ds => ({ ...ds, borderWidth: 2, tension: 0.3, pointRadius: 3, fill: false }))
                },
                options: { ...chartBaseOptions, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: value => value + '%' } } } }
            });
        }
    }
});
</script>
