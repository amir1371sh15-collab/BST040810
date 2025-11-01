<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission check, e.g., base_info.manage
if (!has_permission('base_info.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_part_statuses';
const PRIMARY_KEY = 'StatusID';
const COMPAT_TABLE = 'tbl_family_status_compatibility'; // Compatibility table name

$editMode = false;
$itemToEdit = null;
$linkedFamilyIDs = []; // Array to hold linked family IDs for edit mode
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName"); // Fetch families for the form

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // --- Delete Operation ---
        // Deleting from tbl_part_statuses will automatically cascade delete from tbl_family_status_compatibility
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        // We might still want the usage check here as a safety net if CASCADE fails?
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';

    } else {
        // --- Add/Update Operation ---
        $statusName = trim($_POST['status_name']);
        $selectedFamilyIDs = $_POST['family_ids'] ?? []; // Get selected family IDs

        if (empty($statusName)) {
            $_SESSION['message'] = 'نام وضعیت نمی‌تواند خالی باشد.';
            $_SESSION['message_type'] = 'warning';
        } else {
            $data = ['StatusName' => $statusName];
            $status_id = null;
            $is_success = false;

            $pdo->beginTransaction();
            try {
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    // Update existing record
                    $status_id = (int)$_POST['id'];
                    $result = update_record($pdo, TABLE_NAME, $data, $status_id, PRIMARY_KEY);
                    $is_success = $result['success'];
                     $_SESSION['message'] = $result['message'];
                } else {
                    // Insert new record
                    $result = insert_record($pdo, TABLE_NAME, $data);
                    $is_success = $result['success'];
                     $_SESSION['message'] = $result['message'];
                    if ($is_success) {
                        $status_id = $result['id'];
                    }
                }

                // If status insert/update was successful, manage family links
                if ($is_success && $status_id) {
                    // 1. Delete existing links for this status
                    $delete_compat_stmt = $pdo->prepare("DELETE FROM " . COMPAT_TABLE . " WHERE StatusID = ?");
                    $delete_compat_stmt->execute([$status_id]);

                    // 2. Insert new links
                    if (!empty($selectedFamilyIDs)) {
                        $insert_compat_stmt = $pdo->prepare("INSERT INTO " . COMPAT_TABLE . " (FamilyID, StatusID) VALUES (?, ?)");
                        foreach ($selectedFamilyIDs as $family_id) {
                            if (is_numeric($family_id)) {
                                $insert_compat_stmt->execute([(int)$family_id, $status_id]);
                            }
                        }
                    }
                     $_SESSION['message'] .= ' ارتباط با خانواده‌ها ذخیره شد.'; // Append success message
                }

                $pdo->commit();
                $_SESSION['message_type'] = $is_success ? 'success' : 'danger';

            } catch (PDOException $e) {
                $pdo->rollBack();
                 // Handle unique constraint violation for StatusName specifically
                 if ($e->getCode() == '23000' && strpos($e->getMessage(), 'StatusName_unique') !== false) {
                    $_SESSION['message'] = 'خطا: وضعیتی با این نام از قبل وجود دارد.';
                    $_SESSION['message_type'] = 'warning';
                 } else {
                    $_SESSION['message'] = 'خطای دیتابیس: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'danger';
                 }
                 $is_success = false; // Ensure type is set correctly
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['message'] = 'خطا: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
                $is_success = false;
            }
        }
    }

    header("Location: " . BASE_URL . "modules/base_info/part_statuses.php");
    exit;
}

// --- Handle Edit Request (from URL) ---
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $status_id_to_edit = (int)$_GET['edit_id'];
    $itemToEdit = find_by_id($pdo, TABLE_NAME, $status_id_to_edit, PRIMARY_KEY);
    // Fetch linked families for edit mode
    $linked_families_raw = find_all($pdo, "SELECT FamilyID FROM " . COMPAT_TABLE . " WHERE StatusID = ?", [$status_id_to_edit]);
    $linkedFamilyIDs = array_column($linked_families_raw, 'FamilyID');
}

// --- Fetch all items for display, including linked families ---
$items = find_all($pdo, "
    SELECT ps.*, GROUP_CONCAT(pf.FamilyName SEPARATOR ', ') as LinkedFamilies
    FROM " . TABLE_NAME . " ps
    LEFT JOIN " . COMPAT_TABLE . " fsc ON ps.StatusID = fsc.StatusID
    LEFT JOIN tbl_part_families pf ON fsc.FamilyID = pf.FamilyID
    GROUP BY ps.StatusID
    ORDER BY ps.StatusName
");

// --- Page Setup ---
$pageTitle = "مدیریت وضعیت‌های قطعه";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/parts_dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت به داشبورد قطعات
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
                <h5 class="mb-0"><?php echo $editMode ? 'ویرایش وضعیت' : 'افزودن وضعیت جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="part_statuses.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="status_name" class="form-label">نام وضعیت *</label>
                        <input type="text" class="form-control" id="status_name" name="status_name"
                               value="<?php echo $editMode && $itemToEdit ? htmlspecialchars($itemToEdit['StatusName']) : ''; ?>"
                               placeholder="مثال: آبکاری شده" required>
                    </div>

                    <div class="mb-3">
                         <label class="form-label">خانواده‌های مرتبط</label>
                         <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                             <?php if (empty($families)): ?>
                                <p class="text-muted small">ابتدا خانواده‌ها را در بخش مربوطه تعریف کنید.</p>
                             <?php else: ?>
                                <?php foreach ($families as $family):
                                    $isChecked = in_array($family['FamilyID'], $linkedFamilyIDs);
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="family_ids[]" value="<?php echo $family['FamilyID']; ?>" id="family_<?php echo $family['FamilyID']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="family_<?php echo $family['FamilyID']; ?>">
                                        <?php echo htmlspecialchars($family['FamilyName']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                         </div>
                         <small class="form-text text-muted">انتخاب خانواده مشخص می‌کند این وضعیت در کدام بخش‌ها (مثلاً هنگام ثبت تراکنش برای آن خانواده) نمایش داده شود.</small>
                    </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                        <i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i>
                        <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?>
                    </button>

                    <?php if ($editMode): ?>
                        <a href="part_statuses.php" class="btn btn-secondary">لغو</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Column -->
    <div class="col-lg-8">
        <div class="card content-card">
             <div class="card-header">
                <h5 class="mb-0">لیست وضعیت‌های تعریف شده</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="p-3">ID</th>
                                <th class="p-3">نام وضعیت</th>
                                <th class="p-3">خانواده‌های مرتبط</th>
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($items)): ?>
                                <tr><td colspan="4" class="text-center p-3 text-muted">هیچ وضعیتی تعریف نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td class="p-3"><?php echo $item['StatusID']; ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($item['StatusName']); ?></td>
                                    <td class="p-3 small"><?php echo htmlspecialchars($item['LinkedFamilies'] ?? 'همه / نامشخص'); ?></td>
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
                                                        آیا از حذف وضعیت "<?php echo htmlspecialchars($item['StatusName']); ?>" مطمئن هستید؟ <br>
                                                        <small class="text-danger">توجه: تمام ارتباطات این وضعیت با خانواده‌ها نیز حذف خواهد شد.</small>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST" action="part_statuses.php" class="d-inline">
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

