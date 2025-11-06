<?php
require_once __DIR__ . '/../../config/init.php';

// Permission check
if (!has_permission('engineering.tools.view')) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$pageTitle = "داشبورد انبار ابزار مهندسی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">انبار ابزار مهندسی</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به ماژول مهندسی
    </a>
</div>

<p class="lead">در این بخش می‌توانید ابزارهای عمومی و مصرفی واحد مهندسی را تعریف کرده و ورود و خروج آن‌ها را ثبت نمایید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
    <div class="col"><div class="card h-100 module-card shadow-sm"><a href="eng_tool_types.php"><div class="card-body"><div class="icon mb-3"><i class="bi bi-hammer"></i></div><h5 class="card-title">انواع ابزار مهندسی</h5><p class="card-text">تعریف، ویرایش و مشاهده انواع ابزارها.</p></div></a></div></div>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="eng_tools.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-tools"></i></div>
                    <h5 class="card-title">مدیریت ابزارها</h5>
                    <p class="card-text">تعریف، ویرایش و مشاهده لیست ابزارهای مهندسی.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="eng_tool_transactions.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-arrows-move"></i></div>
                    <h5 class="card-title">ثبت تراکنش ابزار</h5>
                    <p class="card-text">ثبت ورود و خروج ابزارها از انبار.</p>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>
