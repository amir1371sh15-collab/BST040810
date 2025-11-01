<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.plating_hall.view')) { // Assuming view permission is enough for reports
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// Fetch filter data
$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
// No need to fetch all parts here anymore, will be fetched dynamically

$pageTitle = "داشبورد تحلیلی آبکاری";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">داشبورد تحلیلی آبکاری</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card" id="filter-card">
    <div class="card-header"><h5 class="mb-0">فیلتر گزارش</h5></div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">از تاریخ</label><input type="text" id="start_date" name="start_date" class="form-control persian-date"></div>
                <div class="col-md-3 mb-3"><label class="form-label">تا تاریخ</label><input type="text" id="end_date" name="end_date" class="form-control persian-date"></div>
                <div class="col-md-3 mb-3"><label class="form-label">خانواده قطعه</label><select id="part_family_id" name="part_family_id" class="form-select"><option value="">همه</option><?php foreach($part_families as $family): ?><option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-3"><label class="form-label">قطعه خاص</label><select id="part_id" name="part_id" class="form-select" disabled><option value="">همه (ابتدا خانواده را انتخاب کنید)</option></select></div>
            </div>
            <div class="row">
                 <div class="col-md-3 mb-3">
                    <label for="wash_factor" class="form-label">ضریب شستشو</label>
                    <input type="number" step="0.01" id="wash_factor" name="wash_factor" class="form-control" value="0.57">
                 </div>
                 <div class="col-md-3 mb-3">
                     <label for="rework_factor" class="form-label">ضریب دوباره‌کاری</label>
                     <input type="number" step="0.01" id="rework_factor" name="rework_factor" class="form-control" value="0.29">
                 </div>
            </div>

            <hr>
            <label class="form-label fw-bold">انتخاب گزارش‌ها:</label>
            <div class="row">
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_total_trend" checked><label class="form-check-label" for="show_total_trend">روند مجموع تولید (معادل KG)</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_process_breakdown" checked><label class="form-check-label" for="show_process_breakdown">نمودار تفکیک مراحل (واقعی KG)</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_by_part"><label class="form-check-label" for="show_by_part">تولید بر اساس محصول (معادل KG)</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_by_family"><label class="form-check-label" for="show_by_family">تولید بر اساس خانواده (معادل KG)</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_barrel_trend" checked><label class="form-check-label" for="show_barrel_trend">روند تعداد بارل</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_avg_kg_barrel_trend" checked><label class="form-check-label" for="show_avg_kg_barrel_trend">روند میانگین آبکاری KG/Barrel</label></div></div>
                <div class="col-md-4 mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="show_productivity_trend" checked><label class="form-check-label" for="show_productivity_trend">روند بهره‌وری (معادل KG/Hr)</label></div></div>
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
            <div id="report-header" class="mb-4 text-center" style="display:none;"><h2 id="report-main-title"></h2><p id="report-filter-summary" class="text-muted"></p><p id="report-factors-summary" class="text-muted small"></p></div>

            <div class="card content-card mb-3 report-section" id="section_total_trend"><div class="card-header"><h5>روند مجموع تولید روزانه (معادل آبکاری KG)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="totalTrendChart"></canvas></div></div></div>
            <div class="card content-card mb-3 report-section" id="section_process_breakdown"><div class="card-header"><h5>تفکیک مراحل تولید روزانه (واقعی KG)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="processBreakdownChart"></canvas></div></div></div>
            <div class="row report-section" id="section_by_part_family">
                <div class="col-lg-6 mb-3" id="section_by_part"><div class="card content-card h-100"><div class="card-header"><h5>تولید بر اساس محصول (معادل KG) - Top 7</h5></div><div class="card-body"><div class="chart-container"><canvas id="byPartChart"></canvas></div></div></div></div>
                <div class="col-lg-6 mb-3" id="section_by_family"><div class="card content-card h-100"><div class="card-header"><h5>تولید بر اساس خانواده (معادل KG)</h5></div><div class="card-body"><div class="chart-container"><canvas id="byFamilyChart"></canvas></div></div></div></div>
            </div>
            {/* Removed the row wrapper */}
            <div class="card content-card mb-3 report-section" id="section_barrel_trend"><div class="card-header"><h5>روند تعداد بارل روزانه</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="barrelTrendChart"></canvas></div></div></div>
            <div class="card content-card mb-3 report-section" id="section_avg_kg_barrel_trend"><div class="card-header"><h5>روند میانگین وزن آبکاری هر بارل (KG/Barrel)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="avgKgBarrelTrendChart"></canvas></div></div></div>

            <div class="card content-card mb-3 report-section" id="section_productivity_trend"><div class="card-header"><h5>روند بهره‌وری (کیلوگرم معادل بر ساعت کاری)</h5></div><div class="card-body"><div class="chart-container-large"><canvas id="productivityTrendChart"></canvas></div></div></div>
        </div>
    </div>
