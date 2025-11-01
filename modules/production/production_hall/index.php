<?php
require_once __DIR__ . '/../../../config/init.php';

// Check if the user has permission to view this section
if (!has_permission('production.production_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "داشبورد سالن تولید";
include __DIR__ . '/../../../templates/header.php'; // Include header template
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">سالن تولید</h1>
    <a href="<?php echo BASE_URL; ?>modules/production/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به ماژول تولید
    </a>
</div>

<p class="lead">در این بخش می‌توانید آمار تولید روزانه و توقفات دستگاه‌ها را ثبت و گزارش‌گیری کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <!-- Card for Daily Production Entry -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="daily_production.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-speedometer2"></i></div>
                    <h5 class="card-title">ثبت تولید روزانه</h5>
                    <p class="card-text">ثبت آمار تولید هر دستگاه بر اساس قطعه.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Card for Daily Downtime Entry -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="daily_downtime.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-pause-btn"></i></div>
                    <h5 class="card-title">ثبت توقفات روزانه</h5>
                    <p class="card-text">ثبت دلایل و مدت زمان توقفات هر دستگاه.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Card for Downtime Reports -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="downtime_reports.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-graph-down"></i></div>
                    <h5 class="card-title">گزارشات و تحلیل توقفات</h5>
                    <p class="card-text">مشاهده نمودارها و گزارش‌های تحلیلی از داده‌های توقفات.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Card for Production Analytics Reports -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="production_reports.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <h5 class="card-title">گزارشات و تحلیل تولید</h5>
                    <p class="card-text">مشاهده داشبورد تحلیلی عملکرد، بهره‌وری و OEE.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- New Card for Production Log Sheet Report -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="production_log_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-printer-fill"></i></div> {/* Printer icon */}
                    <h5 class="card-title">گزارش چاپی (Log Sheet)</h5>
                    <p class="card-text">خروجی جدولی روزانه تولید و نفرساعت برای چاپ.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../../templates/footer.php'; // Include footer template
?>
