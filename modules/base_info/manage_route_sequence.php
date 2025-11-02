<?php
require_once __DIR__ . '/../../config/init.php';
// فرض می‌کنیم دسترسی 'base_info.manage' وجود دارد. در صورت نیاز، آن را به 'base_info.manage_routes' تغییر دهید.
if (!has_permission('base_info.manage')) { 
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "مدیریت ترتیب مسیرهای تولید";
include __DIR__ . '/../../templates/header.php';

// واکشی تمام خانواده‌ها برای دراپ‌دان
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$selected_family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);
$selected_family_name = "";
if ($selected_family_id) {
    $family_info = find_by_id($pdo, 'tbl_part_families', $selected_family_id, 'FamilyID');
    if ($family_info) {
        $selected_family_name = $family_info['FamilyName'];
    }
}
?>

<div class="container-fluid rtl">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo $pageTitle; ?></h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> بازگشت به اطلاعات پایه
        </a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        ۱. خانواده را انتخاب کنید. ۲. شماره مراحل را در کادرها ویرایش کنید. ۳. دکمه «مرتب‌سازی» را بزنید تا ترتیب ظاهری لیست اصلاح شود. ۴. دکمه «ذخیره ترتیب» را بزنید.
    </div>

    <div class="card content-card mb-4">
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="family_id" class="form-label">خانواده محصول</label>
                        <select class="form-select" id="family_id" name="family_id" onchange="this.form.submit()">
                            <option value="">-- یک خانواده را انتخاب کنید --</option>
                            <?php foreach ($families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>" <?php echo ($selected_family_id == $family['FamilyID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($family['FamilyName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7 d-flex align-items-end">
                        <button type="button" id="sort-list-btn" class="btn btn-primary me-2" <?php echo $selected_family_id ? '' : 'disabled'; ?>>
                            <i class="bi bi-sort-numeric-down me-2"></i> مرتب‌سازی
                        </button>
                        <button type="button" id="save-sequence-btn" class="btn btn-success" <?php echo $selected_family_id ? '' : 'disabled'; ?>>
                            <i class="bi bi-save-fill me-2"></i> ذخیره ترتیب
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Alert for save status -->
    <div id="save-alert" class="alert" style="display: none;"></div>

    <!-- Draggable List -->
    <?php if ($selected_family_id): ?>
        <div class="card content-card">
            <div class="card-header">
                <h5 class="mb-0">ترتیب مراحل برای: <?php echo htmlspecialchars($selected_family_name); ?></h5>
            </div>
            <div class="card-body">
                <ul id="route-list" class="list-group">
                    <!-- Items will be loaded by JavaScript -->
                    <li class="list-group-item text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- NO SortableJS library needed -->

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const familyId = <?php echo $selected_family_id ? $selected_family_id : 'null'; ?>;
    const routeList = document.getElementById('route-list');
    const saveBtn = $('#save-sequence-btn');
    const sortBtn = $('#sort-list-btn');
    const saveAlert = $('#save-alert');

    function loadRoutes(familyId) {
        if (!familyId) return;

        fetch(`../../api/get_routes_for_sequencing.php?family_id=${familyId}`)
            .then(response => response.json())
            .then(data => {
                routeList.innerHTML = ''; // Clear spinner
                if (data.success && data.data.length > 0) {
                    data.data.forEach((route, index) => {
                        let badge = route.RouteType === 'override' 
                            ? '<span class="badge bg-warning text-dark ms-2">Override</span>' 
                            : '<span class="badge bg-secondary ms-2">Standard</span>';
                        
                        // استفاده از StepNumber واقعی از دیتابیس
                        let step = route.StepNumber; 
                        
                        routeList.innerHTML += `
                            <li class="list-group-item d-flex justify-content-between align-items-center" 
                                data-id="${route.ID}" 
                                data-type="${route.RouteType}">
                                
                                <div class="d-flex align-items-center">
                                    <input type="number" class="form-control form-control-sm route-step-input me-3" value="${step}" style="width: 75px;">
                                    
                                    <strong>${route.FromStation}</strong>
                                    <i class="bi bi-arrow-left-right mx-2"></i>
                                    <strong>${route.ToStation}</strong>
                                    <span class="text-muted ms-3">(خروجی: ${route.OutputStatus || '---'})</span>
                                </div>
                                ${badge}
                            </li>
                        `;
                    });
                } else if (data.success) {
                    routeList.innerHTML = '<li class="list-group-item text-center">هیچ مسیری برای این خانواده تعریف نشده است.</li>';
                } else {
                    routeList.innerHTML = `<li class="list-group-item list-group-item-danger">${data.message}</li>`;
                }
            })
            .catch(error => {
                routeList.innerHTML = `<li class="list-group-item list-group-item-danger">خطای شبکه: ${error.message}</li>`;
            });
    }

    // --- NEW: Client-side Sort Button ---
    sortBtn.on('click', function() {
        let items = routeList.querySelectorAll('li');
        let itemsArray = Array.from(items);

        itemsArray.sort(function(a, b) {
            // خواندن مقدار عددی از اینپوت
            let stepA = parseFloat(a.querySelector('.route-step-input').value) || 999;
            let stepB = parseFloat(b.querySelector('.route-step-input').value) || 999;
            return stepA - stepB;
        });

        // خالی کردن لیست و اضافه کردن آیتم‌های مرتب شده
        routeList.innerHTML = '';
        itemsArray.forEach(item => {
            routeList.appendChild(item);
        });
    });

    saveBtn.on('click', function() {
        // مرتب‌سازی نهایی قبل از ذخیره، برای اطمینان
        sortBtn.trigger('click');

        // گرفتن ترتیب بصری فعلی آیتم‌ها پس از مرتب‌سازی
        const sequence = Array.from(routeList.querySelectorAll('li')).map(item => ({
            id: item.getAttribute('data-id'),
            type: item.getAttribute('data-type')
        }));

        saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> در حال ذخیره...');
        saveAlert.hide().removeClass('alert-success alert-danger');

        fetch(`../../api/save_route_sequence.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sequence: sequence })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                saveAlert.addClass('alert-success').html(`<strong>موفق!</strong> ${data.message}`).show();
                // بازخوانی لیست برای نمایش شماره‌های تایید شده
                loadRoutes(familyId);
            } else {
                throw new Error(data.message || 'خطای ناشناخته');
            }
        })
        .catch(error => {
            saveAlert.addClass('alert-danger').html(`<strong>خطا:</strong> ${error.message}`).show();
        })
        .finally(() => {
            saveBtn.prop('disabled', false).html('<i class="bi bi-save-fill me-2"></i> ذخیره ترتیب');
        });
    });

    // Load routes on page load if family is selected
    loadRoutes(familyId);
});
</script>

