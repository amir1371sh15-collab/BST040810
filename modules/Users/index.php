<?php
require_once __DIR__ . '/../../config/db.php';

// --- Permission Check ---
// We'll add a real login system later. For now, simulate a logged-in admin user.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Simulate user with ID 1
}
load_user_permissions($pdo, $_SESSION['user_id']);

// A user needs the 'users.view' permission to see this dashboard.
// The key was changed from 'manage_users' to 'users.view' to match the granular system.
if (!has_permission('users.view')) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}


$pageTitle = "داشبورد مدیریت کاربران";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت کاربران و دسترسی‌ها</h1>
    <a href="<?php echo BASE_URL; ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به داشبورد اصلی
    </a>
</div>

<p class="lead">در این بخش می‌توانید کاربران سیستم را تعریف کرده، برای آن‌ها نقش تعیین کنید و سطح دسترسی هر نقش را مدیریت نمایید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <!-- کارت مدیریت کاربران -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/users/manage_users.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-person-plus-fill"></i></div>
                    <h5 class="card-title">مدیریت کاربران</h5>
                    <p class="card-text">ایجاد کاربر جدید، تخصیص کارمند و نقش به هر کاربر، و مدیریت رمز عبور.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- کارت مدیریت نقش‌ها -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/users/manage_roles.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-shield-lock-fill"></i></div>
                    <h5 class="card-title">مدیریت نقش‌ها</h5>
                    <p class="card-text">تعریف نقش‌های مختلف در سازمان مانند مدیر، اپراتور، مهندس و...</p>
                </div>
            </a>
        </div>
    </div>

    <!-- کارت مدیریت دسترسی‌ها -->
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/users/manage_permissions.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-toggles"></i></div>
                    <h5 class="card-title">مدیریت دسترسی‌ها</h5>
                    <p class="card-text">مشخص کردن اینکه هر نقش به کدام یک از ماژول‌ها و صفحات سیستم دسترسی دارد.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>

