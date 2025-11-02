<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$pageTitle = "مدیریت ناسازگاری ویبره";
include __DIR__ . '/../../templates/header.php';

// Add Select2 CSS and JS libraries
?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Select2 Bootstrap 5 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
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

// 1. Get selected IDs from URL
$selected_family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT) ?: null;
$selected_part_id = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT) ?: null;

// 2. Fetch all families for the first dropdown
$all_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

// 3. Prepare variables for the forms (if a part is selected)
$selected_part = null;
$all_other_parts_grouped = [];
$saved_incompatibilities = []; // This will hold the saved rules

if ($selected_part_id) {
    // 3a. Get the main part's details
    // Use standard PDO to avoid helper function issues
    $stmt = $pdo->prepare("SELECT PartID, PartName, FamilyID FROM tbl_parts WHERE PartID = ?");
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

    // 3c. Get the list of SAVED incompatible rules for the table
    $saved_incompatibilities = find_all($pdo, "
        SELECT 
            comp.IncompatiblePartID, 
            p.PartName AS IncompatiblePartName
        FROM tbl_planning_vibration_incompatibility comp
        JOIN tbl_parts p ON comp.IncompatiblePartID = p.PartID
        WHERE comp.PrimaryPartID = ?
        ORDER BY p.PartName
    ", [$selected_part_id]);
}

?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="constraints_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<p class="mb-3">
    در این صفحه، قطعاتی را که **نمی‌توانند** پشت سر هم در دستگاه ویبره قرار گیرند (بدون تمیزکاری) مشخص کنید.
</p>

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
<form id="vibration-rules-form">
    <input type="hidden" name="primary_part_id" id="primary_part_id" value="<?php echo $selected_part_id; ?>">
    
    <!-- Saved Rules Table -->
    <div class="card content-card">
        <div class="card-header"><h5 class="mb-0">قوانین ناسازگاری ثبت شده (ویبره)</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                لیست قطعاتی که قبلاً به عنوان "ناسازگار" با "<strong><?php echo htmlspecialchars($selected_part['PartName']); ?></strong>" ثبت شده‌اند.
            </p>
            <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-sm table-striped table-hover" id="saved-rules-table">
                    <thead class="table-light">
                        <tr>
                            <th>قطعه ناسازگار</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($saved_incompatibilities)): ?>
                            <tr><td colspan="2" class="text-center text-muted">هیچ قانون ناسازگاری برای این قطعه ثبت نشده است.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($saved_incompatibilities as $rule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rule['IncompatiblePartName']); ?></td>
                            <td>
                                <button type="button" class="btn btn-outline-primary btn-sm edit-rule-btn" 
                                        data-comp-id="<?php echo $rule['IncompatiblePartID']; ?>"
                                        title="پیدا کردن این مورد در لیست پایین">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                
                                <button type="button" class="btn btn-outline-danger btn-sm delete-rule-btn" 
                                        data-comp-id="<?php echo $rule['IncompatiblePartID']; ?>"
                                        data-comp-name="<?php echo htmlspecialchars($rule['IncompatiblePartName']); ?>"
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

    <!-- Compatibility Checklist -->
    <div class="card content-card">
        <div class="card-header"><h5 class="mb-0">افزودن / ویرایش قوانین ناسازگاری</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                قطعاتی را که **نباید** بلافاصله بعد از "<strong><?php echo htmlspecialchars($selected_part['PartName']); ?></strong>" در ویبره قرار گیرند، انتخاب کنید.
            </p>
            <input type="text" id="part-filter" class="form-control mb-3" placeholder="جستجوی قطعه ناسازگار...">
            
            <div id="checklist-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 0.375rem;">
                <ul class="list-unstyled mb-0">
                    <?php 
                    $saved_comp_ids = array_column($saved_incompatibilities, 'IncompatiblePartID');
                    
                    foreach ($all_other_parts_grouped as $family_name => $parts): 
                    ?>
                        <li class="mb-2">
                            <strong class="d-block bg-light p-1 rounded-top small"><?php echo htmlspecialchars($family_name); ?></strong>
                            <div class="p-3 border border-top-0 rounded-bottom">
                                <?php 
                                foreach ($parts as $part): 
                                    $is_checked = in_array($part['PartID'], $saved_comp_ids);
                                ?>
                                <div class="form-check" data-part-name="<?php echo htmlspecialchars(mb_strtolower($part['PartName'])); ?>">
                                    <input class="form-check-input incompatible-checkbox" type="checkbox" 
                                           name="incompatible[<?php echo $part['PartID']; ?>]" 
                                           value="<?php echo $part['PartID']; ?>" 
                                           id="part_<?php echo $part['PartID']; ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="part_<?php echo $part['PartID']; ?>">
                                        <strong><?php echo htmlspecialchars($part['PartName']); ?></strong>
                                    </label>
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
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> ذخیره تمام قوانین ناسازگاری</button>
    </div>
