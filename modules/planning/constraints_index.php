<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "مرکز مدیریت محدودیت‌ها و ظرفیت‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right me-1"></i>
            بازگشت به منوی برنامه‌ریزی
        </a>
    </div>
    <p class="mb-3">
        از این بخش برای تعریف قوانین و محدودیت‌های کلیدی فرایندهای تولید جهت استفاده در ماژول برنامه‌ریزی استفاده کنید.
    </p>

    <div class="row">
        <!-- Card 1: Station Capacity -->
        <?php if (has_permission('planning_constraints.manage')): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-gear-wide-connected me-2"></i>مدیریت ظرفیت ایستگاه‌ها</h5>
                    <p class="card-text small text-muted">
                        تعریف و مدیریت ظرفیت تولیدی (OEE، ثابت و...) برای ایستگاه‌های کلیدی مانند پرسکاری، مونتاژ، رول، آبکاری و بسته‌بندی.
                    </p>
                    <div class="mt-auto">
                        <a href="manage_station_capacity.php" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> مدیریت ظرفیت</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 2: Plating Batch Rules -->
        <?php if (has_permission('planning_constraints.manage')): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-bucket-fill me-2"></i>مدیریت قوانین آبکاری (بچینگ)</h5>
                    <p class="card-text small text-muted">
                        تعریف وزن بارل (تکی و ترکیبی) و مشخص کردن اینکه کدام قطعات می‌توانند با هم در یک بارل آبکاری شوند.
                    </p>
                    <div class="mt-auto">
                        <a href="manage_plating_rules.php" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> مدیریت قوانین آبکاری</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Card 3: Vibration Incompatibility -->
        <?php if (has_permission('planning_constraints.manage')): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary"><i class="bi bi-diagram-3-fill me-2"></i>مدیریت ناسازگاری ویبره</h5>
                    <p class="card-text small text-muted">
                        تعریف قطعاتی که نمی‌توانند پشت سر هم در دستگاه ویبره قرار گیرند تا از اتلاف وقت برای تمیزکاری جلوگیری شود.
                    </p>
                    <div class="mt-auto">
                        <a href="manage_vibration_incompatibility.php" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> مدیریت ناسازگاری</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Old Links (Now replaced by the new system) -->
        <?php /*
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 bg-light">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-muted">مدیریت گروه‌های آبکاری (منسوخ شده)</h5>
                    <p class="card-text small text-muted">تعریف گروه‌های اصلی آبکاری (مثلا: روی-سیانوری) برای مدیریت بچینگ.</p>
                    <div class="mt-auto">
                        <a href="manage_plating_groups.php" class="btn btn-outline-secondary disabled">مدیریت گروه‌ها</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 bg-light">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-muted">اتصال قطعه به گروه (منسوخ شده)</h5>
                    <p class="card-text small text-muted">اتصال هر قطعه به گروه آبکاری مربوط به خود.</p>
                    <div class="mt-auto">
                        <a href="manage_part_to_group.php" class="btn btn-outline-secondary disabled">مدیریت اتصالات</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 bg-light">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-muted">مدیریت سازگاری بچ (منسوخ شده)</h5>
                    <p class="card-text small text-muted">تعریف اینکه کدام قطعات می‌توانند با هم در یک بچ (Batch) آبکاری قرار گیرند.</p>
                    <div class="mt-auto">
                        <a href="manage_batch_compatibility.php" class="btn btn-outline-secondary disabled">مدیریت سازگاری</a>
                    </div>
                </div>
            </div>
        </div>
        */ ?>

    </div> <!-- /.row -->
</div> <!-- /.container-fluid -->

<?php include __DIR__ . '/../../templates/footer.php'; ?>

