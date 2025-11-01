<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning.view')) {
    die('شما مجوز دسترسی به این ماژول را ندارید.');
}

$pageTitle = "داشبورد برنامه‌ریزی تولید";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1 class="h3 mb-0">داشبورد برنامه‌ریزی تولید (MRP)</h1>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <?php if (has_permission('planning.mrp.run')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm text-bg-primary border-0">
            <a href="mrp_run.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-calculator-fill"></i></div>
                    <h5 class="card-title">اجرای برنامه‌ریزی (MRP)</h5>
                    <p class="card-text">محاسبه نیازمندی‌های مواد و ظرفیت بر اساس سفارشات.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning.sales_orders.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="sales_orders.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-cart-check-fill"></i></div>
                    <h5 class="card-title">مدیریت سفارشات فروش</h5>
                    <p class="card-text">ثبت و مدیریت تقاضای مشتریان (ورودی MRP).</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (has_permission('planning.view_alerts')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="product_inventory_alerts.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-exclamation-triangle-fill text-danger"></i></div>
                    <h5 class="card-title">داشبورد هشدار موجودی قطعات</h5>
                    <p class="card-text">بررسی موجودی قطعات (WIP) و محصول نهایی نسبت به نقطه سفارش.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning.bom.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="bom_management.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-diagram-3"></i></div>
                    <h5 class="card-title">مدیریت ساختار محصول (BOM)</h5>
                    <p class="card-text">تعریف قطعات منفصله برای هر محصول نهایی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (has_permission('planning.bom.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_material_bom.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">مدیریت BOM مواد اولیه</h5>
                    <p class="card-text">تعریف مواد اولیه مورد نیاز برای هر قطعه تولیدی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning.safety_stock.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="manage_safety_stock.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-shield-check"></i></div>
                    <h5 class="card-title">مدیریت نقاط سفارش (قطعات)</h5>
                    <p class="card-text">تعریف حداقل موجودی مجاز برای قطعات و محصول نهایی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning_constraints.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="constraints_index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-tools"></i></div>
                    <h5 class="card-title">مدیریت محدودیت‌ها و ظرفیت‌ها</h5>
                    <p class="card-text">تعریف قوانین ظرفیت، ستاپ و بچینگ (FCS).</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

