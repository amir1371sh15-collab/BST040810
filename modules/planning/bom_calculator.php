<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks
if (!has_permission('planning.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "ماشین حساب BOM";
include_once __DIR__ . '/../../templates/header.php';

// واکشی خانواده‌ها برای دراپ‌داون
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid rtl">
    <div class="row">
        <?php // --- REMOVED: sidebar.php --- ?>
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4"> <?php // --- EDITED: Full width --- ?>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت
                </a>
            </div>

            <!-- فرم ورودی -->
            <div class="card content-card">
                <div class="card-header">
                    <h5 class="mb-0">محاسبه نیازمندی‌ها</h5>
                </div>
                <div class="card-body">
                    <form id="bom-calculator-form">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="family_id" class="form-label">خانواده محصول</label>
                                <select class="form-select" id="family_id" name="family_id" required>
                                    <option value="" selected disabled>انتخاب کنید...</option>
                                    <?php foreach ($families as $family): ?>
                                        <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="part_id" class="form-label">نام محصول نهایی</label>
                                <select class="form-select" id="part_id" name="part_id" required disabled>
                                    <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="unit" class="form-label">واحد</label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="Pieces">تعداد (عدد)</option>
                                    <option value="KG">وزن (KG)</option>
                                    <option value="Carton">کارتن</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="quantity" class="form-label">مقدار</label>
                                <input type="number" step="any" class="form-control" id="quantity" name="quantity" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="bi bi-calculator me-2"></i> محاسبه
                        </button>
                    </form>
                </div>
            </div>

            <!-- بخش نتایج -->
            <div id="results-container" class="mt-4" style="display: none;">
                
                <!-- خلاصه محاسبه -->
                <div class="alert alert-success" id="calculation-summary"></div>

                <!-- قطعات نیمه‌ساخته -->
                <div class="card content-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">قطعات نیمه‌ساخته مورد نیاز</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="p-3">نام قطعه</th>
                                        <th class="p-3">تعداد مورد نیاز (عدد)</th>
                                        <th class="p-3">وزن مورد نیاز (KG)</th>
                                    </tr>
                                </thead>
                                <tbody id="semi-finished-results">
                                    <!-- نتایج به صورت داینامیک اضافه می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- مواد خام -->
                <div class="card content-card">
                    <div class="card-header">
                        <h5 class="mb-0">مواد خام مورد نیاز</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="p-3">نام ماده اولیه</th>
                                        <th class="p-3">مقدار مورد نیاز (KG)</th>
                                    </tr>
                                </thead>
                                <tbody id="raw-material-results">
                                    <!-- نتایج به صورت داینامیک اضافه می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- پیام خطا -->
            <div id="error-container" class="alert alert-danger mt-4" style="display: none;"></div>

        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    
    // Initialize Select2
    $('#family_id, #part_id').select2({
        theme: 'bootstrap-5',
        dir: 'rtl'
    });

    // 1. بارگذاری قطعات بر اساس خانواده
    $('#family_id').on('change', function() {
        const familyId = $(this).val();
        const partSelector = $('#part_id');
        
        partSelector.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');

        if (!familyId) {
            partSelector.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }

        // --- استفاده از API اصلاح شده ---
        $.ajax({
            url: '../../api/api_get_all_parts_grouped.php', // API با مجوز اصلاح شده
            type: 'GET',
            data: { family_id: familyId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    partSelector.html('<option value="">-- انتخاب محصول --</option>');
                    // Grouped response
                    $.each(response.data, function(family, parts) {
                        const optgroup = $('<optgroup>').attr('label', family);
                        $.each(parts, function(i, part) {
                            optgroup.append($('<option>', {
                                value: part.PartID,
                                text: part.PartName + ' (' + part.PartCode + ')'
                            }));
                        });
                        partSelector.append(optgroup);
                    });
                    partSelector.prop('disabled', false);
                } else {
                    partSelector.html('<option value="">قطعه‌ای یافت نشد</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                partSelector.html('<option value="">خطا در بارگذاری قطعات</option>');
            }
        });
    });

    // 2. ارسال فرم و دریافت نتایج
    $('#bom-calculator-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const resultsContainer = $('#results-container');
        const errorContainer = $('#error-container');
        const summaryEl = $('#calculation-summary');
        const semiFinishedTbody = $('#semi-finished-results');
        const rawMaterialTbody = $('#raw-material-results');

        // Reset UI
        resultsContainer.hide();
        errorContainer.hide();
        summaryEl.empty();
        semiFinishedTbody.empty();
        rawMaterialTbody.empty();

        $.ajax({
            url: '../../api/calculate_bom_explosion.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // نمایش خلاصه
                    summaryEl.html(`<strong>خلاصه:</strong> برای تولید <strong>${data.summary.finalQuantity} عدد</strong> از محصول <strong>"${data.summary.partName}"</strong> (معادل ${response.message})، نیازمندی‌های زیر محاسبه شد:`);

                    // نمایش قطعات نیمه‌ساخته
                    if (data.semiFinished.length > 0) {
                        $.each(data.semiFinished, function(i, item) {
                            const weightText = item.totalWeightKG ? item.totalWeightKG.toFixed(3) : 'N/A';
                            const row = `<tr>
                                            <td class_name="p-3">${item.partName}</td>
                                            <td class_name="p-3">${item.totalQuantity.toLocaleString()}</td>
                                            <td class_name="p-3">${weightText}</td>
                                        </tr>`;
                            semiFinishedTbody.append(row);
                        });
                    } else {
                        semiFinishedTbody.append('<tr><td colspan="3" class="text-center text-muted p-3">قطعه نیم‌ساخته‌ای مورد نیاز نیست.</td></tr>');
                    }

                    // نمایش مواد خام
                    if (data.rawMaterials.length > 0) {
                        $.each(data.rawMaterials, function(i, item) {
                            const row = `<tr>
                                            <td class_name="p-3">${item.rawMaterialName}</td>
                                            <td class_name="p-3">${item.totalWeightKG.toFixed(3)}</td>
                                        </tr>`;
                            rawMaterialTbody.append(row);
                        });
                    } else {
                        rawMaterialTbody.append('<tr><td colspan="2" class="text-center text-muted p-3">ماده خام مستقیمی یافت نشد.</td></tr>');
                    }

                    resultsContainer.slideDown();

                } else {
                    // نمایش خطای منطقی (مثل: "اطلاعات بسته‌بندی یافت نشد")
                    errorContainer.text('خطای محاسبه: ' + response.message).slideDown();
                }
            },
            error: function(xhr, status, error) {
                // نمایش خطای ارتباطی
                let errorMsg = 'خطای ناشناخته در ارتباط با سرور.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (status === 'parsererror') {
                    errorMsg = 'پاسخ دریافت شده از سرور معتبر نبود. (خطای 200 OK - ParserError)';
                } else if (xhr.status === 404) {
                     errorMsg = 'فایل API مورد نظر یافت نشد. (404 Not Found)';
                }
                errorContainer.text('خطای سیستمی: ' + errorMsg).slideDown();
            }
        });
    });
});
</script>

