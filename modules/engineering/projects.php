<?php
// Use the master init file which handles security and all helpers
require_once __DIR__ . '/../../config/init.php';

// Page-level permission check
if (!has_permission('engineering.projects.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_projects';
const PRIMARY_KEY = 'ProjectID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // Action-level permission check
        if (!has_permission('engineering.projects.manage')) {
            $_SESSION['message'] = 'شما مجوز حذف پروژه را ندارید.';
            $_SESSION['message_type'] = 'danger';
        } else {
            // Also delete related tasks for data integrity
            delete_record($pdo, 'tbl_project_tasks', (int)$_POST['delete_id'], 'ProjectID');
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
    } 
    elseif (isset($_POST['project_name'])) {
        $data = [
            'ProjectName' => trim($_POST['project_name']),
            'Description' => trim($_POST['description']),
            'StartDate' => to_gregorian($_POST['start_date']),
            'Deadline' => !empty($_POST['deadline']) ? to_gregorian($_POST['deadline']) : null,
            'CurrentStage' => trim($_POST['current_stage']),
            'ResponsibleEmployeeID' => !empty($_POST['responsible_employee_id']) ? (int)$_POST['responsible_employee_id'] : null,
            'PriorityID' => (int)$_POST['priority_id'],
        ];
        
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            if (!has_permission('engineering.projects.manage')) {
                $_SESSION['message'] = 'شما مجوز ویرایش پروژه را ندارید.';
                $_SESSION['message_type'] = 'danger';
            } else {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        } else {
            if (!has_permission('engineering.projects.manage')) {
                $_SESSION['message'] = 'شما مجوز ایجاد پروژه جدید را ندارید.';
                $_SESSION['message_type'] = 'danger';
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        }
    }
    header("Location: " . BASE_URL . "modules/engineering/projects.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.projects.manage')) {
        die('شما مجوز ویرایش این پروژه را ندارید.');
    }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items_query = "SELECT p.*, e.name as employee_name, pr.PriorityName 
                FROM " . TABLE_NAME . " p 
                LEFT JOIN tbl_employees e ON p.ResponsibleEmployeeID = e.EmployeeID 
                LEFT JOIN tbl_priorities pr ON p.PriorityID = pr.PriorityID 
                ORDER BY p.StartDate DESC";
$items = find_all($pdo, $items_query);
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$priorities = find_all($pdo, "SELECT PriorityID, PriorityName FROM tbl_priorities ORDER BY PriorityID");

$pageTitle = "مدیریت پروژه‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت پروژه‌ها</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <?php if (has_permission('engineering.projects.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش پروژه' : 'افزودن پروژه جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="projects.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label class="form-label">نام پروژه</label><input type="text" class="form-control" name="project_name" value="<?php echo htmlspecialchars($itemToEdit['ProjectName'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label class="form-label">توضیحات</label><textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($itemToEdit['Description'] ?? ''); ?></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">تاریخ شروع</label><input type="text" class="form-control persian-date" name="start_date" value="<?php echo to_jalali($itemToEdit['StartDate'] ?? date('Y-m-d')); ?>" autocomplete="off"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">ددلاین</label><input type="text" class="form-control persian-date" name="deadline" value="<?php echo to_jalali($itemToEdit['Deadline'] ?? ''); ?>" autocomplete="off"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">مرحله فعلی</label><input type="text" class="form-control" name="current_stage" value="<?php echo htmlspecialchars($itemToEdit['CurrentStage'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label">مسئول</label><select class="form-select" name="responsible_employee_id"><option value="">انتخاب کنید</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo (isset($itemToEdit['ResponsibleEmployeeID']) && $itemToEdit['ResponsibleEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">اولویت</label><select class="form-select" name="priority_id" required><option value="">انتخاب کنید</option><?php foreach ($priorities as $priority): ?><option value="<?php echo $priority['PriorityID']; ?>" <?php echo (isset($itemToEdit['PriorityID']) && $itemToEdit['PriorityID'] == $priority['PriorityID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($priority['PriorityName']); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i> <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="projects.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo has_permission('engineering.projects.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست پروژه‌ها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">نام پروژه</th><th class="p-3">مسئول</th><th class="p-3">تاریخ شروع</th><th class="p-3">ددلاین</th><th class="p-3">اولویت</th>
                        <?php if (has_permission('engineering.projects.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['ProjectName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['employee_name'] ?? '-'); ?></td>
                                <td class="p-3"><?php echo to_jalali($item['StartDate']); ?></td>
                                <td class="p-3"><?php echo to_jalali($item['Deadline']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['PriorityName']); ?></td>
                                
                                <?php if (has_permission('engineering.projects.manage')): ?>
                                <td class="p-3">
                                    <a href="project_tasks.php?project_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-info btn-sm" title="مشاهده وظایف"><i class="bi bi-list-task"></i></a>
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                      <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف پروژه "<?php echo htmlspecialchars($item['ProjectName']); ?>" مطمئن هستید؟ (تمام وظایف آن نیز حذف خواهد شد)</div>
                                        <div class="modal-footer">
                                          <form method="POST" action="projects.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
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

