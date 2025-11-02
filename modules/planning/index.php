<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "ماژول برنامه‌ریزی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo $pageTitle; ?></h1>
    </div>
    <p class="mb-4">به ماژول برنامه‌ریزی و مدیریت تولید خوش آمدید. از این بخش می‌توانید عملیات برنامه‌ریزی، مدیریت BOM، و مشاهده هشدارهای موجودی را انجام دهید.</p>

    <div class="row">
        <!-- Card 1: Sales Orders (NEW) -->
        <?php if (has_permission('planning.sales_orders.manage')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='sales_orders.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-success"><i class="bi bi-cart-check-fill me-2"></i>مدیریت سفارشات فروش</h5>
                    <p class="card-text small text-muted">
                        ورود، ویرایش و مدیریت سفارشات مشتریان (تقاضای خارجی) به عنوان ورودی اصلی MRP.
                    </p>
                    <div class="mt-auto text-success">
                        مدیریت سفارشات <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 2: MRP Run (NEW) -->
        <?php if (has_permission('planning.mrp.run')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-danger border-2" onclick="window.location.href='mrp_run.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-danger"><i class="bi bi-gear-wide-connected me-2"></i>اجرای MRP</h5>
                    <p class="card-text small text-muted">
                        مشاهده تقاضای کل (سفارشات و WIP) و اجرای محاسبات برنامه‌ریزی مواد (MRP).
                    </p>
                    <div class="mt-auto text-danger fw-bold">
                        اجرای برنامه‌ریزی <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 3: BOM Management -->
        <?php if (has_permission('planning.bom.view')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='bom_management.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-diagram-3 me-2"></i>مدیریت BOM (ساختار محصول)</h5>
                    <p class="card-text small text-muted">
                        تعریف ساختار درختی محصولات و قطعات نیمه‌ساخته مورد نیاز برای تولید.
                    </p>
                    <div class="mt-auto text-primary">
                        مدیریت BOM <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Card 4: Raw Material BOM -->
        <?php if (has_permission('planning.bom.view')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='raw_material_bom.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-bricks me-2"></i>مدیریت BOM مواد اولیه</h5>
                    <p class="card-text small text-muted">
                        تعریف مواد خام (مانند ورق یا مفتول) مورد نیاز برای تولید هر قطعه.
                    </p>
                    <div class="mt-auto text-primary">
                        مدیریت مواد اولیه <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 5: BOM Calculator -->
        <?php if (has_permission('planning.view')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='bom_calculator.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-calculator-fill me-2"></i>ماشین حساب BOM</h5>
                    <p class="card-text small text-muted">
                        محاسبه سریع مواد اولیه و قطعات مورد نیاز بر اساس تقاضای یک محصول نهایی.
                    </p>
                    <div class="mt-auto text-primary">
                        اجرای ماشین حساب <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 6: Constraints & Capacity -->
        <?php if (has_permission('planning_constraints.view')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='constraints_index.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-sliders me-2"></i>محدودیت‌ها و ظرفیت‌ها</h5>
                    <p class="card-text small text-muted">
                        مدیریت قوانین ظرفیت ایستگاه‌ها (OEE، ثابت)، قوانین آبکاری و ناسازگاری‌های ویبره.
                    </p>
                    <div class="mt-auto text-primary">
                        مدیریت محدودیت‌ها <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Card 7: Inventory Alerts -->
        <?php if (has_permission('planning.view_alerts')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='product_inventory_alerts.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-exclamation-triangle-fill me-2"></i>هشدارهای موجودی</h5>
                    <p class="card-text small text-muted">
                        مشاهده هشدارهای کسری موجودی محصولات نهایی بر اساس نقطه سفارش (Safety Stock).
                    </p>
                    <div class="mt-auto text-primary">
                        مشاهده هشدارها <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 8: Safety Stock -->
        <?php if (has_permission('planning.safety_stock.manage')): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-0 card-as-link" onclick="window.location.href='manage_safety_stock.php';">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-shield-check me-2"></i>مدیریت نقطه سفارش</h5>
                    <p class="card-text small text-muted">
                        تعریف حداقل موجودی اطمینان (Safety Stock) برای محصولات نهایی.
                    </p>
                    <div class="mt-auto text-primary">
                        مدیریت نقطه سفارش <i class="bi bi-arrow-left-short"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div> <!-- /.row -->
</div> <!-- /.container-fluid -->

<style>
.card-as-link {
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card-as-link:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

