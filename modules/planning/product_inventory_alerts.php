<?php
require_once __DIR__ . '/../../config/init.php';
// بررسی دسترسی
if (!has_permission('planning.view_alerts')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "داشبورد هشدار موجودی قطعات";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت
    </a>
</div>

<p class="lead">این داشبورد، قطعات و محصولاتی را نمایش می‌دهد که موجودی فعلی انبار آن‌ها (بر اساس آخرین تراکنش‌ها) به کمتر از "نقطه سفارش" تعریف شده رسیده است.</p>

<!-- Main container for alerts -->
<div id="alert-container">
    <div class="text-center" id="loader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">در حال بارگذاری هشدارها...</p>
    </div>
    <!-- Tables will be injected here by JavaScript -->
</div>

<style>
    .alert-table th {
        white-space: nowrap;
    }
    .shortfall-col {
        color: #dc3545; /* Bootstrap danger color */
        font-weight: bold;
    }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiAlertsUrl = '<?php echo BASE_URL; ?>api/api_get_product_inventory_alerts.php';
    const alertContainer = $('#alert-container');
    const loader = $('#loader');

    function formatNumber(num, decimals = 3) {
        if (isNaN(num)) return 'NaN';
        return num.toLocaleString('fa-IR', { 
            minimumFractionDigits: decimals, 
            maximumFractionDigits: decimals 
        });
    }
    
    function formatInt(num) {
         if (isNaN(num)) return 'NaN';
         return num.toLocaleString('fa-IR');
    }

    async function loadAlerts() {
        loader.show();
        alertContainer.find('.alert-table, .alert-message, h5').remove(); // Clear previous tables and messages

        try {
            const response = await $.getJSON(apiAlertsUrl);
            
            if (response.success && Array.isArray(response.data)) {
                const allAlerts = response.data;
                
                // *** FIX: Filter the single array into two separate arrays ***
                const kgAlerts = allAlerts.filter(a => a.Unit === 'KG');
                const cartonAlerts = allAlerts.filter(a => a.Unit === 'Carton');
                
                let html = '';

                // --- 1. Render KG Alerts ---
                if (kgAlerts.length > 0) {
                    html += '<h5><i class="bi bi-rulers text-warning"></i> هشدارهای وزنی (KG)</h5>';
                    html += `<table class="table table-sm table-bordered table-hover alert-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>قطعه</th>
                                        <th>ایستگاه/انبار</th>
                                        <th>وضعیت</th>
                                        <th>موجودی فعلی (KG)</th>
                                        <th>نقطه سفارش (KG)</th>
                                        <th class="text-danger">کسری موجودی (KG)</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                    kgAlerts.forEach(alert => {
                        const currentValue = parseFloat(alert.CurrentValue) || 0;
                        const safetyStock = parseFloat(alert.SafetyStockValue) || 0;
                        const shortfall = Math.max(0, safetyStock - currentValue);
                        // ایجاد لینک سفارش‌گذاری با پارامترهای لازم
                        const orderUrl = `sales_orders.php?prefill_part_id=${alert.PartID}&prefill_unit=KG&prefill_quantity=${shortfall.toFixed(3)}`;
                        
                        html += `<tr>
                                    <td>${alert.PartName}</td>
                                    <td>${alert.StationName}</td>
                                    <td><span class="badge bg-secondary">${alert.StatusName}</span></td>
                                    <td>${formatNumber(currentValue, 3)}</td>
                                    <td>${formatNumber(safetyStock, 3)}</td>
                                    <td class="shortfall-col">${formatNumber(shortfall, 3)}</td>
                                    <td class="text-center">
                                        <a href="${orderUrl}" class="btn btn-primary btn-sm py-0 px-1" title="ایجاد سفارش بر اساس کسری">
                                            <i class="bi bi-plus-circle-fill"></i> ایجاد سفارش
                                        </a>
                                    </td>
                                 </tr>`;
                    });
                    html += `</tbody></table>`;
                }

                // --- 2. Render Carton Alerts ---
                if (cartonAlerts.length > 0) {
                    html += '<h5 class="mt-4"><i class="bi bi-box-seam text-info"></i> هشدارهای تعدادی (کارتن)</h5>';
                    html += `<table class="table table-sm table-bordered table-hover alert-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>قطعه</th>
                                        <th>ایستگاه/انبار</th>
                                        <th>وضعیت</th>
                                        <th>موجودی فعلی (کارتن)</th>
                                        <th>نقطه سفارش (کارتن)</th>
                                        <th class="text-danger">کسری موجودی (کارتن)</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                    cartonAlerts.forEach(alert => {
                        const currentValue = parseInt(alert.CurrentValue) || 0;
                        const safetyStock = parseInt(alert.SafetyStockValue) || 0;
                        const shortfall = Math.max(0, safetyStock - currentValue);
                        const orderUrl = `sales_orders.php?prefill_part_id=${alert.PartID}&prefill_unit=Carton&prefill_quantity=${shortfall}`;

                        html += `<tr>
                                    <td>${alert.PartName}</td>
                                    <td>${alert.StationName}</td>
                                    <td><span class="badge bg-secondary">${alert.StatusName}</span></td>
                                    <td>${formatInt(currentValue)}</td>
                                    <td>${formatInt(safetyStock)}</td>
                                    <td class="shortfall-col">${formatInt(shortfall)}</td>
                                    <td class="text-center">
                                        <a href="${orderUrl}" class="btn btn-primary btn-sm py-0 px-1" title="ایجاد سفارش بر اساس کسری">
                                            <i class="bi bi-plus-circle-fill"></i> ایجاد سفارش
                                        </a>
                                    </td>
                                 </tr>`;
                    });
                    html += `</tbody></table>`;
                }
                
                // --- 3. Handle No Alerts Found ---
                if (kgAlerts.length === 0 && cartonAlerts.length === 0) {
                    html = '<div class="alert alert-success alert-message" role="alert"><i class="bi bi-check-circle-fill"></i> در حال حاضر هیچ موجودی به زیر نقطه سفارش نرسیده است.</div>';
                }

                loader.hide();
                alertContainer.append(html);

            } else if (response.success) { // Success but data array is empty
                loader.hide();
                alertContainer.append('<div class="alert alert-success alert-message" role="alert"><i class="bi bi-check-circle-fill"></i> در حال حاضر هیچ موجودی به زیر نقطه سفارش نرسیده است.</div>');
            } else {
                // Handle API error
                loader.hide();
                alertContainer.append(`<div class="alert alert-danger alert-message" role="alert"><strong>خطا:</strong> ${response.message || 'خطا در بارگذاری هشدارها.'}</div>`);
            }
        } catch (error) {
            // Handle AJAX/network error
            console.error("Failed to load alerts:", error);
            loader.hide();
            alertContainer.append('<div class="alert alert-danger alert-message" role="alert"><strong>خطا:</strong> عدم امکان برقراری ارتباط با سرور.</div>');
        }
    }

    // Initial load
    loadAlerts();
});
</script>

