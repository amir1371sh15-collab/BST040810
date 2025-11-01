<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks as needed (e.g., base_info.manage)

const TABLE_NAME = 'tbl_pallet_types';
const PRIMARY_KEY = 'PalletTypeID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Optional: Add permission check for managing
    // if (!has_permission('base_info.manage')) { ... }

    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'PalletName' => trim($_POST['pallet_name']),
            'PalletWeightKG' => !empty($_POST['pallet_weight_kg']) ? (float)$_POST['pallet_weight_kg'] : null,
        ];
        if (empty($data['PalletName'])) {
             $result = ['success' => false, 'message' => 'نام پالت الزامی است.'];
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
    header("Location: " . BASE_URL . "modules/base_info/pallet_types.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    // Optional: Add permission check for managing
    // if (!has_permission('base_info.manage')) { die('Access Denied'); }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT * FROM " . TABLE_NAME . " ORDER BY PalletName");

$pageTitle = "مدیریت انواع پالت";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="warehouses_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش نوع پالت' : 'افزودن نوع پالت جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="pallet_types.php">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">نام پالت</label><input type="text" class="form-control" name="pallet_name" value="<?php echo htmlspecialchars($itemToEdit['PalletName'] ?? ''); ?>" required></div>
                <div class="mb-3"><label class="form-label">وزن پالت خالی (KG)</label><input type="number" step="0.001" class="form-control" name="pallet_weight_kg" value="<?php echo htmlspecialchars($itemToEdit['PalletWeightKG'] ?? ''); ?>"></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="pallet_types.php" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست انواع پالت</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">نام پالت</th><th class="p-3">وزن (KG)</th><th class="p-3">عملیات</th></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-3"><?php echo htmlspecialchars($item['PalletName']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['PalletWeightKG'] ?? '-'); ?></td>
                <td class="p-3">
                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف پالت "<?php echo htmlspecialchars($item['PalletName']); ?>" مطمئن هستید؟</div>
                        <div class="modal-footer">
                            <form method="POST" action="pallet_types.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                        </div>
                    </div></div></div>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
