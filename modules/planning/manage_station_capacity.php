<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('planning_constraints.manage')) { 
    die('شما مجوز دسترسی به این صفحه را ندارید.'); 
}

const TABLE_NAME = 'tbl_planning_station_capacity_rules';
const PRIMARY_KEY = 'RuleID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// واکشی ایستگاه‌ها برای دراپ‌داون
$stations = $pdo->query("SELECT StationID, StationName FROM tbl_stations ORDER BY StationName")->fetchAll(PDO::FETCH_ASSOC);

// تعریف متدهای محاسبه ظرفیت
$calculation_methods = [
    'FixedAmount' => 'مقدار ثابت روزانه (پیش‌فرض)',
    'ManHours' => 'نفر-ساعت در دسترس',
    'OEE' => 'OEE (بر اساس راندمان گذشته)',
    'PlatingManHours' => 'نفر-ساعت آبکاری (منطق خاص)',
    'AssemblySmall' => 'مونتاژ (دستگاه کوچک - ثابت)',
    'AssemblyLarge' => 'مونتاژ (دستگاه بزرگ - ثابت)',
    'Rolling' => 'رول (ثابت)',
    'Packaging' => 'بسته‌بندی (ثابت)'
];

// تعریف واحدهای ظرفیت استاندارد
$capacity_units = [
    'KG/Day' => 'کیلوگرم / روز',
    'Pieces/Day' => 'عدد / روز',
    'Carton/Day' => 'کارتن / روز',
    'ManHours' => 'نفر-ساعت'
];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_id'])) {
            // حذف رکورد
            $sql_delete = "DELETE FROM " . TABLE_NAME . " WHERE " . PRIMARY_KEY . " = ?";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([(int)$_POST['delete_id']]);
            
            $_SESSION['message'] = 'قانون با موفقیت حذف شد.';
            $_SESSION['message_type'] = 'success';
        } else {
            $data = [
                'StationID' => (int)$_POST['station_id'],
                'CalculationMethod' => $_POST['calculation_method'],
                'StandardValue' => !empty($_POST['standard_value']) ? (float)$_POST['standard_value'] : null,
                'CapacityUnit' => $_POST['capacity_unit'],
                'Notes' => trim($_POST['notes'])
            ];

            // اعتبارسنجی
            if (empty($data['StationID']) || empty($data['CalculationMethod']) || empty($data['CapacityUnit'])) {
                $_SESSION['message'] = 'ایستگاه، متد محاسبه و واحد ظرفیت الزامی است.';
                $_SESSION['message_type'] = 'warning';
            } else {
                // جلوگیری از ثبت ایستگاه تکراری
                $existing_rule_q = "SELECT RuleID FROM " . TABLE_NAME . " WHERE StationID = ? AND RuleID != ?";
                $stmt_check = $pdo->prepare($existing_rule_q);
                $stmt_check->execute([$data['StationID'], (int)($_POST[PRIMARY_KEY] ?? 0)]);
                $existing_rule = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($existing_rule) {
                    $_SESSION['message'] = 'یک قانون محاسبه ظرفیت برای این ایستگاه قبلاً ثبت شده است.';
                    $_SESSION['message_type'] = 'danger';
                } else {
                    if (isset($_POST[PRIMARY_KEY]) && !empty($_POST[PRIMARY_KEY])) {
                        // Update
                        $sql_update = "UPDATE " . TABLE_NAME . " SET 
                                      StationID = ?, 
                                      CalculationMethod = ?, 
                                      StandardValue = ?, 
                                      CapacityUnit = ?, 
                                      Notes = ? 
                                      WHERE " . PRIMARY_KEY . " = ?";
                        $stmt_update = $pdo->prepare($sql_update);
                        $stmt_update->execute([
                            $data['StationID'],
                            $data['CalculationMethod'],
                            $data['StandardValue'],
                            $data['CapacityUnit'],
                            $data['Notes'],
                            (int)$_POST[PRIMARY_KEY]
                        ]);
                        
                        $_SESSION['message'] = 'قانون با موفقیت بروزرسانی شد.';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        // Insert
                        $sql_insert = "INSERT INTO " . TABLE_NAME . " 
                                      (StationID, CalculationMethod, StandardValue, CapacityUnit, Notes) 
                                      VALUES (?, ?, ?, ?, ?)";
                        $stmt_insert = $pdo->prepare($sql_insert);
                        $stmt_insert->execute([
                            $data['StationID'],
                            $data['CalculationMethod'],
                            $data['StandardValue'],
                            $data['CapacityUnit'],
                            $data['Notes']
                        ]);
                        
                        $_SESSION['message'] = 'قانون با موفقیت ایجاد شد.';
                        $_SESSION['message_type'] = 'success';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database Error in manage_station_capacity.php: " . $e->getMessage());
        $_SESSION['message'] = 'خطای دیتابیس: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header("Location: " . BASE_URL . "modules/planning/manage_station_capacity.php");
    exit;
}

// Handle GET request for editing
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editMode = true;
    $sql_edit = "SELECT * FROM " . TABLE_NAME . " WHERE " . PRIMARY_KEY . " = ?";
    $stmt_edit = $pdo->prepare($sql_edit);
    $stmt_edit->execute([(int)$_GET['edit_id']]);
    $itemToEdit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// واکشی قوانین ثبت شده
$rules = $pdo->query("
    SELECT r.*, s.StationName 
    FROM tbl_planning_station_capacity_rules r
    JOIN tbl_stations s ON r.StationID = s.StationID
    ORDER BY s.StationName
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "مدیریت قوانین محاسبه ظرفیت";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>modules/planning/constraints_index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-right"></i> بازگشت
    </a>
</div>

<p class="lead mt-3">در این صفحه، "قوانین" محاسبه ظرفیت را برای هر ایستگاه تعریف کنید. این قوانین مشخص می‌کنند که ظرفیت پیشنهادی سیستم چگونه محاسبه شود.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Form Card -->
<div class="card content-card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><?php echo $editMode ? 'ویرایش قانون ظرفیت' : 'افزودن قانون ظرفیت جدید'; ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="manage_station_capacity.php">
            <?php if ($editMode && $itemToEdit): ?>
                <input type="hidden" name="<?php echo PRIMARY_KEY; ?>" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="station_id" class="form-label">ایستگاه <span class="text-danger">*</span></label>
                    <select class="form-select" id="station_id" name="station_id" required>
                        <option value="">-- انتخاب ایستگاه --</option>
                        <?php foreach ($stations as $station): ?>
                            <option value="<?php echo $station['StationID']; ?>" 
                                <?php echo ($editMode && $itemToEdit && $itemToEdit['StationID'] == $station['StationID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($station['StationName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="calculation_method" class="form-label">متد محاسبه ظرفیت <span class="text-danger">*</span></label>
                    <select class="form-select" id="calculation_method" name="calculation_method" required>
                        <?php foreach ($calculation_methods as $key => $label): ?>
                            <option value="<?php echo $key; ?>" 
                                <?php echo ($editMode && $itemToEdit && $itemToEdit['CalculationMethod'] == $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="capacity_unit" class="form-label">واحد ظرفیت <span class="text-danger">*</span></label>
                    <select class="form-select" id="capacity_unit" name="capacity_unit" required>
                        <?php foreach ($capacity_units as $key => $label): ?>
                            <option value="<?php echo $key; ?>" 
                                <?php echo ($editMode && $itemToEdit && $itemToEdit['CapacityUnit'] == $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12">
                    <label for="standard_value" class="form-label">مقدار استاندارد (پیش‌فرض)</label>
                    <input type="number" step="0.01" class="form-control" id="standard_value" name="standard_value" 
                           value="<?php echo htmlspecialchars($itemToEdit['StandardValue'] ?? ''); ?>" 
                           placeholder="مقدار ثابت (در صورت استفاده از متد FixedAmount)">
                    <small class="form-text text-muted">اگر متد "مقدار ثابت" باشد، این عدد استفاده می‌شود. برای سایر متدها به عنوان پشتیبان عمل می‌کند.</small>
                </div>

                <div class="col-md-12">
                    <label for="notes" class="form-label">یادداشت</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($itemToEdit['Notes'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-<?php echo $editMode ? 'primary' : 'success'; ?>">
                    <i class="bi bi-<?php echo $editMode ? 'pencil' : 'check2-circle'; ?>"></i> 
                    <?php echo $editMode ? 'بروزرسانی قانون' : 'افزودن قانون'; ?>
                </button>
                <?php if ($editMode): ?>
                    <a href="manage_station_capacity.php" class="btn btn-outline-secondary">انصراف از ویرایش</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card content-card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">قوانین ظرفیت تعریف شده</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ایستگاه</th>
                        <th>متد محاسبه</th>
                        <th>مقدار استاندارد</th>
                        <th>واحد ظرفیت</th>
                        <th>یادداشت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rules)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                هیچ قانونی تعریف نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rule['StationName']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($calculation_methods[$rule['CalculationMethod']] ?? $rule['CalculationMethod']); ?>
                                    </span>
                                </td>
                                <td><?php echo $rule['StandardValue'] ? number_format($rule['StandardValue'], 2) : '-'; ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($capacity_units[$rule['CapacityUnit']] ?? $rule['CapacityUnit']); ?>
                                    </span>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($rule['Notes']) ?: '-'; ?></td>
                                <td>
                                    <a href="?edit_id=<?php echo $rule[PRIMARY_KEY]; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="ویرایش">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="manage_station_capacity.php" class="d-inline" 
                                          onsubmit="return confirm('آیا از حذف این قانون مطمئن هستید؟');">
                                        <input type="hidden" name="delete_id" value="<?php echo $rule[PRIMARY_KEY]; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>