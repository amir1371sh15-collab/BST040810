<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('quality.view')) { die('شما مجوز دسترسی به این ماژول را ندارید.'); }

$pageTitle = "داشبورد ماژول کیفیت";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ماژول کیفیت</h1>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">مدیریت مجوزهای ارفاقی (Deviations)، مسیرهای غیراستاندارد مجاز و بررسی تراکنش‌های در انتظار.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-4">

    <?php if (has_permission('quality.deviations.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="deviations.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-journal-check"></i></div>
                    <h5 class="card-title">مجوزهای ارفاقی (Deviation)</h5>
                    <p class="card-text">ثبت، مشاهده و مدیریت مجوزهای ارفاقی کیفیت.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('quality.overrides.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="route_overrides.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-signpost-split-fill"></i></div>
                    <h5 class="card-title">مدیریت مسیرهای غیراستاندارد</h5>
                    <p class="card-text">تعریف مسیرهای جایگزین مجاز با لینک به مجوز کیفیت.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('quality.pending_transactions.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="pending_transactions.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-hourglass-split"></i></div>
                    <h5 class="card-title">تراکنش‌های در انتظار</h5>
                    <p class="card-text">بررسی و تعیین تکلیف تراکنش‌های انجام شده در مسیرهای غیراستاندارد.</p>
                </div>
            </a>
        </div>
    </div>
     <?php endif; ?>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

