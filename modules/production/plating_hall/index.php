<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.plating_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "داشبورد سالن آبکاری";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">سالن آبکاری</h1>
    <a href="<?php echo BASE_URL; ?>modules/production/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">آمار تولید، وقایع روزانه، آنالیز وان و گزارشات تحلیلی سالن آبکاری را در این بخش مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="daily_production.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-bucket"></i></div>
                    <h5 class="card-title">ثبت تولید روزانه</h5>
                    <p class="card-text">ثبت میزان شستشو، آبکاری، دوباره کاری، پرسنل و مواد مصرفی.</p>
                </div>
            </a>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="events.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-calendar-event"></i></div>
                    <h5 class="card-title">ثبت وقایع</h5>
                    <p class="card-text">ثبت وقایع و توضیحات مربوط به هر روز کاری.</p>
                </div>
            </a>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="vat_analysis_log.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-clipboard2-pulse-fill"></i></div> 
                    <h5 class="card-title">ثبت نتایج آنالیز وان</h5>
                    <p class="card-text">ثبت مقادیر سیانور، سود و روی برای هر وان و محاسبات.</p>
                </div>
            </a>
        </div>
    </div>
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="plating_reports.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-graph-up"></i></div>
                    <h5 class="card-title">گزارشات و تحلیل آبکاری</h5>
                    <p class="card-text">مشاهده داشبورد تحلیلی عملکرد و بهره‌وری.</p>
                </div>
            </a>
        </div>
    </div>
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="plating_chemical_reports.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-droplet-half"></i></div>
                    <h5 class="card-title">گزارشات و تحلیل مواد شیمیایی</h5>
                    <p class="card-text">مشاهده داشبورد مصرف مواد.</p>
                </div>
            </a>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="plating_log_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-printer-fill"></i></div>
                    <h5 class="card-title">گزارش چاپی (Log Sheet)</h5>
                    <p class="card-text">خروجی جدولی روزانه تولید و نفرساعت در فرمت A4.</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

