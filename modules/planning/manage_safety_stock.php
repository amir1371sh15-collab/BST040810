<?php
require_once __DIR__ . '/../../config/init.php';
// مجوز مدیریت نقطه سفارش (باید در بخش دسترسی‌ها اضافه شود)
if (!has_permission('planning.manage_safety_stock')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_inventory_safety_stock';
const PRIMARY_KEY = 'SafetyStockID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'PartID' => (int)$_POST['part_id'],
            'StationID' => (int)$_POST['station_id'],
            'StatusID' => !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null,
            'SafetyStockValue' => (float)$_POST['safety_stock_value'],
            'Unit' => $_POST['unit']
        ];

        if (empty($data['PartID']) || empty($data['StationID']) || empty($data['SafetyStockValue']) || empty($data['Unit'])) {
             $result = ['success' => false, 'message' => 'قطعه، ایستگاه، مقدار و واحد الزامی هستند.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            // Check for existing rule (unique constraint)
            $existing_sql = "SELECT SafetyStockID FROM " . TABLE_NAME . " WHERE PartID = ? AND StationID = ? AND " . ($data['StatusID'] ? "StatusID = ?" : "StatusID IS NULL");
            $existing_params = $data['StatusID'] ? [$data['PartID'], $data['StationID'], $data['StatusID']] : [$data['PartID'], $data['StationID']];
            
            if (isset($_POST['id']) && !empty($_POST['id'])) { // Update
                $existing_sql .= " AND SafetyStockID != ?";
                $existing_params[] = (int)$_POST['id'];
            }
            // --- BEGIN MISSING CODE ---
            $existing_rule = find_all($pdo, $existing_sql, $existing_params);

            if (!empty($existing_rule)) {
                $result = ['success' => false, 'message' => 'خطا: یک قانون نقطه سفارش برای این قطعه، ایستگاه و وضعیت از قبل وجود دارد.'];
                $_SESSION['message_type'] = 'warning';
            } else {
                if (isset($_POST['id']) && !empty($_POST['id'])) { // Update
                    $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                } else { // Insert
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
                 $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        } // <-- **این آکولاد بسته شدن برای بلوک else (خط 31) بود که جا افتاده بود**
        // --- END MISSING CODE ---
          $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/planning/manage_safety_stock.php");
    exit;
}

// Handle Edit
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
    
    // --- NEW: Find FamilyID for edit mode ---
    if ($itemToEdit) {
        $partInfo = find_by_id($pdo, 'tbl_parts', $itemToEdit['PartID'], 'PartID');
        $itemToEdit['FamilyID'] = $partInfo ? $partInfo['FamilyID'] : null;
    }
    // --- END NEW ---
}

// Fetch data for table
$items_query = "
    SELECT ss.*, p.PartName, s.StationName, ps.StatusName 
    FROM " . TABLE_NAME . " ss
    JOIN tbl_parts p ON ss.PartID = p.PartID
    JOIN tbl_stations s ON ss.StationID = s.StationID
    LEFT JOIN tbl_part_statuses ps ON ss.StatusID = ps.StatusID
    ORDER BY p.PartName, s.StationName, ps.StatusName
";
$items = find_all($pdo, $items_query);

// Data for dropdowns (loaded via AJAX)
$all_statuses = find_all($pdo, "SELECT StatusID, StatusName FROM tbl_part_statuses ORDER BY StatusName");
// --- NEW: Fetch families for the new dropdown ---
$all_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$pageTitle = "مدیریت نقاط سفارش (Safety Stock)";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card mb-4">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش نقطه سفارش' : 'افزودن نقطه سفارش جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="manage_safety_stock.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    
                    <!-- NEW: Family Filter Dropdown -->
                    <div class="mb-3">
                        <label for="family_id" class="form-label">خانواده قطعه *</label>
                        <select class="form-select" id="family_id" name="family_id_filter" required> <!-- Name is not submitted, just for filter -->
                            <option value="">-- انتخاب خانواده --</option>
                            <?php foreach ($all_families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>" 
                                    <?php echo ($editMode && isset($itemToEdit['FamilyID']) && $itemToEdit['FamilyID'] == $family['FamilyID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($family['FamilyName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="part_id" class="form-label">قطعه *</label>
                        <select class="form-select" id="part_id" name="part_id" required disabled>
                            <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="station_id" class="form-label">ایستگاه/انبار *</label>
                        <select class="form-select" id="station_id" name="station_id" required>
                             <option value="">-- در حال بارگذاری... --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status_id" class="form-label">وضعیت قطعه *</label>
                        <select class="form-select" id="status_id" name="status_id" required>
                             <option value="">-- انتخاب کنید --</option>
                             <option value="NULL" <?php echo ($editMode && $itemToEdit['StatusID'] === null) ? 'selected' : ''; ?>>-- بدون وضعیت --</option>
                             <?php foreach ($all_statuses as $status): ?>
                                <option value="<?php echo $status['StatusID']; ?>" <?php echo ($editMode && $itemToEdit['StatusID'] == $status['StatusID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['StatusName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                             <label for="safety_stock_value" class="form-label">مقدار نقطه سفارش *</label>
                             <input type="number" step="0.01" class="form-control" id="safety_stock_value" name="safety_stock_value" value="<?php echo htmlspecialchars($itemToEdit['SafetyStockValue'] ?? ''); ?>" required>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="unit" class="form-label">واحد *</label>
                            <select class="form-select" id="unit" name="unit" required>
                                <option value="">--</option>
                                <option value="KG" <?php echo ($editMode && $itemToEdit['Unit'] == 'KG') ? 'selected' : ''; ?>>KG (کیلوگرم)</option>
                                <option value="Carton" <?php echo ($editMode && $itemToEdit['Unit'] == 'Carton') ? 'selected' : ''; ?>>Carton (کارتن)</option>
                            </select>
                        </div>
                    </div>
                    <small class.="form-text text-muted">قانون: اگر موجودی (قطعه/ایستگاه/وضعیت) به این مقدار رسید، هشدار صادر شود.</small>
                    <hr>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="manage_safety_stock.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست نقاط سفارش تعریف شده</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead><tr><th class="p-2">قطعه</th><th class="p-2">ایستگاه</th><th class="p-2">وضعیت</th><th class="p-2">نقطه سفارش</th><th class="p-2">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="5" class="text-center p-3 text-muted">هیچ نقطه سفارشی تعریف نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="p-2"><?php echo htmlspecialchars($item['PartName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['StationName']); ?></td>
                                    <td class="p-2"><span class="badge bg-secondary"><?php echo htmlspecialchars($item['StatusName'] ?? '-- بدون وضعیت --'); ?></span></td>
                                    <td class="p-2 fw-bold"><?php echo htmlspecialchars($item['SafetyStockValue'] . ' ' . $item['Unit']); ?></td>
                                    <td class="p-2 text-nowrap">
                                        <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                        <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">آیا از حذف این قانون نقطه سفارش مطمئن هستید؟</div>
                                            <div class="modal-footer">
                                                <form method="POST" action=""><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                            </div>
                                        </div></div></div>
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
<script>
$(document).ready(function() {
    const partSelect = $('#part_id');
    const familySelect = $('#family_id'); // NEW
    const stationSelect = $('#station_id');
    const apiPartsByFamilyUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php'; // NEW
    const apiStationsUrl = '<?php echo BASE_URL; ?>api/api_get_stations_by_type.php';
    
    const initialPartId = '<?php echo $itemToEdit['PartID'] ?? ''; ?>';
    const initialFamilyId = '<?php echo $itemToEdit['FamilyID'] ?? ''; ?>'; // NEW
    const initialStationId = '<?php echo $itemToEdit['StationID'] ?? ''; ?>';

    // --- NEW: Function to populate parts based on family ---
    async function populateParts(familyId, selectedPartId = null) {
        partSelect.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
        if (!familyId) {
            partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }
        
        try {
            const response = await $.getJSON(apiPartsByFamilyUrl, { family_id: familyId });
            partSelect.html('<option value="">-- انتخاب قطعه --</option>');
            if (response.success && response.data.length > 0) {
                response.data.forEach(function(part) {
                    const option = $('<option>', { value: part.PartID, text: part.PartName });
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

    // --- Modified: Load Stations (independent) and trigger parts load ---
    async function loadDropdowns() {
        try {
            // Load Stations (this is independent)
            const stationResponse = await $.getJSON(apiStationsUrl);
            stationSelect.html('<option value="">-- انتخاب ایستگاه/انبار --</option>');
            if (stationResponse.success && stationResponse.data) {
                 $.each(stationResponse.data, function(type, stations) {
                    let group = $('<optgroup>', { label: type });
                    stations.forEach(station => {
                        group.append($('<option>', { value: station.StationID, text: station.StationName }));
                    });
                    stationSelect.append(group);
                });
            }
             if (initialStationId) stationSelect.val(initialStationId);

            // --- NEW: Load parts if in edit mode or family is pre-selected ---
            if (initialFamilyId) {
                await populateParts(initialFamilyId, initialPartId);
            }

        } catch (error) {
            console.error("Error loading dropdown data:", error);
            stationSelect.html('<option value="">خطا در بارگذاری</option>');
            if (!initialFamilyId) {
                 partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            }
        }
    }

    loadDropdowns();

    // --- NEW: Event listener for family select ---
    familySelect.on('change', function() {
        populateParts($(this).val());
    });
});
</script>

