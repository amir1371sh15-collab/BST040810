<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('production.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "ماژول تولید";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ماژول تولید</h1>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">در این بخش می‌توانید آمار و وقایع مربوط به سالن‌های مختلف تولید را ثبت و مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
    <?php if (has_permission('production.production_hall.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="production_hall/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-gear-wide-connected"></i></div>
                    <h5 class="card-title">سالن تولید</h5>
                    <p class="card-text">ثبت تولید روزانه دستگاه‌ها و گزارش توقفات.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('production.plating_hall.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="plating_hall/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-droplet-half"></i></div>
                    <h5 class="card-title">سالن آبکاری</h5>
                    <p class="card-text">ثبت آمار شستشو، آبکاری و وقایع روزانه.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (has_permission('production.assembly_hall.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="assembly_hall/index.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">سالن مونتاژ</h5>
                    <p class="card-text">ثبت آمار مونتاژ، رول و بسته‌بندی محصولات.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

