<?php
require_once __DIR__ . '/../../config/db.php';

const TABLE_NAME_MACHINES = 'tbl_machines';
const PRIMARY_KEY_MACHINES = 'MachineID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME_MACHINES, (int)$_POST['delete_id'], PRIMARY_KEY_MACHINES);
    } else {
        if (empty(trim($_POST['machine_name']))) {
            $result = ['success' => false, 'message' => 'نام دستگاه نمی‌تواند خالی باشد.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            $data = [
                'MachineName' => trim($_POST['machine_name']),
                'MachineType' => trim($_POST['machine_type']),
                'Status' => trim($_POST['status']),
                'strokes_per_minute' => !empty($_POST['strokes_per_minute']) ? (int)$_POST['strokes_per_minute'] : null
            ];
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME_MACHINES, $data, (int)$_POST['id'], PRIMARY_KEY_MACHINES);
            } else {
                $result = insert_record($pdo, TABLE_NAME_MACHINES, $data);
            }
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    
    header("Location: " . BASE_URL . "modules/base_info/machines.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME_MACHINES, (int)$_GET['edit_id'], PRIMARY_KEY_MACHINES);
}

$items = find_all($pdo, "SELECT * FROM " . TABLE_NAME_MACHINES . " ORDER BY MachineName");

$pageTitle = "مدیریت دستگاه‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت دستگاه‌ها</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/machinery_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش دستگاه' : 'افزودن دستگاه جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="machines.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY_MACHINES]; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="machine_name" class="form-label">نام دستگاه</label>
                        <input type="text" class="form-control" id="machine_name" name="machine_name" value="<?php echo htmlspecialchars($itemToEdit['MachineName'] ?? ''); ?>" required>
                    </div>
                     <div class="mb-3">
                        <label for="machine_type" class="form-label">نوع دستگاه</label>
                        <input type="text" class="form-control" id="machine_type" name="machine_type" value="<?php echo htmlspecialchars($itemToEdit['MachineType'] ?? ''); ?>">
                    </div>
                     <div class="mb-3">
                        <label for="strokes_per_minute" class="form-label">تعداد ضرب در دقیقه (استاندارد)</label>
                        <input type="number" class="form-control" id="strokes_per_minute" name="strokes_per_minute" value="<?php echo htmlspecialchars($itemToEdit['strokes_per_minute'] ?? ''); ?>">
                    </div>
                     <div class="mb-3">
                        <label for="status" class="form-label">وضعیت</label>
                        <input type="text" class="form-control" id="status" name="status" value="<?php echo htmlspecialchars($itemToEdit['Status'] ?? 'Active'); ?>">
                    </div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="machines.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست دستگاه‌ها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">نام دستگاه</th><th class="p-3">نوع</th><th class="p-3">ضرب در دقیقه</th><th class="p-3">وضعیت</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['MachineName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['MachineType']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['strokes_per_minute'] ?? '-'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['Status']); ?></td>
                                <td class="p-3">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY_MACHINES]; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY_MACHINES]; ?>"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY_MACHINES]; ?>" tabindex="-1">
                                      <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف دستگاه "<?php echo htmlspecialchars($item['MachineName']); ?>" مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                          <form method="POST" action="machines.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY_MACHINES]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
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