<?php
require_once __DIR__ . '/../../config/db.php';

const TABLE_NAME_WTYPES = 'tbl_warehouse_types';
const PRIMARY_KEY_WTYPES = 'WarehouseTypeID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME_WTYPES, (int)$_POST['delete_id'], PRIMARY_KEY_WTYPES);
    } else {
        if (empty(trim($_POST['type_name']))) {
            $result = ['success' => false, 'message' => 'نام نوع انبار نمی‌تواند خالی باشد.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            $data = ['TypeName' => trim($_POST['type_name'])];
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME_WTYPES, $data, (int)$_POST['id'], PRIMARY_KEY_WTYPES);
            } else {
                $result = insert_record($pdo, TABLE_NAME_WTYPES, $data);
            }
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    
    header("Location: " . BASE_URL . "modules/base_info/warehouse_types.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME_WTYPES, (int)$_GET['edit_id'], PRIMARY_KEY_WTYPES);
}

$items = find_all($pdo, "SELECT * FROM " . TABLE_NAME_WTYPES . " ORDER BY TypeName");

$pageTitle = "مدیریت انواع انبار";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت انواع انبار</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/warehouses_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش نوع' : 'افزودن نوع جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="warehouse_types.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY_WTYPES]; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="type_name" class="form-label">نام نوع انبار</label>
                        <input type="text" class="form-control" id="type_name" name="type_name" value="<?php echo htmlspecialchars($itemToEdit['TypeName'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="warehouse_types.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست انواع انبار</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">نام نوع</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['TypeName']); ?></td>
                                <td class="p-3">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY_WTYPES]; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY_WTYPES]; ?>"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY_WTYPES]; ?>" tabindex="-1">
                                      <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف نوع "<?php echo htmlspecialchars($item['TypeName']); ?>" مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                          <form method="POST" action="warehouse_types.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY_WTYPES]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
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

