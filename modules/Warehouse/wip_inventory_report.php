<?php
require_once __DIR__ . '/../../config/init.php';
// این گزارش برای مدیران انبار یا تولید است که مجوز انبارگردانی دارند


$pageTitle = "گزارش کنترل موجودی در جریان (WIP)";

// دریافت ایستگاه‌های تولیدی برای فیلتر
$production_stations = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations WHERE StationType = 'Production' ORDER BY StationName");

// تاریخ‌های پیش‌فرض (ماه جاری)
$default_start_date = to_jalali(date('Y-m-01'));
$default_end_date = to_jalali(date('Y-m-d'));

include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="card content-card mb-4">
    <div class="card-header">
        <h5 class="mb-0">فیلترهای گزارش</h5>
    </div>
    <div class="card-body">
        <form id="wip-report-form">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="from_date" class="form-label">از تاریخ</label>
                    <input type="text" id="from_date" name="from_date" class="form-control persian-date" value="<?php echo $default_start_date; ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="to_date" class="form-label">تا تاریخ</label>
                    <input type="text" id="to_date" name="to_date" class="form-control persian-date" value="<?php echo $default_end_date; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="station_id" class="form-label">ایستگاه (اختیاری)</label>
                    <select id="station_id" name="station_id" class="form-select">
                        <option value="">-- همه ایستگاه‌های تولیدی --</option>
                        <?php foreach ($production_stations as $station): ?>
                            <option value="<?php echo $station['StationID']; ?>"><?php echo htmlspecialchars($station['StationName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" id="run-report-btn" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> اجرای گزارش
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="report-results-container">
    <div id="report-spinner" class="text-center" style="display: none;">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">در حال بارگذاری...</span>
        </div>
        <p>در حال محاسبه موجودی... این عملیات ممکن است چند لحظه طول بکشد.</p>
    </div>
    <div id="report-results" class="mt-4">
        </div>
</div>


<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiWipUrl = '<?php echo BASE_URL; ?>api/api_get_wip_report.php';
    const stocktakeUrl = '<?php echo BASE_URL; ?>modules/Warehouse/inventory_stocktake.php';
    const form = $('#wip-report-form');
    const runBtn = $('#run-report-btn');
    const spinner = $('#report-spinner');
    const resultsContainer = $('#report-results');
    const stationOptions = <?php echo json_encode(array_column($production_stations, 'StationName', 'StationID')); ?>;

    // --- 1. Form Submission ---
    form.on('submit', function(e) {
        e.preventDefault();
        runBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ...');
        spinner.show();
        resultsContainer.empty();

        const formData = $(this).serialize();

        $.ajax({
            url: apiWipUrl,
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderReport(response.data);
                } else {
                    resultsContainer.html(`<div class="alert alert-danger">${response.message || 'خطا در اجرای گزارش.'}</div>`);
                }
            },
            error: function(xhr) {
                console.error(xhr);
                const errorMsg = xhr.responseJSON?.message || xhr.responseText || 'خطای ناشناخته سرور.';
                resultsContainer.html(`<div class="alert alert-danger"><strong>خطا:</strong> ${errorMsg}</div>`);
            },
            complete: function() {
                runBtn.prop('disabled', false).html('<i class="bi bi-search"></i> اجرای گزارش');
                spinner.hide();
            }
        });
    });

    // --- 2. Render Report ---
    function renderReport(stationsData) {
        if (Object.keys(stationsData).length === 0) {
            resultsContainer.html('<div class="alert alert-info">هیچ داده‌ای برای ایستگاه(های) انتخابی در این بازه زمانی یافت نشد.</div>');
            return;
        }

        let allHtml = '';

        for (const stationId in stationsData) {
            const station = stationsData[stationId];
            if (station.parts.length === 0) continue; // Skip stations with no data

            allHtml += `<div class="card content-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">ایستگاه: ${htmlspecialchars(station.station_name)} (واحد: ${station.unit})</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 wip-table" data-station-id="${stationId}" data-station-unit="${station.unit}">
                            <thead class="table-light">
                                <tr>
                                    <th>قطعه</th>
                                    <th>موجودی اولیه</th>
                                    <th>کل ورودی</th>
                                    <th>کل خروجی</th>
                                    <th class="table-primary">موجودی سیستمی</th>
                                    <th style="width: 130px;">موجودی فیزیکی</th>
                                    <th style="width: 130px;">مغایرت</th>
                                    <th style="width: 100px;">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            station.parts.forEach(part => {
                const format = (num) => (station.unit === 'KG') ? parseFloat(num).toFixed(3) : parseInt(num);
                
                const opening = format(part.Opening);
                const totalIn = format(part.In);
                const totalOut = format(part.Out);
                const systemBalance = format(part.System);
                
                const inTooltip = part.TooltipIn ? `data-bs-toggle="tooltip" title="${htmlspecialchars(part.TooltipIn)}"` : '';
                const outTooltip = part.TooltipOut ? `data-bs-toggle="tooltip" title="${htmlspecialchars(part.TooltipOut)}"` : '';

                allHtml += `<tr data-part-id="${part.PartID}" data-part-name="${htmlspecialchars(part.PartName)}" data-status-id="${part.StatusID}" data-family-id="${part.FamilyID}">
                                <td>${htmlspecialchars(part.PartName)}
                                    <span class="text-muted small"> (ID: ${part.PartID})</span>
                                </td>
                                <td>${opening}</td>
                                <td ${inTooltip}>${totalIn} ${part.TooltipIn ? '<i class="bi bi-info-circle-fill text-muted ms-1"></i>' : ''}</td>
                                <td ${outTooltip}>${totalOut} ${part.TooltipOut ? '<i class="bi bi-info-circle-fill text-muted ms-1"></i>' : ''}</td>
                                <td class="fw-bold table-primary system-balance">${systemBalance}</td>
                                <td>
                                    <input type="number" step="${(station.unit === 'KG') ? '0.01' : '1'}" class="form-control form-control-sm physical-count-input" placeholder="شمارش...">
                                </td>
                                <td class="fw-bold discrepancy-cell"></td>
                                <td>
                                    <button class="btn btn-info btn-sm adjust-btn" title="ثبت تعدیل" disabled>
                                        <i class="bi bi-arrow-right-square"></i> ثبت
                                    </button>
                                </td>
                            </tr>`;
            });

            allHtml += `</tbody></table></div></div></div>`;
        }

        resultsContainer.html(allHtml);
        // Activate tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    }

    // --- 3. Live Calculation ---
    $(document).on('input', '.physical-count-input', function() {
        const row = $(this).closest('tr');
        const table = $(this).closest('.wip-table');
        const unit = table.data('station-unit');
        
        const systemBalanceCell = row.find('.system-balance');
        const discrepancyCell = row.find('.discrepancy-cell');
        const adjustBtn = row.find('.adjust-btn');

        const systemBalance = parseFloat(systemBalanceCell.text());
        const physicalCount = parseFloat($(this).val());

        if (isNaN(systemBalance) || isNaN(physicalCount)) {
            discrepancyCell.text('');
            adjustBtn.prop('disabled', true);
            return;
        }

        const discrepancy = systemBalance - physicalCount;
        const discrepancyFormatted = (unit === 'KG') ? discrepancy.toFixed(3) : discrepancy.toFixed(0);

        discrepancyCell.text(discrepancyFormatted);
        
        if (discrepancy > 0) {
            discrepancyCell.removeClass('text-success').addClass('text-danger'); // System has more -> Need Deduction
        } else if (discrepancy < 0) {
            discrepancyCell.removeClass('text-danger').addClass('text-success'); // System has less -> Need Addition
        } else {
            discrepancyCell.removeClass('text-danger text-success');
        }

        // Enable button only if there is a non-zero discrepancy
        adjustBtn.prop('disabled', Math.abs(discrepancy) < 0.0001);
    });

    // --- 4. Adjust Button Click ---
    $(document).on('click', '.adjust-btn', function() {
        const row = $(this).closest('tr');
        const table = $(this).closest('.wip-table');
        
        const stationId = table.data('station-id');
        const partId = row.data('part-id');
        const familyId = row.data('family-id');
        const statusId = row.data('status-id'); // This is the required status *for* the station
        const discrepancy = parseFloat(row.find('.discrepancy-cell').text());

        if (isNaN(discrepancy) || discrepancy === 0) {
            alert('مقدار مغایرت نامعتبر است.');
            return;
        }

        // 'کسر انبارگردانی' (Deduction) or 'اضافه انبارگردانی' (Addition)
        // Note: These IDs (5, 6) are based on the DB dump.
        let transactionTypeId, transactionTypeName;
        if (discrepancy > 0) {
            // System > Physical. We need to *reduce* system stock.
            // This is a "Deduction" (کسر انبارگردانی)
            transactionTypeId = 5; 
            transactionTypeName = 'کسر'; // Pass simple text for query
        } else {
            // System < Physical. We need to *increase* system stock.
            // This is an "Addition" (اضافه انبارگردانی)
            transactionTypeId = 6;
            transactionTypeName = 'اضافه'; // Pass simple text for query
        }

        const quantity = Math.abs(discrepancy);

        // Construct the URL for inventory_stocktake.php
        // This will pre-fill the form on that page
        let url = new URL(stocktakeUrl, window.location.origin);
        url.searchParams.append('prefill', 'true');
        url.searchParams.append('family_id', familyId);
        url.searchParams.append('part_id', partId);
        url.searchParams.append('station_id', stationId);
        url.searchParams.append('status_id', statusId);
        url.searchParams.append('quantity', quantity);
        url.searchParams.append('transaction_type_name', transactionTypeName); // 'کسر' or 'اضافه'

        // Open in a new tab
        window.open(url.toString(), '_blank');
    });

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    }
});
</script>