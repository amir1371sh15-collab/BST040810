<?php
require_once __DIR__ . '/config/init.php';

$pageTitle = "داشبورد اصلی";
include __DIR__ . '/templates/header.php';
?>

<div class="page-header">
    <h1 class="h2">سیستم جامع مدیریت تولید</h1>
    <p class="lead">ماژول مورد نظر خود را برای شروع انتخاب کنید.</p>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <?php if (has_permission('base_info.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/base_info/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-info-circle-fill"></i></div>
                    <h5 class="card-title">اطلاعات پایه</h5>
                    <p class="card-text">مدیریت اطلاعات اولیه و پایه‌ای سیستم.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('engineering.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-tools"></i></div>
                    <h5 class="card-title">ماژول مهندسی</h5>
                    <p class="card-text">مدیریت پروژه‌ها، انبار قطعات یدکی و سفارشات.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('production.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/production/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-wrench-adjustable-circle"></i></div>
                    <h5 class="card-title">ماژول تولید</h5>
                    <p class="card-text">ثبت آمار تولید، توقفات و گزارش‌گیری سالن‌ها.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- New Planning Module Card -->
    <?php if (has_permission('planning.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/Planning/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-calendar-check-fill"></i></div>
                    <h5 class="card-title">برنامه‌ریزی تولید</h5>
                    <p class="card-text">مدیریت BOM، تقاضا، هشدارها و اجرای MRP.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('quality.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/quality/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-patch-check-fill"></i></div>
                    <h5 class="card-title">ماژول کیفیت</h5>
                    <p class="card-text">مدیریت مجوزهای ارفاقی و کنترل‌های کیفی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('warehouse.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/warehouse/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">ماژول انبار</h5>
                    <p class="card-text">ثبت تراکنش‌ها، ردیابی جریان مواد و گزارش موجودی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('users.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/users/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-people-fill"></i></div>
                    <h5 class="card-title">مدیریت کاربران</h5>
                    <p class="card-text">تعریف کاربران، نقش‌ها و تعیین سطح دسترسی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
include __DIR__ . '/templates/footer.php';
?>
