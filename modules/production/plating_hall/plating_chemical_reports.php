<?php
require_once __DIR__ . '/../../../config/init.php';

// Assuming view permission is enough for reports
if (!has_permission('production.plating_hall.view')) { 
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// Fetch filter data
// Fetch only Plating Additives for the chemical dropdown
$additive_type_id_for_filter = $pdo->query("SELECT ChemicalTypeID FROM tbl_chemical_types WHERE TypeName = 'افزودنی های وان آبکاری'")->fetchColumn();
$chemicals = find_all($pdo, "SELECT ChemicalID, ChemicalName FROM tbl_chemicals WHERE ChemicalTypeID = ? ORDER BY ChemicalName", [$additive_type_id_for_filter ?: 0]);
$vats = find_all($pdo, "SELECT VatID, VatName FROM tbl_plating_vats WHERE IsActive = 1 ORDER BY VatName");

$pageTitle = "داشبورد تحلیلی مواد شیمیایی آبکاری";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">داشبورد تحلیلی مواد شیمیایی آبکاری</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header"><h5 class="mb-0">فیلتر گزارش</h5></div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">از تاریخ</label><input type="text" id="start_date" name="start_date" class="form-control persian-date"></div>
                <div class="col-md-3 mb-3"><label class="form-label">تا تاریخ</label><input type="text" id="end_date" name="end_date" class="form-control persian-date"></div>
                <div class="col-md-3 mb-3">
                    <label for="chemical_id" class="form-label">ماده خاص (فقط افزودنی‌ها)</label>
                    <select id="chemical_id" name="chemical_id" class="form-select">
                        <option value="">همه</option>
                        <?php foreach($chemicals as $chem): ?>
                            <option value="<?php echo $chem['ChemicalID']; ?>"><?php echo htmlspecialchars($chem['ChemicalName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                     <label for="vat_id" class="form-label">وان خاص</label>
                     <select id="vat_id" name="vat_id" class="form-select">
                         <option value="">همه</option>
                          <?php foreach($vats as $vat): ?>
                            <option value="<?php echo $vat['VatID']; ?>"><?php echo htmlspecialchars($vat['VatName']); ?></option>
                        <?php endforeach; ?>
                     </select>
                 </div>
            </div>
            
            <hr>
            <label class="form-label fw-bold">انتخاب گزارش‌ها:</label>
            <div class="row">
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_total_consumption" checked data-target="section_total_consumption"><label class="form-check-label" for="show_total_consumption">نمودار مجموع مصرف مواد</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_consumption_per_vat" checked data-target="section_consumption_per_vat"><label class="form-check-label" for="show_consumption_per_vat">جدول مصرف به تفکیک وان</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input report-selector" type="checkbox" id="show_consumption_rates" data-target="section_consumption_rates" disabled><label class="form-check-label" for="show_consumption_rates">کارت و نمودار نرخ مصرف</label></div><small class="text-muted d-block">(فقط با انتخاب ماده خاص)</small></div>
                
                <div class="col-12 mt-2"><button type="button" class="btn btn-sm btn-outline-primary" id="select-all">انتخاب همه فعال‌ها</button><button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="deselect-all">حذف همه</button></div>
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
            
            <div class="card content-card mb-3 report-section" id="section_total_consumption" data-checkbox-id="show_total_consumption"><div class="card-header"><h5>مجموع مصرف مواد شیمیایی</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="totalConsumptionChart"></canvas></div></div></div>
            <div class="card content-card mb-3 report-section" id="section_consumption_per_vat" data-checkbox-id="show_consumption_per_vat"><div class="card-header"><h5>مصرف مواد به تفکیک وان</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered text-center"><thead><tr class="table-light"><th>وان</th><th>ماده شیمیایی</th><th>مقدار مصرف</th><th>واحد</th></tr></thead><tbody id="consumption-per-vat-body"></tbody></table></div></div></div>
            
            <div class="report-section" id="section_consumption_rates" data-checkbox-id="show_consumption_rates">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card content-card h-100">
                             <div class="card-header"><h5>نرخ مصرف <span id="rate-chemical-name"></span></h5></div>
                             <div class="card-body">
                                 <p><strong>مجموع مصرف:</strong> <span id="rate-total-added"></span> <span id="rate-unit"></span></p>
                                 <p><strong>مجموع بارل:</strong> <span id="rate-total-barrels"></span></p>
                                 <p><strong>مجموع کیلوگرم آبکاری:</strong> <span id="rate-total-platedkg"></span> KG</p>
                                 <hr>
                                 <p><strong>نرخ مصرف بر بارل:</strong> <span id="rate-per-barrel" class="fw-bold"></span> <span class="rate-unit-label"></span>/Barrel</p>
                                 <p><strong>نرخ مصرف بر کیلوگرم:</strong> <span id="rate-per-kg" class="fw-bold"></span> <span class="rate-unit-label"></span>/KG</p>
                             </div>
                        </div>
                    </div>
                     <div class="col-md-6">
                         <div class="card content-card h-100">
                             <div class="card-header"><h5>روند نرخ مصرف بر بارل (<span class="rate-unit-label"></span>/Barrel)</h5></div>
                              <div class="card-body"><div class="chart-container-small"><canvas id="ratePerBarrelTrendChart"></canvas></div></div>
                         </div>
                    </div>
                </div>
                 <div class="card content-card mb-3">
                    <div class="card-header"><h5>روند نرخ مصرف بر کیلوگرم (<span class="rate-unit-label"></span>/KG)</h5></div>
                     <div class="card-body"><div class="chart-container-small"><canvas id="ratePerKgTrendChart"></canvas></div></div>
                </div>
            </div>

        </div>
    </div>
</div>
<style>#a4-wrapper{background:#525659;padding:30px 0;}.a4-page{background:white;width:21cm;min-height:29.7cm;display:block;margin:0 auto;padding:1.5cm;box-shadow:0 0 .5cm rgba(0,0,0,.5);}.chart-container{position:relative;height:300px;width:100%;}.chart-container-large{position:relative;height:250px;width:100%;}.chart-container-small{position:relative;height:200px;width:100%;}.report-section{display:none;}.report-section.active{display:block;}@media print{body *{visibility:hidden;}#report-container,#report-container *{visibility:visible;}#report-container{position:absolute;left:0;top:0;width:100%;}body{background:white!important;margin:0!important;padding:0!important;}.navbar,.page-header,#filter-card,#loader{display:none!important;}#a4-wrapper{background:white!important;padding:0!important;box-shadow:none!important;}.a4-page{width:100%!important;height:auto!important;min-height:0!important;margin:0!important;padding:1cm!important;box-shadow:none!important;border:none!important;}#report-header{display:block!important;page-break-after:avoid;}h2{font-size:16pt!important;}#report-filter-summary{font-size:9pt!important;}.col-lg-4,.col-lg-6,.col-lg-8, .col-md-6{float:right;}.col-lg-4{width:33.333%!important;}.col-lg-6, .col-md-6{width:50%!important;}.col-lg-8{width:66.666%!important;}.report-section{page-break-inside:avoid;}.card{border:1px solid #ccc!important;}.card-header h5{font-size:11pt!important;}.chart-container,.chart-container-large, .chart-container-small{height:220px!important;}canvas{display:none!important;}.print-image{display:block!important;max-width:100%;height:auto;}@page{size:A4 portrait;margin:1.5cm;}}</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/patternomaly@1.3.2/dist/patternomaly.min.js"></script>
<script>
$(document).ready(function() {
    Chart.defaults.font.family = "'Vazirmatn', sans-serif";
    Chart.defaults.font.size = 10;
    let charts = {};

    $('#select-all').on('click', function() { $('.report-selector:not(:disabled)').prop('checked', true); });
    $('#deselect-all').on('click', function() { $('.report-selector').prop('checked', false); });

    function destroyCharts() {
        Object.values(charts).forEach(chart => chart.destroy());
        charts = {};
        $('.print-image').remove();
    }

    function updateSectionVisibility() {
        $('.report-section').each(function() {
            $(this).toggleClass('active', $('#' + $(this).data('checkbox-id')).is(':checked'));
        });
    }

    // --- Enable/Disable specific report checkboxes ---
     $('#chemical_id').on('change', function() {
        $('#show_consumption_rates').prop('disabled', !$(this).val());
        if (!$(this).val()) { $('#show_consumption_rates').prop('checked', false); }
    }).trigger('change'); // Initial check

    // Chemical Type Filter Removed - Chemical list loads directly

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        if ($('.report-selector:checked').length === 0) {
            alert('لطفاً حداقل یک گزارش را برای نمایش انتخاب کنید.'); return;
        }
        $('#report-container').show(); $('#report-content').hide(); $('#loader').show(); destroyCharts();

        let summaryParts = [];
        if ($('#start_date').val() && $('#end_date').val()) summaryParts.push(`بازه: ${$('#start_date').val()} تا ${$('#end_date').val()}`);
        if ($('#chemical_id').val()) summaryParts.push(`ماده: ${$('#chemical_id option:selected').text()}`);
        if ($('#vat_id').val()) summaryParts.push(`وان: ${$('#vat_id option:selected').text()}`);

        $('#report-main-title').text('گزارش تحلیلی مصرف مواد شیمیایی');
        $('#report-filter-summary').text(summaryParts.join(' | '));
        $('#report-header').show();

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_plating_chemical_analytics.php', $(this).serialize())
            .done(function(response) {
                if (response.success) {
                    renderReports(response.data);
                    updateSectionVisibility();
                    $('#report-content').show();
                } else { alert('خطا: ' + (response.message || 'خطای نامشخص')); }
            })
            .fail(function() { alert('خطا در ارتباط با سرور.'); })
            .always(function() { $('#loader').hide(); });
    });

    $('#print-btn').on('click', function() {
        updateSectionVisibility();
        $('.chart-container, .chart-container-large, .chart-container-small').each(function() { // Include small charts
            if ($(this).closest('.report-section').hasClass('active')) {
                const canvas = $(this).find('canvas')[0];
                if (canvas && charts[canvas.id]) { const imageUrl = charts[canvas.id].toBase64Image('image/png', 1.0); $(this).append($('<img>', { class: 'print-image', src: imageUrl })); }
            }
        });
        setTimeout(() => window.print(), 300);
     });
    window.onafterprint = () => { $('.print-image').remove(); };

    const chartBaseOptions = { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { labels: { font: { size: 10 } } } } };
    const lineChartOptions = {...chartBaseOptions, scales: {y: {beginAtZero: true}}, elements: {line: {tension: 0.1}} };
    const barColors = ['#36A2EB', '#FF6384', '#4BC0C0', '#FFCE56', '#9966FF', '#FF9F40', '#E7E9ED', '#8A2BE2'];
    const patterns = ['diagonal-right-left', 'cross', 'dash', 'dot', 'square', 'plus', 'line', 'zigzag'];


    function renderReports(data) {
        // 1. Total Consumption Chart
        if ($('#show_total_consumption').is(':checked') && data.total_consumption && data.total_consumption.length > 0) {
            const groupedData = data.total_consumption.reduce((acc, item) => { /* ... same grouping ... */ 
                const unit = item.Unit || 'N/A';
                if (!acc[unit]) acc[unit] = { labels: [], values: [] };
                acc[unit].labels.push(item.ChemicalName);
                acc[unit].values.push(item.TotalQuantity);
                return acc;
            }, {});
            const firstUnit = Object.keys(groupedData)[0];
            if (firstUnit) {
                 charts.totalConsumptionChart = new Chart($('#totalConsumptionChart'), {
                    type: 'bar',
                    data: {
                        labels: groupedData[firstUnit].labels,
                        datasets: [{ label: `مجموع مصرف (${firstUnit})`, data: groupedData[firstUnit].values, backgroundColor: barColors[0] }]
                    },
                    options: {...chartBaseOptions, plugins:{legend:{display:false}}, scales: {y: {beginAtZero: true}} }
                });
            }
        } else if ($('#show_total_consumption').is(':checked')) {
             if(charts.totalConsumptionChart) charts.totalConsumptionChart.destroy();
             $('#totalConsumptionChart').parent().html('<canvas id="totalConsumptionChart"></canvas><p class="text-muted text-center small mt-3">داده‌ای برای نمودار مصرف کل یافت نشد.</p>');
        }

        // 2. Consumption per Vat Table
        if ($('#show_consumption_per_vat').is(':checked') && data.consumption_per_vat && data.consumption_per_vat.length > 0) {
            const body = $('#consumption-per-vat-body').empty();
            data.consumption_per_vat.forEach(item => { body.append(`<tr><td>${item.VatName || 'نامشخص'}</td><td>${item.ChemicalName}</td><td>${parseFloat(item.TotalQuantity).toFixed(3)}</td><td>${item.Unit}</td></tr>`); });
        } else if ($('#show_consumption_per_vat').is(':checked')) {
             $('#consumption-per-vat-body').empty().append('<tr><td colspan="4" class="text-muted text-center">داده‌ای برای نمایش یافت نشد.</td></tr>');
        }

        // 3. Consumption Rates
        if ($('#show_consumption_rates').is(':checked') && data.consumption_rates) {
             const rates = data.consumption_rates;
             $('#rate-chemical-name').text(rates.chemical_name || '');
             $('#rate-total-added').text(parseFloat(rates.total_added || 0).toFixed(3));
             $('#rate-unit, .rate-unit-label').text(rates.unit || '');
             $('#rate-total-barrels').text((rates.total_barrels || 0).toLocaleString());
             $('#rate-total-platedkg').text(parseFloat(rates.total_plated_kg || 0).toFixed(1));
             $('#rate-per-barrel').text(rates.rate_per_barrel || 0);
             $('#rate-per-kg').text(rates.rate_per_kg || 0);

             // Rate Trends
             const ratesTrendData = data.consumption_rates_trend || [];
             // Clear previous charts first
             if(charts.ratePerBarrelTrendChart) charts.ratePerBarrelTrendChart.destroy();
             if(charts.ratePerKgTrendChart) charts.ratePerKgTrendChart.destroy();
             // Ensure canvas elements exist
             $('#ratePerBarrelTrendChart').parent().html('<canvas id="ratePerBarrelTrendChart"></canvas>');
             $('#ratePerKgTrendChart').parent().html('<canvas id="ratePerKgTrendChart"></canvas>');

             if(ratesTrendData.length > 0) {
                 const labels = ratesTrendData.map(d => new persianDate(new Date(d.LogDate)).format('YYYY/MM/DD'));
                 charts.ratePerBarrelTrendChart = new Chart($('#ratePerBarrelTrendChart'), { type: 'line', data: { labels: labels, datasets: [{ label: `نرخ مصرف بر بارل (${rates.unit || ''}/Barrel)`, data: ratesTrendData.map(d => d.RatePerBarrel), borderColor: '#4BC0C0', fill: false }] }, options: lineChartOptions });
                 charts.ratePerKgTrendChart = new Chart($('#ratePerKgTrendChart'), { type: 'line', data: { labels: labels, datasets: [{ label: `نرخ مصرف بر کیلوگرم (${rates.unit || ''}/KG)`, data: ratesTrendData.map(d => d.RatePerKG), borderColor: '#FF9F40', fill: false }] }, options: lineChartOptions });
             } else {
                 $('#ratePerBarrelTrendChart').parent().append('<p class="text-muted text-center small mt-3">داده‌ای برای نمودار روند یافت نشد.</p>');
                 $('#ratePerKgTrendChart').parent().append('<p class="text-muted text-center small mt-3">داده‌ای برای نمودار روند یافت نشد.</p>');
             }
        }
    }
});
</script>

