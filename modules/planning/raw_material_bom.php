<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_part_raw_materials';
const PRIMARY_KEY = 'PartBomID';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_id_redirect = $_POST['part_id'] ?? null;

    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } elseif (isset($_POST['part_id']) && isset($_POST['raw_material_item_id']) && isset($_POST['quantity_gram'])) {
        $data = [
            'PartID' => (int)$_POST['part_id'],
            'RawMaterialItemID' => (int)$_POST['raw_material_item_id'],
            'QuantityGram' => (float)$_POST['quantity_gram'],
        ];
        if (empty($data['PartID']) || empty($data['RawMaterialItemID']) || empty($data['QuantityGram'])) {
             $result = ['success' => false, 'message' => 'انتخاب قطعه، ماده اولیه و وزن الزامی است.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            $result = insert_record($pdo, TABLE_NAME, $data); // insert_record handles unique constraint
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/planning/raw_material_bom.php?part_id=" . $part_id_redirect);
    exit;
}

// Fetch data for forms
$selected_part_id = isset($_GET['part_id']) && is_numeric($_GET['part_id']) ? (int)$_GET['part_id'] : null;
$selected_part = null;
$linked_materials = [];

// Fetch ONLY manufactured parts (Tireh, Mahfazeh, Pich)
$manufactured_families = ['تسمه بزرگ بدون دنده', 'تسمه بزرگ دنده شده', 'تسمه کوچک', 'محفظه بزرگ', 'محفظه کوچک', 'پیچ بزرگ', 'پیچ کوچک'];
$placeholders = implode(',', array_fill(0, count($manufactured_families), '?'));
$parts = find_all($pdo, 
    "SELECT p.PartID, p.PartName, pf.FamilyName 
     FROM tbl_parts p 
     JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID 
     WHERE pf.FamilyName IN ($placeholders)
     ORDER BY pf.FamilyName, p.PartName", 
    $manufactured_families
);

// Fetch raw material categories for the new filter
$raw_categories = find_all($pdo, "SELECT CategoryID, CategoryName FROM tbl_raw_categories ORDER BY CategoryName");


if ($selected_part_id) {
    $selected_part = find_by_id($pdo, 'tbl_parts', $selected_part_id, 'PartID');
    // Fetch linked raw materials
    $linked_materials = find_all($pdo, 
        "SELECT b.*, i.ItemName, u.Symbol 
         FROM " . TABLE_NAME . " b
         JOIN tbl_raw_items i ON b.RawMaterialItemID = i.ItemID
         JOIN tbl_units u ON i.UnitID = u.UnitID
         WHERE b.PartID = ?
         ORDER BY i.ItemName", 
        [$selected_part_id]
    );
}

$pageTitle = "مدیریت BOM مواد اولیه";
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
            <div class="card-header"><h5 class="mb-0">۱. انتخاب قطعه تولیدی (والد)</h5></div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="part_select" class="form-label">یک قطعه (تسمه، محفظه، پیچ) را انتخاب کنید:</label>
                        <select name="part_id" id="part_select" class="form-select" onchange="this.form.submit()">
                            <option value="">-- انتخاب کنید --</option>
                            <?php 
                            $current_family = '';
                            foreach($parts as $part): 
                                if ($part['FamilyName'] !== $current_family) {
                                    if ($current_family !== '') echo '</optgroup>';
                                    $current_family = $part['FamilyName'];
                                    echo '<optgroup label="' . htmlspecialchars($current_family) . '">';
                                }
                            ?>
                                <option value="<?php echo $part['PartID']; ?>" <?php echo ($selected_part_id == $part['PartID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($part['PartName']); ?>
                                </option>
                            <?php endforeach; 
                            if ($current_family !== '') echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if($selected_part): ?>
            <div class="card content-card">
                 <div class="card-header"><h5 class="mb-0">۲. افزودن ماده اولیه (فرزند)</h5></div>
                 <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="part_id" value="<?php echo $selected_part_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">قطعه والد:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_part['PartName']); ?>" readonly>
                        </div>
                        
                        <!-- NEW: Filter by Material Category -->
                        <div class="mb-3">
                            <label for="raw_category_select" class="form-label">فیلتر دسته مواد اولیه</label>
                            <select id="raw_category_select" class="form-select">
                                <option value="">-- همه دسته‌ها --</option>
                                <?php foreach($raw_categories as $cat): ?>
                                    <option value="<?php echo $cat['CategoryID']; ?>"><?php echo htmlspecialchars($cat['CategoryName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="raw_material_select" class="form-label">ماده اولیه *</label>
                            <select name="raw_material_item_id" id="raw_material_select" class="form-select" required disabled>
                                <option value="">-- ابتدا دسته را فیلتر کنید --</option>
                            </select>
                        </div>
                        <div class="mb-3">
                             <label for="quantity_gram" class="form-label">مقدار مورد نیاز (گرم) *</label>
                             <input type="number" step="0.001" name="quantity_gram" id="quantity_gram" class="form-control" placeholder="وزن ماده اولیه برای ۱ عدد قطعه" required>
                             <small class="text-muted">وزن استاندارد این قطعه <span id="standard-weight-info" class="fw-bold">...</span> گرم است.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">افزودن ماده اولیه</button>
                    </form>
                 </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست مواد اولیه مورد نیاز</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead><tr><th class="p-2">قطعه والد</th><th class="p-2">ماده اولیه</th><th class="p-2">مقدار (گرم)</th><th class="p-2">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($linked_materials)): ?>
                                <tr><td colspan="4" class="text-center p-3 text-muted">هیچ ماده اولیه‌ای برای این قطعه تعریف نشده یا قطعه‌ای انتخاب نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($linked_materials as $item): ?>
                                <tr>
                                    <td class="p-2"><strong><?php echo htmlspecialchars($selected_part['PartName']); ?></strong></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['ItemName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['QuantityGram']); ?> (<?php echo htmlspecialchars($item['Symbol']); ?>)</td>
                                    <td class="p-2">
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>">
                                            <input type="hidden" name="part_id" value="<?php echo $selected_part_id; ?>">
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
    const rawCategorySelect = $('#raw_category_select'); // New category filter
    const rawMaterialSelect = $('#raw_material_select');
    const quantityInput = $('#quantity_gram');
    const weightInfo = $('#standard-weight-info');
    const apiRawMaterialsUrl = '<?php echo BASE_URL; ?>api/api_raw_get_items.php'; // Updated API
    const apiPartWeightUrl = '<?php echo BASE_URL; ?>api/api_get_part_base_weight.php';
    const selectedPartId = <?php echo $selected_part_id ?? 'null'; ?>;

    async function populateRawMaterials(categoryId) {
        rawMaterialSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        
        let apiParams = {};
        if (categoryId) {
            apiParams.category_id = categoryId;
        }

        try {
            const response = await $.getJSON(apiRawMaterialsUrl, apiParams);
            rawMaterialSelect.html('<option value="">-- انتخاب ماده اولیه --</option>');
            if (response.success && response.data) {
                // The API now returns { CategoryName: [items], ... }
                $.each(response.data, function(categoryName, items) {
                    let group = $('<optgroup>', { label: categoryName });
                    items.forEach(item => {
                        group.append($('<option>', { value: item.ItemID, text: `${item.ItemName} (${item.UnitSymbol})` }));
                    });
                    rawMaterialSelect.append(group);
                });
                rawMaterialSelect.prop('disabled', false);
            } else {
                 rawMaterialSelect.html('<option value="">-- ماده اولیه‌ای یافت نشد --</option>');
            }
        } catch (error) {
            console.error("Error fetching raw materials:", error);
            rawMaterialSelect.html('<option value="">-- خطا در بارگذاری --</option>');
        }
    }

    async function fetchPartWeight(partId) {
        weightInfo.text('...').removeClass('text-success text-danger');
        if (!partId) {
             weightInfo.text(' (قطعه انتخاب نشده)');
             return;
        }
        try {
            const response = await $.getJSON(apiPartWeightUrl, { part_id: partId });
            if (response.success && response.weight_gr) {
                const weight = parseFloat(response.weight_gr).toFixed(3);
                weightInfo.text(weight).addClass('text-success');
                if (!quantityInput.val() || parseFloat(quantityInput.val()) === 0) {
                    quantityInput.val(weight);
                }
            } else {
                 weightInfo.text(' (تعریف نشده)').addClass('text-danger');
            }
        } catch (error) {
            console.error("Error fetching part weight:", error);
            weightInfo.text(' (خطا)').addClass('text-danger');
        }
    }
    
    // --- Event Listeners ---
    rawCategorySelect.on('change', function() {
        populateRawMaterials($(this).val());
    });
    
    // --- Initial Load ---
    if (selectedPartId) {
        populateRawMaterials(null); // Load all materials initially
        fetchPartWeight(selectedPartId);
    }
});
</script>

