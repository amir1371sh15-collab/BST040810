<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.changes.view') || !isset($_GET['change_id']) || !is_numeric($_GET['change_id'])) {
    header("Location: " . BASE_URL . "modules/engineering/engineering_changes.php");
    exit;
}

$change_id = (int)$_GET['change_id'];

// Fetch parent change details
$change_details_query = "
    SELECT ec.*, 
           emp.name as ApproverName,
           CASE 
               WHEN ec.ChangeType = 'Mold' THEN m.MoldName
               WHEN ec.ChangeType = 'Process' THEN p.ProcessName
           END as EntityName
    FROM tbl_engineering_changes ec
    LEFT JOIN tbl_employees emp ON ec.ApprovedByEmployeeID = emp.EmployeeID
    LEFT JOIN tbl_molds m ON ec.ChangeType = 'Mold' AND ec.EntityID = m.MoldID
    LEFT JOIN tbl_processes p ON ec.ChangeType = 'Process' AND ec.EntityID = p.ProcessID
    WHERE ec.ChangeID = ?
";
$change = find_all($pdo, $change_details_query, [$change_id])[0] ?? null;

if (!$change) {
    header("Location: " . BASE_URL . "modules/engineering/engineering_changes.php");
    exit;
}

const TABLE_NAME = 'tbl_engineering_change_feedback';
const PRIMARY_KEY = 'FeedbackID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (!has_permission('engineering.changes.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $data = [
                'ChangeID' => $change_id,
                'FeedbackDate' => to_gregorian($_POST['feedback_date']),
                'FeedbackDescription' => trim($_POST['feedback_description']),
                'FutureActions' => trim($_POST['future_actions']),
            ];

            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
        }
         $_SESSION['message'] = $result['message'];
         $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.changes.manage')) {
        die('شما مجوز ویرایش را ندارید.');
    }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$feedbacks = find_all($pdo, "SELECT * FROM " . TABLE_NAME . " WHERE ChangeID = ? ORDER BY FeedbackDate DESC", [$change_id]);

$pageTitle = "بازخورد تغییرات";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-0">بازخورد و اقدامات آتی</h1>
        <small class="text-muted">مربوط به تغییر: <?php echo htmlspecialchars($change['EntityName']); ?> (<?php echo to_jalali($change['ChangeDate']); ?>)</small>
    </div>
    <a href="engineering_changes.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت به لیست تغییرات</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card content-card mb-4">
    <div class="card-header"><h5 class="mb-0">جزئیات تغییر اصلی</h5></div>
    <div class="card-body">
        <p><strong>علل تغییر:</strong> <?php echo nl2br(htmlspecialchars($change['ReasonForChange'])); ?></p>
        <p><strong>تغییرات انجام شده:</strong> <?php echo nl2br(htmlspecialchars($change['ChangesMade'])); ?></p>
    </div>
</div>

<div class="row">
    <?php if (has_permission('engineering.changes.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش بازخورد' : 'ثبت بازخورد جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="change_feedback.php?change_id=<?php echo $change_id; ?>">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label class="form-label">تاریخ بازخورد</label><input type="text" class="form-control persian-date" name="feedback_date" value="<?php echo to_jalali($itemToEdit['FeedbackDate'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">شرح بازخورد</label><textarea class="form-control" name="feedback_description" rows="4" required><?php echo htmlspecialchars($itemToEdit['FeedbackDescription'] ?? ''); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">اقدامات آتی</label><textarea class="form-control" name="future_actions" rows="3"><?php echo htmlspecialchars($itemToEdit['FutureActions'] ?? ''); ?></textarea></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'ثبت'; ?></button>
                    <?php if ($editMode): ?><a href="change_feedback.php?change_id=<?php echo $change_id; ?>" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?php echo has_permission('engineering.changes.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست بازخوردها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr>
                            <th class="p-3">تاریخ</th><th class="p-3">شرح بازخورد</th><th class="p-3">اقدامات آتی</th>
                            <?php if(has_permission('engineering.changes.manage')): ?><th class="p-3">عملیات</th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach($feedbacks as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo to_jalali($item['FeedbackDate']); ?></td>
                                <td class="p-3"><?php echo nl2br(htmlspecialchars($item['FeedbackDescription'])); ?></td>
                                <td class="p-3"><?php echo nl2br(htmlspecialchars($item['FutureActions'])); ?></td>
                                 <?php if(has_permission('engineering.changes.manage')): ?>
                                <td class="p-3">
                                    <a href="?change_id=<?php echo $change_id; ?>&edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                      <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف این بازخورد مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                          <form method="POST" action=""><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                        </div>
                                      </div></div>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
