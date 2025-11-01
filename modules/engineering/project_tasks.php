<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.projects.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header("Location: projects.php"); exit;
}
$project_id = (int)$_GET['project_id'];
$project = find_by_id($pdo, 'tbl_projects', $project_id, 'ProjectID');
if (!$project) {
    header("Location: projects.php"); exit;
}

const TABLE_NAME = 'tbl_project_tasks';
const PRIMARY_KEY = 'TaskID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('engineering.projects.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $data = [
                'ProjectID' => $project_id,
                'TaskDescription' => trim($_POST['task_description']),
                'ResponsibleEmployeeID' => !empty($_POST['responsible_employee_id']) ? (int)$_POST['responsible_employee_id'] : null,
                'StartDate' => !empty($_POST['start_date']) ? to_gregorian($_POST['start_date']) : null,
                'TaskStatusID' => (int)$_POST['task_status_id'],
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
    header("Location: " . BASE_URL . "modules/engineering/project_tasks.php?project_id=" . $project_id);
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.projects.manage')) {
        die('شما مجوز ویرایش این وظیفه را ندارید.');
    }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT t.*, e.name as employee_name, ts.StatusName FROM " . TABLE_NAME . " t LEFT JOIN tbl_employees e ON t.ResponsibleEmployeeID = e.EmployeeID JOIN tbl_task_statuses ts ON t.TaskStatusID = ts.TaskStatusID WHERE t.ProjectID = ? ORDER BY t.StartDate", [$project_id]);
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$task_statuses = find_all($pdo, "SELECT TaskStatusID, StatusName FROM tbl_task_statuses");

$pageTitle = "مدیریت وظایف پروژه: " . $project['ProjectName'];
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-0">مدیریت وظایف</h1>
        <small class="text-muted">پروژه: <?php echo htmlspecialchars($project['ProjectName']); ?></small>
    </div>
    <a href="projects.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت به پروژه‌ها</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <?php if (has_permission('engineering.projects.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش وظیفه' : 'افزودن وظیفه جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="project_tasks.php?project_id=<?php echo $project_id; ?>">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">شرح وظیفه</label><textarea class="form-control" name="task_description" rows="3" required><?php echo htmlspecialchars($itemToEdit['TaskDescription'] ?? ''); ?></textarea></div>
                <div class="mb-3"><label class="form-label">تاریخ شروع</label><input type="text" class="form-control persian-date" name="start_date" value="<?php echo to_jalali($itemToEdit['StartDate'] ?? ''); ?>" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">مسئول</label><select class="form-select" name="responsible_employee_id"><option value="">انتخاب کنید</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo (isset($itemToEdit['ResponsibleEmployeeID']) && $itemToEdit['ResponsibleEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">وضعیت</label><select class="form-select" name="task_status_id" required><option value="">انتخاب کنید</option><?php foreach ($task_statuses as $status): ?><option value="<?php echo $status['TaskStatusID']; ?>" <?php echo (isset($itemToEdit['TaskStatusID']) && $itemToEdit['TaskStatusID'] == $status['TaskStatusID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status['StatusName']); ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i> <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="project_tasks.php?project_id=<?php echo $project_id; ?>" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <?php endif; ?>
    <div class="<?php echo has_permission('engineering.projects.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست وظایف</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">شرح وظیفه</th><th class="p-3">مسئول</th><th class="p-3">تاریخ شروع</th><th class="p-3">وضعیت</th><?php if (has_permission('engineering.projects.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-3" style="white-space: pre-wrap;"><?php echo htmlspecialchars($item['TaskDescription']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['employee_name'] ?? '-'); ?></td>
                <td class="p-3"><?php echo to_jalali($item['StartDate']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['StatusName']); ?></td>
                <?php if (has_permission('engineering.projects.manage')): ?>
                <td class="p-3">
                    <a href="?project_id=<?php echo $project_id; ?>&edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف این وظیفه مطمئن هستید؟</div>
                        <div class="modal-footer">
                            <form method="POST" action="project_tasks.php?project_id=<?php echo $project_id; ?>" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                        </div>
                    </div></div></div>
                </td>
                <?php endif; ?>
            </tr><?php endforeach; ?></tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