</div>
<style>#a4-wrapper{background:#525659;padding:30px 0;}.a4-page{background:white;width:21cm;min-height:29.7cm;display:block;margin:0 auto;padding:1.5cm;box-shadow:0 0 .5cm rgba(0,0,0,.5);}.chart-container{position:relative;height:300px;width:100%;}.chart-container-large{position:relative;height:250px;width:100%;}.report-section{display:none;}.report-section.active{display:block;}@media print{body *{visibility:hidden;}#report-container,#report-container *{visibility:visible;}#report-container{position:absolute;left:0;top:0;width:100%;}body{background:white!important;margin:0!important;padding:0!important;}.navbar,.page-header,#filter-card,#loader{display:none!important;}#a4-wrapper{background:white!important;padding:0!important;box-shadow:none!important;}.a4-page{width:100%!important;height:auto!important;min-height:0!important;margin:0!important;padding:1cm!important;box-shadow:none!important;border:none!important;}#report-header{display:block!important;page-break-after:avoid;}h2{font-size:16pt!important;}#report-filter-summary, #report-factors-summary{font-size:9pt!important;}.col-lg-4,.col-lg-6,.col-lg-8{float:right;}.col-lg-4{width:33.333%!important;}.col-lg-6{width:50%!important;}.col-lg-8{width:66.666%!important;}.report-section{page-break-inside:avoid;}.card{border:1px solid #ccc!important;}.card-header h5{font-size:11pt!important;}.chart-container,.chart-container-large{height:220px!important;}canvas{display:none!important;}.print-image{display:block!important;max-width:100%;height:auto;}@page{size:A4 portrait;margin:1.5cm;}}</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<!-- Chart.js Patternomaly Plugin -->
<script src="https://cdn.jsdelivr.net/npm/patternomaly@1.3.2/dist/patternomaly.min.js"></script>
<!-- Note: chartjs-plugin-datalabels is now included in footer.php -->