</form>
<?php endif; // End if($selected_part) ?>


<?php include __DIR__ . '/../../templates/footer.php'; ?>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Helper function to show alerts
function showAlert(message, type = 'danger') {
    var alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    // Clear previous messages
    $('#message-container').html(alertHtml);
    // Scroll to top to see message
    window.scrollTo(0, 0);
}

$(document).ready(function() {
    var isPreloading = true; // Flag to prevent reload loop

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
            // We reuse the same API as the plating rules page
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
                        showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر).');
                    } else {
                        showAlert(`(خطای ${xhr.status}) ${error}.`);
                    }
                }
            });
        } else {
            partSelector.html('<option value="">-- ابتدا خانواده --</option>').trigger('change');
        }
    });

    // 2. Part Selector Change
    $('#part-selector').on('change', function() {
        if (isPreloading) return; // Prevent reload on init
        
        var partId = $(this).val();
        var familyId = $('#family-selector').val();
        if (partId) {
            window.location.href = `manage_vibration_incompatibility.php?family_id=${familyId}&part_id=${partId}`;
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
                isPreloading = false;
            },
            error: function(xhr, status, error) {
                 partSelector.html('<option value="">خطای ارتباط</Soption>').trigger('change');
                 if (status === 'parsererror') {
                    showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر).');
                 } else {
                    showAlert(`(خطای ${xhr.status}) ${error}.`);
                 }
                 isPreloading = false;
            }
        });
    } else {
        isPreloading = false;
    }

    // --- Logic for Forms ---

    // 4. Checklist filter
    $('#part-filter').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#checklist-container .form-check[data-part-name]').filter(function() {
            // Toggle the visibility of the .form-check div
            $(this).toggle($(this).data('part-name').indexOf(value) > -1);
            
            // Also toggle the <hr> associated with it
            $(this).next('hr').toggle($(this).data('part-name').indexOf(value) > -1);
        });
    });

    // 5. Save All Rules
    $('#vibration-rules-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: '../../api/save_vibration_rules.php', // Use the new API
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('قوانین ناسازگاری با موفقیت ذخیره شد. صفحه در حال بارگذاری مجدد است...', 'success');
                    setTimeout(function() {
                        location.reload(); 
                    }, 1500);
                } else {
                    showAlert(response.message || 'خطا در ذخیره‌سازی.');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'parsererror') {
                    showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر).');
                } else {
                    showAlert(`(خطای ${xhr.status}) ${error}.`);
                }
            }
        });
    });

    // 6. Delete Rule
    $('#saved-rules-table').on('click', '.delete-rule-btn', function() {
        var compId = $(this).data('comp-id');
        var compName = $(this).data('comp-name');
        var primaryId = $('#primary_part_id').val();
        var $row = $(this).closest('tr');

        if (confirm(`آیا از حذف قانون ناسازگاری با قطعه "${compName}" مطمئن هستید؟`)) {
            $.ajax({
                url: '../../api/delete_vibration_rule.php', // Use the new API
                type: 'POST',
                data: {
                    primary_part_id: primaryId,
                    incompatible_part_id: compId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('قانون با موفقیت حذف شد.', 'success');
                        $row.fadeOut(300, function() { $(this).remove(); });
                        // Also uncheck it in the checklist below
                        $('#part_' + compId).prop('checked', false);
                    } else {
                        showAlert(response.message || 'خطا در حذف.');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'parsererror') {
                        showAlert('خطا در پردازش پاسخ سرور (JSON نامعتبر).');
                    } else {
                        showAlert(`(خطای ${xhr.status}) ${error}.`);
                    }
                }
            });
        }
    });

    // 7. Edit Rule (Find and Highlight)
    $('#saved-rules-table').on('click', '.edit-rule-btn', function() {
        var compId = $(this).data('comp-id');
        var $targetCheckDiv = $('#part_' + compId).closest('.form-check');

        if ($targetCheckDiv.length) {
            // 1. Clear any filters
            $('#part-filter').val('').trigger('keyup');
            
            // 2. Scroll to the element
            $('html, body').animate({
                scrollTop: $targetCheckDiv.offset().top - 150 // 150px offset
            }, 500);

            // 3. Highlight
            $('.highlight-edit').removeClass('highlight-edit');
            $targetCheckDiv.addClass('highlight-edit');
            setTimeout(function() {
                $targetCheckDiv.removeClass('highlight-edit');
            }, 2500);
            
        } else {
            showAlert('خطای عجیب: ردیف مورد نظر در چک‌لیست پایین پیدا نشد.');
        }
    });

});
</script>

