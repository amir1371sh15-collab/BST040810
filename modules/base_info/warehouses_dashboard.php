<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('base_info.view')) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$pageTitle = "داشبورد مدیریت انبارها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت انبارها</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به اطلاعات پایه
    </a>
</div>

<p class="lead">در این بخش می‌توانید اطلاعات اولیه مربوط به انبارها، انواع تراکنش، بسته‌بندی و پالت‌ها را مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="warehouses.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-house-door-fill"></i></div><h5 class="card-title">مدیریت انبارها</h5>
        <p class="card-text">تعریف و ویرایش انبارهای مختلف در سازمان.</p>
    </div></a></div></div>

    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="warehouse_types.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-tags-fill"></i></div><h5 class="card-title">انواع انبار</h5>
        <p class="card-text">تعریف انواع مختلف انبار (مواد اولیه، محصول نهایی و...).</p>
    </div></a></div></div>

    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="transaction_types.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-arrows-expand"></i></div><h5 class="card-title">انواع تراکنش انبار</h5>
        <p class="card-text">تعریف انواع تراکنش‌های انبار (ورود، خروج و...).</p>
    </div></a></div></div>

    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="pallet_types.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-stack"></i></div><h5 class="card-title">مدیریت انواع پالت</h5>
        <p class="card-text">تعریف و مدیریت انواع پالت و وزن آن‌ها.</p>
    </div></a></div></div>

    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="packaging_configs.php"><div class="card-body">
        <div class="icon mb-3"><i class="bi bi-box2-fill"></i></div><h5 class="card-title">پیکربندی بسته‌بندی</h5>
        <p class="card-text">تعریف تعداد محصول در کارتن برای بست‌ها.</p>
    </div></a></div></div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>