<script>
$(document).ready(function() {
    // --- Initialize Chart.js Defaults and Plugins ---
    Chart.defaults.font.family = "'Vazirmatn', sans-serif";
    Chart.defaults.font.size = 10;

    // Check if ChartDataLabels plugin is loaded before registering
    if (typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);
        // Set global defaults for the plugin *after* registration
        Chart.defaults.set('plugins.datalabels', {
            display: false // Disable labels globally by default
        });
    } else {
        console.error("ChartDataLabels plugin not loaded. Ensure it's included in footer.php AFTER Chart.js.");
    }

    let charts = {};

    // --- UI Element Event Handlers ---
    $('#select-all').on('click', function() { $('input[type="checkbox"][id^="show_"]').prop('checked', true); });
    $('#deselect-all').on('click', function() { $('input[type="checkbox"][id^="show_"]').prop('checked', false); });

    function destroyCharts() {
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        charts = {};
        $('.print-image').remove();
    }

    function updateSectionVisibility() {
        $('.report-section').each(function() {
            const sectionId = $(this).attr('id');
            const checkboxId = sectionId ? sectionId.replace('section_', 'show_') : null;
            if (checkboxId && $('#' + checkboxId).length) {
                $(this).toggleClass('active', $('#' + checkboxId).is(':checked'));
            }
        });
        $('#section_by_part_family').toggleClass('active', $('#show_by_part').is(':checked') || $('#show_by_family').is(':checked'));
    }

    // --- Dependent Part Filter Logic ---
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
                    } else {
                         partSelect.html('<option value="">قطعه‌ای یافت نشد</option>');
                    }
                })
                .fail(function() {
                     partSelect.html('<option value="">خطا در بارگذاری</option>');
                });
        } else {
             partSelect.html('<option value="">همه (ابتدا خانواده را انتخاب کنید)</option>');
        }
     });

    // --- Form Submission Logic ---
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        $('#report-container').show();
        $('#report-content').hide();
        $('#loader').show();
        destroyCharts();

        // Update report header text
        let summaryParts = [];
        if ($('#start_date').val() && $('#end_date').val()) summaryParts.push(`بازه: ${$('#start_date').val()} تا ${$('#end_date').val()}`);
        if ($('#part_family_id').val()) summaryParts.push(`خانواده: ${$('#part_family_id option:selected').text()}`);
        if ($('#part_id').val()) summaryParts.push(`قطعه: ${$('#part_id option:selected').text()}`);
        $('#report-main-title').text('گزارش تحلیلی سالن آبکاری');
        $('#report-filter-summary').text(summaryParts.join(' | '));
        $('#report-factors-summary').text(`ضرایب معادل‌سازی: شستشو=${$('#wash_factor').val()}, دوباره‌کاری=${$('#rework_factor').val()}`);
        $('#report-header').show();

        // Fetch data and render reports
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_plating_analytics.php', $(this).serialize())
            .done(function(response) {
                if (response.success) {
                    renderReports(response.data);
                    updateSectionVisibility();
                    $('#report-content').show();
                } else {
                    alert('خطا: ' + (response.message || 'خطای نامشخص'));
                }
            })
            .fail(function() { alert('خطا در ارتباط با سرور.'); })
            .always(function() { $('#loader').hide(); });
    });

    // --- Print Logic ---
    $('#print-btn').on('click', function() {
        updateSectionVisibility();
        // Convert active charts to images for printing
        $('.chart-container, .chart-container-large').each(function() {
            const canvas = $(this).find('canvas')[0];
            // Check if the section is active and the chart exists
            if ($(this).closest('.report-section').hasClass('active') && canvas && charts[canvas.id]) {
                try {
                    const imageUrl = charts[canvas.id].toBase64Image('image/png', 1.0);
                    $(this).append($('<img>', { class: 'print-image', src: imageUrl, alt: 'Chart Image' }));
                } catch (e) {
                    console.error("Error converting chart to image:", e);
                }
            }
        });
        // Delay printing slightly to allow images to render
        setTimeout(() => window.print(), 300);
     });
     // Remove images after printing
    window.onafterprint = () => { $('.print-image').remove(); };

    // --- Chart Rendering Logic ---
    const chartBaseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
            legend: {
                labels: { font: { size: 10 } }
            },
            datalabels: { // Use the global default (false) unless overridden
                display: false
            }
        }
    };
    const barColors = ['#36A2EB', '#FF6384', '#4BC0C0', '#FFCE56', '#9966FF', '#FF9F40'];
    const patterns = ['diagonal-right-left', 'cross', 'dash', 'dot', 'square', 'plus'];

    // Function to calculate average
    function calculateAverage(dataArray) {
        if (!dataArray || dataArray.length === 0) return 0;
        const sum = dataArray.reduce((acc, val) => acc + parseFloat(val || 0), 0);
        return (sum / dataArray.length).toFixed(2);
    }

    // Configuration for the average line dataset
    const averageLineDatasetOptions = {
        label: 'میانگین',
        borderColor: 'rgba(255, 99, 132, 0.8)',
        borderWidth: 1,
        borderDash: [5, 5],
        pointRadius: 0,
        fill: false,
        tension: 0
    };

    // Configuration for displaying the average label using datalabels plugin
    const averageDatalabelConfig = {
        display: true,
        align: 'end',
        anchor: 'end',
        color: 'rgba(255, 99, 132, 1)',
        font: { weight: 'bold', size: 9 }, // Smaller font size
        formatter: (value, context) => {
            // Only show label for the last point of the 'میانگین' dataset
            if (context.dataset.label === 'میانگین' && context.dataIndex === context.chart.data.labels.length - 1) {
                // Return value directly, it's already formatted by calculateAverage
                return 'میانگین: ' + value;
            }
            return null; // Hide labels for other points/datasets
        },
        offset: 8, // Add some offset from the end of the line
        padding: 0 // No padding around the label text
    };

    function renderReports(data) {
        const trendBreakdownData = data.total_trend_breakdown || [];
        const productivityData = data.productivity_trend || [];
        const dailyAvgKgBarrel = data.daily_avg_kg_barrel || [];

        // 1. Total Production Trend (Equivalent KG)
        if ($('#show_total_trend').is(':checked') && trendBreakdownData.length > 0) {
            // *** USE LogDateJalali from API ***
            const labels = trendBreakdownData.map(d => d.LogDateJalali);
            const prodData = trendBreakdownData.map(d => parseFloat(d.EquivalentTotalKG).toFixed(1));
            const avgProd = calculateAverage(prodData);
            const avgProdData = Array(labels.length).fill(avgProd);

            charts.totalTrendChart = new Chart($('#totalTrendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'مجموع تولید (معادل KG)', data: prodData, borderColor: '#36A2EB', backgroundColor: 'rgba(54, 162, 235, 0.1)', fill: true, tension: 0.1 },
                        { ...averageLineDatasetOptions, data: avgProdData }
                    ]
                },
                options: {...chartBaseOptions, scales: {y: {beginAtZero: true}}, plugins: { datalabels: averageDatalabelConfig } }
            });
        }
        // 2. Process Breakdown Chart (Actual KG)
        if ($('#show_process_breakdown').is(':checked') && trendBreakdownData.length > 0) {
             // *** USE LogDateJalali from API, shortened ***
            const labels = trendBreakdownData.map(d => d.LogDateJalali ? d.LogDateJalali.substring(5) : ''); // Get MM/DD part
            charts.processBreakdownChart = new Chart($('#processBreakdownChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'شستشو', data: trendBreakdownData.map(d => d.TotalWashed), backgroundColor: pattern.draw(patterns[0], barColors[0]) },
                        { label: 'آبکاری', data: trendBreakdownData.map(d => d.TotalPlated), backgroundColor: pattern.draw(patterns[1], barColors[1]) },
                        { label: 'دوباره کاری', data: trendBreakdownData.map(d => d.TotalReworked), backgroundColor: pattern.draw(patterns[2], barColors[2]) }
                    ]
                },
                options: {...chartBaseOptions, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } } // Datalabels remain globally disabled
            });
        }
        // 3. By Part Chart (Equivalent KG) - No change needed
        if ($('#show_by_part').is(':checked') && data.by_part && data.by_part.length > 0) {
            charts.byPartChart = new Chart($('#byPartChart'), { type: 'doughnut', data: { labels: data.by_part.slice(0, 7).map(d => d.PartName), datasets: [{ data: data.by_part.slice(0, 7).map(d => parseFloat(d.EquivalentTotalKG).toFixed(1)), backgroundColor: barColors.map((c, i) => pattern.draw(patterns[i % patterns.length], c)) }] }, options: {...chartBaseOptions, plugins: { legend: { position: 'bottom' } } } });
        }
        // 4. By Family Chart (Equivalent KG) - No change needed
        if ($('#show_by_family').is(':checked') && data.by_family && data.by_family.length > 0) {
            charts.byFamilyChart = new Chart($('#byFamilyChart'), { type: 'pie', data: { labels: data.by_family.map(d => d.FamilyName), datasets: [{ data: data.by_family.map(d => parseFloat(d.EquivalentTotalKG).toFixed(1)), backgroundColor: barColors.map((c, i) => pattern.draw(patterns[i % patterns.length], c)) }] }, options: {...chartBaseOptions, plugins: { legend: { position: 'bottom' } } } });
        }
        // 5. Barrel Trend Chart (Line)
        if ($('#show_barrel_trend').is(':checked') && trendBreakdownData.length > 0) {
             // *** USE LogDateJalali from API ***
            const labels = trendBreakdownData.map(d => d.LogDateJalali);
            const barrelData = trendBreakdownData.map(d => d.TotalBarrels);
            const avgBarrels = calculateAverage(barrelData);
            const avgBarrelData = Array(labels.length).fill(avgBarrels);

            charts.barrelTrendChart = new Chart($('#barrelTrendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'تعداد بارل', data: barrelData, borderColor: '#FFCE56', fill: false, tension: 0.1 },
                        { ...averageLineDatasetOptions, data: avgBarrelData, borderColor: 'rgba(75, 192, 192, 0.8)' }
                    ]
                },
                options: {...chartBaseOptions, scales: {y: {beginAtZero: true}}, plugins: { datalabels: averageDatalabelConfig } }
            });
        }
        // 6. Average Plated KG per Barrel Trend Chart (Line)
        if ($('#show_avg_kg_barrel_trend').is(':checked') && dailyAvgKgBarrel.length > 0) {
             // *** USE LogDateJalali from API ***
             const labels = dailyAvgKgBarrel.map(d => d.LogDateJalali);
             const avgKgData = dailyAvgKgBarrel.map(d => d.AvgKGPerBarrel);
             const avgKgAvg = calculateAverage(avgKgData);
             const avgKgAvgData = Array(labels.length).fill(avgKgAvg);

            charts.avgKgBarrelTrendChart = new Chart($('#avgKgBarrelTrendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'میانگین آبکاری KG/Barrel', data: avgKgData, borderColor: '#4BC0C0', fill: false, tension: 0.1 },
                        { ...averageLineDatasetOptions, data: avgKgAvgData }
                    ]
                },
                options: {...chartBaseOptions, scales: {y: {beginAtZero: true}}, plugins: { datalabels: averageDatalabelConfig } }
            });
        }
        // 7. Productivity Trend Chart (Equivalent KG / Hr)
        if ($('#show_productivity_trend').is(':checked') && productivityData.length > 0) {
            // *** USE LogDateJalali from API ***
            const labels = productivityData.map(d => d.LogDateJalali);
            const prodData = productivityData.map(d => d.Productivity);
            const avgProd = calculateAverage(prodData);
            const avgProdData = Array(labels.length).fill(avgProd);

            charts.productivityTrendChart = new Chart($('#productivityTrendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'بهره وری (معادل KG/Hr)', data: prodData, borderColor: '#9966FF', fill: false, tension: 0.1 },
                         { ...averageLineDatasetOptions, data: avgProdData }
                    ]
                },
                options: {...chartBaseOptions, scales: {y: {beginAtZero: true}}, plugins: { datalabels: averageDatalabelConfig } }
            });
        }
    }

});
</script>
