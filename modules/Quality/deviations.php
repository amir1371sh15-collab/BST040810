<?php
require_once __DIR__ . '/../../config/init.php';

// --- Permission Checks (Example - uncomment and adjust later) ---
// if (!has_permission('quality.deviations.view')) {
//     die('شما مجوز دسترسی به این صفحه را ندارید.');
// }
// $can_manage = has_permission('quality.deviations.manage');

$can_manage = true; // Placeholder for permissions

// --- Constants ---
const TABLE_NAME = 'tbl_quality_deviations';
const PRIMARY_KEY = 'DeviationID';
const UPLOAD_DIR_RELATIVE = 'documents/quality_deviations/'; // Directory relative to BASE_URL root
const UPLOAD_DIR_ABSOLUTE = __DIR__ . '/../../' . UPLOAD_DIR_RELATIVE; // Absolute path for PHP functions

// --- Initialization ---
$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_manage) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "modules/quality/deviations.php");
        exit;
    }

    if (isset($_POST['delete_id'])) {
        // --- Delete Operation ---
        $item_to_delete = find_by_id($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        // Delete associated file first
        if ($item_to_delete && !empty($item_to_delete['DocumentationLink'])) {
            $file_path = UPLOAD_DIR_ABSOLUTE . basename($item_to_delete['DocumentationLink']); // Use basename for security
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        // --- Add/Update Operation ---
        $documentationPath = $_POST['existing_documentation_link'] ?? null;

        // Handle file upload
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(UPLOAD_DIR_ABSOLUTE)) {
                mkdir(UPLOAD_DIR_ABSOLUTE, 0777, true);
            }
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['doc_file']['name']));
            $targetPath = UPLOAD_DIR_ABSOLUTE . $fileName;

            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetPath)) {
                // If a new file is uploaded, set the new path (relative for DB)
                $documentationPath = UPLOAD_DIR_RELATIVE . $fileName;
                // Delete the old file if it exists and we are updating
                if (isset($_POST['id']) && !empty($_POST['existing_documentation_link']) && $documentationPath !== $_POST['existing_documentation_link']) {
                    $old_file_path = UPLOAD_DIR_ABSOLUTE . basename($_POST['existing_documentation_link']);
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            } else {
                $result = ['success' => false, 'message' => 'خطا در آپلود فایل مستندات.'];
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = 'danger';
                header("Location: " . $_SERVER['REQUEST_URI']); // Redirect back to form
                exit;
            }
        }

        $data = [
            // DeviationCode is handled after insert
            'FamilyID' => !empty($_POST['family_id']) ? (int)$_POST['family_id'] : null,
            'PartID' => !empty($_POST['part_id']) ? (int)$_POST['part_id'] : null,
            'Reason' => trim($_POST['reason']),
            'Status' => $_POST['status'],
            'ValidFrom' => !empty($_POST['valid_from']) ? to_gregorian($_POST['valid_from']) : null,
            'ValidTo' => !empty($_POST['valid_to']) ? to_gregorian($_POST['valid_to']) : null,
            'CreatedBy' => $_SESSION['user_id'] ?? null,
            'DocumentationLink' => $documentationPath // Add file path
        ];

        // Basic Validation
        if (empty($data['Reason']) || empty($data['Status'])) {
             $result = ['success' => false, 'message' => 'دلیل و وضعیت مجوز الزامی است.'];
             $_SESSION['message_type'] = 'warning';
        } elseif (empty($data['FamilyID']) && empty($data['PartID'])) {
             $result = ['success' => false, 'message' => 'حداقل خانواده یا قطعه باید مشخص شود.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update existing record (DeviationCode is not updated)
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                // Insert new record (without DeviationCode initially)
                $insert_result = insert_record($pdo, TABLE_NAME, $data);
                if ($insert_result['success']) {
                    $new_id = $insert_result['id'];
                    // Generate and update DeviationCode
                    $deviation_code = 'DEV-' . $new_id;
                    $update_code_result = update_record($pdo, TABLE_NAME, ['DeviationCode' => $deviation_code], $new_id, PRIMARY_KEY);
                    if ($update_code_result['success']) {
                         $result = $insert_result; // Use the original success message
                    } else {
                         // Rollback or log error - could not update code
                         $result = ['success' => false, 'message' => 'مجوز ثبت شد ولی در تولید کد خودکار خطایی رخ داد.'];
                         // Consider deleting the record or logging for manual fix
                    }
                } else {
                    $result = $insert_result; // Use the insert error message
                }
            }
             $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
         $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/quality/deviations.php");
    exit;
}

