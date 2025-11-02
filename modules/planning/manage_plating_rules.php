<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$pageTitle = "مدیریت قوانین آبکاری";
include __DIR__ . '/../../templates/header.php';

// FIX: Add Select2 CSS and JS libraries because they are not in the main header
?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Select2 Bootstrap 5 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Style for highlighting the row on edit */
    .highlight-edit {
        transition: background-color 0.5s ease;
        background-color: #fff3cd !important; /* Light yellow */
    }
</style>

<?php
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- Fetch Data for Dropdowns and Page Load ---
$family_id_tasmekoochak = 1; // ID خانواده 'تسمه کوچک'

// 1. Get selected IDs from URL
$selected_family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT) ?: null;
$selected_part_id = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT) ?: null;

// 2. Fetch all families for the first dropdown
$all_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

// 3. Prepare variables for the forms (if a part is selected)
$selected_part = null;
$all_other_parts_grouped = [];
$saved_compatibilities = []; // This will hold the saved rules

if ($selected_part_id) {
    // 3a. Get the main part's details (for solo weight)
    $stmt = $pdo->prepare("
        SELECT p.PartID, p.PartName, p.FamilyID, p.BarrelWeight_Solo_KG, ps.PlatingMustBeMixed
        FROM tbl_parts p
        LEFT JOIN tbl_part_sizes ps ON p.SizeID = ps.SizeID
        WHERE p.PartID = ?");
    $stmt->execute([$selected_part_id]);
    $selected_part = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3b. Get all OTHER parts for the checklist
    $all_other_parts_raw = find_all($pdo, "
        SELECT p.PartID, p.PartName, p.FamilyID, pf.FamilyName
        FROM tbl_parts p
        JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
        WHERE p.PartID != ?
        ORDER BY pf.FamilyName, p.PartName
    ", [$selected_part_id]);

    foreach ($all_other_parts_raw as $part) {
        $all_other_parts_grouped[$part['FamilyName']][] = $part;
    }

    // 3c. Get the list of SAVED rules for the table
    $saved_compatibilities = find_all($pdo, "
        SELECT 
            comp.CompatiblePartID, 
            p.PartName AS CompatiblePartName,
            comp.PrimaryPartWeight_KG, 
            comp.CompatiblePartWeight_KG
        FROM tbl_planning_batch_compatibility comp
        JOIN tbl_parts p ON comp.CompatiblePartID = p.PartID
        WHERE comp.PrimaryPartID = ?
        ORDER BY p.PartName
    ", [$selected_part_id]);
}

?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="constraints_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<!-- Display session messages -->
<div id="message-container">
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
</div>


<!-- Step 1: Selection Card -->
<div class="card content-card">
    <div class="card-header">
        <h5 class="mb-0">انتخاب قطعه اصلی</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">برای مشاهده یا تعریف قوانین، ابتدا خانواده و سپس قطعه مورد نظر را انتخاب کنید.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="family-selector" class="form-label">۱. انتخاب خانواده</label>
                <select class="form-select" id="family-selector">
                    <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                    <?php foreach ($all_families as $family): ?>
                        <option value="<?php echo $family['FamilyID']; ?>" <?php echo ($selected_family_id == $family['FamilyID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($family['FamilyName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="part-selector" class="form-label">۲. انتخاب قطعه</label>
                <select class="form-select" id="part-selector" <?php echo !$selected_family_id ? 'disabled' : ''; ?>>
                    <option value="">-- منتظر انتخاب خانواده --</option>
                    <!-- Options will be loaded by AJAX or pre-filled if page is reloaded -->
                </select>
            </div>
        </div>
    </div>
</div>

<?php if ($selected_part): // Only show forms and tables if a part is selected ?>
<form id="plating-rules-form">
    <input type="hidden" name="primary_part_id" id="primary_part_id" value="<?php echo $selected_part_id; ?>">
    
    <div class="row">
        <!-- Solo Weight Card -->
        <div class="col-lg-4">
            <div class="card content-card">
                <div class="card-header"><h5 class="mb-0">وزن بارل تکی</h5></div>
                <div class="card-body">
                    <?php if (isset($selected_part['PlatingMustBeMixed']) && $selected_part['PlatingMustBeMixed']): ?>
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>قانون ۲:</strong> این سایز حتماً باید به صورت ترکیبی آبکاری شود.
                        </div>
                    <?php endif; ?>
                    
                    <label for="barrel_weight_solo" class="form-label">ظرفیت بارل (اگر تنها باشد)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" id="barrel_weight_solo" 
                               name="barrel_weight_solo" 
                               value="<?php echo htmlspecialchars($selected_part['BarrelWeight_Solo_KG'] ?? ''); ?>"
                               <?php echo (isset($selected_part['PlatingMustBeMixed']) && $selected_part['PlatingMustBeMixed']) ? 'disabled' : ''; ?>
                               placeholder="<?php echo (isset($selected_part['PlatingMustBeMixed']) && $selected_part['PlatingMustBeMixed']) ? 'باید ترکیبی باشد' : 'مثلا: 15.5'; ?>">
                        <span class="input-group-text">KG</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Saved Rules Table -->
        <div class="col-lg-8">
            <div class="card content-card">
                <div class="card-header"><h5 class="mb-0">قوانین ترکیبی ثبت شده</h5></div>
                <div class="card-body">
                    <p class="text-muted small">لیست قطعاتی که قبلاً به عنوان "سازگار" برای این قطعه ثبت شده‌اند.</p>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped table-hover" id="saved-rules-table">
                            <thead class="table-light">
                                <tr>
                                    <th>قطعه سازگار</th>
                                    <th>وزن قطعه اصلی (KG)</th>
                                    <th>وزن قطعه سازگار (KG)</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($saved_compatibilities)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">هیچ قانون ترکیبی برای این قطعه ثبت نشده است.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($saved_compatibilities as $rule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rule['CompatiblePartName']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['PrimaryPartWeight_KG']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['CompatiblePartWeight_KG']); ?></td>
                                    <td>
                                        <!-- NEW: Edit Button -->
                                        <button type="button" class="btn btn-outline-primary btn-sm edit-rule-btn" 
                                                data-comp-id="<?php echo $rule['CompatiblePartID']; ?>"
                                                title="ویرایش این مورد در لیست پایین">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-rule-btn" 
                                                data-comp-id="<?php echo $rule['CompatiblePartID']; ?>"
                                                data-comp-name="<?php echo htmlspecialchars($rule['CompatiblePartName']); ?>"
                                                title="حذف این قانون">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

    <!-- Compatibility Checklist -->
    <div class="card content-card">
        <div class="card-header"><h5 class="mb-0">افزودن / ویرایش قوانین ترکیبی</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                قطعاتی را که می‌توانند در کنار "<strong><?php echo htmlspecialchars($selected_part['PartName']); ?></strong>" آبکاری شوند، انتخاب کنید و وزن هر کدام در ترکیب را مشخص نمایید.
            </p>
            <input type="text" id="part-filter" class="form-control mb-3" placeholder="جستجوی قطعه سازگار...">
            
            <div id="checklist-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 0.375rem;">
                <ul class="list-unstyled mb-0">
                    <?php 
                    $is_selected_tasmekoochak = ($selected_part['FamilyID'] == $family_id_tasmekoochak);
                    $saved_comp_ids = array_column($saved_compatibilities, 'CompatiblePartID');
                    
                    foreach ($all_other_parts_grouped as $family_name => $parts): 
                    ?>
                        <li class="mb-2">
                            <strong class="d-block bg-light p-1 rounded-top small"><?php echo htmlspecialchars($family_name); ?></strong>
                            <div class="p-3 border border-top-0 rounded-bottom">
                                <?php 
                                foreach ($parts as $part): 
                                    $is_checked = in_array($part['PartID'], $saved_comp_ids);
                                    $is_disabled = false;
                                    $disable_reason = '';
                                    
                                    // Apply Rule 1: "تسمه کوچک" (FamilyID 1) cannot be mixed with itself
                                    if ($is_selected_tasmekoochak && $part['FamilyID'] == $family_id_tasmekoochak) {
                                        $is_disabled = true;
                                        $disable_reason = ' (قانون ۱: تسمه کوچک با هم ترکیب نمی‌شوند)';
                                    }
                                    
                                    // Find weights if already saved
                                    $p_weight = ''; $c_weight = '';
                                    if ($is_checked) {
                                        foreach ($saved_compatibilities as $rule) {
                                            if ($rule['CompatiblePartID'] == $part['PartID']) {
                                                $p_weight = $rule['PrimaryPartWeight_KG'];
                                                $c_weight = $rule['CompatiblePartWeight_KG'];
                                                break;
                                            }
                                        }
                                    }
                                ?>
                                <div class="form-check" data-part-name="<?php echo htmlspecialchars(mb_strtolower($part['PartName'])); ?>">
                                    <input class="form-check-input compatible-checkbox" type="checkbox" 
                                           name="compatible[<?php echo $part['PartID']; ?>][enabled]" 
                                           value="<?php echo $part['PartID']; ?>" 
                                           id="part_<?php echo $part['PartID']; ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                           <?php echo $is_disabled ? 'disabled' : ''; ?>>
                                    <label class="form-check-label <?php echo $is_disabled ? 'text-muted' : ''; ?>" for="part_<?php echo $part['PartID']; ?>">
                                        <strong><?php echo htmlspecialchars($part['PartName']); ?></strong>
                                        <?php echo htmlspecialchars($disable_reason); ?>
                                    </label>
                                    
                                    <div class="row g-2 mt-1 compatible-weights" style="<?php echo $is_checked ? '' : 'display: none;'; ?>">
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">وزن قطعه اصلی</span>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="compatible[<?php echo $part['PartID']; ?>][primary_weight]" 
                                                       value="<?php echo htmlspecialchars($p_weight); ?>"
                                                       placeholder="وزن <?php echo htmlspecialchars($selected_part['PartName']); ?>"
                                                       <?php echo $is_disabled ? 'disabled' : ''; ?>>
                                                <span class="input-group-text">KG</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">وزن قطعه سازگار</span>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="compatible[<?php echo $part['PartID']; ?>][compatible_weight]" 
                                                       value="<?php echo htmlspecialchars($c_weight); ?>"
                                                       placeholder="وزن <?php echo htmlspecialchars($part['PartName']); ?>"
                                                       <?php echo $is_disabled ? 'disabled' : ''; ?>>
                                                <span class="input-group-text">KG</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="my-2">
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> ذخیره تمام قوانین برای این قطعه</button>
    </div>
</form>
<?php endif; // End if($selected_part) ?>


<?php include __DIR__ . '/../../templates/footer.php'; ?>
<!-- FIX: Add Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Helper function to show alerts
function showAlert(message, type = 'danger') {
    var alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#message-container').html(alertHtml);
}

$(document).ready(function() {
    // --- FIX: Add preloading flag to prevent refresh loop ---
    var isPreloading = true;

    // --- Initialize Select2 ---
    $('#family-selector, #part-selector').select2({
        theme: 'bootstrap-5',
        dir: 'rtl'
    });

    // --- Logic for Dropdowns ---
    var selectedPartID = <?php echo $selected_part_id ? $selected_part_id : 'null'; ?>;
    var selectedFamilyID = <?php echo $selected_family_id ? $selected_family_id : 'null'; ?>;

    // 1. Family Selector Change
    $('#family-selector').on('change', function() {
        var familyId = $(this).val();
        var partSelector = $('#part-selector');
        
        partSelector.prop('disabled', true).html('<option value="">...درحال بارگذاری...</option>').trigger('change');
        
        if (familyId) {
            $.ajax({
                url: '../../api/get_parts_for_plating.php',
                type: 'GET',
                data: { family_id: familyId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        partSelector.html('<option value="">-- یک قطعه انتخاب کنید --</option>');
                        response.data.forEach(function(part) {
                            partSelector.append($('<option>', { value: part.PartID, text: part.PartName }));
                        });
                        partSelector.prop('disabled', false);
                        partSelector.trigger('change');
                    } else {
                        partSelector.html('<option value="">خطا در بارگذاری قطعات</option>').trigger('change');
                        showAlert(response.message || 'خطای ناشناخته');
                    }
                },
                error: function(xhr, status, error) {
                    partSelector.html('<option value="">خطای ارتباط</option>').trigger('change');
                    if (status === 'parsererror') {
                        showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر). این معمولاً به دلیل یک خطای PHP در فایل API است.');
                    } else {
                        showAlert(`(خطای ${xhr.status}) ${error}. (وضعیت: ${status})`);
                    }
                }
            });
        } else {
            partSelector.html('<option value="">-- ابتدا خانواده --</option>').trigger('change');
        }
    });

    // 2. Part Selector Change
    $('#part-selector').on('change', function() {
        // FIX: Check the preloading flag before reloading
        if (isPreloading) {
            return; // Do not reload page if this 'change' was triggered by pre-filling
        }
        
        var partId = $(this).val();
        var familyId = $('#family-selector').val();
        if (partId) {
            // This is now safe to run only on user interaction
            window.location.href = `manage_plating_rules.php?family_id=${familyId}&part_id=${partId}`;
        }
    });
    
    // 3. Pre-fill part selector if family is already selected (on page load)
    if (selectedFamilyID) {
        var partSelector = $('#part-selector');
        $.ajax({
            url: '../../api/get_parts_for_plating.php',
            type: 'GET',
            data: { family_id: selectedFamilyID },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    partSelector.html('<option value="">-- یک قطعه انتخاب کنید --</option>');
                    response.data.forEach(function(part) {
                        var isSelected = (part.PartID == selectedPartID);
                        partSelector.append($('<option>', { value: part.PartID, text: part.PartName, selected: isSelected }));
                    });
                    partSelector.prop('disabled', false);
                    partSelector.trigger('change'); // Notify select2
                }
                // FIX: Set preloading flag to false *after* success
                isPreloading = false;
            },
            error: function(xhr, status, error) {
                 partSelector.html('<option value="">خطای ارتباط</Soption>').trigger('change');
                 if (status === 'parsererror') {
                    showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر). این معمولاً به دلیل یک خطای PHP در فایل API است.');
                 } else {
                    showAlert(`(خطای ${xhr.status}) ${error}. (وضعیت: ${status})`);
                 }
                 // FIX: Set preloading flag to false *after* error
                 isPreloading = false;
            }
        });
    } else {
        // FIX: If no family is selected, preloading is finished
        isPreloading = false;
    }

    // --- Logic for Forms ---

    // 4. Show/Hide weight inputs when checkbox is toggled
    $('#checklist-container').on('change', '.compatible-checkbox', function() {
        $(this).closest('.form-check').find('.compatible-weights').toggle(this.checked);
    });

    // 5. Checklist filter
    $('#part-filter').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#checklist-container .form-check[data-part-name]').filter(function() {
            $(this).toggle($(this).data('part-name').indexOf(value) > -1);
        });
    });

    // 6. Save All Rules (Solo + Compatibility)
    $('#plating-rules-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: '../../api/save_plating_rules.php', // Correct path
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('قوانین با موفقیت ذخیره شد. صفحه در حال بارگذاری مجدد است...', 'success');
                    setTimeout(function() {
                        location.reload(); 
                    }, 1500);
                } else {
                    showAlert(response.message || 'خطا در ذخیره‌سازی.');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'parsererror') {
                    showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر). این معمولاً به دلیل یک خطای PHP در فایل API است.');
                } else {
                    showAlert(`(خطای ${xhr.status}) ${error}. (وضعیت: ${status})`);
                }
            }
        });
    });

    // 7. Delete Rule
    $('#saved-rules-table').on('click', '.delete-rule-btn', function() {
        var compId = $(this).data('comp-id');
        var compName = $(this).data('comp-name');
        var primaryId = $('#primary_part_id').val();
        var $row = $(this).closest('tr');

        if (confirm(`آیا از حذف قانون ترکیب با قطعه "${compName}" مطمئن هستید؟`)) {
            $.ajax({
                url: '../../api/delete_plating_rule.php', // Correct path
                type: 'POST',
                data: {
                    primary_part_id: primaryId,
                    compatible_part_id: compId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('قانون با موفقیت حذف شد.', 'success');
                        $row.fadeOut(300, function() { $(this).remove(); });
                        // Also uncheck it in the checklist below
                        $('#part_' + compId).prop('checked', false).trigger('change');
                    } else {
                        showAlert(response.message || 'خطا در حذف.');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'parsererror') {
                        showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر). این معمولاً به دلیل یک خطای PHP در فایل API است.');
                    } else {
                        showAlert(`(خطای ${xhr.status}) ${error}. (وضعیت: ${status})`);
                    }
                }
            });
        }
    });

    // NEW: 8. Edit Rule (Find and Highlight)
    $('#saved-rules-table').on('click', '.edit-rule-btn', function() {
        var compId = $(this).data('comp-id');
        var $targetCheckDiv = $('#part_' + compId).closest('.form-check');

        if ($targetCheckDiv.length) {
            // 1. Clear any filters to make sure it's visible
            $('#part-filter').val('').trigger('keyup');
            
            // 2. Scroll to the element
            $('html, body').animate({
                scrollTop: $targetCheckDiv.offset().top - 150 // 150px offset for headers
            }, 500);

            // 3. Highlight the element
            // Remove highlight from others
            $('.highlight-edit').removeClass('highlight-edit');
            // Add highlight
            $targetCheckDiv.addClass('highlight-edit');
            // Remove highlight after 2.5 seconds
            setTimeout(function() {
                $targetCheckDiv.removeClass('highlight-edit');
            }, 2500);
            
        } else {
            showAlert('خطای عجیب: ردیف مورد نظر در چک‌لیست پایین پیدا نشد.');
        }
    });

});
</script>

