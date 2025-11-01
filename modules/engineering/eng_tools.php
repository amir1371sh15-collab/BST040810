<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.tools.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_eng_tools';
const PRIMARY_KEY = 'ToolID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('engineering.tools.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $data = [
                'ToolName' => trim($_POST['tool_name']),
                'ToolTypeID' => !empty($_POST['tool_type_id']) ? (int)$_POST['tool_type_id'] : null,
                'DepartmentID' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            ];

            if (empty($data['ToolName'])) {
                $result = ['success' => false, 'message' => 'نام ابزار الزامی است.'];
            } else {
                if (isset($_POST['id']) && !empty($_POST['id'])) { // Update
                    $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                } else { // Insert
                    $temp_result = insert_record($pdo, TABLE_NAME, $data);
                    if ($temp_result['success']) {
                        $newId = $temp_result['id'];
                        $toolCode = 'T-' . ($data['ToolTypeID'] ?? 0) . '-' . ($data['DepartmentID'] ?? 0) . '-' . $newId;
                        // Update the record with the new tool code
                        $result = update_record($pdo, TABLE_NAME, ['ToolCode' => $toolCode], $newId, PRIMARY_KEY);
                        $result['message'] = 'ابزار با موفقیت ایجاد شد.';
                    } else {
                        $result = $temp_result;
                    }
                }
            }
        }
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header("Location: " . BASE_URL . "modules/engineering/eng_tools.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.tools.manage')) die('شما مجوز ویرایش را ندارید.');
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT t.*, tt.TypeName, d.DepartmentName FROM " . TABLE_NAME . " t LEFT JOIN tbl_eng_tool_types tt ON t.ToolTypeID = tt.ToolTypeID LEFT JOIN tbl_departments d ON t.DepartmentID = d.DepartmentID ORDER BY t.ToolName");
$tool_types = find_all($pdo, "SELECT * FROM tbl_eng_tool_types ORDER BY TypeName");
$departments = find_all($pdo, "SELECT * FROM tbl_departments ORDER BY DepartmentName");

$pageTitle = "مدیریت ابزارهای مهندسی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت ابزارها</h1>
    <a href="eng_tools_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <?php if (has_permission('engineering.tools.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش ابزار' : 'افزودن ابزار جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="eng_tools.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label class="form-label">نام ابزار</label><input type="text" class="form-control" name="tool_name" value="<?php echo htmlspecialchars($itemToEdit['ToolName'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label class="form-label">نوع ابزار</label><select class="form-select" name="tool_type_id"><option value="">انتخاب کنید...</option><?php foreach ($tool_types as $type): ?><option value="<?php echo $type['ToolTypeID']; ?>" <?php echo ($editMode && $itemToEdit['ToolTypeID'] == $type['ToolTypeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['TypeName']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">دپارتمان</label><select class="form-select" name="department_id"><option value="">انتخاب کنید...</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['DepartmentID']; ?>" <?php echo ($editMode && $itemToEdit['DepartmentID'] == $dept['DepartmentID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['DepartmentName']); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="eng_tools.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo has_permission('engineering.tools.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست ابزارها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-striped table-hover mb-0">
                    <thead><tr><th class="p-3">کد</th><th class="p-3">نام ابزار</th><th class="p-3">نوع</th><th class="p-3">دپارتمان</th><?php if (has_permission('engineering.tools.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="p-3"><?php echo htmlspecialchars($item['ToolCode']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($item['ToolName']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($item['TypeName'] ?? '-'); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($item['DepartmentName'] ?? '-'); ?></td>
                            <?php if (has_permission('engineering.tools.manage')): ?>
                            <td class="p-3">
                                <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف ابزار "<?php echo htmlspecialchars($item['ToolName']); ?>" مطمئن هستید؟</div><div class="modal-footer"><form method="POST" action="eng_tools.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button></div></div></div></div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