// --- Handle Edit Request ---
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    if (!$can_manage) { die('شما مجوز ویرایش را ندارید.'); }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// --- Fetch Data for Display ---
$items_query = "
    SELECT qd.*, pf.FamilyName, p.PartName, u.Username as CreatorName
    FROM " . TABLE_NAME . " qd
    LEFT JOIN tbl_part_families pf ON qd.FamilyID = pf.FamilyID
    LEFT JOIN tbl_parts p ON qd.PartID = p.PartID
    LEFT JOIN tbl_users u ON qd.CreatedBy = u.UserID
    ORDER BY qd.DeviationID DESC";
$items = find_all($pdo, $items_query);

$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
// Parts will be loaded via AJAX

$status_options = ['Draft' => 'پیش‌نویس', 'Approved' => 'تایید شده', 'Expired' => 'منقضی شده'];

// --- Page Setup ---
$pageTitle = "مدیریت مجوزهای ارفاقی";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>modules/quality/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <?php if ($can_manage): ?>
    <!-- Form Column -->
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش مجوز' : 'ثبت مجوز جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="deviations.php" enctype="multipart/form-data">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                        <input type="hidden" name="existing_documentation_link" value="<?php echo htmlspecialchars($itemToEdit['DocumentationLink'] ?? ''); ?>">
                        {/* Display Deviation Code in edit mode but make it readonly */}
                        <div class="mb-3">
                            <label for="deviation_code_display" class="form-label">کد مجوز</label>
                            <input type="text" class="form-control" id="deviation_code_display" value="<?php echo htmlspecialchars($itemToEdit['DeviationCode'] ?? ''); ?>" readonly>
                        </div>
                    <?php else: ?>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="family_id" class="form-label">خانواده قطعه</label>
                        <select class="form-select" id="family_id" name="family_id">
                            <option value="">-- انتخاب کنید (اختیاری) --</option>
                            <?php foreach ($part_families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>" <?php echo (isset($itemToEdit['FamilyID']) && $itemToEdit['FamilyID'] == $family['FamilyID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($family['FamilyName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="part_id" class="form-label">قطعه خاص</label>
                        <select class="form-select" id="part_id" name="part_id" <?php echo ($editMode && $itemToEdit['FamilyID']) ? '' : 'disabled'; ?>>
                            <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                            <?php // Options loaded by JS ?>
                        </select>
                         <small class="form-text text-muted">انتخاب خانواده یا قطعه خاص الزامی است.</small>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">دلیل مجوز *</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo htmlspecialchars($itemToEdit['Reason'] ?? ''); ?></textarea>
                    </div>

                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">وضعیت *</label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach ($status_options as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($itemToEdit['Status']) && $itemToEdit['Status'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-6 mb-3">
                             <label for="created_by" class="form-label">ایجاد کننده</label>
                             <input type="text" class="form-control" id="created_by" value="<?php echo htmlspecialchars($editMode ? ($itemToEdit['CreatorName'] ?? '-') : $_SESSION['username']); ?>" disabled>
                             <?php // CreatedBy is set automatically in PHP ?>
                        </div>
                    </div>

                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valid_from" class="form-label">معتبر از تاریخ</label>
                            <input type="text" class="form-control persian-date" id="valid_from" name="valid_from" value="<?php echo to_jalali($itemToEdit['ValidFrom'] ?? ''); ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="valid_to" class="form-label">معتبر تا تاریخ</label>
                            <input type="text" class="form-control persian-date" id="valid_to" name="valid_to" value="<?php echo to_jalali($itemToEdit['ValidTo'] ?? ''); ?>" autocomplete="off">
                        </div>
                    </div>

                    
                    <div class="mb-3">
                        <label for="doc_file" class="form-label">آپلود مستندات</label>
                        <input class="form-control" type="file" id="doc_file" name="doc_file">
                        <?php if ($editMode && !empty($itemToEdit['DocumentationLink'])): ?>
                            <div class="form-text">فایل فعلی: <a href="<?php echo BASE_URL . htmlspecialchars($itemToEdit['DocumentationLink']); ?>" target="_blank" title="<?php echo htmlspecialchars(basename($itemToEdit['DocumentationLink'])); ?>">مشاهده</a> (انتخاب فایل جدید، جایگزین فایل فعلی خواهد شد)</div>
                        <?php endif; ?>
                    </div>
             


                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                        <i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i>
                        <?php echo $editMode ? 'بروزرسانی' : 'ثبت مجوز'; ?>
                    </button>
                    <?php if ($editMode): ?>
                        <a href="deviations.php" class="btn btn-secondary">لغو</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Table Column -->
    <div class="<?php echo $can_manage ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست مجوزهای ارفاقی</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="p-2">کد</th>
                                <th class="p-2">خانواده/قطعه</th>
                                <th class="p-2">دلیل</th>
                                <th class="p-2">وضعیت</th>
                                <th class="p-2">بازه اعتبار</th>
                                <th class="p-2">مستندات</th>
                                <th class="p-2">ایجاد کننده</th>
                                <?php if ($can_manage): ?><th class="p-2">عملیات</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-2"><?php echo htmlspecialchars($item['DeviationCode']); ?></td>
                                <td class="p-2 small"><?php echo htmlspecialchars($item['PartName'] ?: ($item['FamilyName'] ?: '-')); ?></td>
                                <td class="p-2 small"><?php echo htmlspecialchars(mb_substr($item['Reason'], 0, 50)) . (mb_strlen($item['Reason']) > 50 ? '...' : ''); ?></td>
                                <td class="p-2">
                                    <?php
                                        $status_label = $status_options[$item['Status']] ?? $item['Status'];
                                        $status_class = '';
                                        switch ($item['Status']) {
                                            case 'Approved': $status_class = 'bg-success'; break;
                                            case 'Expired': $status_class = 'bg-secondary'; break;
                                            case 'Draft': $status_class = 'bg-warning text-dark'; break;
                                        }
                                        echo "<span class='badge {$status_class}'>{$status_label}</span>";
                                    ?>
                                </td>
                                <td class="p-2 small"><?php echo to_jalali($item['ValidFrom']); ?> تا <?php echo to_jalali($item['ValidTo']); ?></td>
                                <td class="p-2">
                                    <?php if (!empty($item['DocumentationLink'])): ?>
                                        <a href="<?php echo BASE_URL . htmlspecialchars($item['DocumentationLink']); ?>" target="_blank" class="btn btn-outline-info btn-sm py-0 px-1" title="<?php echo htmlspecialchars(basename($item['DocumentationLink'])); ?>">
                                            <i class="bi bi-paperclip"></i>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="p-2 small"><?php echo htmlspecialchars($item['CreatorName'] ?? '-'); ?></td>
                                <?php if ($can_manage): ?>
                                <td class="p-2 text-nowrap">
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                     <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                <div class="modal-body">آیا از حذف مجوز "<?php echo htmlspecialchars($item['DeviationCode']); ?>" مطمئن هستید؟ (فایل پیوست نیز حذف خواهد شد)</div>
                                                <div class="modal-footer">
                                                    <form method="POST" action="deviations.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <?php endif; ?>
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
    const familySelect = $('#family_id');
    const partSelect = $('#part_id');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php';
    const initialPartId = '<?php echo $itemToEdit['PartID'] ?? ''; ?>';

    function fetchParts(familyId, selectedPartId = null) {
        partSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }
        $.getJSON(apiPartsUrl, { family_id: familyId }, function(response) {
            partSelect.html('<option value="">-- انتخاب کنید (اختیاری) --</option>');
            if (response.success && response.data.length > 0) {
                $.each(response.data, function(i, part) {
                    const option = $('<option>', { value: part.PartID, text: part.PartName });
                    if (part.PartID == selectedPartId) {
                        option.prop('selected', true);
                    }
                    partSelect.append(option);
                });
            } else {
                 partSelect.append('<option value="" disabled>قطعه‌ای یافت نشد</option>');
            }
             partSelect.prop('disabled', false);
        }).fail(function() {
            partSelect.html('<option value="">خطا در بارگذاری</option>');
        });
    }

    familySelect.on('change', function() {
        fetchParts($(this).val());
    });

    // Initial load for edit mode
    if (familySelect.val()) {
        fetchParts(familySelect.val(), initialPartId);
    }
});
</script>

