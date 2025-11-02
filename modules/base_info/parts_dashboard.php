<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('base_info.view')) { die('شما مجوز دسترسی به این بخش را ندارید.'); }
$pageTitle = "داشبورد مدیریت قطعات";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت قطعات و فرآیندهای تولید</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<p class="lead">در این بخش می‌توانید قطعات، خانواده‌ها، وزن‌ها، ایستگاه‌های کاری و مسیرهای تولید هر قطعه را تعریف و مدیریت کنید.</p>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="parts.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-box-seam-fill"></i></div><h5 class="card-title">مدیریت قطعات</h5><p class="card-text">تعریف قطعات اصلی سیستم بر اساس خانواده و سایز.</p>
    </div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="part_families.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-diagram-2-fill"></i></div><h5 class="card-title">خانواده قطعات</h5><p class="card-text">تعریف دسته‌بندی‌های کلی برای قطعات.</p>
    </div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="part_sizes.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-aspect-ratio-fill"></i></div><h5 class="card-title">مدیریت سایز قطعات</h5><p class="card-text">تعریف سایزهای مختلف برای هر خانواده قطعه.</p>
    </div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="part_weights.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-rulers"></i></div><h5 class="card-title">مدیریت وزن قطعات</h5><p class="card-text">تعریف و مدیریت وزن پایه قطعات بر اساس تاریخ.</p>
    </div></a></div></div>
     <div class="col"><div class="card h-100 module-card shadow-sm"><a href="process_weight_changes.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-percent"></i></div><h5 class="card-title">مدیریت تغییرات وزن فرآیند</h5><p class="card-text">تعریف درصد تغییر وزن قطعات در فرآیندهای خاص.</p>
    </div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="stations.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-geo-alt-fill"></i></div><h5 class="card-title">مدیریت ایستگاه‌ها</h5><p class="card-text">تعریف ایستگاه‌های کاری، انبارها و مراکز کنترلی.</p>
    </div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="routes.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-signpost-split-fill"></i></div><h5 class="card-title">مدیریت مسیرهای تولید</h5><p class="card-text">تعریف نقشه راه تولید برای هر خانواده قطعه.</p>
    </div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="manage_route_sequence.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-clock-history"></i></div><h5 class="card-title">ترتیب‌دهی مسیرهای تولید</h5><p class="card-text">تعریف شماره مرحله و ترتیب مراحل تولید برای هر خانواده.</p>
    </div></a></div></div>
    <!-- New Card for Part Statuses -->
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="part_statuses.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-tags-fill"></i></div><h5 class="card-title">مدیریت وضعیت‌های قطعه</h5><p class="card-text">تعریف وضعیت‌های ممکن برای قطعات در فرآیند تولید.</p>
    </div></a></div></div>
    <!-- End New Card -->
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

