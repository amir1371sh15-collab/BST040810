<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks as needed

const TABLE_NAME = 'tbl_packaging_weights';
const PRIMARY_KEY = 'PackageWeightID';

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
            'SizeID' => (int)$_POST['size_id'],
            'ContainedQuantity' => (int)$_POST['contained_quantity'],
            'TotalWeightKG' => !empty($_POST['total_weight_kg']) ? (float)$_POST['total_weight_kg'] : null,
        ];
        if (empty($data['SizeID']) || empty($data['ContainedQuantity'])) {
             $result = ['success' => false, 'message' => 'انتخاب سایز و تعداد در کارتن الزامی است.'];
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
    header("Location: " . BASE_URL . "modules/base_info/packaging_weights.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// SMART FILTERING: Only fetch sizes that belong to the "clamp" families.
$allowed_families = ['بست بزرگ', 'بست کوچک'];
$placeholders = implode(',', array_fill(0, count($allowed_families), '?'));

$items_query = "
    SELECT 
        pw.*, ps.SizeName, pf.FamilyName,
        (SELECT p.UnitWeight FROM tbl_parts p WHERE p.SizeID = ps.SizeID LIMIT 1) as UnitWeight,
        (SELECT u.Symbol FROM tbl_parts p JOIN tbl_units u ON p.BaseUnitID = u.UnitID WHERE p.SizeID = ps.SizeID LIMIT 1) as UnitSymbol
    FROM " . TABLE_NAME . " pw 
    JOIN tbl_part_sizes ps ON pw.SizeID = ps.SizeID 
    JOIN tbl_part_families pf ON ps.FamilyID = pf.FamilyID 
    WHERE pf.FamilyName IN ($placeholders)
    ORDER BY pf.FamilyName, ps.SizeName
";
$items = find_all($pdo, $items_query, $allowed_families);

$sizes = find_all(
    $pdo,
    "SELECT ps.SizeID, ps.SizeName, pf.FamilyName 
     FROM tbl_part_sizes ps 
     JOIN tbl_part_families pf ON ps.FamilyID = pf.FamilyID 
     WHERE pf.FamilyName IN ($placeholders)
     ORDER BY pf.FamilyName, ps.SizeName",
    $allowed_families
);

$pageTitle = "مدیریت وزن بسته‌بندی";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت وزن بسته‌بندی (محصولات بست)</h1>
    <a href="warehouses_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش وزن' : 'افزودن وزن جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="packaging_weights.php">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">سایز محصول (فقط بست‌ها)</label>
                    <select class="form-select" name="size_id" required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($sizes as $size): ?>
                            <option value="<?php echo $size['SizeID']; ?>" <?php echo (isset($itemToEdit['SizeID']) && $itemToEdit['SizeID'] == $size['SizeID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($size['FamilyName'] . ' - ' . $size['SizeName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">تعداد در هر کارتن</label><input type="number" class="form-control" name="contained_quantity" value="<?php echo htmlspecialchars($itemToEdit['ContainedQuantity'] ?? ''); ?>" required></div>
                <div class="mb-3"><label class="form-label">وزن کل کارتن پر شده (کیلوگرم)</label><input type="number" step="0.001" class="form-control" name="total_weight_kg" value="<?php echo htmlspecialchars($itemToEdit['TotalWeightKG'] ?? ''); ?>"></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="packaging_weights.php" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست وزن‌های بسته‌بندی</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">خانواده محصول</th><th class="p-3">سایز</th><th class="p-3">تعداد در کارتن</th><th class="p-3">وزن محاسبه‌شده کارتن</th><th class="p-3">عملیات</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="p-3"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($item['SizeName']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($item['ContainedQuantity']); ?></td>
                    <td class="p-3">
                        <?php
                        if (!empty($item['UnitWeight'])) {
                            $totalWeight = $item['UnitWeight'] * $item['ContainedQuantity'];
                            // Assuming base unit is always KG for this calculation as per 'UnitWeightKG'
                            echo number_format($totalWeight, 3) . ' kg';
                        } else {
                            echo '<span class="text-muted">وزن قطعه تعریف نشده</span>';
                        }
                        ?>
                    </td>
                    <td class="p-3">
                        <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                        <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">آیا از حذف این رکورد مطمئن هستید؟</div>
                            <div class="modal-footer">
                                <form method="POST" action="packaging_weights.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                            </div>
                        </div></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

