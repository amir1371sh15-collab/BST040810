<?php
require_once __DIR__ . '/../../config/init.php';

// Permission check
if (!has_permission('engineering.changes.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_engineering_changes';
const PRIMARY_KEY = 'ChangeID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST requests with file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('engineering.changes.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            // Before deleting record, also delete the associated file if it exists
            $item_to_delete = find_by_id($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
            if ($item_to_delete && !empty($item_to_delete['DocumentationLink'])) {
                $file_path = __DIR__ . '/../../' . $item_to_delete['DocumentationLink'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $documentationPath = $_POST['existing_documentation_link'] ?? null;

            // Handle file upload
            if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                // Sanitize filename and create a unique name
                $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['doc_file']['name']));
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetPath)) {
                    // If a new file is uploaded, set the new path
                    $documentationPath = 'documents/' . $fileName;
                } else {
                    $result = ['success' => false, 'message' => 'خطا در آپلود فایل مستندات.'];
                    $_SESSION['message'] = $result['message'];
                    $_SESSION['message_type'] = 'danger';
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }

            $change_type = $_POST['change_type'];
            $entity_id = null;
            $entity_name_custom = null;

            if ($change_type === 'Mold') $entity_id = !empty($_POST['entity_id_mold']) ? (int)$_POST['entity_id_mold'] : null;
            elseif ($change_type === 'Process') $entity_id = !empty($_POST['entity_id_process']) ? (int)$_POST['entity_id_process'] : null;
            elseif ($change_type === 'Other') $entity_name_custom = trim($_POST['entity_name_custom']);
            
            $data = [
                'ChangeDate' => to_gregorian($_POST['change_date']),
                'ChangeType' => $change_type,
                'EntityID' => $entity_id,
                'EntityNameCustom' => $entity_name_custom,
                'SparePartID' => ($change_type === 'Mold' && !empty($_POST['spare_part_id'])) ? (int)$_POST['spare_part_id'] : null,
                'CurrentSituation' => trim($_POST['current_situation']),
                'ReasonForChange' => trim($_POST['reason_for_change']),
                'ChangesMade' => trim($_POST['changes_made']),
                'ApprovedByEmployeeID' => !empty($_POST['approved_by_employee_id']) ? (int)$_POST['approved_by_employee_id'] : null,
                'DocumentationLink' => $documentationPath
            ];

            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
        }
        $_SESSION['message'] = $result['message'] ?? 'عملیات نامشخص';
        $_SESSION['message_type'] = ($result['success'] ?? false) ? 'success' : 'danger';
    }
    header("Location: " . BASE_URL . "modules/engineering/engineering_changes.php");
    exit;
}

