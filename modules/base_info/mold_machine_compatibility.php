<?php
require_once __DIR__ . '/../../config/db.php';

const TABLE_NAME_COMPAT = 'tbl_mold_machine_compatibility';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This form works differently. It doesn't have a single primary key.
    // We'll handle insert and delete. Edit is effectively a delete then insert.
    if (isset($_POST['delete_mold_id']) && isset($_POST['delete_machine_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME_COMPAT . " WHERE MoldID = ? AND MachineID = ?");
            $stmt->execute([(int)$_POST['delete_mold_id'], (int)$_POST['delete_machine_id']]);
            $_SESSION['message'] = 'رابطه با موفقیت حذف شد.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'خطا در حذف رابطه: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    } else if (isset($_POST['mold_id']) && isset($_POST['machine_id'])) {
         if (empty($_POST['mold_id']) || empty($_POST['machine_id'])) {
            $_SESSION['message'] = 'قالب و دستگاه باید انتخاب شوند.';
            $_SESSION['message_type'] = 'warning';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME_COMPAT . " (MoldID, MachineID) VALUES (?, ?)");
                $stmt->execute([(int)$_POST['mold_id'], (int)$_POST['machine_id']]);
                $_SESSION['message'] = 'رابطه جدید با موفقیت ثبت شد.';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                // Handle potential duplicate entry
                if ($e->getCode() == '23000') {
                    $_SESSION['message'] = 'خطا: این رابطه از قبل وجود دارد.';
                } else {
                    $_SESSION['message'] = 'خطا در ثبت رابطه: ' . $e->getMessage();
                }
                $_SESSION['message_type'] = 'danger';
            }
        }
    }
    
    header("Location: " . BASE_URL . "modules/base_info/mold_machine_compatibility.php");
    exit;
}

$items = find_all($pdo, "SELECT mm.*, m.MoldName, ma.MachineName 
                        FROM " . TABLE_NAME_COMPAT . " mm
                        JOIN tbl_molds m ON mm.MoldID = m.MoldID
                        JOIN tbl_machines ma ON mm.MachineID = ma.MachineID
                        ORDER BY m.MoldName, ma.MachineName");
$molds = find_all($pdo, "SELECT * FROM tbl_molds ORDER BY MoldName");
$machines = find_all($pdo, "SELECT * FROM tbl_machines ORDER BY MachineName");


$pageTitle = "سازگاری قالب و دستگاه";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">سازگاری قالب و دستگاه</h1>
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
            <div class="card-header"><h5 class="mb-0">تعریف رابطه جدید</h5></div>
            <div class="card-body">
                <form method="POST" action="mold_machine_compatibility.php">
                    <div class="mb-3">
                        <label for="mold_id" class="form-label">قالب</label>
                        <select class="form-select" id="mold_id" name="mold_id" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach($molds as $mold): ?>
                                <option value="<?php echo $mold['MoldID']; ?>"><?php echo htmlspecialchars($mold['MoldName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label for="machine_id" class="form-label">دستگاه</label>
                        <select class="form-select" id="machine_id" name="machine_id" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach($machines as $machine): ?>
                                <option value="<?php echo $machine['MachineID']; ?>"><?php echo htmlspecialchars($machine['MachineName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">افزودن رابطه</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست سازگاری‌ها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">قالب</th><th class="p-3">دستگاه</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['MoldName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['MachineName']); ?></td>
                                <td class="p-3">
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['MoldID'] . '-' . $item['MachineID']; ?>"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item['MoldID'] . '-' . $item['MachineID']; ?>" tabindex="-1">
                                      <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف این رابطه مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                          <form method="POST" action="mold_machine_compatibility.php" class="d-inline">
                                              <input type="hidden" name="delete_mold_id" value="<?php echo $item['MoldID']; ?>">
                                              <input type="hidden" name="delete_machine_id" value="<?php echo $item['MachineID']; ?>">
                                              <button type="submit" class="btn btn-danger">بله، حذف کن</button>
                                          </form>
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

