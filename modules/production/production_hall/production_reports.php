<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.production_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// Fetch filter data
$machine_types = $pdo->query("SELECT DISTINCT MachineType FROM tbl_machines WHERE MachineType IS NOT NULL AND MachineType != '' ORDER BY MachineType")->fetchAll(PDO::FETCH_COLUMN);
$machines = find_all($pdo, "SELECT MachineID, MachineName FROM tbl_machines ORDER BY MachineName");
$molds = find_all($pdo, "SELECT MoldID, MoldName FROM tbl_molds ORDER BY MoldName");
$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$pageTitle = "داشبورد تحلیلی تولید";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">داشبورد تحلیلی تولید</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header"><h5 class="mb-0">فیلتر گزارش</h5></div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">از تاریخ</label><input type="text" id="start_date" name="start_date" class="form-control persian-date"></div>
                <div class="col-md-3 mb-3"><label class="form-label">تا تاریخ</label><input type="text" id="end_date" name="end_date" class="form-control persian-date"></div>
                <div class="col-md-3 mb-3"><label class="form-label">نوع دستگاه</label><select id="machine_type" name="machine_type" class="form-select"><option value="">همه</option><?php foreach($machine_types as $type): ?><option value="<?php echo $type; ?>"><?php echo $type; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">دستگاه</label><select id="machine_id" name="machine_id" class="form-select"><option value="">همه</option><?php foreach($machines as $machine): ?><option value="<?php echo $machine['MachineID']; ?>"><?php echo $machine['MachineName']; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">قالب</label><select id="mold_id" name="mold_id" class="form-select"><option value="">همه</option><?php foreach($molds as $mold): ?><option value="<?php echo $mold['MoldID']; ?>"><?php echo $mold['MoldName']; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">خانواده قطعه</label><select id="part_family_id" name="part_family_id" class="form-select"><option value="">همه</option><?php foreach($part_families as $family): ?><option value="<?php echo $family['FamilyID']; ?>"><?php echo $family['FamilyName']; ?></option><?php endforeach; ?></select></div>
            </div>

            <hr>
            <label class="form-label fw-bold">انتخاب گزارش‌ها (به ترتیب نمایش):</label>
            <div class="row">
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_prod_trend" checked><label class="form-check-label" for="show_prod_trend">روند تولید (KG)</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_prod_by_part" checked><label class="form-check-label" for="show_prod_by_part">تولید بر اساس قطعه</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_prod_by_family" checked><label class="form-check-label" for="show_prod_by_family">تولید بر اساس خانواده</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_prod_by_machine" checked><label class="form-check-label" for="show_prod_by_machine">تولید بر اساس دستگاه</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_prod_by_mold" checked><label class="form-check-label" for="show_prod_by_mold">تولید بر اساس قالب</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_productivity_trend" checked><label class="form-check-label" for="show_productivity_trend">روند بهره‌وری (KG/N-Hr)</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_availability_details" checked><label class="form-check-label" for="show_availability_details">جدول محاسبه تلفات دسترسی</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_performance_details" checked><label class="form-check-label" for="show_performance_details">جدول محاسبه تلفات عملکرد</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_loss_comparison_table" checked><label class="form-check-label" for="show_loss_comparison_table">جدول مقایسه تلفات</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_loss_breakdown" checked><label class="form-check-label" for="show_loss_breakdown">نمودار کالبدشکافی تلفات</label></div></div>
                <div class="col-12 mt-2"><button type="button" class="btn btn-sm btn-outline-primary" id="select-all">انتخاب همه</button><button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="deselect-all">حذف همه</button></div>
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
            <div id="report-header" class="mb-4 text-center" style="display:none;"><h2 id="report-main-title"></h2><p id="report-filter-summary" class="text-muted"></p></div>

            <!-- Standard Reports -->
            <div class="card content-card mb-3 report-section" id="section_prod_trend"><div class="card-header"><h5>روند تولید روزانه (KG)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="prodTrendChart"></canvas></div></div></div>
            <div class="row report-section" id="section_by_part_family">
                <div class="col-lg-6 mb-3" id="section_prod_by_part"><div class="card content-card h-100"><div class="card-header"><h5>تولید بر اساس قطعه (KG)</h5></div><div class="card-body"><div class="chart-container"><canvas id="prodByPartChart"></canvas></div></div></div></div>
                <div class="col-lg-6 mb-3" id="section_prod_by_family"><div class="card content-card h-100"><div class="card-header"><h5>تولید بر اساس خانواده (KG)</h5></div><div class="card-body"><div class="chart-container"><canvas id="prodByFamilyChart"></canvas></div></div></div></div>
            </div>
             <div class="row report-section" id="section_by_machine_mold">
                <div class="col-lg-6 mb-3" id="section_prod_by_machine"><div class="card content-card h-100"><div class="card-header"><h5>تولید بر اساس دستگاه (KG)</h5></div><div class="card-body"><div class="chart-container"><canvas id="prodByMachineChart"></canvas></div></div></div></div>
                <div class="col-lg-6 mb-3" id="section_prod_by_mold"><div class="card content-card h-100"><div class="card-header"><h5>تولید بر اساس قالب (KG)</h5></div><div class="card-body"><div class="chart-container"><canvas id="prodByMoldChart"></canvas></div></div></div></div>
            </div>
            <div class="card content-card mb-3 report-section" id="section_productivity_trend"><div class="card-header"><h5>روند بهره‌وری (کیلوگرم بر نفر-ساعت)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="productivityTrendChart"></canvas></div></div></div>

            <!-- Analysis Reports -->
            <div class="card content-card mb-3 report-section" id="section_availability_details"><div class="card-header"><h5>جزئیات محاسبه تلفات دسترسی (Availability)</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered text-center"><thead><tr class="table-light"><th>دستگاه</th><th>زمان کل در دسترس</th><th>تولید تئوری</th><th>توقف تعدیل‌شده (دقیقه)</th><th>تولید از دست رفته (توقفات)</th></tr></thead><tbody id="availability-details-body"></tbody></table></div></div></div>
            <div class="card content-card mb-3 report-section" id="section_performance_details"><div class="card-header"><h5>جزئیات محاسبه تلفات عملکرد (Performance)</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered text-center"><thead><tr class="table-light"><th>دستگاه</th><th>زمان کل در دسترس</th><th>تولید تئوری</th><th>تولید واقعی</th><th>کل تلفات عملکرد</th><th>راندمان عملکرد</th></tr></thead><tbody id="performance-details-body"></tbody></table></div></div></div>
            <div class="card content-card mb-3 report-section" id="section_loss_comparison_table"><div class="card-header"><h5>مقایسه تلفات عملکرد و دسترسی (تعداد قطعه)</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered text-center"><thead><tr class="table-light"><th>دستگاه</th><th>کل تلفات عملکرد</th><th>تلفات توقفات (Availability)</th><th>تلفات عملکرد پنهان (Performance)</th></tr></thead><tbody id="loss-comparison-body"></tbody></table></div></div></div>
            <div class="card content-card mb-3 report-section" id="section_loss_breakdown"><div class="card-header"><h5>نمودار کالبدشکافی تلفات (تعداد قطعه)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="lossBreakdownChart"></canvas></div></div></div>

        </div>
    </div>
