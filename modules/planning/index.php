<?php
require_once __DIR__ . '/../../config/init.php';
// بررسی دسترسی اصلی ماژول
if (!has_permission('planning.view')) {
    die('شما مجوز دسترسی به این ماژول را ندارید.');
}

$pageTitle = "داشبورد برنامه‌ریزی تولید";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ماژول برنامه‌ریزی تولید (MRP)</h1>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به داشبورد اصلی
    </a>
</div>

<p class="lead">در این بخش می‌توانید تقاضای مشتری (سفارشات)، ساختار محصول (BOM)، نقاط سفارش و اجرای MRP را مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <?php if (has_permission('planning.sales_orders.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="sales_orders.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-cart-check-fill"></i></div>
                    <h5 class="card-title">۱. سفارشات فروش (تقاضا)</h5>
                    <p class="card-text">مدیریت سفارشات مشتریان و برنامه‌های فروش.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning.bom.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="bom_management.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-diagram-3-fill"></i></div>
                    <h5 class="card-title">۲. ساختار محصول (BOM)</h5>
                    <p class="card-text">تعریف ارتباط قطعه به قطعه (مثلاً بست = تسمه + محفظه + پیچ).</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning.bom.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_material_bom.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-rulers"></i></div>
                    <h5 class="card-title">۳. BOM مواد اولیه</h5>
                    <p class="card-text">تعریف ارتباط قطعه به مواد اولیه (مثلاً تسمه = ورق فولادی).</p>
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
                    <div class="icon mb-3"><i class="bi bi-shield-shaded"></i></div>
                    <h5 class="card-title">۴. مدیریت نقاط سفارش (WIP)</h5>
                    <p class="card-text">تعریف حداقل موجودی مجاز (نقطه سفارش) برای قطعات و محصولات.</p>
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
                    <div class="icon mb-3" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <h5 class="card-title">۵. داشبورد هشدار موجودی (WIP)</h5>
                    <p class="card-text">مشاهده قطعاتی که موجودی آن‌ها به زیر نقطه سفارش رسیده است.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (has_permission('planning.mrp.run')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="mrp_run.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-calculator-fill"></i></div>
                    <h5 class="card-title">۶. اجرای برنامه‌ریزی (MRP)</h5>
                    <p class="card-text">محاسبه نیازمندی‌های مواد و ظرفیت بر اساس تقاضا و BOM.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

