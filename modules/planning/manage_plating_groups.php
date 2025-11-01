<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_planning_plating_groups';
const PRIMARY_KEY = 'GroupID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'GroupName' => trim($_POST['group_name']),
            'SetupTimeMinutes' => (int)$_POST['setup_time_minutes'],
        ];

        if (empty($data['GroupName'])) {
            $result = ['success' => false, 'message' => 'نام گروه الزامی است.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/Planning/manage_plating_groups.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// Data for table
$items = find_all($pdo, "
    SELECT g.*, COUNT(pg.PartID) as PartCount
    FROM " . TABLE_NAME . " g
    LEFT JOIN tbl_planning_part_to_group pg ON g.GroupID = pg.GroupID
    GROUP BY g.GroupID
    ORDER BY g.GroupName
");

$pageTitle = "مدیریت گروه‌های آبکاری";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="constraints_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش گروه' : 'افزودن گروه جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="manage_plating_groups.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="group_name" class="form-label">نام گروه *</label>
                        <input type="text" class="form-control" id="group_name" name="group_name" 
                               value="<?php echo htmlspecialchars($itemToEdit['GroupName'] ?? ''); ?>" placeholder="مثال: روی-سیانوری" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="setup_time_minutes" class="form-label">زمان ستاپ/تعویض (دقیقه)</label>
                        <input type="number" class="form-control" id="setup_time_minutes" name="setup_time_minutes" 
                               value="<?php echo htmlspecialchars($itemToEdit['SetupTimeMinutes'] ?? '0'); ?>">
                        <small class="text-muted">زمان لازم برای جابجایی از گروه دیگر به این گروه.</small>
                    </div>
                    
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="manage_plating_groups.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">گروه‌های تعریف شده</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">نام گروه</th><th class="p-3">زمان ستاپ (دقیقه)</th><th class="p-3">تعداد قطعات</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="4" class="text-center p-3 text-muted">هیچ گروهی تعریف نشده است.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['GroupName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['SetupTimeMinutes']); ?></td>
                                <td class="p-3"><?php echo $item['PartCount']; ?></td>
                                <td class="p-3">
                                    <a href="manage_part_to_group.php?group_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-info btn-sm" title="اتصال قطعات"><i class="bi bi-link-45deg"></i></a>
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                    
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                        <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف این گروه مطمئن هستید؟ (تمام اتصالات قطعات به این گروه نیز حذف خواهد شد)</div>
                                        <div class="modal-footer">
                                            <form method="POST"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                        </div>
                                        </div></div>
                                    </div>
                                </td>
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