</div>
<style>#a4-wrapper{background:#525659;padding:30px 0;}.a4-page{background:white;width:21cm;min-height:29.7cm;display:block;margin:0 auto;padding:1.5cm;box-shadow:0 0 .5cm rgba(0,0,0,.5);}.chart-container{position:relative;height:300px;width:100%;}.chart-container-large{position:relative;height:250px;width:100%;}.report-section{display:none;}.report-section.active{display:block;}@media print{body *{visibility:hidden;}#report-container,#report-container *{visibility:visible;}#report-container{position:absolute;left:0;top:0;width:100%;}body{background:white!important;margin:0!important;padding:0!important;}.navbar,.page-header,#filter-card,#loader{display:none!important;}#a4-wrapper{background:white!important;padding:0!important;box-shadow:none!important;}.a4-page{width:100%!important;height:auto!important;min-height:0!important;margin:0!important;padding:1cm!important;box-shadow:none!important;border:none!important;}#report-header{display:block!important;page-break-after:avoid;}h2{font-size:16pt!important;}#report-filter-summary{font-size:9pt!important;}.col-lg-6{width:50%!important;float:right;}.report-section{page-break-inside:avoid;}.card{border:1px solid #ccc!important;}.card-header h5{font-size:11pt!important;}.chart-container,.chart-container-large{height:220px!important;}canvas{display:none!important;}.print-image{display:block!important;max-width:100%;height:auto;}@page{size:A4 portrait;margin:1.5cm;}}</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/patternomaly@1.3.2/dist/patternomaly.min.js"></script>
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
        // Handle individual report sections
        $('.report-section').each(function() {
            const sectionId = $(this).attr('id');
            const checkboxId = sectionId ? sectionId.replace('section_', 'show_') : null;
            if (checkboxId && $('#' + checkboxId).length) {
                $(this).toggleClass('active', $('#' + checkboxId).is(':checked'));
            }
        });

        // Handle grouped sections for charts
        $('#section_by_part_family').toggleClass('active', $('#show_prod_by_part').is(':checked') || $('#show_prod_by_family').is(':checked'));
        $('#section_by_machine_mold').toggleClass('active', $('#show_prod_by_machine').is(':checked') || $('#show_prod_by_mold').is(':checked'));
    }

    $('#filter-form').on('submit', function(e) { e.preventDefault(); $('#report-container').show(); $('#report-content').hide(); $('#loader').show(); destroyCharts();
        let summaryParts = [];
        if ($('#start_date').val() && $('#end_date').val()) summaryParts.push(`بازه: ${$('#start_date').val()} تا ${$('#end_date').val()}`);
        if ($('#machine_id').val()) summaryParts.push(`دستگاه: ${$('#machine_id option:selected').text()}`);
        $('#report-main-title').text('گزارش تحلیلی عملکرد و تلفات تولید'); $('#report-filter-summary').text(summaryParts.join(' | ')); $('#report-header').show();
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_production_analytics.php', $(this).serialize())
            .done(function(response) { if (response.success) { renderReports(response.data); updateSectionVisibility(); $('#report-content').show(); } else { alert('خطا: ' + (response.message || 'خطای نامشخص')); } })
            .fail(function() { alert('خطا در ارتباط با سرور.'); }).always(function() { $('#loader').hide(); });
    });

    $('#print-btn').on('click', function() {
        updateSectionVisibility();
        $('.chart-container, .chart-container-large').each(function() {
            if ($(this).closest('.report-section').hasClass('active')) {
                const canvas = $(this).find('canvas')[0];
                if (canvas && charts[canvas.id]) { const imageUrl = charts[canvas.id].toBase64Image('image/png', 1.0); $(this).append($('<img>', { class: 'print-image', src: imageUrl })); }
            }
        });
        setTimeout(() => window.print(), 300);
    });
    window.onafterprint = () => { $('.print-image').remove(); };

    const chartBaseOptions = { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { labels: { font: { size: 10 } } } } };
    const barColors = ['#36A2EB', '#FF6384', '#4BC0C0', '#FFCE56', '#9966FF', '#FF9F40'];
    const patterns = ['diagonal-right-left', 'cross', 'dash', 'dot', 'square', 'plus'];

    function renderReports(data) {
        const lossAnalysisData = data.loss_analysis || [];

        // --- Loss Analysis Reports ---
        if ($('#show_loss_breakdown').is(':checked') && lossAnalysisData.length > 0) {
            charts.lossBreakdownChart = new Chart($('#lossBreakdownChart'), {
                type: 'bar',
                data: {
                    labels: lossAnalysisData.map(d => d.machine_name),
                    datasets: [
                        { label: 'تولید واقعی', data: lossAnalysisData.map(d => d.actual_pieces), backgroundColor: 'rgba(75, 192, 192, 0.7)' },
                        { label: 'تلفات عملکرد (پنهان)', data: lossAnalysisData.map(d => d.hidden_loss_pieces), backgroundColor: pattern.draw('diagonal', 'rgba(255, 206, 86, 0.7)') }, // Using loss_oper_pieces/hidden_loss_pieces
                        { label: 'تلفات توقفات', data: lossAnalysisData.map(d => d.loss_avail_pieces), backgroundColor: pattern.draw('cross', 'rgba(255, 99, 132, 0.7)') }
                    ]
                },
                options: {...chartBaseOptions, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { callback: value => value.toLocaleString() } } } }
            });
        }
        if ($('#show_availability_details').is(':checked') && lossAnalysisData.length > 0) {
            const body = $('#availability-details-body').empty();
            lossAnalysisData.forEach(i => { body.append(`<tr><td>${i.machine_name}</td><td>${i.total_available_time.toLocaleString()}</td><td>${i.theoretical_full_pieces.toLocaleString()}</td><td class="text-danger">${i.downtime_estimated.toLocaleString()}</td><td class="text-danger">${i.loss_avail_pieces.toLocaleString()}</td></tr>`); });
        }
        if ($('#show_performance_details').is(':checked') && lossAnalysisData.length > 0) {
            const body = $('#performance-details-body').empty();
            lossAnalysisData.forEach(i => { body.append(`<tr><td>${i.machine_name}</td><td>${i.total_available_time.toLocaleString()}</td><td>${i.theoretical_full_pieces.toLocaleString()}</td><td class="text-primary">${i.actual_pieces.toLocaleString()}</td><td class="text-danger">${i.loss_total_performance.toLocaleString()}</td><td><b>${i.performance_percent}%</b></td></tr>`); });
        }
        if ($('#show_loss_comparison_table').is(':checked') && lossAnalysisData.length > 0) {
            const body = $('#loss-comparison-body').empty();
            lossAnalysisData.forEach(i => { body.append(`<tr><td>${i.machine_name}</td><td class="text-danger">${i.loss_total_performance.toLocaleString()}</td><td>${i.loss_avail_pieces.toLocaleString()}</td><td class="text-warning">${i.hidden_loss_pieces.toLocaleString()}</td></tr>`); });
        }

        // --- Standard Production Reports ---
        // *** USE LogDateJalali from API ***
        if ($('#show_prod_trend').is(':checked') && data.production_trend) { charts.prodTrendChart = new Chart($('#prodTrendChart'), { type: 'line', data: { labels: data.production_trend.map(d => d.LogDateJalali), datasets: [{ label: 'مجموع تولید (KG)', data: data.production_trend.map(d => d.TotalKG), borderColor: '#36A2EB', fill: true }] }, options: {...chartBaseOptions, scales: {y: {beginAtZero: true}} } }); }
        if ($('#show_prod_by_part').is(':checked') && data.by_part) { charts.prodByPartChart = new Chart($('#prodByPartChart'), { type: 'doughnut', data: { labels: data.by_part.slice(0, 7).map(d => d.PartName), datasets: [{ data: data.by_part.slice(0, 7).map(d => d.TotalKG), backgroundColor: barColors.map((c, i) => pattern.draw(patterns[i % patterns.length], c)) }] }, options: {...chartBaseOptions, plugins: { legend: { position: 'bottom' } } } }); }
        if ($('#show_prod_by_family').is(':checked') && data.by_family) { charts.prodByFamilyChart = new Chart($('#prodByFamilyChart'), { type: 'pie', data: { labels: data.by_family.map(d => d.FamilyName), datasets: [{ data: data.by_family.map(d => d.TotalKG), backgroundColor: barColors.map((c, i) => pattern.draw(patterns[i % patterns.length], c)) }] }, options: {...chartBaseOptions, plugins: { legend: { position: 'bottom' } } } }); }
        if ($('#show_prod_by_machine').is(':checked') && data.by_machine) { charts.prodByMachineChart = new Chart($('#prodByMachineChart'), { type: 'bar', data: { labels: data.by_machine.map(d => d.MachineName), datasets: [{ label: 'تولید (KG)', data: data.by_machine.map(d => d.TotalKG), backgroundColor: '#4BC0C0' }] }, options: {...chartBaseOptions, scales: {y: {beginAtZero: true}} } }); }
        if ($('#show_prod_by_mold').is(':checked') && data.by_mold) { charts.prodByMoldChart = new Chart($('#prodByMoldChart'), { type: 'bar', data: { labels: data.by_mold.map(d => d.MoldName), datasets: [{ label: 'تولید (KG)', data: data.by_mold.map(d => d.TotalKG), backgroundColor: '#FF9F40' }] }, options: {...chartBaseOptions, scales: {y: {beginAtZero: true}} } }); }
        // *** USE LogDateJalali from API ***
        if ($('#show_productivity_trend').is(':checked') && data.productivity_trend) { charts.productivityTrendChart = new Chart($('#productivityTrendChart'), { type: 'line', data: { labels: data.productivity_trend.map(d => d.LogDateJalali), datasets: [{ label: 'بهره وری (KG/N-Hr)', data: data.productivity_trend.map(d => parseFloat(d.Productivity).toFixed(2)), borderColor: '#9966FF', fill: false, tension: 0.1 }] }, options: {...chartBaseOptions, scales: {y: {beginAtZero: true}} } }); }
    }
});
</script>
