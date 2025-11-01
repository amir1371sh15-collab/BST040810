<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('planning_constraints.view')) {
    die('شما مجوز دسترسی به این ماژول را ندارید.');
}

$pageTitle = "داشبورد محدودیت‌ها و ظرفیت‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت محدودیت‌ها و ظرفیت‌ها (FCS)</h1>
    <a href="<?php echo BASE_URL; ?>modules/Planning/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به داشبورد برنامه‌ریزی
    </a>
</div>

<p class="lead mt-3">در این بخش، قوانین، محدودیت‌ها و ظرفیت‌های واقعی خطوط تولید (مانند نیروی انسانی، زمان ستاپ و سازگاری فرآیندها) را برای استفاده در زمان‌بندی پیشرفته (FCS) تعریف کنید.</p>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">

    <?php 
    // کارت جدید برای بازبینی ظرفیت
    if (has_permission('planning_constraints.planning_capacity.run')): 
    ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm card-highlight">
            <a href="capacity_planning.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-calendar-check-fill"></i></div>
                    <h5 class="card-title">برنامه‌ریزی و بازبینی ظرفیت</h5>
                    <p class="card-text">محاسبه، بازبینی و تایید ظرفیت نهایی ایستگاه‌ها برای MRP.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php 
    if (has_permission('planning_constraints.manage')): 
    ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="manage_station_capacity.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-sliders"></i></div>
                    <h5 class="card-title">مدیریت قوانین ظرفیت</h5>
                    <p class="card-text">تعریف "روش محاسبه" ظرفیت برای هر ایستگاه (OEE، نفر-ساعت، ...).</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning_constraints.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="manage_plating_groups.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-collection-fill"></i></div>
                    <h5 class="card-title">مدیریت گروه‌های آبکاری</h5>
                    <p class="card-text">تعریف گروه‌های فرآیندی آبکاری و زمان ستاپ بین آن‌ها.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (has_permission('planning_constraints.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="manage_part_to_group.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-link-45deg"></i></div>
                    <h5 class="card-title">اتصال قطعه به گروه آبکاری</h5>
                    <p class="card-text">مشخص کنید هر قطعه به کدام گروه آبکاری تعلق دارد.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('planning_constraints.manage')): ?>
    <div class="col">
        <div class="card h-100 module-card shadow-sm">
            <a href="manage_batch_compatibility.php">
                <div class="card-body">
                    <div class="icon mb-3"><i class="bi bi-boxes"></i></div>
                    <h5 class="card-title">مدیریت سازگاری بچ</h5>
                    <p class="card-text">تعریف اینکه کدام قطعات می‌توانند با هم در یک بچ تولید (مثلاً آبکاری) شوند.</p>
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