if (isset($_GET['edit_id'])) {
     if (!has_permission('engineering.changes.manage')) die('شما مجوز ویرایش را ندارید.');
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// Fetch data for dropdowns
$molds = find_all($pdo, "SELECT MoldID, MoldName FROM tbl_molds ORDER BY MoldName");
$processes = find_all($pdo, "SELECT ProcessID, ProcessName FROM tbl_processes ORDER BY ProcessName");
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$items = find_all($pdo, "SELECT ec.*, emp.name as ApproverName, sp.PartName as SparePartName, CASE WHEN ec.ChangeType = 'Mold' THEN m.MoldName WHEN ec.ChangeType = 'Process' THEN p.ProcessName WHEN ec.ChangeType = 'Other' THEN ec.EntityNameCustom END as EntityName FROM " . TABLE_NAME . " ec LEFT JOIN tbl_employees emp ON ec.ApprovedByEmployeeID = emp.EmployeeID LEFT JOIN tbl_molds m ON ec.ChangeType = 'Mold' AND ec.EntityID = m.MoldID LEFT JOIN tbl_processes p ON ec.ChangeType = 'Process' AND ec.EntityID = p.ProcessID LEFT JOIN tbl_eng_spare_parts sp ON ec.SparePartID = sp.PartID ORDER BY ec.ChangeDate DESC");

$pageTitle = "مدیریت تغییرات مهندسی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت تغییرات مهندسی</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <?php if (has_permission('engineering.changes.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش تغییر' : 'ثبت تغییر جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="engineering_changes.php" enctype="multipart/form-data">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                        <input type="hidden" name="existing_documentation_link" value="<?php echo htmlspecialchars($itemToEdit['DocumentationLink'] ?? ''); ?>">
                    <?php endif; ?>
                    <div class="mb-3"><label class="form-label">تاریخ تغییر</label><input type="text" class="form-control persian-date" name="change_date" value="<?php echo to_jalali($itemToEdit['ChangeDate'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">نوع تغییر</label><select class="form-select" name="change_type" id="change_type" required><option value="">انتخاب کنید...</option><option value="Mold" <?php echo ($editMode && $itemToEdit['ChangeType'] == 'Mold') ? 'selected' : ''; ?>>قالب</option><option value="Process" <?php echo ($editMode && $itemToEdit['ChangeType'] == 'Process') ? 'selected' : ''; ?>>فرآیند</option><option value="Other" <?php echo ($editMode && $itemToEdit['ChangeType'] == 'Other') ? 'selected' : ''; ?>>سایر</option></select></div>
                    <div id="mold_fields" class="mb-3" style="display:none;"><label class="form-label">نام قالب</label><select class="form-select" name="entity_id_mold" id="entity_id_mold"><option value="">انتخاب قالب...</option><?php foreach ($molds as $mold): ?><option value="<?php echo $mold['MoldID']; ?>" <?php echo ($editMode && $itemToEdit['ChangeType'] == 'Mold' && $itemToEdit['EntityID'] == $mold['MoldID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mold['MoldName']); ?></option><?php endforeach; ?></select><label class="form-label mt-2">قطعه یدکی (اختیاری)</label><select class="form-select" name="spare_part_id" id="spare_part_id" disabled><option value="">ابتدا قالب را انتخاب کنید...</option></select></div>
                    <div id="process_fields" class="mb-3" style="display:none;"><label class="form-label">نام فرآیند</label><select class="form-select" name="entity_id_process"><option value="">انتخاب فرآیند...</option><?php foreach ($processes as $process): ?><option value="<?php echo $process['ProcessID']; ?>" <?php echo ($editMode && $itemToEdit['ChangeType'] == 'Process' && $itemToEdit['EntityID'] == $process['ProcessID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($process['ProcessName']); ?></option><?php endforeach; ?></select></div>
                    <div id="other_fields" class="mb-3" style="display:none;"><label class="form-label">نام مورد</label><input type="text" class="form-control" name="entity_name_custom" value="<?php echo htmlspecialchars($itemToEdit['EntityNameCustom'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label">وضعیت موجود</label><textarea class="form-control" name="current_situation" rows="2"><?php echo htmlspecialchars($itemToEdit['CurrentSituation'] ?? ''); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">علل تغییر</label><textarea class="form-control" name="reason_for_change" rows="3" required><?php echo htmlspecialchars($itemToEdit['ReasonForChange'] ?? ''); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">تغییرات انجام شده</label><textarea class="form-control" name="changes_made" rows="3" required><?php echo htmlspecialchars($itemToEdit['ChangesMade'] ?? ''); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">تایید کننده</label><select class="form-select" name="approved_by_employee_id"><option value="">انتخاب کنید...</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo ($editMode && $itemToEdit['ApprovedByEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3">
                        <label for="doc_file" class="form-label">آپلود مستندات</label>
                        <input class="form-control" type="file" id="doc_file" name="doc_file">
                        <?php if ($editMode && !empty($itemToEdit['DocumentationLink'])): ?>
                            <div class="form-text">فایل فعلی: <a href="<?php echo BASE_URL . htmlspecialchars($itemToEdit['DocumentationLink']); ?>" target="_blank">مشاهده</a> (انتخاب فایل جدید، جایگزین فایل فعلی خواهد شد)</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'ثبت تغییر'; ?></button>
                    <?php if ($editMode): ?><a href="engineering_changes.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?php echo has_permission('engineering.changes.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست تغییرات ثبت شده</h5></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
                <thead><tr><th class="p-3">تاریخ</th><th class="p-3">مورد تغییر</th><th class="p-3">تایید کننده</th><th class="p-3">مستندات</th><th class="p-3">عملیات</th></tr></thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td class="p-3"><?php echo to_jalali($item['ChangeDate']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['EntityName']); ?><small class="text-muted d-block"><?php if ($item['ChangeType'] == 'Mold') echo 'قالب'; elseif ($item['ChangeType'] == 'Process') echo 'فرآیند'; else echo 'سایر'; if ($item['SparePartName']) echo ' - ' . htmlspecialchars($item['SparePartName']); ?></small></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['ApproverName'] ?? '-'); ?></td>
                        <td class="p-3"><?php if (!empty($item['DocumentationLink'])): ?><a href="<?php echo BASE_URL . htmlspecialchars($item['DocumentationLink']); ?>" target="_blank" class="btn btn-outline-info btn-sm" title="<?php echo htmlspecialchars($item['DocumentationLink']); ?>"><i class="bi bi-paperclip"></i> مشاهده</a><?php else: ?>-<?php endif; ?></td>
                        <td class="p-3">
                            <a href="change_feedback.php?change_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-info btn-sm" title="بازخورد و اقدامات آتی"><i class="bi bi-chat-left-text"></i></a>
                             <?php if (has_permission('engineering.changes.manage')): ?>
                            <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                            <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف این رکورد تغییر مطمئن هستید؟ (فایل پیوست نیز حذف خواهد شد)</div><div class="modal-footer"><form method="POST" action="engineering_changes.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button></div></div></div></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    const changeTypeSelect = $('#change_type'), moldFields = $('#mold_fields'), processFields = $('#process_fields'), otherFields = $('#other_fields'), moldSelect = $('#entity_id_mold'), sparePartSelect = $('#spare_part_id');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_spare_parts.php', isInEditMode = <?php echo $editMode ? 'true' : 'false'; ?>, initialSparePartId = '<?php echo $itemToEdit['SparePartID'] ?? ''; ?>';
    function toggleEntityFields() { const selectedType = changeTypeSelect.val(); moldFields.hide(); processFields.hide(); otherFields.hide(); if (selectedType === 'Mold') moldFields.show(); else if (selectedType === 'Process') processFields.show(); else if (selectedType === 'Other') otherFields.show(); }
    function fetchSpareParts(moldId, selectedPartId = null) {
        sparePartSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!moldId) { sparePartSelect.html('<option value="">ابتدا قالب را انتخاب کنید...</option>'); return; }
        $.ajax({
            url: apiPartsUrl, type: 'GET', data: { mold_id: moldId }, dataType: 'json',
            success: function(response) {
                sparePartSelect.html('<option value="">قطعه یدکی (اختیاری)</option>');
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(i, part) { const option = $('<option>', { value: part.PartID, text: `${part.PartName} (${part.PartCode})` }); if (part.PartID == selectedPartId) option.prop('selected', true); sparePartSelect.append(option); });
                    sparePartSelect.prop('disabled', false);
                } else { sparePartSelect.html('<option value="">هیچ قطعه‌ای برای این قالب یافت نشد</option>'); }
            },
            error: function() { sparePartSelect.html('<option value="">خطا در بارگذاری</option>'); }
        });
    }
    changeTypeSelect.on('change', toggleEntityFields);
    moldSelect.on('change', function() { fetchSpareParts($(this).val()); });
    toggleEntityFields();
    if (isInEditMode && changeTypeSelect.val() === 'Mold' && moldSelect.val()) { fetchSpareParts(moldSelect.val(), initialSparePartId); }
});
</script>

