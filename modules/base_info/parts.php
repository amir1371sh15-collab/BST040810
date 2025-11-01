<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks as needed

const TABLE_NAME = 'tbl_parts';
const PRIMARY_KEY = 'PartID';
const RECORDS_PER_PAGE = 20;

$editMode = false;
$itemToEdit = null;
$itemSizeName = null; // Variable to hold SizeName in edit mode
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- PAGINATION ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

// --- HANDLE POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // --- DELETE ---
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        // --- ADD / UPDATE ---
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // --- UPDATE ---
            $partId = (int)$_POST['id'];
            // Family and Size are generally not editable after creation in this flow
            $data = [
                'PartName' => trim($_POST['part_name_edit']),
                'PartCode' => trim($_POST['part_code_edit']),
                'Description' => trim($_POST['description']),
                // FamilyID and SizeID are NOT updated here intentionally
            ];
             if (empty($data['PartName']) || empty($data['PartCode'])) {
                $result = ['success' => false, 'message' => 'نام و کد قطعه در حالت ویرایش الزامی است.'];
                $_SESSION['message_type'] = 'warning';
            } else {
                $result = update_record($pdo, TABLE_NAME, $data, $partId, PRIMARY_KEY);
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        } else {
            // --- INSERT ---
            $family_id = isset($_POST['family_id']) ? (int)$_POST['family_id'] : null;
            $size_id = isset($_POST['size_id']) ? (int)$_POST['size_id'] : null;
            $description = trim($_POST['description']);

            if (empty($family_id) || empty($size_id)) {
                $result = ['success' => false, 'message' => 'انتخاب خانواده و سایز برای ایجاد قطعه جدید الزامی است.'];
                $_SESSION['message_type'] = 'warning';
            } else {
                // Fetch FamilyName and SizeName
                $family = find_by_id($pdo, 'tbl_part_families', $family_id, 'FamilyID');
                $size = find_by_id($pdo, 'tbl_part_sizes', $size_id, 'SizeID');

                if (!$family || !$size || $size['FamilyID'] != $family_id) {
                    $result = ['success' => false, 'message' => 'خانواده یا سایز انتخاب شده نامعتبر است.'];
                    $_SESSION['message_type'] = 'warning';
                } else {
                    $partName = $family['FamilyName'] . ' ' . $size['SizeName']; // Combine Name
                    $partCode = 'F' . $family_id . 'S' . $size_id . '-' . time(); // Generate Code

                    $data = [
                        'PartCode' => $partCode,
                        'PartName' => $partName,
                        'Description' => $description,
                        'FamilyID' => $family_id,
                        'SizeID' => $size_id, // Store SizeID
                    ];

                    // Check if part with this name already exists (optional but recommended)
                    $existingPart = find_one_by_field($pdo, TABLE_NAME, 'PartName', $partName);
                    if ($existingPart) {
                         $result = ['success' => false, 'message' => 'قطعه با این نام (' . $partName . ') از قبل وجود دارد.'];
                         $_SESSION['message_type'] = 'warning';
                    } else {
                        $result = insert_record($pdo, TABLE_NAME, $data);
                        $_SESSION['message'] = $result['message'];
                        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
                    }
                }
            }
        }
        // Use result message if set, otherwise use session message
        if (!isset($_SESSION['message'])) {
             $_SESSION['message'] = $result['message'] ?? 'خطای ناشناخته رخ داد.';
        }
    }
    header("Location: " . BASE_URL . "modules/base_info/parts.php?page=" . $current_page);
    exit;
}

// --- HANDLE EDIT REQUEST (GET) ---
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
    // Fetch Size Name for display in edit mode
    if ($itemToEdit && isset($itemToEdit['SizeID'])) {
        $sizeInfo = find_by_id($pdo, 'tbl_part_sizes', $itemToEdit['SizeID'], 'SizeID');
        if($sizeInfo) {
            $itemSizeName = $sizeInfo['SizeName'];
        }
    }
}

// --- FETCH DATA FOR DISPLAY ---
$items_query = "SELECT p.*, pf.FamilyName, ps.SizeName
                FROM " . TABLE_NAME . " p
                LEFT JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
                LEFT JOIN tbl_part_sizes ps ON p.SizeID = ps.SizeID /* Join to get SizeName */
                ORDER BY p.PartName
                LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($items_query);
$stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$families = find_all($pdo, "SELECT * FROM tbl_part_families ORDER BY FamilyName");

