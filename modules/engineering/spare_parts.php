<?php
// Use the master init file which handles security and all helpers
require_once __DIR__ . '/../../config/init.php';

// Page-level permission check
if (!has_permission('engineering.spare_parts.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_eng_spare_parts';
const PRIMARY_KEY = 'PartID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action-level permission check
    if (!has_permission('engineering.spare_parts.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $data = [
                'PartCode' => trim($_POST['part_code']),
                'PartName' => trim($_POST['part_name']),
                'MoldID' => !empty($_POST['mold_id']) ? (int)$_POST['mold_id'] : null,
                'ReorderPoint' => !empty($_POST['reorder_point']) ? (int)$_POST['reorder_point'] : 0,
            ];
            if (empty($data['PartCode']) || empty($data['PartName'])) {
                 $result = ['success' => false, 'message' => 'کد و نام قطعه نمی‌تواند خالی باشد.'];
                 $_SESSION['message_type'] = 'warning';
            } else {
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/engineering/spare_parts.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.spare_parts.manage')) {
        die('شما مجوز ویرایش را ندارید.');
    }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT sp.*, m.MoldName FROM " . TABLE_NAME . " sp LEFT JOIN tbl_molds m ON sp.MoldID = m.MoldID ORDER BY sp.PartName");
$molds = find_all($pdo, "SELECT MoldID, MoldName FROM tbl_molds ORDER BY MoldName");

$pageTitle = "مدیریت قطعات یدکی قالب‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت قطعات یدکی قالب‌ها</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/spare_parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <?php if (has_permission('engineering.spare_parts.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش قطعه' : 'افزودن قطعه جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="spare_parts.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label class="form-label">کد قطعه</label><input type="text" class="form-control" name="part_code" value="<?php echo htmlspecialchars($itemToEdit['PartCode'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label class="form-label">نام قطعه</label><input type="text" class="form-control" name="part_name" value="<?php echo htmlspecialchars($itemToEdit['PartName'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label class="form-label">مربوط به قالب</label><select class="form-select" name="mold_id"><option value="">(اختیاری) انتخاب کنید</option><?php foreach ($molds as $mold): ?><option value="<?php echo $mold['MoldID']; ?>" <?php echo (isset($itemToEdit['MoldID']) && $itemToEdit['MoldID'] == $mold['MoldID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mold['MoldName']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">نقطه سفارش</label><input type="number" class="form-control" name="reorder_point" value="<?php echo htmlspecialchars($itemToEdit['ReorderPoint'] ?? '0'); ?>"></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="spare_parts.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo has_permission('engineering.spare_parts.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست قطعات یدکی</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">کد قطعه</th><th class="p-3">نام قطعه</th><th class="p-3">قالب</th><th class="p-3">نقطه سفارش</th><?php if (has_permission('engineering.spare_parts.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['PartCode']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['PartName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['MoldName'] ?? '-'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['ReorderPoint']); ?></td>
                                <?php if (has_permission('engineering.spare_parts.manage')): ?>
                                <td class="p-3">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف قطعه "<?php echo htmlspecialchars($item['PartName']); ?>" مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                          <form method="POST" action="spare_parts.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                        </div>
                                    </div></div></div>
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

