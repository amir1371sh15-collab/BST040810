<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks as needed

const TABLE_NAME = 'tbl_stations';
const PRIMARY_KEY = 'StationID';

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
            'StationName' => trim($_POST['station_name']),
            'StationType' => $_POST['station_type'],
        ];
        if (empty($data['StationName'])) {
             $result = ['success' => false, 'message' => 'نام ایستگاه الزامی است.'];
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
    header("Location: " . BASE_URL . "modules/base_info/stations.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT * FROM " . TABLE_NAME . " ORDER BY StationName");
$station_types = ['Production' => 'تولید', 'Warehouse' => 'انبار', 'QC' => 'کنترل کیفیت', 'External' => 'خارجی'];

$pageTitle = "مدیریت ایستگاه‌ها";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت ایستگاه‌ها</h1>
    <a href="parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش ایستگاه' : 'افزودن ایستگاه جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="stations.php">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">نام ایستگاه</label><input type="text" class="form-control" name="station_name" value="<?php echo htmlspecialchars($itemToEdit['StationName'] ?? ''); ?>" required></div>
                <div class="mb-3"><label class="form-label">نوع ایستگاه</label><select class="form-select" name="station_type" required><option value="">انتخاب کنید</option><?php foreach ($station_types as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo (isset($itemToEdit['StationType']) && $itemToEdit['StationType'] == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="stations.php" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست ایستگاه‌ها</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">نام ایستگاه</th><th class="p-3">نوع</th><th class="p-3">عملیات</th></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-3"><?php echo htmlspecialchars($item['StationName']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($station_types[$item['StationType']] ?? $item['StationType']); ?></td>
                <td class="p-3">
                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف ایستگاه "<?php echo htmlspecialchars($item['StationName']); ?>" مطمئن هستید؟</div>
                        <div class="modal-footer">
                            <form method="POST" action="stations.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                        </div>
                    </div></div></div>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
