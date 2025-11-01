<?php
require_once __DIR__ . '/../../config/init.php';

// Permission check - Assuming 'base_info.manage' is needed
if (!has_permission('base_info.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_break_times';
const PRIMARY_KEY = 'BreakID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'BreakName' => trim($_POST['break_name']),
            'StartTime' => $_POST['start_time'] . ':00', // Add seconds
            'EndTime' => $_POST['end_time'] . ':00',     // Add seconds
            'DepartmentID' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'IsActive' => isset($_POST['is_active']) ? 1 : 0,
        ];

        // Basic validation
        if (empty($data['BreakName']) || empty($_POST['start_time']) || empty($_POST['end_time'])) {
            $result = ['success' => false, 'message' => 'نام، زمان شروع و پایان استراحت الزامی است.'];
            $_SESSION['message_type'] = 'warning';
        } elseif (strtotime($data['StartTime']) >= strtotime($data['EndTime'])) {
            $result = ['success' => false, 'message' => 'زمان پایان باید بعد از زمان شروع باشد.'];
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
    header("Location: " . BASE_URL . "modules/base_info/break_times.php");
    exit;
}

// Handle Edit Request
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
    // Format times for display in input type="time"
    if ($itemToEdit) {
        $itemToEdit['StartTimeFmt'] = date('H:i', strtotime($itemToEdit['StartTime']));
        $itemToEdit['EndTimeFmt'] = date('H:i', strtotime($itemToEdit['EndTime']));
    }
}

// Fetch data for display
$items = find_all($pdo, "SELECT bt.*, d.DepartmentName FROM " . TABLE_NAME . " bt LEFT JOIN tbl_departments d ON bt.DepartmentID = d.DepartmentID ORDER BY bt.StartTime");
$departments = find_all($pdo, "SELECT DepartmentID, DepartmentName FROM tbl_departments ORDER BY DepartmentName");

$pageTitle = "مدیریت زمان‌های استراحت";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت زمان‌های استراحت</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Form Column -->
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $editMode ? 'ویرایش زمان استراحت' : 'افزودن زمان استراحت جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="break_times.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="break_name" class="form-label">نام استراحت</label>
                        <input type="text" class="form-control" id="break_name" name="break_name"
                               value="<?php echo htmlspecialchars($itemToEdit['BreakName'] ?? ''); ?>" placeholder="مثال: ناهار" required>
                    </div>
                     <div class="row">
                        <div class="col-md-6 mb-3">
                             <label for="start_time" class="form-label">زمان شروع</label>
                             <input type="time" class="form-control" id="start_time" name="start_time"
                                    value="<?php echo htmlspecialchars($itemToEdit['StartTimeFmt'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                             <label for="end_time" class="form-label">زمان پایان</label>
                             <input type="time" class="form-control" id="end_time" name="end_time"
                                     value="<?php echo htmlspecialchars($itemToEdit['EndTimeFmt'] ?? ''); ?>" required>
                        </div>
                     </div>
                     <div class="mb-3">
                        <label for="department_id" class="form-label">مخصوص دپارتمان (اختیاری)</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">-- همه دپارتمان‌ها --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['DepartmentID']; ?>" <?php echo (isset($itemToEdit['DepartmentID']) && $itemToEdit['DepartmentID'] == $dept['DepartmentID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['DepartmentName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($itemToEdit['IsActive']) || $itemToEdit['IsActive'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">فعال</label>
                    </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                        <i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i>
                        <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?>
                    </button>
                    <?php if ($editMode): ?>
                        <a href="break_times.php" class="btn btn-secondary">لغو</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Column -->
    <div class="col-lg-8">
        <div class="card content-card">
             <div class="card-header">
                <h5 class="mb-0">لیست زمان‌های استراحت</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="p-3">نام</th>
                                <th class="p-3">شروع</th>
                                <th class="p-3">پایان</th>
                                <th class="p-3">دپارتمان</th>
                                <th class="p-3">وضعیت</th>
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['BreakName']); ?></td>
                                <td class="p-3"><?php echo date('H:i', strtotime($item['StartTime'])); ?></td>
                                <td class="p-3"><?php echo date('H:i', strtotime($item['EndTime'])); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['DepartmentName'] ?? 'همه'); ?></td>
                                <td class="p-3"><?php echo $item['IsActive'] ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>'; ?></td>
                                <td class="p-3">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>

                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">تایید حذف</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    آیا از حذف زمان استراحت "<?php echo htmlspecialchars($item['BreakName']); ?>" مطمئن هستید؟
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" action="break_times.php" class="d-inline">
                                                        <input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>">
                                                        <button type="submit" class="btn btn-danger">بله، حذف کن</button>
                                                    </form>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                                </div>
                                            </div>
                                        </div>
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

<?php
include __DIR__ . '/../../templates/footer.php';
?>
