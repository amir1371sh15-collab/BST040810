<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_planning_batch_compatibility';
const PRIMARY_KEY = 'CompatibilityID';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // Find the pair to delete the reverse
        $item = find_by_id($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        if ($item) {
            // Delete the reverse pair
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . " WHERE PrimaryPartID = ? AND CompatiblePartID = ?");
            $stmt->execute([$item['CompatiblePartID'], $item['PrimaryPartID']]);
        }
        // Delete the main pair
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        
    } else {
        $primary_part_id = (int)$_POST['primary_part_id'];
        $compatible_part_id = (int)$_POST['compatible_part_id'];

        if (empty($primary_part_id) || empty($compatible_part_id)) {
            $result = ['success' => false, 'message' => 'هر دو قطعه اصلی و سازگار باید انتخاب شوند.'];
            $_SESSION['message_type'] = 'warning';
        } elseif ($primary_part_id === $compatible_part_id) {
            $result = ['success' => false, 'message' => 'قطعه نمی‌تواند با خودش سازگار باشد.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            $data = [
                'PrimaryPartID' => $primary_part_id,
                'CompatiblePartID' => $compatible_part_id,
            ];
            try {
                // Insert A -> B
                $result = insert_record($pdo, TABLE_NAME, $data);
                
                // Insert B -> A for easier lookup
                $data_reverse = [
                    'PrimaryPartID' => $compatible_part_id,
                    'CompatiblePartID' => $primary_part_id,
                ];
                // Try to insert reverse, ignore if it fails (e.g., duplicate)
                try {
                    insert_record($pdo, TABLE_NAME, $data_reverse);
                } catch (PDOException $e) {
                    // Ignore duplicate entry error for the reverse pair
                }
                
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $result = ['success' => false, 'message' => 'خطا: این جفت سازگاری از قبل تعریف شده است.'];
                    $_SESSION['message_type'] = 'warning';
                } else {
                    $result = ['success' => false, 'message' => 'خطا در ثبت: ' . $e->getMessage()];
                    $_SESSION['message_type'] = 'danger';
                }
            }
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/Planning/manage_batch_compatibility.php");
    exit;
}

$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
// Data for table (show only one direction, e.g., A->B where A < B)
$items = find_all($pdo, "
    SELECT c.*, p1.PartName as PrimaryPartName, p2.PartName as CompatiblePartName
    FROM " . TABLE_NAME . " c
    JOIN tbl_parts p1 ON c.PrimaryPartID = p1.PartID
    JOIN tbl_parts p2 ON c.CompatiblePartID = p2.PartID
    WHERE c.PrimaryPartID < c.CompatiblePartID -- فقط یک جهت را برای نمایش نشان بده
    ORDER BY p1.PartName, p2.PartName
");

$pageTitle = "مدیریت سازگاری بچ (آبکاری)";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="constraints_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">تعریف جفت سازگار</h5></div>
            <div class="card-body">
                <form method="POST" action="manage_batch_compatibility.php">
                    <p class="small text-muted">تعریف کنید کدام قطعه «می‌تواند» در کنار قطعه دیگر آبکاری شود.</p>
                    
                    <label class="form-label fw-bold">قطعه اصلی (Primary)</label>
                    <div class="row">
                        <div class="col-6 mb-3"><select class="form-select family-selector" data-target="#primary_part_id"><option value="">-- خانواده --</option><?php foreach($families as $f): ?><option value="<?php echo $f['FamilyID']; ?>"><?php echo htmlspecialchars($f['FamilyName']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-6 mb-3"><select class="form-select part-selector" id="primary_part_id" name="primary_part_id" required disabled><option value="">-- قطعه --</option></select></div>
                    </div>

                    <label class="form-label fw-bold">قطعه سازگار (Compatible)</label>
                    <div class="row">
                        <div class="col-6 mb-3"><select class="form-select family-selector" data-target="#compatible_part_id"><option value="">-- خانواده --</option><?php foreach($families as $f): ?><option value="<?php echo $f['FamilyID']; ?>"><?php echo htmlspecialchars($f['FamilyName']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-6 mb-3"><select class="form-select part-selector" id="compatible_part_id" name="compatible_part_id" required disabled><option value="">-- قطعه --</option></select></div>
                    </div>

                    <button type="submit" class="btn btn-primary">افزودن سازگاری</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">جفت‌های سازگار تعریف شده</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">قطعه اصلی</th><th class="p-3">سازگار با</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="3" class="text-center p-3 text-muted">هیچ جفت سازگاری تعریف نشده است.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['PrimaryPartName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['CompatiblePartName']); ?></td>
                                <td class="p-3">
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                        <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">آیا از حذف این سازگاری مطمئن هستید؟ (جفت برعکس نیز حذف خواهد شد)</div>
                                        <div class="modal-footer">
                                            <form method="POST"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
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
<script>
$(document).ready(function() {
    // We re-use the API from BOM management
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_all_parts_for_bom.php'; 

    $('.family-selector').on('change', function() {
        const familyId = $(this).val();
        const targetPartSelector = $(this).data('target');
        const partSelect = $(targetPartSelector);
        
        partSelect.prop('disabled', true).html('<option value="">...</option>');
        
        if (familyId) {
            $.getJSON(apiPartsUrl, { family_id: familyId }, function(response) {
                partSelect.html('<option value="">-- انتخاب قطعه --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(part) {
                        partSelect.append($('<option>', { value: part.PartID, text: part.PartName }));
                    });
                    partSelect.prop('disabled', false);
                } else {
                    partSelect.html('<option value="">قطعه‌ای یافت نشد</option>');
                }
            });
        } else {
            partSelect.html('<option value="">-- ابتدا خانواده --</option>');
        }
    });
});
</script>

