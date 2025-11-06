<?php
require_once __DIR__ . '/../../config/init.php';

// We will add granular permission checks later. For now, a general check.
if (!has_permission('engineering.base_info')) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$pageTitle = "اطلاعات پایه مهندسی";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">اطلاعات پایه مهندسی</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">در این بخش می‌توانید اطلاعات اولیه و زیرساختی مربوط به ماژول مهندسی، سفارشات و نگهداری و تعمیرات را مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mt-3">
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="contractors.php"><div class="card-body"><div class="icon mb-3"><i class="bi bi-briefcase-fill"></i></div><h5 class="card-title">پیمانکاران</h5></div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="processes.php"><div class="card-body"><div class="icon mb-3"><i class="bi bi-diagram-3"></i></div><h5 class="card-title">فرآیندها</h5></div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="order_statuses.php"><div class="card-body"><div class="icon mb-3"><i class="bi bi-clipboard-check-fill"></i></div><h5 class="card-title">وضعیت‌های سفارش</h5></div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="priorities.php"><div class="card-body"><div class="icon mb-3"><i class="bi bi-bar-chart-line-fill"></i></div><h5 class="card-title">اولویت‌ها</h5></div></a></div></div>
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="task_statuses.php"><div class="card-body"><div class="icon mb-3"><i class="bi bi-check2-square"></i></div><h5 class="card-title">وضعیت‌های وظیفه</h5></div></a></div></div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
