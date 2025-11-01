<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('base_info.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "ماژول اطلاعات پایه";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ماژول اطلاعات پایه</h1>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به داشبورد اصلی
    </a>
</div>

<p class="lead">در این بخش می‌توانید اطلاعات اولیه و زیرساختی کل سیستم را مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="departments.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-diagram-3-fill"></i></div>
                    <h5 class="card-title">مدیریت دپارتمان‌ها</h5>
                    <p class="card-text">تعریف و ویرایش بخش‌های مختلف سازمان.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="employees.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-people-fill"></i></div>
                    <h5 class="card-title">مدیریت کارمندان</h5>
                    <p class="card-text">افزودن و مدیریت اطلاعات پرسنل.</p>
                </div>
            </a>
        </div>
    </div>

     <!-- NEW CARD for Break Times -->
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="break_times.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-clock-history"></i></div>
                    <h5 class="card-title">مدیریت زمان‌های استراحت</h5>
                    <p class="card-text">تعریف زمان‌های استاندارد استراحت (ناهار، صبحانه...).</p>
                </div>
            </a>
        </div>
    </div>
    <!-- END NEW CARD -->


    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="parts_dashboard.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">مدیریت قطعات</h5>
                    <p class="card-text">تعریف قطعات، خانواده‌ها، گروه‌ها و سایزها.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="warehouses_dashboard.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-archive-fill"></i></div>
                    <h5 class="card-title">مدیریت انبارها</h5>
                    <p class="card-text">تعریف انبارها، انواع انبار و انواع تراکنش.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="machinery_dashboard.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-gear-wide-connected"></i></div>
                    <h5 class="card-title">مدیریت ماشین‌آلات</h5>
                    <p class="card-text">تعریف دستگاه‌ها، قالب‌ها و دلایل توقفات.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="units.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-rulers"></i></div>
                    <h5 class="card-title">مدیریت واحدها</h5>
                    <p class="card-text">تعریف واحدهای اندازه‌گیری (KG, L, gr...).</p>
                </div>
            </a>
        </div>
    </div>

     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="chemicals.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-exclamation-triangle" role="img" aria-label="هشدار"></i></div>
                    <h5 class="card-title">مدیریت مواد شیمیایی</h5>
                    <p class="card-text">تعریف انواع و نام مواد شیمیایی و ضرایب مصرف.</p>
                </div>
            </a>
        </div>
    </div>

     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="plating_vats.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-inbox-fill"></i></div>
                    <h5 class="card-title">مدیریت وان‌های آبکاری</h5>
                    <p class="card-text">تعریف نام، حجم و وضعیت وان‌های آبکاری.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>
