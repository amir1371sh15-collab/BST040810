<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks
if (!has_permission('planning.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "ماژول برنامه‌ریزی تولید (Planning)";
include_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-calendar-range-fill me-2"></i><?php echo $pageTitle; ?></h1>
            <a href="http://localhost/bst/dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
            </div>

            <div class="row">
                <!-- Section 1: Core Planning Flow (MRP & Scheduling) -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm content-card h-100">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>فرآیند اصلی برنامه‌ریزی</h4>
                        </div>
                        <div class="list-group list-group-flush">
                            
                            <!-- Phase 1: Net Requirements (MRP) -->
                            <?php if (has_permission('planning.mrp.run')): ?>
                                <a href="mrp_run.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-calculator-fill me-2 text-danger"></i>1. اجرای محاسبه نیازمندی‌ها (MRP)
                                </a>
                            <?php endif; ?>

                            <!-- Phase 2: Production Scheduling (Pressing) -->
                            <?php if (has_permission('planning.production_schedule.view')): ?>
                                <a href="pressing_schedule.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-display me-2 text-info"></i>2. برنامه‌ریزی پرسکاری و پیچ‌سازی
                                </a>
                            <?php endif; ?>
                            
                           <!-- [EDIT] - لینک آبکاری فعال شد و شماره‌گذاری اصلاح شد -->
                             <?php if (has_permission('planning.mrp.run')): // فرض بر استفاده از این کلید دسترسی ?>
                                <a href="plating_schedule.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-droplet-half me-2 text-primary"></i>3. برنامه‌ریزی آبکاری و شستشو
                                </a>
                            <?php endif; ?>
                             <?php /* if (has_permission('planning.assembly_schedule.view')): ?>
                                <a href="assembly_schedule.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-tools me-2 text-info"></i>۲. برنامه‌ریزی مونتاژ
                                </a>
                            <?php endif; */ ?>
                            
                            <!-- Phase 3: Work Order List -->
                            <?php if (has_permission('planning.mrp.run')): ?>
                                <a href="work_order_list.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-card-checklist me-2 text-success"></i>4. مشاهده لیست دستور کارها
                                </a>
                            <?php endif; ?>
                            <?php if (has_permission('planning.mrp.run')): // استفاده از دسترسی مشابه ?>
                                <a href="plan_fulfillment.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-check-all me-2 text-dark"></i>۵. پیگیری و تحقق برنامه‌ها
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- Section 2: Supporting Data & BOM -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm content-card h-100">
                        <div class="card-header bg-secondary text-white">
                            <h4 class="mb-0"><i class="bi bi-tools me-2"></i>تنظیمات و داده‌های پشتیبان</h4>
                        </div>
                        <div class="list-group list-group-flush">
                            <!-- Sales Orders -->
                            <?php if (has_permission('planning.sales_orders.view')): ?>
                                <a href="sales_orders.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-cart-check me-2"></i>مدیریت سفارشات فروش
                                </a>
                            <?php endif; ?>
                            
                            <!-- BOM Management -->
                            <?php if (has_permission('planning.bom.manage')): ?>
                                <a href="bom_management.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-diagram-3 me-2"></i>مدیریت BOM محصولات
                                </a>
                            <?php endif; ?>

                            <!-- Raw Material BOM -->
                            <?php if (has_permission('planning.bom.manage')): ?>
                                <a href="raw_material_bom.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-bricks me-2"></i>مدیریت BOM مواد خام
                                </a>
                            <?php endif; ?>

                            <!-- BOM Calculator -->
                            <?php if (has_permission('planning.bom.view')): ?>
                                <a href="bom_calculator.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-magic me-2"></i>تجزیه BOM (Calculator)
                                </a>
                            <?php endif; ?>

                            <!-- Safety Stock -->
                            <?php if (has_permission('planning.safety_stock.view')): ?>
                                <a href="manage_safety_stock.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-shield-check me-2"></i>مدیریت نقاط سفارش (Safety Stock)
                                </a>
                            <?php endif; ?>

                            <!-- Constraints Index -->
                            <?php if (has_permission('planning_constraints.view')): // This permission was corrected based on assumption ?>
                                <a href="constraints_index.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-exclamation-octagon me-2"></i>مدیریت قوانین محدودیت و ستاپ (FCS)
                                </a>
                            <?php endif; ?>
                            
                            <!-- Inventory Alerts -->
                            <?php if (has_permission('planning.view_alerts')): ?>
                                <a href="product_inventory_alerts.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-bell me-2"></i>هشدارهای کسری موجودی قطعات
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>