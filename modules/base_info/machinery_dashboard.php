<?php
require_once __DIR__ . '/../../config/init.php'; // Use init.php for security and helpers

// Add permission check if needed, e.g., base_info.view
if (!has_permission('base_info.view')) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$pageTitle = "داشبورد مدیریت ماشین‌آلات";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">مدیریت ماشین‌آلات</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به اطلاعات پایه
    </a>
</div>

<p class="lead">در این بخش می‌توانید دستگاه‌ها، قالب‌ها و اطلاعات مرتبط با آن‌ها را در سیستم تعریف و مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-4">

    <!-- مدیریت دستگاه‌ها -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/base_info/machines.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-gear-wide-connected"></i></div>
                    <h5 class="card-title">مدیریت دستگاه‌ها</h5>
                    <p class="card-text">افزودن، ویرایش و حذف دستگاه‌های خط تولید.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- مدیریت قالب‌ها -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/base_info/molds.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-exclude"></i></div>
                    <h5 class="card-title">مدیریت قالب‌ها</h5>
                    <p class="card-text">افزودن، ویرایش و حذف قالب‌های تولیدی.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- ارتباط قالب و دستگاه -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/base_info/mold_machine_compatibility.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-link-45deg"></i></div>
                    <h5 class="card-title">سازگاری قالب و دستگاه</h5>
                    <p class="card-text">تعیین کنید کدام قالب‌ها روی کدام دستگاه‌ها قابل استفاده هستند.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- NEW: ارتباط دستگاه و خانواده محصول -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/base_info/machine_family_compatibility.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-diagram-2-fill"></i></div> 
                    <h5 class="card-title">ارتباط دستگاه و خانواده محصول</h5>
                    <p class="card-text">تعیین کنید هر دستگاه کدام خانواده‌های محصول را تولید می‌کند.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- دلایل توقفات -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/base_info/downtime_reasons.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-pause-circle"></i></div>
                    <h5 class="card-title">مدیریت دلایل توقفات</h5>
                    <p class="card-text">تعریف دلایل استاندارد برای توقفات دستگاه‌ها.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>