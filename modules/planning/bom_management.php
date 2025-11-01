<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_bom_structure';
const PRIMARY_KEY = 'BomID';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent_part_id_redirect = $_POST['parent_part_id'] ?? null;

    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } elseif (isset($_POST['parent_part_id']) && isset($_POST['child_part_id']) && isset($_POST['quantity'])) {
        $data = [
            'ParentPartID' => (int)$_POST['parent_part_id'],
            'ChildPartID' => (int)$_POST['child_part_id'],
            'QuantityPerParent' => (float)$_POST['quantity'],
            'RequiredStatusID' => !empty($_POST['required_status_id']) ? (int)$_POST['required_status_id'] : null, // Add new field
        ];
        if (empty($data['ParentPartID']) || empty($data['ChildPartID']) || empty($data['QuantityPerParent']) || empty($data['RequiredStatusID'])) { // Status is now required
             $result = ['success' => false, 'message' => 'انتخاب هر دو قطعه، وضعیت مورد نیاز و تعداد الزامی است.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            $result = insert_record($pdo, TABLE_NAME, $data);
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/planning/bom_management.php?parent_part_id=" . $parent_part_id_redirect);
    exit;
}

// Fetch data for forms
$selected_parent_id = isset($_GET['parent_part_id']) && is_numeric($_GET['parent_part_id']) ? (int)$_GET['parent_part_id'] : null;
$selected_parent = null;
$linked_children = [];
$api_params = ['action' => 'all_parts'];

// Fetch parent products (Baste)
$parent_product_families = ['بست بزرگ', 'بست کوچک'];
$placeholders = implode(',', array_fill(0, count($parent_product_families), '?'));
$parent_parts = find_all($pdo, 
    "SELECT p.PartID, p.PartName 
     FROM tbl_parts p 
     JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID 
     WHERE pf.FamilyName IN ($placeholders)
     ORDER BY p.PartName", 
    $parent_product_families
);

// Fetch all part families for the new filter
$all_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

if ($selected_parent_id) {
    $selected_parent = find_by_id($pdo, 'tbl_parts', $selected_parent_id, 'PartID');
    // Fetch linked children
    $linked_children = find_all($pdo, 
        "SELECT b.*, p.PartName, ps.StatusName 
         FROM " . TABLE_NAME . " b
         JOIN tbl_parts p ON b.ChildPartID = p.PartID
         LEFT JOIN tbl_part_statuses ps ON b.RequiredStatusID = ps.StatusID
         WHERE b.ParentPartID = ?
         ORDER BY p.PartName", 
        [$selected_parent_id]
    );
    $api_params['exclude_part_id'] = $selected_parent_id;
}

$pageTitle = "مدیریت ساختار محصول (BOM)";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card content-card mb-4">
            <div class="card-header"><h5 class="mb-0">۱. انتخاب محصول نهایی (والد)</h5></div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="parent_select" class="form-label">یک محصول نهایی (بست) را برای مدیریت ساختار آن انتخاب کنید:</label>
                        <select name="parent_part_id" id="parent_select" class="form-select" onchange="this.form.submit()">
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($parent_parts as $part): ?>
                                <option value="<?php echo $part['PartID']; ?>" <?php echo ($selected_parent_id == $part['PartID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($part['PartName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if($selected_parent): ?>
            <div class="card content-card">
                 <div class="card-header"><h5 class="mb-0">۲. افزودن قطعه منفصله (فرزند)</h5></div>
                 <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="parent_part_id" value="<?php echo $selected_parent_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">محصول والد:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_parent['PartName']); ?>" readonly>
                        </div>
                        
                        <!-- NEW: Filter by Child Family -->
                        <div class="mb-3">
                            <label for="child_family_select" class="form-label">فیلتر خانواده فرزند</label>
                            <select id="child_family_select" class="form-select">
                                <option value="">-- همه خانواده‌ها --</option>
                                <?php foreach($all_families as $family): ?>
                                    <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="child_part_select" class="form-label">قطعه منفصله (فرزند) *</label>
                            <select name="child_part_id" id="child_part_select" class="form-select" required disabled>
                                <option value="">-- ابتدا خانواده را فیلتر کنید --</option>
                            </select>
                        </div>

                        <!-- NEW: Required Status -->
                        <div class="mb-3">
                            <label for="required_status_select" class="form-label">وضعیت مورد نیاز *</label>
                            <select name="required_status_id" id="required_status_select" class="form-select" required disabled>
                                <option value="">-- ابتدا قطعه را انتخاب کنید --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                             <label for="quantity" class="form-label">تعداد مورد نیاز *</label>
                             <input type="number" step="0.01" name="quantity" id="quantity" class="form-control" value="1.0" required>
                        </div>
                        <button type="submit" class="btn btn-primary">افزودن قطعه</button>
                    </form>
                 </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست ساختار محصول</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead><tr><th class="p-2">محصول والد</th><th class="p-2">قطعه فرزند</th><th class="p-2">وضعیت مورد نیاز</th><th class="p-2">تعداد</th><th class="p-2">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($linked_children)): ?>
                                <tr><td colspan="5" class="text-center p-3 text-muted">هیچ قطعه‌ای برای این محصول تعریف نشده یا محصولی انتخاب نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($linked_children as $item): ?>
                                <tr>
                                    <td class="p-2"><strong><?php echo htmlspecialchars($selected_parent['PartName']); ?></strong></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['PartName']); ?></td>
                                    <td class="p-2"><span class="badge bg-secondary"><?php echo htmlspecialchars($item['StatusName'] ?? 'نامشخص'); ?></span></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['QuantityPerParent']); ?></td>
                                    <td class="p-2">
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>">
                                            <input type="hidden" name="parent_part_id" value="<?php echo $selected_parent_id; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm py-0 px-1" title="حذف"><i class="bi bi-trash-fill"></i></button>
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
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    const childFamilySelect = $('#child_family_select');
    const childPartSelect = $('#child_part_select');
    const requiredStatusSelect = $('#required_status_select');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_all_parts_for_bom.php';
    const apiStatusesUrl = '<?php echo BASE_URL; ?>api/api_get_statuses_by_family.php';
    const parentPartId = <?php echo $selected_parent_id ?? 'null'; ?>;

    // Function to populate child parts based on family
    async function populateChildParts(familyId) {
        childPartSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        requiredStatusSelect.html('<option value="">-- ابتدا قطعه --</option>').prop('disabled', true); // Reset status
        
        let apiParams = { family_id: familyId };
        if (parentPartId) {
            apiParams.exclude_part_id = parentPartId;
        }

        try {
            const response = await $.getJSON(apiPartsUrl, apiParams);
            childPartSelect.html('<option value="">-- انتخاب قطعه فرزند --</option>');
            if (response.success && response.data) {
                // The API groups by family, but since we filter by one family, we take the first group
                const familyKey = Object.keys(response.data)[0];
                if (familyKey && response.data[familyKey].length > 0) {
                     response.data[familyKey].forEach(part => {
                        childPartSelect.append($('<option>', { value: part.PartID, 'data-family-id': familyId, text: part.PartName }));
                    });
                     childPartSelect.prop('disabled', false);
                } else {
                     childPartSelect.html('<option value="">-- قطعه‌ای یافت نشد --</option>');
                }
            } else {
                 childPartSelect.html('<option value="">-- قطعه‌ای یافت نشد --</option>');
            }
        } catch (error) {
            console.error("Error fetching child parts:", error);
            childPartSelect.html('<option value="">-- خطا در بارگذاری --</option>');
        }
    }

    // Function to populate required statuses based on child's family
    async function populateRequiredStatuses(familyId) {
        requiredStatusSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            requiredStatusSelect.html('<option value="">-- ابتدا قطعه --</option>');
            return;
        }
        
        try {
            const response = await $.getJSON(apiStatusesUrl, { family_id: familyId });
            requiredStatusSelect.html('<option value="">-- وضعیت مورد نیاز --</option>');
            requiredStatusSelect.append('<option value="NULL">-- بدون وضعیت --</option>');
            if (response.success && response.data.length > 0) {
                 response.data.forEach(status => {
                    requiredStatusSelect.append($('<option>', { value: status.StatusID, text: status.StatusName }));
                });
                requiredStatusSelect.prop('disabled', false);
            } else {
                 requiredStatusSelect.prop('disabled', false); // Still enable, even if only "No Status" is viable
            }
        } catch (error) {
             console.error("Error fetching statuses:", error);
             requiredStatusSelect.html('<option value="">-- خطا در بارگذاری --</option>');
        }
    }

    // --- Event Listeners ---
    
    // When family filter changes
    childFamilySelect.on('change', function() {
        const familyId = $(this).val();
        populateChildParts(familyId);
        // Also populate statuses based on the selected family
        populateRequiredStatuses(familyId); 
    });

    // When child part selection changes (to get its family ID)
    // This is a fallback in case the family filter isn't used
    childPartSelect.on('change', function() {
        if (!childFamilySelect.val()) { // Only if family filter is NOT set
            const selectedOption = $(this).find('option:selected');
            const familyId = selectedOption.data('family-id'); // We need to ensure API returns this
            if (familyId) {
                populateRequiredStatuses(familyId);
            }
        }
    });
    
    // --- Initial Load ---
    if (parentPartId) {
        // Initially load all parts (grouped) if no family is pre-selected
        // We'll modify this: initially, don't load anything. Force user to pick family.
        // populateChildParts(null); 
    }
});
</script>

