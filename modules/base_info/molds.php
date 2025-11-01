<?php
require_once __DIR__ . '/../../config/db.php';

const TABLE_NAME_MOLDS = 'tbl_molds';
const PRIMARY_KEY_MOLDS = 'MoldID';

$editMode = false;
$itemToEdit = null;

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // Deleting a mold will also delete its part associations due to CASCADE constraint
        $result = delete_record($pdo, TABLE_NAME_MOLDS, (int)$_POST['delete_id'], PRIMARY_KEY_MOLDS);
    } else {
        if (empty(trim($_POST['mold_name']))) {
            $result = ['success' => false, 'message' => 'نام قالب نمی‌تواند خالی باشد.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            $data = [
                'MoldName' => trim($_POST['mold_name']),
                'Status' => trim($_POST['status'])
            ];
            
            $pdo->beginTransaction();
            try {
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    // --- UPDATE ---
                    $moldId = (int)$_POST['id'];
                    $result = update_record($pdo, TABLE_NAME_MOLDS, $data, $moldId, PRIMARY_KEY_MOLDS);
                } else {
                    // --- INSERT ---
                    $result = insert_record($pdo, TABLE_NAME_MOLDS, $data);
                    $moldId = $result['id'];
                }

                if ($result['success']) {
                    // --- Handle Part Associations ---
                    $producible_parts = $_POST['producible_parts'] ?? [];

                    // 1. Delete existing associations for this mold
                    $delete_stmt = $pdo->prepare("DELETE FROM tbl_mold_producible_parts WHERE MoldID = ?");
                    $delete_stmt->execute([$moldId]);

                    // 2. Insert new associations
                    if (!empty($producible_parts)) {
                        $insert_stmt = $pdo->prepare("INSERT INTO tbl_mold_producible_parts (MoldID, PartID) VALUES (?, ?)");
                        foreach ($producible_parts as $part_id) {
                            $insert_stmt->execute([$moldId, (int)$part_id]);
                        }
                    }
                }
                $pdo->commit();
                 $result['message'] = $editMode ? 'قالب با موفقیت بروزرسانی شد.' : 'قالب با موفقیت ایجاد شد.';

            } catch (Exception $e) {
                $pdo->rollBack();
                $result = ['success' => false, 'message' => 'خطا در عملیات پایگاه داده: ' . $e->getMessage()];
            }

            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    
    header("Location: " . BASE_URL . "modules/base_info/molds.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME_MOLDS, (int)$_GET['edit_id'], PRIMARY_KEY_MOLDS);
}

// Corrected query to show all molds and their associated parts
$items = find_all($pdo, "
    SELECT 
        m.*, 
        GROUP_CONCAT(p.PartName SEPARATOR ' - ') as ProducibleParts
    FROM " . TABLE_NAME_MOLDS . " m
    LEFT JOIN tbl_mold_producible_parts mpp ON m.MoldID = mpp.MoldID
    LEFT JOIN tbl_parts p ON mpp.PartID = p.PartID
    GROUP BY m.MoldID
    ORDER BY m.MoldName
");

// Data for dropdowns
$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$pageTitle = "مدیریت قالب‌ها";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت قالب‌ها</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/machinery_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش قالب' : 'افزودن قالب جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="molds.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY_MOLDS]; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="mold_name" class="form-label">نام قالب</label>
                        <input type="text" class="form-control" id="mold_name" name="mold_name" value="<?php echo htmlspecialchars($itemToEdit['MoldName'] ?? ''); ?>" required>
                    </div>

                    <hr>
                    <h6 class="mb-3">قطعات قابل تولید</h6>
                    <div class="mb-3">
                        <label for="family_id_selector" class="form-label">۱. خانواده قطعه را برای نمایش انتخاب کنید:</label>
                        <select class="form-select" id="family_id_selector">
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($part_families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">۲. قطعات قابل تولید را تیک بزنید:</label>
                        <div id="parts-checkbox-container" class="border rounded p-2" style="height: 200px; overflow-y: auto;">
                            <small class="text-muted">ابتدا یک خانواده را انتخاب کنید.</small>
                        </div>
                    </div>

                     <hr>

                    <div class="mb-3">
                        <label for="status" class="form-label">وضعیت</label>
                        <input type="text" class="form-control" id="status" name="status" value="<?php echo htmlspecialchars($itemToEdit['Status'] ?? 'Active'); ?>">
                    </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="molds.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست قالب‌ها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">نام قالب</th><th class="p-3">قطعات تولیدی</th><th class="p-3">وضعیت</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['MoldName']); ?></td>
                                <td class="p-3 small"><?php echo htmlspecialchars($item['ProducibleParts'] ?? 'تعریف نشده'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['Status']); ?></td>
                                <td class="p-3">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY_MOLDS]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY_MOLDS]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                     <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY_MOLDS]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف قالب "<?php echo htmlspecialchars($item['MoldName']); ?>" مطمئن هستید؟</div>
                                        <div class="modal-footer">
                                          <form method="POST" action="molds.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY_MOLDS]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                        </div>
                                      </div></div></div>
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

<script>
$(document).ready(function() {
    const familySelect = $('#family_id_selector');
    const partsContainer = $('#parts-checkbox-container');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php';
    const apiMoldPartsUrl = '<?php echo BASE_URL; ?>api/api_get_mold_parts.php'; // A new API will be needed
    
    // For edit mode, get the current mold ID
    const moldId = <?php echo $editMode ? (int)$_GET['edit_id'] : 'null'; ?>;
    let existingPartIds = [];

    function fetchAndDisplayParts(familyId) {
        partsContainer.html('<small class="text-muted">در حال بارگذاری قطعات...</small>');

        $.getJSON(apiPartsUrl, { family_id: familyId }, function(response) {
            partsContainer.empty();
            if (response.success && response.data.length > 0) {
                $.each(response.data, function(i, part) {
                    const isChecked = existingPartIds.includes(part.PartID.toString());
                    const checkbox = `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="producible_parts[]" value="${part.PartID}" id="part_${part.PartID}" ${isChecked ? 'checked' : ''}>
                            <label class="form-check-label" for="part_${part.PartID}">${part.PartName}</label>
                        </div>`;
                    partsContainer.append(checkbox);
                });
            } else {
                partsContainer.html('<small class="text-muted">هیچ قطعه‌ای برای این خانواده یافت نشد.</small>');
            }
        });
    }

    familySelect.on('change', function() {
        const familyId = $(this).val();
        if (familyId) {
            fetchAndDisplayParts(familyId);
        } else {
            partsContainer.html('<small class="text-muted">ابتدا یک خانواده را انتخاب کنید.</small>');
        }
    });

    // --- In Edit Mode ---
    // We need to fetch the currently associated parts first
    if (moldId) {
        // Create an API to get associated parts for a mold
        // For now, let's assume `api_get_mold_parts.php` exists
        $.getJSON(apiMoldPartsUrl, { mold_id: moldId }, function(response) {
            if (response.success) {
                existingPartIds = response.data; // This should be an array of PartIDs like ['12', '15']
            }
        }).fail(function() {
             console.error("Could not load existing parts for mold. Make sure api_get_mold_parts.php is created.");
        });
    }
});
</script>