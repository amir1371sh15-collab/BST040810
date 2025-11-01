<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('base_info.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_part_weights';
const PRIMARY_KEY = 'PartWeightID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$part_id_filter = isset($_GET['part_id']) && is_numeric($_GET['part_id']) ? (int)$_GET['part_id'] : null;
$family_id_filter = isset($_GET['family_id']) && is_numeric($_GET['family_id']) ? (int)$_GET['family_id'] : null;

$selected_family_id_for_form = $family_id_filter;
if ($part_id_filter && !$family_id_filter) {
    $part_info = find_by_id($pdo, 'tbl_parts', $part_id_filter, 'PartID');
    if ($part_info) {
        $selected_family_id_for_form = $part_info['FamilyID'];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_id_redirect = null;
    $family_id_redirect = $_POST['redirect_family_id'] ?? null;

    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        $part_id_redirect = $_POST['redirect_part_id'] ?? null;
    } else {
        $part_id_redirect = (int)$_POST['part_id'];
        $effective_from = to_gregorian($_POST['effective_from']);
        $weight_gr = (float)$_POST['weight_gr'];

        if (empty($part_id_redirect) || empty($effective_from) || $weight_gr <= 0) {
            $result = ['success' => false, 'message' => 'انتخاب قطعه، وزن (مثبت) و تاریخ شروع الزامی است.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            $pdo->beginTransaction();
            try {
                $new_effective_to = !empty($_POST['effective_to']) ? to_gregorian($_POST['effective_to']) : null;
                $data = [
                    'PartID' => $part_id_redirect,
                    'WeightGR' => $weight_gr,
                    'EffectiveFrom' => $effective_from,
                    'EffectiveTo' => $new_effective_to
                ];

                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    $record_id = (int)$_POST['id'];
                    $update_previous_result = update_record(
                        $pdo,
                        'tbl_part_weights',
                        ['EffectiveTo' => date('Y-m-d', strtotime($effective_from . ' -1 day'))],
                        null,
                        null,
                        "EffectiveTo IS NULL AND PartID = ? AND PartWeightID != ?",
                        [$part_id_redirect, $record_id]
                    );
                    if (!$update_previous_result['success'] && $update_previous_result['affected_rows'] == 0 && strpos($update_previous_result['message'], 'Duplicate entry') === false) {
                        error_log("Warning: Closing previous weight record affected 0 rows for PartID $part_id_redirect when updating PartWeightID $record_id.");
                    } elseif (!$update_previous_result['success']) {
                        throw new Exception('خطا در بستن رکورد وزن قبلی (هنگام ویرایش): ' . $update_previous_result['message']);
                    }

                    $result = update_record(
                        $pdo,
                        TABLE_NAME,
                        $data,
                        $record_id,
                        PRIMARY_KEY
                    );

                } else {
                     $update_current_active_result = update_record(
                        $pdo,
                        'tbl_part_weights',
                        ['EffectiveTo' => date('Y-m-d', strtotime($effective_from . ' -1 day'))],
                        null,
                        null,
                        "EffectiveTo IS NULL AND PartID = ?",
                        [$part_id_redirect]
                    );
                     if (!$update_current_active_result['success'] && $update_current_active_result['affected_rows'] == 0 && strpos($update_current_active_result['message'], 'Duplicate entry') === false) {
                         error_log("Warning: No active weight record found to close for PartID $part_id_redirect when inserting new weight.");
                     } elseif (!$update_current_active_result['success']) {
                        throw new Exception('خطا در بستن رکورد وزن قبلی (هنگام افزودن): ' . $update_current_active_result['message']);
                    }

                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
                $pdo->commit();
                $result['message'] = $result['success'] ? (isset($_POST['id']) ? 'وزن با موفقیت بروزرسانی شد.' : 'وزن جدید با موفقیت ثبت شد.') : $result['message'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $result = ['success' => false, 'message' => 'خطا در ثبت وزن: ' . $e->getMessage()];
            }
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    $redirect_params = [];
    if($family_id_redirect) $redirect_params[] = 'family_id=' . $family_id_redirect;
    if($part_id_redirect) $redirect_params[] = 'part_id=' . $part_id_redirect;
    $redirect_url = BASE_URL . "modules/base_info/part_weights.php" . (!empty($redirect_params) ? '?' . implode('&', $redirect_params) : '');
    header("Location: " . $redirect_url);
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
    if($itemToEdit) {
        $part_id_filter = $itemToEdit['PartID'];
        $part_info_edit = find_by_id($pdo, 'tbl_parts', $part_id_filter, 'PartID');
        if($part_info_edit){
             $family_id_filter = $part_info_edit['FamilyID'];
             $selected_family_id_for_form = $family_id_filter;
        }
    }
}

$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$items_query = "SELECT pw.*, p.PartName FROM " . TABLE_NAME . " pw JOIN tbl_parts p ON pw.PartID = p.PartID";
$params = [];
if ($part_id_filter) {
    $items_query .= " WHERE pw.PartID = ?";
    $params[] = $part_id_filter;
} elseif ($family_id_filter) {
     $items_query .= " WHERE p.FamilyID = ?";
     $params[] = $family_id_filter;
}
$items_query .= " ORDER BY p.PartName, pw.EffectiveFrom DESC";
$items = find_all($pdo, $items_query, $params);

$pageTitle = "مدیریت وزن قطعات";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card content-card mb-4">
    <div class="card-header"><h5 class="mb-0">فیلتر نمایش تاریخچه</h5></div>
    <div class="card-body">
        <form id="filter-form" method="GET" action="" class="row align-items-end">
             <div class="col-md-4">
                <label for="filter_family_id" class="form-label">فیلتر بر اساس خانواده:</label>
                <select class="form-select" id="filter_family_id" name="family_id">
                    <option value="">-- همه خانواده‌ها --</option>
                    <?php foreach ($families as $family): ?>
                        <option value="<?php echo $family['FamilyID']; ?>" <?php echo ($family_id_filter == $family['FamilyID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($family['FamilyName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="col-md-4">
                 <label for="filter_part_id" class="form-label">فیلتر بر اساس قطعه:</label>
                 <select class="form-select" id="filter_part_id" name="part_id" <?php echo !$family_id_filter ? 'disabled' : ''; ?>>
                     <option value="">-- همه قطعات <?php echo $family_id_filter ? 'این خانواده' : '(ابتدا خانواده را انتخاب کنید)'; ?> --</option>
                 </select>
             </div>
             <div class="col-md-auto">
                 <button type="submit" class="btn btn-primary">فیلتر</button>
                 <a href="part_weights.php" class="btn btn-outline-secondary ms-2">پاک کردن</a>
             </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش وزن' : 'افزودن وزن جدید'; ?></h5></div><div class="card-body">
            <form id="part-weight-form" method="POST" action="part_weights.php">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <input type="hidden" name="redirect_family_id" value="<?php echo $family_id_filter; ?>">
                <input type="hidden" name="redirect_part_id" value="<?php echo $part_id_filter; ?>">

                <div class="mb-3">
                    <label for="form_family_id" class="form-label">خانواده قطعه</label>
                    <select class="form-select" id="form_family_id" name="form_family_id" required <?php echo $editMode ? 'disabled' : ''; ?>>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($families as $family): ?>
                            <option value="<?php echo $family['FamilyID']; ?>" <?php echo ($selected_family_id_for_form == $family['FamilyID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($family['FamilyName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="family_id_edit_value" value="<?php echo $selected_family_id_for_form; ?>">
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">قطعه</label>
                    <select class="form-select" id="form_part_id" name="part_id" required <?php echo $editMode || !$selected_family_id_for_form ? 'disabled' : ''; ?>>
                        <option value="">-- <?php echo $selected_family_id_for_form ? 'انتخاب کنید' : 'ابتدا خانواده را انتخاب کنید'; ?> --</option>
                    </select>
                     <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="part_id" value="<?php echo $itemToEdit['PartID']; ?>">
                    <?php endif; ?>
                </div>

                <div class="mb-3"><label class="form-label">وزن (گرم)</label><input type="number" step="0.001" class="form-control" name="weight_gr" value="<?php echo htmlspecialchars($itemToEdit['WeightGR'] ?? ''); ?>" required></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">از تاریخ</label><input type="text" class="form-control persian-date" name="effective_from" value="<?php echo to_jalali($itemToEdit['EffectiveFrom'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">تا تاریخ (اختیاری)</label><input type="text" class="form-control persian-date" name="effective_to" value="<?php echo to_jalali($itemToEdit['EffectiveTo'] ?? ''); ?>"></div>
                </div>
                <small class="form-text text-muted mb-3 d-block">در صورت ثبت وزن جدید برای یک قطعه، وزن قبلی که 'تا تاریخ' ندارد به صورت خودکار بسته می‌شود.</small>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="part_weights.php?family_id=<?php echo $family_id_filter; ?>&part_id=<?php echo $part_id_filter; ?>" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">تاریخچه وزن قطعات <?php
        if($part_id_filter) { $p = find_by_id($pdo, 'tbl_parts', $part_id_filter, 'PartID'); echo '(' . htmlspecialchars($p['PartName']) . ')'; }
        elseif($family_id_filter) { $f = find_by_id($pdo, 'tbl_part_families', $family_id_filter, 'FamilyID'); echo '(خانواده: ' . htmlspecialchars($f['FamilyName']) . ')'; }
        ?></h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">قطعه</th><th class="p-3">وزن (گرم)</th><th class="p-3">از تاریخ</th><th class="p-3">تا تاریخ</th><th class="p-3">عملیات</th></tr></thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" class="text-center p-3 text-muted">موردی برای نمایش یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="p-3"><?php echo htmlspecialchars($item['PartName']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['WeightGR']); ?></td>
                        <td class="p-3"><?php echo to_jalali($item['EffectiveFrom']); ?></td>
                        <td class="p-3"><?php echo $item['EffectiveTo'] ? to_jalali($item['EffectiveTo']) : '<span class="badge bg-success">فعال</span>'; ?></td>
                        <td class="p-3">
                            <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>&family_id=<?php echo $family_id_filter; ?>&part_id=<?php echo $item['PartID']; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                            <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">آیا از حذف این رکورد وزن مطمئن هستید؟</div>
                                <div class="modal-footer">
                                    <form method="POST" action="part_weights.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><input type="hidden" name="redirect_part_id" value="<?php echo $item['PartID']; ?>"><input type="hidden" name="redirect_family_id" value="<?php echo $family_id_filter; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                </div>
                            </div></div></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const filterFamilySelect = $('#filter_family_id');
    const filterPartSelect = $('#filter_part_id');
    const formFamilySelect = $('#form_family_id');
    const formPartSelect = $('#form_part_id');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php';
    const isEditMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const initialPartIdFilter = '<?php echo $part_id_filter ?? ''; ?>';
    const initialPartIdForm = '<?php echo $itemToEdit['PartID'] ?? ($part_id_filter ?? ''); ?>';

    async function populateParts(familyId, targetSelect, selectedPartId = null, defaultOptionText = '-- انتخاب کنید --') {
        targetSelect.prop('disabled', true).html(`<option value="">${familyId ? 'در حال بارگذاری...' : '-- ابتدا خانواده را انتخاب کنید --'}</option>`);
        if (!familyId) return;

        try {
            // Added explicit error handling for AJAX
            const response = await $.getJSON(apiPartsUrl, { family_id: familyId });
            targetSelect.html(`<option value="">${defaultOptionText}</option>`);
            if (response.success && response.data.length > 0) {
                response.data.forEach(part => {
                    const option = $('<option>', {
                        value: part.PartID,
                        text: part.PartName // Display only PartName
                    });
                    if (part.PartID == selectedPartId) {
                        option.prop('selected', true);
                    }
                    targetSelect.append(option);
                });
                targetSelect.prop('disabled', false);
            } else {
                targetSelect.html(`<option value="">${response.message || '-- قطعه‌ای یافت نشد --'}</option>`);
            }
        } catch (jqXHR) { // Changed 'error' to 'jqXHR' for clarity
            console.error("Error fetching parts via AJAX:", jqXHR.status, jqXHR.statusText, jqXHR.responseText);
            let errorMsg = '-- خطا در بارگذاری --';
            if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                 errorMsg = jqXHR.responseJSON.message; // Use API message if available
            } else if (jqXHR.status === 404) {
                 errorMsg = '-- آدرس API یافت نشد --';
            } else if (jqXHR.status === 500) {
                 errorMsg = '-- خطای داخلی سرور --';
            }
            targetSelect.html(`<option value="">${errorMsg}</option>`);
        }
    }

    filterFamilySelect.on('change', function() {
        const familyId = $(this).val();
        populateParts(familyId, filterPartSelect, null, '-- همه قطعات این خانواده --');
        if (!isEditMode || $(this).data('triggered-by-load') !== true) {
             filterPartSelect.val('');
        }
         $(this).data('triggered-by-load', false);
    });

    formFamilySelect.on('change', function() {
        if (isEditMode) return;
        const familyId = $(this).val();
        populateParts(familyId, formPartSelect);
    });

    // --- Initial Population ---
    // Populate filter dropdown if family is selected
    if (filterFamilySelect.val()) {
         filterFamilySelect.data('triggered-by-load', true);
        populateParts(filterFamilySelect.val(), filterPartSelect, initialPartIdFilter, '-- همه قطعات این خانواده --');
    }

    // Populate form dropdown if family is selected (in add or edit mode)
    const initialFamilyIdForm = formFamilySelect.val();
    if (initialFamilyIdForm) {
        // Use initialPartIdForm which correctly considers edit mode or filter selection
        populateParts(initialFamilyIdForm, formPartSelect, initialPartIdForm);
    }
});
</script>

