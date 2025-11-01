<?php
require_once __DIR__ . '/../../config/db.php';

// --- Define constants for this module ---
const TABLE_NAME = 'tbl_departments';
const PRIMARY_KEY = 'DepartmentID';

// --- Initialize variables ---
$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);


// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine if it's a delete or add/update operation
    if (isset($_POST['delete_id'])) {
        // --- Delete Operation ---
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        // --- Add/Update Operation ---
        $departmentName = trim($_POST['department_name']);
        
        if (empty($departmentName)) {
            $_SESSION['message'] = 'نام دپارتمان نمی‌تواند خالی باشد.';
            $_SESSION['message_type'] = 'warning';
        } else {
            $data = ['DepartmentName' => $departmentName];
            
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update existing record
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                // Insert new record
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
    }
    
    // Redirect to the same page to prevent form resubmission on refresh
    header("Location: " . BASE_URL . "modules/base_info/departments.php");
    exit;
}

// --- Handle Edit Request (from URL) ---
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// --- Fetch all items for display ---
$items = find_all($pdo, "SELECT * FROM " . TABLE_NAME . " ORDER BY DepartmentName");


// --- Page Setup ---
$pageTitle = "مدیریت دپارتمان‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت دپارتمان‌ها</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Form Column -->
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $editMode ? 'ویرایش دپارتمان' : 'افزودن دپارتمان جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="departments.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="department_name" class="form-label">نام دپارتمان</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" 
                               value="<?php echo $editMode && $itemToEdit ? htmlspecialchars($itemToEdit['DepartmentName']) : ''; ?>" 
                               required>
                    </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                        <i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i>
                        <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?>
                    </button>
                    
                    <?php if ($editMode): ?>
                        <a href="departments.php" class="btn btn-secondary">لغو</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Column -->
    <div class="col-lg-8">
        <div class="card content-card">
             <div class="card-header">
                <h5 class="mb-0">لیست دپارتمان‌ها</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="p-3">#</th>
                                <th class="p-3">نام دپارتمان</th>
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td class="p-3"><?php echo $index + 1; ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['DepartmentName']); ?></td>
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
                                                    آیا از حذف دپارتمان "<?php echo htmlspecialchars($item['DepartmentName']); ?>" مطمئن هستید؟
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" action="departments.php" class="d-inline">
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

