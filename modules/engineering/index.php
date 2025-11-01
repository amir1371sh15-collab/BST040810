<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.view')) {
    die('شما مجوز دسترسی به این ماژول را ندارید.');
}

$pageTitle = "داشبورد ماژول مهندسی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ماژول مهندسی</h1>
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<p class="lead">در این بخش می‌توانید اطلاعات مربوط به پروژه‌ها، سفارشات، نت و سایر موارد مهندسی را مدیریت کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-4">

    <?php if (has_permission('engineering.maintenance.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/maintenance_dashboard.php">
                <div class="card-body"><div class="icon mb-3"><i class="bi bi-heart-pulse-fill"></i></div><h5 class="card-title">نگهداری و تعمیرات (نت)</h5><p class="card-text">ثبت و پیگیری گزارشات خرابی قالب‌ها و ماشین‌آلات.</p></div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('engineering.base_info')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/base_info_dashboard.php">
                <div class="card-body"><div class="icon mb-3"><i class="bi bi-journal-plus"></i></div><h5 class="card-title">اطلاعات پایه مهندسی</h5><p class="card-text">مدیریت پیمانکاران، اولویت‌ها، وضعیت‌ها و انواع اقدامات نت.</p></div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('engineering.projects.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/projects.php">
                <div class="card-body"><div class="icon mb-3"><i class="bi bi-kanban-fill"></i></div><h5 class="card-title">مدیریت پروژه‌ها</h5><p class="card-text">تعریف پروژه‌های جدید، تخصیص وظایف و پیگیری پیشرفت آن‌ها.</p></div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (has_permission('engineering.changes.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/engineering_changes.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-pencil-square"></i></div>
                    <h5 class="card-title">مدیریت تغییرات</h5>
                    <p class="card-text">ثبت، پیگیری و بازخورد تغییرات مهندسی.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('engineering.spare_parts.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/spare_parts_dashboard.php">
                <div class="card-body"><div class="icon mb-3"><i class="bi bi-archive-fill"></i></div><h5 class="card-title">انبار قطعات یدکی</h5><p class="card-text">مدیریت موجودی، سفارشات و تراکنش‌های قطعات یدکی قالب‌ها.</p></div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('engineering.tools.view')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="<?php echo BASE_URL; ?>modules/engineering/eng_tools_dashboard.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-hammer"></i></div>
                    <h5 class="card-title">انبار ابزار مهندسی</h5>
                    <p class="card-text">مدیریت ابزارهای عمومی و ثبت تراکنش‌های آن‌ها.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

