<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('warehouse.view')) { die('شما مجوز دسترسی به این ماژول را ندارید.'); }

$pageTitle = "داشبورد ماژول انبار";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">مدیریت تراکنش‌های انبار، موجودی و مسیرهای جریان مواد.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-4">

    <?php if (has_permission('warehouse.transactions.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="transactions.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-input-cursor-text"></i></div>
                    <h5 class="card-title">ثبت تراکنش انبار (WIP)</h5>
                    <p class="card-text">ثبت ورود و خروج کالا بین ایستگاه‌ها.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="inventory_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                    <h5 class="card-title">گزارش گردش موجودی (WIP)</h5>
                    <p class="card-text">مشاهده تاریخچه تراکنش‌ها و موجودی با فیلتر.</p>
                </div>
            </a>
        </div>
    </div>

     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="inventory_dashboard.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-clipboard-data"></i></div>
                    <h5 class="card-title">داشبورد موجودی (WIP)</h5>
                    <p class="card-text">مشاهده موجودی فعلی و ثبت عکس لحظه‌ای.</p>
                </div>
            </a>
        </div>
    </div>

    <?php if (has_permission('warehouse.transactions.manage')): // Use same permission for now ?>
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="inventory_stocktake.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-clipboard-check"></i></div>
                    <h5 class="card-title">انبارگردانی (WIP)</h5>
                    <p class="card-text">ثبت موجودی اولیه یا تعدیلات انبارگردانی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
     <?php if (has_permission('warehouse.transactions.manage')): // Use same permission for now ?>
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="wip_inventory_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-calculator-fill"></i></div>
                    <h5 class="card-title">کنترل موجودی (WIP)</h5>
                    <p class="card-text">کنترل موجودی و مغایرت ها.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- NEW CARD for Raw Materials -->
    <?php if (has_permission('warehouse.raw.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-bricks"></i></div>
                    <h5 class="card-title">انبار مواد اولیه</h5>
                    <p class="card-text">مدیریت ورق‌ها، مفتول‌ها و مواد خام ورودی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- NEW CARD for Misc Warehouse -->
    <?php if (has_permission('warehouse.misc.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="misc_index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-bookshelf"></i></div>
                    <h5 class="card-title">انبار متفرقه</h5>
                    <p class="card-text">مدیریت مواد مصرفی، شیمیایی، کارتن‌ها و...</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- MOVED CARD for Alerts -->
    <?php if (has_permission('warehouse.view')): // General view permission
    ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="inventory_alerts.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <h5 class="card-title">هشدارهای انبار (مواد)</h5>
                    <p class="card-text">مشاهده وضعیت مواد اولیه و متفرقه‌ای که به نقطه سفارش رسیده‌اند.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>


</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<style>
.disabled {  opacity: 0.6; pointer-events: none; }
</style>

