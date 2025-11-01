<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.assembly_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "داشبورد سالن مونتاژ";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">سالن مونتاژ</h1>
    <a href="<?php echo BASE_URL; ?>modules/production/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">آمار مربوط به مونتاژ، رول و بسته‌بندی را در این بخش ثبت و گزارش‌گیری کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="assembly.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-person-bounding-box"></i></div>
                    <h5 class="card-title">ثبت آمار مونتاژ</h5>
                    <p class="card-text">ثبت تولیدات دستگاه‌های مونتاژ.</p>
                </div>
            </a>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="rolling.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-arrow-repeat"></i></div>
                    <h5 class="card-title">ثبت آمار رول</h5>
                    <p class="card-text">ثبت تولیدات دستگاه‌های رول.</p>
                </div>
            </a>
        </div>
    </div>
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="packaging.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box2-heart"></i></div>
                    <h5 class="card-title">ثبت آمار بسته‌بندی</h5>
                    <p class="card-text">ثبت تعداد کارتن و شرینک محصولات.</p>
                </div>
            </a>
        </div>
    </div>
     <!-- New Card for Assembly Log Report -->
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="assembly_log_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-table"></i></div>
                    <h5 class="card-title">گزارش فرآیند مونتاژ (جدول)</h5>
                    <p class="card-text">مشاهده گزارش جدولی روزانه با محاسبات عملکرد.</p>
                </div>
            </a>
        </div>
    </div>
    <!-- New Card for Assembly Analytics Dashboard -->
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="assembly_reports.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <h5 class="card-title">داشبورد تحلیلی مونتاژ</h5>
                    <p class="card-text">نمودارهای روند تولید، بهره‌وری و تجمعی.</p>
                </div>
            </a>
        </div>
    </div>
    <!-- *** NEW CARD FOR DAILY SUMMARY REPORT *** -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="daily_summary_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-file-earmark-text-fill"></i></div> {/* Text file icon */}
                    <h5 class="card-title">گزارش خلاصه روزانه (A4)</h5>
                    <p class="card-text">نمایش گزارش جامع روزانه مونتاژ، رول و بسته‌بندی.</p>
                </div>
            </a>
        </div>
    </div>
    <!-- *** END NEW CARD *** -->
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
