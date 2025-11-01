<?php
require_once __DIR__ . '/../../config/init.php';
// بررسی دسترسی
if (!has_permission('planning.sales_orders.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}
$can_manage = has_permission('planning.sales_orders.manage');

const TABLE_NAME = 'tbl_sales_orders';
const PRIMARY_KEY = 'SalesOrderID';
const RECORDS_PER_PAGE = 20;

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$editMode = false;
$itemToEdit = null;

// --- منطق جدید برای پیش-پر کردن فرم از داشبورد هشدار ---
$prefill_family_id = null;
$prefill_part_id = null;
$prefill_quantity = null;
$prefill_unit = null;

if (isset($_GET['prefill_part_id']) && is_numeric($_GET['prefill_part_id']) && !$editMode) {
    $prefill_part_id = (int)$_GET['prefill_part_id'];
    $prefill_quantity = $_GET['prefill_quantity'] ?? null;
    $prefill_unit = $_GET['prefill_unit'] ?? null;

    // پیدا کردن FamilyID بر اساس PartID برای پر کردن صحیح دراپ‌دان
    $partInfo = find_by_id($pdo, 'tbl_parts', $prefill_part_id, 'PartID');
    if ($partInfo) {
        $prefill_family_id = $partInfo['FamilyID'];
    }
}
// --- پایان منطق پیش-پر کردن ---


// Handle POST (Add/Update/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'PartID' => (int)$_POST['part_id'],
            'Quantity' => (float)$_POST['quantity'],
            'Unit' => $_POST['unit'],
            'RequiredDate' => to_gregorian($_POST['required_date']),
            'CustomerName' => trim($_POST['customer_name']),
            'Priority' => (int)$_POST['priority']
        ];

        if (empty($data['PartID']) || empty($data['Quantity']) || empty($data['RequiredDate'])) {
            $result = ['success' => false, 'message' => 'قطعه، تعداد و تاریخ نیاز الزامی است.'];
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
    header("Location: sales_orders.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$can_manage) {
    $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
    $_SESSION['message_type'] = 'danger';
    header("Location: sales_orders.php");
    exit;
}

// Handle GET (Edit)
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    if (!$can_manage) { die('شما مجوز ویرایش را ندارید.'); }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
    // Find family ID for edit mode to pre-select dropdowns
    if ($itemToEdit) {
        $partInfo = find_by_id($pdo, 'tbl_parts', $itemToEdit['PartID'], 'PartID');
        if($partInfo) {
            $itemToEdit['FamilyID'] = $partInfo['FamilyID'];
        }
    }
}

// Fetch data for lists
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

// Pagination
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

$orders = find_all($pdo, "
    SELECT so.*, p.PartName, p.PartCode 
    FROM " . TABLE_NAME . " so
    JOIN tbl_parts p ON so.PartID = p.PartID
    ORDER BY so.RequiredDate ASC, so.Priority DESC
    LIMIT :limit OFFSET :offset",
    [':limit' => RECORDS_PER_PAGE, ':offset' => $offset]
);

$pageTitle = "مدیریت سفارشات فروش";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <?php if ($can_manage): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش سفارش' : 'ثبت سفارش جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="sales_orders.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="family_id" class="form-label">خانواده قطعه *</label>
                        <select class="form-select" id="family_id" name="family_id" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>" 
                                    <?php echo (($editMode && $itemToEdit['FamilyID'] == $family['FamilyID']) || ($prefill_family_id == $family['FamilyID'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($family['FamilyName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="part_id" class="form-label">قطعه *</label>
                        <select class="form-select" id="part_id" name="part_id" required <?php echo ($editMode || $prefill_family_id) ? '' : 'disabled'; ?>>
                            <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">تعداد / مقدار *</label>
                            <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo htmlspecialchars($itemToEdit['Quantity'] ?? $prefill_quantity ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="unit" class="form-label">واحد *</label>
                            <select class="form-select" id="unit" name="unit" required>
                                <option value="KG" <?php echo (($editMode && $itemToEdit['Unit'] == 'KG') || ($prefill_unit == 'KG')) ? 'selected' : ''; ?>>KG</option>
                                <option value="Carton" <?php echo (($editMode && $itemToEdit['Unit'] == 'Carton') || ($prefill_unit == 'Carton')) ? 'selected' : ''; ?>>Carton</option>
                                <option value="Count" <?php echo (($editMode && $itemToEdit['Unit'] == 'Count') || ($prefill_unit == 'Count')) ? 'selected' : ''; ?>>Count (عدد)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="required_date" class="form-label">تاریخ نیاز *</label>
                        <input type="text" class="form-control persian-date" id="required_date" name="required_date" 
                               value="<?php echo to_jalali($itemToEdit['RequiredDate'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">نام مشتری (اختیاری)</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" 
                               value="<?php echo htmlspecialchars($itemToEdit['CustomerName'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="priority" class="form-label">اولویت</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="1" <?php echo (isset($itemToEdit['Priority']) && $itemToEdit['Priority'] == 1) ? 'selected' : ''; ?>>پایین</option>
                            <option value="2" <?php echo (!isset($itemToEdit['Priority']) || $itemToEdit['Priority'] == 2) ? 'selected' : ''; ?>>متوسط</option>
                            <option value="3" <?php echo (isset($itemToEdit['Priority']) && $itemToEdit['Priority'] == 3) ? 'selected' : ''; ?>>بالا</option>
                        </select>
                    </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                        <?php echo $editMode ? 'بروزرسانی سفارش' : 'ثبت سفارش'; ?>
                    </button>
                    <?php if ($editMode): ?>
                        <a href="sales_orders.php" class="btn btn-secondary">لغو</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo $can_manage ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست سفارشات فروش</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="p-2">تاریخ نیاز</th>
                                <th class="p-2">قطعه</th>
                                <th class="p-2">تعداد/مقدار</th>
                                <th class="p-2">واحد</th>
                                <th class="p-2">مشتری</th>
                                <th class="p-2">اولویت</th>
                                <?php if ($can_manage): ?>
                                <th class="p-2">عملیات</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="<?php echo $can_manage ? 7 : 6; ?>" class="text-center p-3 text-muted">هیچ سفارشی ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="p-2"><?php echo to_jalali($order['RequiredDate']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($order['PartName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars(number_format($order['Quantity'])); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($order['Unit']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($order['CustomerName'] ?: '-'); ?></td>
                                    <td class="p-2"><?php echo ['1' => 'پایین', '2' => 'متوسط', '3' => 'بالا'][$order['Priority']] ?? '-'; ?></td>
                                    <?php if ($can_manage): ?>
                                    <td class="p-2 text-nowrap">
                                        <a href="?edit_id=<?php echo $order[PRIMARY_KEY]; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $order[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                        
                                        <div class="modal fade" id="deleteModal<?php echo $order[PRIMARY_KEY]; ?>" tabindex="-1">
                                            <div class="modal-dialog"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">آیا از حذف این سفارش مطمئن هستید؟</div>
                                            <div class="modal-footer">
                                                <form method="POST" action="sales_orders.php?page=<?php echo $current_page; ?>"><input type="hidden" name="delete_id" value="<?php echo $order[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                            </div>
                                            </div></div>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="card-footer d-flex justify-content-center">
                <nav><ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">قبلی</a></li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a></li>
                </ul></nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const familySelect = $('#family_id');
    const partSelect = $('#part_id');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php';
    
    // --- منطق جدید برای خواندن مقادیر اولیه از PHP ---
    const initialFamilyId = '<?php echo $itemToEdit['FamilyID'] ?? $prefill_family_id ?? ''; ?>';
    const initialPartId = '<?php echo $itemToEdit['PartID'] ?? $prefill_part_id ?? ''; ?>';

    async function populateParts(familyId, selectedPartId = null) {
        partSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }
        try {
            const response = await $.getJSON(apiPartsUrl, { family_id: familyId });
            partSelect.html('<option value="">-- انتخاب قطعه --</option>');
            if (response.success && response.data.length > 0) {
                response.data.forEach(function(part) {
                    const option = $('<option>', {
                        value: part.PartID,
                        text: part.PartName
                    });
                    if (part.PartID == selectedPartId) {
                        option.prop('selected', true);
                    }
                    partSelect.append(option);
                });
                partSelect.prop('disabled', false);
            } else {
                partSelect.html('<option value="">-- قطعه‌ای یافت نشد --</option>');
            }
        } catch (error) {
            console.error("Error fetching parts:", error);
            partSelect.html('<option value="">-- خطا در بارگذاری --</option>');
        }
    }

    // Event listener for family dropdown
    familySelect.on('change', function() {
        populateParts($(this).val());
    });

    // --- اجرای اولیه برای پیش-پر کردن یا حالت ویرایش ---
    if (initialFamilyId) {
        // familySelect.val(initialFamilyId); // این خط دیگر لازم نیست چون PHP مستقیماً 'selected' را می‌گذارد
        populateParts(initialFamilyId, initialPartId);
    }
});
</script>

