<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('warehouse.misc.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_misc_items';
const PRIMARY_KEY = 'ItemID';
const RECORDS_PER_PAGE = 20;

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- Pagination ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

// --- Handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'ItemName' => trim($_POST['item_name']),
            'CategoryID' => (int)$_POST['category_id'],
            'UnitID' => (int)$_POST['unit_id'],
            'SafetyStock' => !empty($_POST['safety_stock']) ? (float)$_POST['safety_stock'] : null,
        ];
        if (empty($data['ItemName']) || empty($data['CategoryID']) || empty($data['UnitID'])) {
             $result = ['success' => false, 'message' => 'نام، دسته‌بندی و واحد الزامی است.'];
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
    header("Location: " . BASE_URL . "modules/Warehouse/misc_items.php?page=" . $current_page);
    exit;
}

// --- Handle GET (Edit) ---
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// --- Fetch Data for Display ---
$items_query = "SELECT mi.*, mc.CategoryName, u.Symbol as UnitSymbol
                FROM " . TABLE_NAME . " mi
                JOIN tbl_misc_categories mc ON mi.CategoryID = mc.CategoryID
                JOIN tbl_units u ON mi.UnitID = u.UnitID
                ORDER BY mc.CategoryName, mi.ItemName
                LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($items_query);
$stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$categories = find_all($pdo, "SELECT * FROM tbl_misc_categories ORDER BY CategoryName");
$units = find_all($pdo, "SELECT * FROM tbl_units ORDER BY UnitName"); // Using existing units table

$pageTitle = "مدیریت مواد متفرقه";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="misc_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش ماده' : 'افزودن ماده جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="misc_items.php?page=<?php echo $current_page; ?>">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">نام ماده *</label><input type="text" class="form-control" name="item_name" value="<?php echo htmlspecialchars($itemToEdit['ItemName'] ?? ''); ?>" required></div>
                <div class="mb-3"><label class="form-label">دسته‌بندی *</label><select class="form-select" name="category_id" required><option value="">انتخاب کنید</option><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['CategoryID']; ?>" <?php echo (isset($itemToEdit['CategoryID']) && $itemToEdit['CategoryID'] == $cat['CategoryID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['CategoryName']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">واحد اندازه‌گیری *</label><select class="form-select" name="unit_id" required><option value="">انتخاب کنید</option><?php foreach ($units as $unit): ?><option value="<?php echo $unit['UnitID']; ?>" <?php echo (isset($itemToEdit['UnitID']) && $itemToEdit['UnitID'] == $unit['UnitID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($unit['UnitName'] . ' (' . $unit['Symbol'] . ')'); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">موجودی اطمینان (اختیاری)</label><input type="number" step="0.01" class="form-control" name="safety_stock" value="<?php echo htmlspecialchars($itemToEdit['SafetyStock'] ?? ''); ?>"></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="misc_items.php?page=<?php echo $current_page; ?>" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست مواد متفرقه</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover table-sm mb-0">
            <thead><tr><th class="p-2">نام ماده</th><th class="p-2">دسته‌بندی</th><th class="p-2">واحد</th><th class="p-2">موجودی اطمینان</th><th class="p-2">عملیات</th></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-2"><?php echo htmlspecialchars($item['ItemName']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($item['CategoryName']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($item['UnitSymbol']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($item['SafetyStock'] ?? '-'); ?></td>
                <td class="p-2 text-nowrap">
                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف ماده "<?php echo htmlspecialchars($item['ItemName']); ?>" مطمئن هستید؟</div>
                        <div class="modal-footer">
                            <form method="POST" action="misc_items.php?page=<?php echo $current_page; ?>"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                        </div>
                    </div></div></div>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div>
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
