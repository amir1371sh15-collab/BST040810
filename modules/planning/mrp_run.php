<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks
if (!has_permission('planning.mrp.run')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "اجرای MRP (فاز ۱: نیازمندی‌های خالص)";
include_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <div class="alert alert-info">
                <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>راهنمای MRP (فاز ۱)</h5>
                <p>
                    <b>فاز ۱: محاسبه نیازمندی‌های خالص.</b>
                    <br>
                    ۱. ابتدا داده‌های ورودی را بارگذاری کنید.
                    <br>
                    ۲. <b>فقط سفارشات مشتری (تقاضای خارجی)</b> مورد نظر خود را انتخاب کنید.
                    <br>
                    ۳. با فشردن دکمه "اجرای MRP"، سیستم تمام سفارشات انتخابی را بر اساس BOM منفجر کرده (نیازمندی ناخالص) و با <b>کل موجودی‌های در دسترس</b> (شامل WIP، محصول نهایی و مواد خام) مقایسه می‌کند.
                    <br>
                    ۴. نتیجه، لیستی از "نیازمندی‌های خالص" (کسری واقعی) خواهد بود.
                </p>
            </div>

            <div class="mb-3">
                <button id="load-data-btn" class="btn btn-primary btn-lg">
                    <i class="bi bi-arrow-clockwise me-2"></i> بارگذاری داده‌های ورودی MRP
                </button>
                <button id="run-mrp-btn" class="btn btn-danger btn-lg" style="display: none;">
                    <i class="bi bi-gear-wide-connected me-2"></i> <strong>اجرای MRP برای موارد انتخابی</strong>
                </button>
            </div>

            <div id="mrp-data-container" class="row" style="display: none;">
                <!-- 1. External Demand (Sales Orders) -->
                <div class="col-lg-6 mb-4">
                    <div class="card content-card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-cart-check-fill me-2"></i>۱. تقاضای خارجی (سفارشات فروش)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="p-3 text-center" style="width: 50px;">
                                                <input class="form-check-input" type="checkbox" id="select-all-orders">
                                            </th>
                                            <th class="p-3">محصول</th>
                                            <th class="p-3">تعداد</th>
                                            <th class="p-3">تاریخ تحویل</th>
                                        </tr>
                                    </thead>
                                    <tbody id="demand-sales-orders">
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Available Supply (WIP) -->
                <div class="col-lg-6 mb-4">
                    <div class="card content-card h-100">
                        <div class="card-header bg-info text-dark">
                            <h5 class="mb-0"><i class="bi bi-box-seam-fill me-2"></i>۲. موجودی در دسترس (WIP)</h5>
                        </div>
                        <div class="card-body p-0">
                             <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="p-3">قطعه نیمه‌ساخته</th>
                                            <th class="p-3">وضعیت فعلی</th>
                                            <th class="p-3">موجودی (KG)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="demand-wip">
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results container -->
            <div id="mrp-results-container" class="mt-4" style="display: none;">
                <div class="card content-card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-clipboard-data-fill me-2"></i>نتایج MRP (فاز ۱): نیازمندی‌های خالص</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="p-3">نوع</th>
                                        <th class="p-3">نام قطعه / ماده اولیه</th>
                                        <th class="p-3">نیازمندی ناخالص</th>
                                        <th class="p-3">
                                            موجودی در دسترس
                                            <i class="bi bi-info-circle-fill text-white-50" 
                                               data-bs-toggle="tooltip" 
                                               title="جزئیات محاسبه موجودی در دسترس را با نگه داشتن ماوس روی عدد آن ببینید.">
                                            </i>
                                        </th>
                                        <th class="p-3 text-danger">نیازمندی خالص (کسری)</th>
                                        <th class="p-3">واحد</th>
                                    </tr>
                                </thead>
                                <tbody id="mrp-results-tbody">
                                    <!-- Data loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // --- (Initialize Tooltips) ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // --- (Setup Select All Checkboxes) ---
    function setupSelectAll(selectAllId, itemsClass) {
        $(document).on('change', selectAllId, function() {
            $(itemsClass).prop('checked', $(this).prop('checked'));
        });
        $(document).on('change', itemsClass, function() {
            if (!$(this).prop('checked')) {
                $(selectAllId).prop('checked', false);
            }
        });
    }
    setupSelectAll('#select-all-orders', '.order-item-select');


    // --- (Load Input Data) ---
    $('#load-data-btn').on('click', function() {
        const $this = $(this);
        $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>در حال بارگذاری داده‌ها...');
        
        const salesTbody = $('#demand-sales-orders');
        const wipTbody = $('#demand-wip');
        const container = $('#mrp-data-container');
        
        salesTbody.empty();
        wipTbody.empty();
        $('#select-all-orders').prop('checked', false);
        $('#mrp-results-container').slideUp(); 

        $.ajax({
            url: '../../api/get_mrp_inputs.php', 
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 1. Populate Sales Orders (Selectable)
                    if (response.data.sales_orders.length > 0) {
                        $.each(response.data.sales_orders, function(i, order) {
                            salesTbody.append(`
                                <tr data-id="${order.SalesOrderID}">
                                    <td class="p-3 text-center">
                                        <input class="form-check-input order-item-select" type="checkbox" value="${order.SalesOrderID}">
                                    </td>
                                    <td class="p-3">${order.PartName}</td>
                                    <td class="p-3">${parseInt(order.QuantityRequired).toLocaleString()}</td>
                                    <td class="p-3">${order.DueDate}</td>
                                </tr>
                            `);
                        });
                    } else {
                        salesTbody.append('<tr><td colspan="4" class="text-center text-muted p-4">سفارش فروش بازی یافت نشد.</td></tr>');
                    }
                    
                    // 2. Populate WIP (Informational)
                    if (response.data.wip.length > 0) {
                        $.each(response.data.wip, function(i, item) {
                            wipTbody.append(`
                                <tr>
                                    <td class="p-3">${item.PartName}</td>
                                    <td class="p-3">${item.StatusName} (در ${item.StationName})</td>
                                    <td class="p-3">${parseFloat(item.TotalNetWeightKG).toFixed(2)}</td>
                                </tr>
                            `);
                        });
                    } else {
                        wipTbody.append('<tr><td colspan="3" class="text-center text-muted p-4">موجودی نیمه‌ساخته‌ای یافت نشد.</td></tr>');
                    }

                    container.slideDown();
                    $('#run-mrp-btn').fadeIn();
                    $this.fadeOut();

                } else {
                    alert('خطا در بارگذاری داده‌ها: ' + response.message);
                    resetLoadButton();
                }
            },
            error: function(xhr) {
                alert('خطای سیستمی در ارتباط با API. ' + (xhr.responseJSON ? xhr.responseJSON.message : ''));
                resetLoadButton();
            }
        });
    });

    function resetLoadButton() {
        $('#load-data-btn').prop('disabled', false).html('<i class="bi bi-arrow-clockwise me-2"></i> بارگذاری داده‌های ورودی MRP').show();
    }


    // --- (Run MRP Calculation - LOGIC UPDATED) ---
    $('#run-mrp-btn').on('click', function() {
        
        const $this = $(this);
        const selectedOrders = [];
        $('.order-item-select:checked').each(function() {
            selectedOrders.push($(this).val());
        });

        if (selectedOrders.length === 0) {
            alert("لطفاً حداقل یک سفارش فروش را برای اجرای MRP انتخاب کنید.");
            return;
        }

        $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>در حال محاسبه نیازمندی‌های خالص...');
        $('#mrp-results-container').slideUp();
        
        // Destroy old popovers before emptying
        $('#mrp-results-tbody [data-bs-toggle="popover"]').each(function() {
            var popover = bootstrap.Popover.getInstance(this);
            if (popover) {
                popover.dispose();
            }
        });
        
        const resultsTbody = $('#mrp-results-tbody').empty();

        $.ajax({
            url: '../../api/run_mrp_calculation.php', 
            type: 'POST',
            data: JSON.stringify({ 
                orders: selectedOrders 
            }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.data.net_requirements.length > 0) {
                        $.each(response.data.net_requirements, function(i, item) {
                            
                            // --- Build Popover Content ---
                            let popoverTitle = 'جزئیات موجودی';
                            let popoverContent = '';
                            let availableSupplyDisplay = '';

                            if (item.Type === 'قطعه') {
                                availableSupplyDisplay = parseInt(item.AvailableSupply).toLocaleString();
                                popoverContent = `
                                    <ul class="list-unstyled mb-0 small">
                                        <li>موجودی (KG): <strong>${parseFloat(item.Supply_Source_KG).toFixed(2)}</strong></li>
                                        <li>موجودی (کارتن): <strong>${parseInt(item.Supply_Source_Carton)}</strong></li>
                                        <li>وزن واحد (GR): <strong>${parseFloat(item.Supply_Unit_Weight_GR).toFixed(3)}</strong></li>
                                        <hr class="my-1">
                                        <li><b>تعداد محاسبه شده: <strong>${availableSupplyDisplay}</strong></b></li>
                                    </ul>
                                `;
                            } else { // Raw Material
                                availableSupplyDisplay = parseFloat(item.AvailableSupply).toLocaleString();
                                popoverContent = `
                                    <ul class="list-unstyled mb-0 small">
                                        <li>موجودی (KG): <strong>${parseFloat(item.Supply_Source_KG).toFixed(2)}</strong></li>
                                        <hr class="my-1">
                                        <li><b>تعداد محاسبه شده: <strong>${availableSupplyDisplay}</strong></b></li>
                                    </ul>
                                `;
                            }
                            // --- End Popover Content ---

                            resultsTbody.append(`
                                <tr>
                                    <td class="p-3">${item.Type}</td>
                                    <td class="p-3">${item.Name} (${item.Key})</td>
                                    <td class="p-3">${parseFloat(item.GrossRequirement).toLocaleString()}</td>
                                    <td class="p-3">
                                        <span class="text-primary"
                                            style="cursor: help; border-bottom: 1px dotted;"
                                            data-bs-toggle="popover" 
                                            data-bs-trigger="hover focus" 
                                            data-bs-html="true" 
                                            title="${popoverTitle}" 
                                            data-bs-content="${popoverContent.replace(/"/g, '&quot;')}">
                                            ${availableSupplyDisplay}
                                        </span>
                                    </td>
                                    <td class="p-3 text-danger fw-bold">${parseFloat(item.NetRequirement).toLocaleString()}</td>
                                    <td class="p-3">${item.Unit}</td>
                                </tr>
                            `);
                        });
                        
                        // Initialize new popovers AFTER appending all rows
                        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
                        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                          return new bootstrap.Popover(popoverTriggerEl)
                        });
                        
                    } else {
                        resultsTbody.append('<tr><td colspan="6" class="text-center text-success p-4">بر اساس سفارشات انتخابی، هیچ کسری موجودی یافت نشد.</td></tr>');
                    }
                    $('#mrp-results-container').slideDown();
                } else {
                    alert('خطا در محاسبه MRP: ' + response.message);
                }
                resetRunButton();
            },
            error: function(xhr) {
                let errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'پاسخ معتبری از سرور دریافت نشد.';
                alert('خطای سیستمی در اجرای MRP. ' + errorMsg);
                resetRunButton();
            }
        });
    });

    function resetRunButton() {
        $('#run-mrp-btn').prop('disabled', false).html('<i class="bi bi-gear-wide-connected me-2"></i> <strong>اجرای MRP برای موارد انتخابی</strong>');
    }

});
</script>

