<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('planning.run_mrp')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "اجرای برنامه‌ریزی MRP";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="alert alert-info mt-4">
    <h4 class="alert-heading">در دست ساخت!</h4>
    <p>
        این صفحه، مغز متفکر سیستم برنامه‌ریزی خواهد بود. در این بخش، سیستم به صورت خودکار موارد زیر را انجام خواهد داد:
    </p>
    <hr>
    <ul class="mb-0">
        <li>بررسی سفارشات فروش باز (تقاضا).</li>
        <li>بررسی موجودی انبارهای محصول نهایی، نیمه‌ساخته و مواد اولیه.</li>
        <li>محاسبه نیاز خالص (Net Requirement) بر اساس ساختار محصول (BOM).</li>
        <li>ارائه "برنامه پیشنهادی تولید" (برای پرسکاری، آبکاری، مونتاژ).</li>
        <li>ارائه "برنامه پیشنهادی خرید" (برای مواد اولیه و متفرقه).</li>
    </ul>
    <p class="mt-3">
        این ماژول پس از تکمیل ورود داده‌های BOM و سفارشات، پیاده‌سازی خواهد شد.
    </p>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