$pageTitle = "مدیریت قطعات";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش قطعه' : 'افزودن قطعه جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="parts.php?page=<?php echo $current_page; ?>">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>

                    <!-- Family Dropdown (Enabled in Add mode, Disabled in Edit mode) -->
                    <div class="mb-3">
                        <label class="form-label">خانواده قطعه</label>
                        <select class="form-select" id="family_id" name="family_id" <?php echo $editMode ? 'disabled' : 'required'; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>" <?php echo ($editMode && isset($itemToEdit['FamilyID']) && $itemToEdit['FamilyID'] == $family['FamilyID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($family['FamilyName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editMode && $itemToEdit): ?>
                            <input type="hidden" name="family_id_hidden_on_edit" value="<?php echo $itemToEdit['FamilyID']; ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Size Dropdown (Add mode) / Display (Edit mode) -->
                    <div class="mb-3">
                        <label class="form-label">سایز قطعه</label>
                        <?php if (!$editMode): ?>
                            <select class="form-select" id="size_id" name="size_id" required disabled>
                                <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                            </select>
                        <?php else: ?>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($itemSizeName ?? 'نامشخص'); ?>" readonly>
                             <input type="hidden" name="size_id_hidden_on_edit" value="<?php echo $itemToEdit['SizeID'] ?? ''; ?>">
                        <?php endif; ?>
                    </div>

                    <?php if ($editMode && $itemToEdit): ?>
                        <!-- Name and Code only editable in Edit mode -->
                        <div class="mb-3">
                            <label class="form-label">نام قطعه</label>
                            <input type="text" class="form-control" name="part_name_edit" value="<?php echo htmlspecialchars($itemToEdit['PartName']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">کد قطعه</label>
                            <input type="text" class="form-control" name="part_code_edit" value="<?php echo htmlspecialchars($itemToEdit['PartCode']); ?>" required>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($itemToEdit['Description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                        <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?>
                    </button>
                    <?php if ($editMode): ?>
                        <a href="parts.php?page=<?php echo $current_page; ?>" class="btn btn-secondary">لغو</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست قطعات</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="p-3">کد</th>
                                <th class="p-3">نام قطعه</th>
                                <th class="p-3">خانواده</th>
                                <th class="p-3">سایز</th> <!-- Added Size Column -->
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="p-3"><?php echo htmlspecialchars($item['PartCode']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['PartName']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['FamilyName'] ?? '-'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($item['SizeName'] ?? '-'); ?></td> <!-- Display Size Name -->
                                <td class="p-3">
                                    <a href="part_weights.php?part_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-info btn-sm" title="مدیریت وزن"><i class="bi bi-speedometer2"></i></a>
                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                <div class="modal-body">آیا از حذف قطعه "<?php echo htmlspecialchars($item['PartName']); ?>" مطمئن هستید؟ (تمام وزن‌های ثبت شده برای آن نیز حذف خواهد شد)</div>
                                                <div class="modal-footer">
                                                    <form method="POST" action="parts.php?page=<?php echo $current_page; ?>"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="card-footer d-flex justify-content-center">
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">قبلی</a></li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a></li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const familySelect = $('#family_id');
    const sizeSelect = $('#size_id');
    const apiSizesUrl = '<?php echo BASE_URL; ?>api/api_get_part_sizes.php'; // URL for the new API
    const isEditMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const initialSizeId = '<?php echo $editMode && isset($itemToEdit['SizeID']) ? $itemToEdit['SizeID'] : ''; ?>';

    function populateSizes(familyId, selectedSizeId = null) {
        sizeSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            sizeSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }

        $.getJSON(apiSizesUrl, { family_id: familyId })
            .done(function(response) {
                sizeSelect.html('<option value="">-- انتخاب سایز --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(size) {
                        const option = $('<option>', {
                            value: size.SizeID,
                            text: size.SizeName
                        });
                        if (size.SizeID == selectedSizeId) {
                            option.prop('selected', true);
                        }
                        sizeSelect.append(option);
                    });
                    sizeSelect.prop('disabled', false);
                } else {
                    sizeSelect.html('<option value="">-- سایزی یافت نشد --</option>');
                }
            })
            .fail(function(jqXHR) {
                console.error("Error fetching sizes:", jqXHR.responseText);
                sizeSelect.html('<option value="">-- خطا در بارگذاری --</option>');
            });
    }

    // Event listener for family dropdown (only in Add mode)
    if (!isEditMode) {
        familySelect.on('change', function() {
            populateSizes($(this).val());
        });
    }

    // Initial population for Edit mode (if family is selected)
    if (isEditMode && familySelect.val()) {
        populateSizes(familySelect.val(), initialSizeId);
    }
});
</script>
