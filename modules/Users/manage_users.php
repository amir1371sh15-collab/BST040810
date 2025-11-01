<?php
require_once __DIR__ . '/../../config/db.php';

const TABLE_NAME = 'tbl_users';
const PRIMARY_KEY = 'UserID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'Username' => trim($_POST['username']),
            'EmployeeID' => (int)$_POST['employee_id'],
            'RoleID' => (int)$_POST['role_id'],
        ];

        if (!empty($_POST['password'])) {
            $data['PasswordHash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
        } else {
            if (empty($_POST['password'])) {
                $result = ['success' => false, 'message' => 'رمز عبور برای کاربر جدید الزامی است.'];
                $_SESSION['message_type'] = 'warning';
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
        }
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/users/manage_users.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT u.*, e.name as EmployeeName, r.RoleName FROM " . TABLE_NAME . " u JOIN tbl_employees e ON u.EmployeeID = e.EmployeeID JOIN tbl_roles r ON u.RoleID = r.RoleID ORDER BY u.Username");
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$roles = find_all($pdo, "SELECT RoleID, RoleName FROM tbl_roles ORDER BY RoleName");

$pageTitle = "مدیریت کاربران";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت کاربران</h1>
    <a href="<?php echo BASE_URL; ?>modules/users/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش کاربر' : 'افزودن کاربر جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="manage_users.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label for="username" class="form-label">نام کاربری</label><input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($itemToEdit['Username'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label for="password" class="form-label">رمز عبور</label><input type="password" class="form-control" id="password" name="password" <?php echo !$editMode ? 'required' : ''; ?>><small class="form-text text-muted"><?php echo $editMode ? 'برای تغییر رمز عبور، آن را وارد کنید.' : ''; ?></small></div>
                    <div class="mb-3"><label for="employee_id" class="form-label">کارمند مرتبط</label><select class="form-select" id="employee_id" name="employee_id" required><option value="">انتخاب کنید</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo ($editMode && $itemToEdit['EmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label for="role_id" class="form-label">نقش</label><select class="form-select" id="role_id" name="role_id" required><option value="">انتخاب کنید</option><?php foreach ($roles as $role): ?><option value="<?php echo $role['RoleID']; ?>" <?php echo ($editMode && $itemToEdit['RoleID'] == $role['RoleID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['RoleName']); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i> <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="manage_users.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست کاربران</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-striped table-hover mb-0">
                    <thead><tr><th class="p-3">نام کاربری</th><th class="p-3">نام کارمند</th><th class="p-3">نقش</th><th class="p-3">عملیات</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="p-3"><?php echo htmlspecialchars($item['Username']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($item['EmployeeName']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($item['RoleName']); ?></td>
                            <td class="p-3">
                                <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                  <div class="modal-dialog"><div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">آیا از حذف کاربر "<?php echo htmlspecialchars($item['Username']); ?>" مطمئن هستید؟</div>
                                    <div class="modal-footer">
                                      <form method="POST" action="manage_users.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
                                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                    </div>
                                  </div></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

