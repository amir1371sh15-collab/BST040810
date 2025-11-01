<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks as needed

const TABLE_NAME = 'tbl_employees';
const PRIMARY_KEY = 'EmployeeID';
const RECORDS_PER_PAGE = 20;

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- PAGINATION LOGIC ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = ceil($total_records / RECORDS_PER_PAGE);
$offset = ($current_page - 1) * RECORDS_PER_PAGE;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'DepartmentID' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'JobTitle' => trim($_POST['job_title']),
            'HireDate' => !empty($_POST['hire_date']) ? to_gregorian($_POST['hire_date']) : null,
            'Status' => $_POST['status']
        ];
        if (empty($data['name'])) {
            $result = ['success' => false, 'message' => 'نام کارمند نمی‌تواند خالی باشد.'];
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
    header("Location: " . BASE_URL . "modules/base_info/employees.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items_query = "SELECT e.*, d.DepartmentName 
                FROM " . TABLE_NAME . " e 
                LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID 
                ORDER BY e.name 
                LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($items_query);
$stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$departments = find_all($pdo, "SELECT DepartmentID, DepartmentName FROM tbl_departments ORDER BY DepartmentName");

$pageTitle = "مدیریت کارمندان";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت کارمندان</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش کارمند' : 'افزودن کارمند جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="employees.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label class="form-label">نام و نام خانوادگی</label><input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($itemToEdit['name'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label class="form-label">دپارتمان</label><select class="form-select" name="department_id"><option value="">انتخاب کنید</option><?php foreach($departments as $dept): ?><option value="<?php echo $dept['DepartmentID']; ?>" <?php echo (isset($itemToEdit['DepartmentID']) && $itemToEdit['DepartmentID'] == $dept['DepartmentID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['DepartmentName']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">سمت شغلی</label><input type="text" class="form-control" name="job_title" value="<?php echo htmlspecialchars($itemToEdit['JobTitle'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label">تاریخ استخدام</label><input type="text" class="form-control persian-date" name="hire_date" value="<?php echo to_jalali($itemToEdit['HireDate'] ?? ''); ?>" autocomplete="off"></div>
                    <div class="mb-3"><label class="form-label">وضعیت</label><select class="form-select" name="status"><option value="Active" <?php echo (isset($itemToEdit['Status']) && $itemToEdit['Status'] == 'Active') ? 'selected' : ''; ?>>فعال</option><option value="Inactive" <?php echo (isset($itemToEdit['Status']) && $itemToEdit['Status'] == 'Inactive') ? 'selected' : ''; ?>>غیرفعال</option></select></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="employees.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card content-card">
             <div class="card-header"><h5 class="mb-0">لیست کارمندان</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">نام</th><th class="p-3">دپارتمان</th><th class="p-3">سمت</th><th class="p-3">تاریخ استخدام</th><th class="p-3">وضعیت</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['DepartmentName'] ?? '-'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['JobTitle'] ?? '-'); ?></td>
                                <td class="p-3"><?php echo to_jalali($item['HireDate']); ?></td>
                                <td class="p-3"><?php echo $item['Status'] == 'Active' ? 'فعال' : 'غیرفعال'; ?></td>
                                <td class="p-3">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>"><i class="bi bi-trash-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="card-footer d-flex justify-content-center">
                <nav><ul class="pagination mb-0">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">قبلی</a></li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a></li>
                </ul></nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>

