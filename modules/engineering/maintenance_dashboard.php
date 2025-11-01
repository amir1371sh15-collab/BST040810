<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.maintenance.view')) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$pageTitle = "داشبورد نگهداری و تعمیرات";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">نگهداری و تعمیرات (نت)</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به ماژول مهندسی
    </a>
</div>

<p class="lead">در این بخش می‌توانید گزارش‌های خرابی قالب‌ها را ثبت کرده و تاریخچه تعمیرات را مشاهده نمایید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="maintenance_reports.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-journal-plus"></i></div>
                    <h5 class="card-title">ثبت و مشاهده گزارشات نت</h5>
                    <p class="card-text">ایجاد گزارش جدید برای خرابی قالب‌ها و مشاهده سوابق.</p>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="maintenance_relations.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-link-45deg"></i></div>
                    <h5 class="card-title">مدیریت روابط نت</h5>
                    <p class="card-text">تعریف علل احتمالی برای هر خرابی و اقدامات لازم برای هر علت.</p>
                </div>
            </a>
        </div>
    </div>
    
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="base_info_dashboard.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-info-circle-fill"></i></div>
                    <h5 class="card-title">اطلاعات پایه نت</h5>
                    <p class="card-text">مدیریت انواع خرابی، دلایل و اقدامات.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>
