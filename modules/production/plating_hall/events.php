<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.plating_hall.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'Description' => trim($_POST['description']),
        'EmployeeID' => (int)$_SESSION['user_id'] // Assuming the logged-in user is the one logging the event
    ];
    if(!empty($data['Description'])) {
        insert_record($pdo, 'tbl_plating_events_log', $data);
        $_SESSION['message'] = 'رویداد با موفقیت ثبت شد.';
        $_SESSION['message_type'] = 'success';
    }
    header("Location: events.php");
    exit;
}

// New UNION query to fetch both manual events and production log descriptions
$events_sql = "
(
    SELECT 
        e.EventDate as LogTimestamp, 
        e.Description, 
        emp.name as SubmitterName,
        'رویداد دستی' as LogType,
        e.EventDate as OrderDate
    FROM tbl_plating_events_log e
    JOIN tbl_employees emp ON e.EmployeeID = emp.EmployeeID
)
UNION ALL
(
    SELECT 
        TIMESTAMP(h.LogDate, '17:00:00') as LogTimestamp, -- Assume production log descriptions are for end of day
        h.Description, 
        'سیستم (ثبت تولید)' as SubmitterName,
        'توضیحات تولید' as LogType,
        h.LogDate as OrderDate
    FROM tbl_plating_log_header h
    WHERE h.Description IS NOT NULL AND h.Description != ''
)
ORDER BY OrderDate DESC, LogTimestamp DESC
LIMIT 100
";
$events = find_all($pdo, $events_sql);


$pageTitle = "ثبت وقایع سالن آبکاری";
include __DIR__ . '/../../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت وقایع سالن آبکاری</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">ثبت رویداد جدید</h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3"><label class="form-label">تاریخ و ساعت</label><input type="text" class="form-control" value="<?php echo to_jalali(date('Y-m-d H:i:s')); ?>" disabled></div>
                    <div class="mb-3"><label class="form-label">توضیحات رویداد</label><textarea name="description" class="form-control" rows="5" required></textarea></div>
                    <div class="mb-3"><label class="form-label">ثبت کننده</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" disabled></div>
                    <button type="submit" class="btn btn-primary">ثبت رویداد</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">آخرین رویدادها و توضیحات ثبت شده</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">تاریخ</th><th class="p-3">توضیحات</th><th class="p-3">ثبت کننده / نوع</th></tr></thead>
                        <tbody>
                            <?php if (empty($events)): ?>
                                <tr><td colspan="3" class="text-center p-3 text-muted">هیچ موردی یافت نشد.</td></tr>
                            <?php else: ?>
                                <?php foreach($events as $event): ?>
                                <tr>
                                    <td class="p-3 text-nowrap"><?php echo to_jalali($event['LogTimestamp']); ?></td>
                                    <td class="p-3" style="white-space: pre-wrap;"><?php echo htmlspecialchars($event['Description']); ?></td>
                                    <td class="p-3 text-nowrap">
                                        <?php echo htmlspecialchars($event['SubmitterName']); ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($event['LogType']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../templates/footer.php'; ?>
