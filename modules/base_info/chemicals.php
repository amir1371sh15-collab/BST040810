<?php
require_once __DIR__ . '/../../config/init.php';

// Add appropriate permission check if needed, e.g., 'base_info.manage'
// if (!has_permission('base_info.manage')) { die('Access Denied.'); }

const TABLE_NAME = 'tbl_chemicals';
const PRIMARY_KEY = 'ChemicalID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle Chemical Type Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_chemical_type_name'])) {
    $newTypeName = trim($_POST['new_chemical_type_name']);
    if (!empty($newTypeName)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tbl_chemical_types (TypeName) VALUES (?)");
            $stmt->execute([$newTypeName]);
            $_SESSION['message'] = 'نوع ماده شیمیایی جدید با موفقیت اضافه شد.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            // Handle unique constraint violation
            if ($e->getCode() == '23000') {
                 $_SESSION['message'] = 'خطا: نوع ماده شیمیایی با این نام از قبل وجود دارد.';
                 $_SESSION['message_type'] = 'warning';
            } else {
                 $_SESSION['message'] = 'خطا در افزودن نوع ماده شیمیایی: ' . $e->getMessage();
                 $_SESSION['message_type'] = 'danger';
            }
        }
    } else {
        $_SESSION['message'] = 'نام نوع ماده شیمیایی نمی‌تواند خالی باشد.';
        $_SESSION['message_type'] = 'warning';
    }
    header("Location: " . BASE_URL . "modules/base_info/chemicals.php");
    exit;
}


// Handle Chemical CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['new_chemical_type_name'])) {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'ChemicalName' => trim($_POST['chemical_name']),
            'ChemicalTypeID' => (int)$_POST['chemical_type_id'],
            'UnitID' => !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null,
            'consumption_g_per_barrel' => !empty($_POST['consumption_g_per_barrel']) ? (float)$_POST['consumption_g_per_barrel'] : null,
            'consumption_g_per_kg' => !empty($_POST['consumption_g_per_kg']) ? (float)$_POST['consumption_g_per_kg'] : null,
        ];

        if (empty($data['ChemicalName']) || empty($data['ChemicalTypeID'])) {
            $result = ['success' => false, 'message' => 'نام ماده و نوع آن الزامی است.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                 try {
                     $result = insert_record($pdo, TABLE_NAME, $data);
                 } catch (PDOException $e) {
                     if ($e->getCode() == '23000') {
                        $result = ['success' => false, 'message' => 'خطا: ماده شیمیایی با این نام از قبل وجود دارد.'];
                        $_SESSION['message_type'] = 'warning';
                     } else {
                        $result = ['success' => false, 'message' => 'خطا در ثبت ماده: ' . $e->getMessage()];
                        $_SESSION['message_type'] = 'danger';
                     }
                 }
            }
            if (!isset($_SESSION['message_type'])) { // Set message type only if not set by exception
               $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }

        }
         // Set message only if not set by exception
         if (!isset($_SESSION['message'])) {
             $_SESSION['message'] = $result['message'];
         }
    }
    header("Location: " . BASE_URL . "modules/base_info/chemicals.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// Fetch data for lists
$items = find_all($pdo, "SELECT c.*, ct.TypeName as ChemicalTypeName, u.Symbol as UnitSymbol FROM " . TABLE_NAME . " c JOIN tbl_chemical_types ct ON c.ChemicalTypeID = ct.ChemicalTypeID LEFT JOIN tbl_units u ON c.UnitID = u.UnitID ORDER BY ct.TypeName, c.ChemicalName");
$chemical_types = find_all($pdo, "SELECT * FROM tbl_chemical_types ORDER BY TypeName");
$units = find_all($pdo, "SELECT UnitID, UnitName, Symbol FROM tbl_units ORDER BY UnitName");

$pageTitle = "مدیریت مواد شیمیایی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت مواد شیمیایی</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card mb-4">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش ماده شیمیایی' : 'افزودن ماده شیمیایی جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="chemicals.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="chemical_name" class="form-label">نام ماده شیمیایی</label>
                        <input type="text" class="form-control" id="chemical_name" name="chemical_name" value="<?php echo htmlspecialchars($itemToEdit['ChemicalName'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="chemical_type_id" class="form-label">نوع ماده</label>
                        <select class="form-select" id="chemical_type_id" name="chemical_type_id" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($chemical_types as $type): ?>
                                <?php // Handle 'سایر' type logic if needed in the future ?>
                                <option value="<?php echo $type['ChemicalTypeID']; ?>" <?php echo (isset($itemToEdit['ChemicalTypeID']) && $itemToEdit['ChemicalTypeID'] == $type['ChemicalTypeID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['TypeName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         </div>
                     <div class="mb-3">
                        <label for="unit_id" class="form-label">واحد پیش‌فرض</label>
                        <select class="form-select" id="unit_id" name="unit_id">
                            <option value="">-- (اختیاری) --</option>
                             <?php foreach($units as $unit): ?>
                                <option value="<?php echo $unit['UnitID']; ?>" <?php echo (isset($itemToEdit['UnitID']) && $itemToEdit['UnitID'] == $unit['UnitID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['UnitName'] . ' (' . $unit['Symbol'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="row">
                         <div class="col-md-6 mb-3">
                             <label for="consumption_g_per_barrel" class="form-label small">مصرف (گرم/بارل)</label>
                             <input type="number" step="0.001" class="form-control form-control-sm" id="consumption_g_per_barrel" name="consumption_g_per_barrel" value="<?php echo htmlspecialchars($itemToEdit['consumption_g_per_barrel'] ?? ''); ?>" placeholder="مثال: 120">
                         </div>
                         <div class="col-md-6 mb-3">
                             <label for="consumption_g_per_kg" class="form-label small">مصرف (گرم/کیلوگرم آبکاری)</label>
                             <input type="number" step="0.001" class="form-control form-control-sm" id="consumption_g_per_kg" name="consumption_g_per_kg" value="<?php echo htmlspecialchars($itemToEdit['consumption_g_per_kg'] ?? ''); ?>" placeholder="مثال: 3">
                         </div>
                     </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="chemicals.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>

         <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">افزودن نوع ماده جدید</h5></div>
            <div class="card-body">
                <form method="POST" action="chemicals.php">
                     <div class="mb-3">
                        <label for="new_chemical_type_name" class="form-label">نام نوع جدید</label>
                        <input type="text" class="form-control" id="new_chemical_type_name" name="new_chemical_type_name" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">افزودن نوع</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست مواد شیمیایی</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead><tr><th class="p-2">نام ماده</th><th class="p-2">نوع</th><th class="p-2">واحد</th><th class="p-2">گرم/بارل</th><th class="p-2">گرم/کیلوگرم</th><th class="p-2">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-2"><?php echo htmlspecialchars($item['ChemicalName']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($item['ChemicalTypeName']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($item['UnitSymbol'] ?? '-'); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($item['consumption_g_per_barrel'] ?? '-'); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($item['consumption_g_per_kg'] ?? '-'); ?></td>
                                <td class="p-2">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm py-0 px-1"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                        <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف ماده "<?php echo htmlspecialchars($item['ChemicalName']); ?>" مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                            <form method="POST" action="chemicals.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
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

