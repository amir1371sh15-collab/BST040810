<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks as needed
if (!has_permission('base_info.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_packaging_configs';
const PRIMARY_KEY = 'PackageConfigID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- SMART FILTERING LOGIC (برای دراپ‌داون) ---
$allowed_families = ['بست بزرگ', 'بست کوچک'];
$placeholders_families = implode(',', array_fill(0, count($allowed_families), '?'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        // --- اصلاح شد: فیلد TotalWeightKG حذف شد ---
        $data = [
            'SizeID' => (int)$_POST['size_id'],
            'ContainedQuantity' => (int)$_POST['contained_quantity'],
        ];
        if (empty($data['SizeID']) || empty($data['ContainedQuantity'])) {
             $result = ['success' => false, 'message' => 'انتخاب سایز و تعداد در کارتن الزامی است.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                // Check for duplicates
                $existing = find_one_by_field($pdo, TABLE_NAME, 'SizeID', $data['SizeID']);
                if ($existing) {
                    $result = ['success' => false, 'message' => 'خطا: یک پیکربندی برای این سایز از قبل وجود دارد.'];
                    $_SESSION['message_type'] = 'warning';
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
            }
            if (!isset($_SESSION['message_type'])) {
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        }
        if (!isset($_SESSION['message'])) {
             $_SESSION['message'] = $result['message'];
        }
    }
    header("Location: " . BASE_URL . "modules/base_info/packaging_configs.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}


// --- اصلاح شد: کوئری برای واکشی وزن فعلی قطعه ---
// --- از CONCAT برای اتصال Size به Part استفاده شده است ---
$items_query = "
    SELECT
        pc.PackageConfigID, pc.ContainedQuantity,
        ps.SizeID, ps.SizeName, pf.FamilyName,
        pw.WeightGR
    FROM " . TABLE_NAME . " pc
    JOIN tbl_part_sizes ps ON pc.SizeID = ps.SizeID
    JOIN tbl_part_families pf ON ps.FamilyID = pf.FamilyID
    
    -- اصلاحیه: اتصال قطعه بر اساس قانون نام‌گذاری به جای ستون NULL
    LEFT JOIN tbl_parts p ON p.FamilyID = pf.FamilyID AND p.PartName = CONCAT(pf.FamilyName, ' ', ps.SizeName)
    
    LEFT JOIN tbl_part_weights pw ON p.PartID = pw.PartID AND (pw.EffectiveTo IS NULL OR pw.EffectiveTo >= CURDATE()) -- Get current active weight
    WHERE pf.FamilyName IN ($placeholders_families)
    GROUP BY pc.PackageConfigID
    ORDER BY pf.FamilyName, ps.SizeName
";
$items = find_all($pdo, $items_query, $allowed_families);

// Query for the dropdown, also filtered by the allowed families.
$sizes = find_all(
    $pdo,
    "SELECT ps.SizeID, ps.SizeName, pf.FamilyName
     FROM tbl_part_sizes ps
     JOIN tbl_part_families pf ON ps.FamilyID = pf.FamilyID
     WHERE pf.FamilyName IN ($placeholders_families)
     ORDER BY pf.FamilyName, ps.SizeName",
    $allowed_families
);


$pageTitle = "پیکربندی بسته‌بندی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <?php
        // --- اصلاح شد: آدرس‌دهی sidebar.php ---
        // این فایل باید در `templates/sidebar.php` باشد
        // مسیر `__DIR__ . '/../../templates/sidebar.php'` صحیح است
        
        // **!! نکته مهم: اگر فایل سایدبار شما در پوشه دیگری است، باید این مسیر را اصلاح کنید !!**
        // **!! بر اساس ساختار فعلی، این مسیر باید درست کار کند. **
        
        // include_once __DIR__ . '/../../templates/sidebar.php'; 
        // **!! اگر فایل سایدبار ندارید، این خط را کاملاً حذف یا کامنت کنید !!**
        ?>
        
        <!-- اصلاح شد: کلاس سایدبار (اگر وجود داشته باشد) -->
        <!-- <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"> -->
        
        <!-- حالت بدون سایدبار (اگر فایل سایدبار وجود ندارد) -->
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?> (محصولات بست)</h1>
                <a href="warehouses_dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت
                </a>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card content-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo $editMode ? 'ویرایش پیکربندی' : 'افزودن پیکربندی جدید'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="packaging_configs.php">
                                <?php if ($editMode && $itemToEdit): ?>
                                    <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                                <?php endif; ?>
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
                                <div class="mb-3">
                                    <label class="form-label">تعداد در هر کارتن</label>
                                    <input type="number" class="form-control" name="contained_quantity" value="<?php echo htmlspecialchars($itemToEdit['ContainedQuantity'] ?? ''); ?>" required>
                                </div>
                                <!-- --- اصلاح شد: فیلد وزن کل کارتن حذف شد --- -->
                                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                                    <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?>
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="packaging_configs.php" class="btn btn-secondary">لغو</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card content-card">
                        <div class="card-header">
                            <h5 class="mb-0">لیست پیکربندی‌های بسته‌بندی</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="p-3">خانواده محصول</th>
                                            <th class="p-3">سایز</th>
                                            <th class="p-3">تعداد در کارتن</th>
                                            <th class="p-3">وزن محاسبه‌شده کارتن (KG)</th>
                                            <th class="p-3">عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($items)): ?>
                                            <tr><td colspan="5" class="text-center p-3 text-muted">هیچ رکوردی یافت نشد.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td class="p-3"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($item['SizeName']); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($item['ContainedQuantity']); ?></td>
                                                <!-- --- اصلاح شد: محاسبه خودکار وزن --- -->
                                                <td class="p-3">
                                                    <?php
                                                    if (!empty($item['WeightGR'])) {
                                                        // تبدیل گرم به کیلوگرم و سپس ضرب در تعداد
                                                        $totalWeightKG = ((float)$item['WeightGR'] / 1000) * (int)$item['ContainedQuantity'];
                                                        echo number_format($totalWeightKG, 3) . ' KG';
                                                    } else {
                                                        echo '<span class="text-muted small">وزن قطعه تعریف نشده</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="p-3">
                                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">تایید حذف</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">آیا از حذف این پیکربندی مطمئن هستید؟</div>
                                                                <div class="modal-footer">
                                                                    <form method="POST" action="packaging_configs.php">
                                                                        <input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>">
                                                                        <button type="submit" class="btn btn-danger">بله</button>
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
        </main>
    </div>
</div>
<?php
// --- اصلاح شد: آدرس‌دهی footer.php ---
include __DIR__ . '/../../templates/footer.php';
?>

