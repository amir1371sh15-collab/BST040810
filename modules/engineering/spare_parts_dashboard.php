<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.spare_parts.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "داشبورد انبار قطعات یدکی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">انبار قطعات یدکی قالب‌ها</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به ماژول مهندسی
    </a>
</div>

<p class="lead">در این بخش می‌توانید قطعات یدکی مربوط به قالب‌ها را تعریف کرده، برای آن‌ها سفارش ثبت کنید و ورود و خروج آن‌ها را مدیریت نمایید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="spare_parts.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">مدیریت قطعات یدکی</h5>
                    <p class="card-text">تعریف، ویرایش و حذف قطعات یدکی مربوط به هر قالب.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="spare_part_orders.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-cart-plus-fill"></i></div>
                    <h5 class="card-title">مدیریت سفارشات</h5>
                    <p class="card-text">ثبت سفارش خرید برای قطعات یدکی از پیمانکاران.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="spare_part_transactions.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-arrows-move"></i></div>
                    <h5 class="card-title">ثبت تراکنش انبار</h5>
                    <p class="card-text">ثبت ورود (بر اساس سفارش یا دستی) و خروج قطعات از انبار.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>

