<?php
require_once __DIR__ . '/../../config/init.php';
// مجوزهای جدید انبار مواد اولیه
if (!has_permission('warehouse.raw.view')) { die('شما مجوز دسترسی به این ماژول را ندارید.'); }

$can_manage_raw = has_permission('warehouse.raw.manage');

$pageTitle = "داشبورد انبار مواد اولیه";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>modules/Warehouse/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت به ماژول انبار</a>
</div>

<p class="lead">مدیریت دسته‌بندی‌ها، اقلام، تراکنش‌ها و گزارشات انبار مواد اولیه.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-4">

    <!-- قسمت 1: تعریف دسته‌بندی -->
    <?php if ($can_manage_raw): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_categories.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-tags-fill"></i></div>
                    <h5 class="card-title">۱. تعریف دسته‌بندی مواد اولیه</h5>
                    <p class="card-text">مدیریت انواع مواد اولیه.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- قسمت 2: تعریف مواد -->
     <?php if ($can_manage_raw): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_items.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">۲. تعریف مواد اولیه</h5>
                    <p class="card-text">تعریف نام، دسته‌بندی، واحد و موجودی اطمینان مواد.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- قسمت 3: انبارگردانی -->
     <?php if ($can_manage_raw): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_stocktake.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-clipboard-check"></i></div>
                    <h5 class="card-title">۳. انبارگردانی</h5>
                    <p class="card-text">ثبت موجودی اولیه یا تعدیلات انبارگردانی مواد اولیه.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- قسمت 4: ثبت تراکنش -->
     <?php if ($can_manage_raw): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_transactions.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-input-cursor-text"></i></div>
                    <h5 class="card-title">۴. ثبت تراکنش (ورود/خروج)</h5>
                    <p class="card-text">ثبت تراکنش‌های ساده ورود و خروج مواد اولیه.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- قسمت 5: گزارش گردش -->
    <?php if ($can_manage_raw): // یا warehouse.raw.view ?>
     <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="raw_inventory_report.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                    <h5 class="card-title">۵. گزارش گردش موجودی</h5>
                    <p class="card-text">مشاهده تاریخچه تراکنش‌ها و موجودی مواد اولیه.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
