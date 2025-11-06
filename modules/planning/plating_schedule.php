<?php
require_once __DIR__ . '/../../config/init.php';

// [!!!] مدیریت درخواست حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!has_permission('planning.production_schedule.save')) { // فرض بر استفاده از همین دسترسی
        $_SESSION['message'] = 'شما مجوز حذف دستور کار را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $delete_id = (int)$_POST['delete_id'];
        // [!!!] ایمن‌سازی: فقط دستور کارهای با وضعیت 'Generated' قابل حذف هستند
        $result = $pdo->prepare("DELETE FROM tbl_planning_work_orders WHERE WorkOrderID = ? AND Status = 'Generated'");
        if ($result->execute([$delete_id])) {
            if ($result->rowCount() > 0) {
                $_SESSION['message'] = 'دستور کار با موفقیت حذف شد.';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'خطا: دستور کار یافت نشد یا وضعیت آن "Generated" نیست.';
                $_SESSION['message_type'] = 'warning';
            }
        } else {
            $_SESSION['message'] = 'خطا در اجرای حذف.';
            $_SESSION['message_type'] = 'danger';
        }
    }
    // ریدایرکت به همین صفحه برای جلوگیری از ارسال مجدد فرم
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
// [!!!] پایان مدیریت حذف

$pageTitle = "برنامه‌ریزی آبکاری و شستشو";
include_once __DIR__ . '/../../templates/header.php';

// [!!!] واکشی تاریخچه (شامل ستون‌های جدید)
$work_orders_history = find_all($pdo, "
    SELECT 
        wo.WorkOrderID, wo.PlannedDate, wo.Quantity, wo.Unit, wo.Status,
        wo.AuxQuantity, wo.AuxUnit, wo.BatchGUID,
        p.PartName, s.StationName
    FROM tbl_planning_work_orders wo
    JOIN tbl_parts p ON wo.PartID = p.PartID
    JOIN tbl_stations s ON wo.StationID = s.StationID
    WHERE wo.StationID IN (1, 4, 7) -- 1:شستشو, 4:آبکاری, 7:دوباره کاری
    ORDER BY wo.WorkOrderID DESC 
    LIMIT 30
");

// [!!!] نمایش پیام‌های سشن (برای حذف)
if (isset($_SESSION['message'])) {
    echo '<div class="container-fluid rtl"><div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">';
    echo $_SESSION['message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div></div>';
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <!-- Nav Tabs -->
            <ul class="nav nav-tabs nav-fill mb-3" id="platingTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="plating-tab" data-bs-toggle="tab" data-bs-target="#plating-content" type="button" role="tab" aria-controls="plating-content" aria-selected="true">
                        <i class="bi bi-droplet-half me-2"></i>۱. برنامه‌ریزی آبکاری
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="washing-tab" data-bs-toggle="tab" data-bs-target="#washing-content" type="button" role="tab" aria-controls="washing-content" aria-selected="false">
                        <i class="bi bi-water me-2"></i>۲. برنامه‌ریزی شستشو
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="rework-tab" data-bs-toggle="tab" data-bs-target="#rework-content" type="button" role="tab" aria-controls="rework-content" aria-selected="false">
                        <i class="bi bi-arrow-repeat me-2"></i>۳. برنامه‌ریزی دوباره‌کاری
                    </button>
                </li>
            </ul>

            <!-- General Controls: Date & Man-Hours -->
            <div class="row g-3 mb-3 px-3 py-3 bg-light border rounded">
                <div class="col-md-4">
                    <label for="planning-date" class="form-label">تاریخ اجرای برنامه *</label>
                    <input type="text" id="planning-date" class="form-control text-center persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="plating-man-hours" class="form-label">نفر-ساعت در دسترس (آبکاری)</label>
                    <div class="input-group">
                        <input type="number" id="plating-man-hours" class="form-control" value="8" min="1">
                        <span class="input-group-text">ساعت</span>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <!-- [!!!] دکمه اختیاری شد -->
                    <button id="calculate-capacity-btn" class="btn btn-info w-100">
                        <i class="bi bi-calculator me-2"></i> محاسبه ظرفیت کل (اختیاری)
                    </button>
                </div>
            </div>
            <div id="capacity-alert-container"></div>


            <!-- Tab Content -->
            <div class="tab-content" id="platingTabContent">

                <!-- ============================================= -->
                <!-- TAB 1: PLATING (آبکاری)                        -->
                <!-- ============================================= -->
                <div class="tab-pane fade show active" id="plating-content" role="tabpanel" aria-labelledby="plating-tab">
                    
                    <!-- Step 1: Load Parts -->
                    <div class="card content-card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                گام ۱: انتخاب قطعات برای آبکاری
                                <button id="load-plating-parts-btn" class="btn btn-primary btn-sm">
                                    <i class="bi bi-box-seam me-2"></i> بارگذاری قطعات نیمه‌ساخته (WIP)
                                </button>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-striped table-sm align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="p-2"><input type="checkbox" id="select-all-plating-parts"></th>
                                            <th class="p-2">قطعه</th>
                                            <th class="p-2">خانواده</th>
                                            <th class="p-2">وضعیت فعلی</th>
                                            <th class="p-2">موجودی (KG)</th>
                                            <th class="p-2">برنامه امروز (KG)</th> <!-- [!!!] ستون جدید -->
                                        </tr>
                                    </thead>
                                    <tbody id="plating-parts-tbody">
                                        <tr><td colspan="6" class="text-center text-muted p-3">لطفاً دکمه بارگذاری را بزنید...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Batching -->
                    <div class="card content-card mb-3" id="batching-container" style="display: none;">
                        <div class="card-header">
                            <h5 class="mb-0">گام ۲: بچ‌بندی (گروه‌بندی بارل‌ها)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 border-end">
                                    <h6 class="text-muted">قطعات انتخاب شده</h6>
                                    <ul id="selected-parts-list" class="list-group list-group-flush">
                                        <!-- Selected parts will be listed here -->
                                    </ul>
                                    <button id="add-batch-btn" class="btn btn-success btn-sm mt-3 w-100">
                                        <i class="bi bi-plus-circle me-2"></i> ایجاد بچ جدید
                                    </button>
                                </div>
                                <div class="col-md-8">
                                    <h6 class="text-muted">بچ‌های ایجاد شده (برای تخصیص بارل)</h6>
                                    <div id="batches-list-container">
                                        <p class="text-center text-muted" id="no-batch-placeholder">هنوز بچی ایجاد نشده است...</p>
                                        <!-- Batches will be dynamically added here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Capacity Planning & Inventory Check -->
                    <!-- [!!!] گام ۳ و ۴ ادغام شدند -->
                    <div class="card content-card mb-3" id="plating-planning-container" style="display: none;">
                        <div class="card-header">
                             <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                گام ۳: بررسی موجودی و نهایی‌سازی
                                <!-- [!!!] نمایشگر ظرفیت اختیاری -->
                                <span id="capacity-display-wrapper" class="fs-6 fw-bold" style="display: none;">
                                    ظرفیت کل: <span id="total-capacity-display" class="text-primary me-3">0 بارل</span>
                                    تخصیص یافته: <span id="assigned-capacity-display" class="text-warning">0 بارل</span>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="vibration-warning-container"></div>
                            
                            <h6><i class="bi bi-clipboard-data me-2"></i>خلاصه مصرف موجودی</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>قطعه</th>
                                            <th>موجودی کل (KG)</th>
                                            <th>برنامه قبلی (KG)</th> <!-- [!!!] ستون جدید -->
                                            <th>موجودی در دسترس (KG)</th> <!-- [!!!] ستون جدید -->
                                            <th>مصرف این برنامه (KG)</th>
                                            <th>باقیمانده نهایی (KG)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="plating-inventory-tbody">
                                        <!-- Inventory summary rows will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <hr>
                            <div class="text-center">
                                <button id="save-plating-plan-btn" class="btn btn-danger btn-lg">
                                    <i class="bi bi-save me-2"></i> نهایی‌سازی و ایجاد دستور کار آبکاری (ایستگاه ۴)
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================================= -->
                <!-- TAB 2: WASHING (شستشو)                         -->
                <!-- ============================================= -->
                <div class="tab-pane fade" id="washing-content" role="tabpanel" aria-labelledby="washing-tab">
                     <div class="card content-card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                گام ۱: انتخاب قطعات برای شستشو
                                <button id="load-washing-parts-btn" class="btn btn-primary btn-sm">
                                    <i class="bi bi-box-seam me-2"></i> بارگذاری قطعات (WIP)
                                </button>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-striped table-sm align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="p-2">قطعه</th>
                                            <th class="p-2">خانواده</th>
                                            <th class="p-2">وضعیت فعلی</th>
                                            <th class="p-2">موجودی (KG)</th>
                                            <th class="p-2">برنامه امروز (KG)</th> <!-- [!!!] ستون جدید -->
                                            <th class="p-2" style="width: 200px;">مقدار برنامه (KG) *</th>
                                        </tr>
                                    </thead>
                                    <tbody id="washing-parts-tbody">
                                        <tr><td colspan="6" class="text-center text-muted p-3">لطفاً دکمه بارگذاری را بزنید...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <hr>
                            <div class="text-center">
                                <button id="save-washing-plan-btn" class="btn btn-danger btn-lg">
                                    <i class="bi bi-save me-2"></i> ایجاد دستور کار شستشو (ایستگاه ۱)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================= -->
                <!-- TAB 3: REWORK (دوباره کاری)                    -->
                <!-- ============================================= -->
                <div class="tab-pane fade" id="rework-content" role="tabpanel" aria-labelledby="rework-tab">
                    <div class="card content-card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                گام ۱: انتخاب قطعات برای دوباره‌کاری
                                <button id="load-rework-parts-btn" class="btn btn-primary btn-sm">
                                    <i class="bi bi-box-seam me-2"></i> بارگذاری همه قطعات (WIP)
                                </button>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-striped table-sm align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="p-2">قطعه</th>
                                            <th class="p-2">خانواده</th>
                                            <th class="p-2">وضعیت فعلی</th>
                                            <th class="p-2">موجودی (KG)</th>
                                            <th class="p-2">برنامه امروز (KG)</th> <!-- [!!!] ستون جدید -->
                                            <th class="p-2" style="width: 200px;">مقدار برنامه (KG) *</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rework-parts-tbody">
                                        <tr><td colspan="6" class="text-center text-muted p-3">لطفاً دکمه بارگذاری را بزنید...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <hr>
                            <div class="text-center">
                                <button id="save-rework-plan-btn" class="btn btn-danger btn-lg">
                                    <i class="bi bi-save me-2"></i> ایجاد دستور کار دوباره‌کاری (ایستگاه ۷)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- History Table -->
            <div class="card content-card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>تاریخچه آخرین دستور کارها</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th class="p-2">ID</th>
                                    <th class="p-2">تاریخ برنامه</th>
                                    <th class="p-2">ایستگاه</th>
                                    <th class="p-2">قطعه</th>
                                    <th class="p-2">مقدار (KG)</th>
                                    <th class="p-2">تعداد (بارل)</th> <!-- [!!!] ستون بارل -->
                                    <th class="p-2">وضعیت</th>
                                    <th class="p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($work_orders_history)): ?>
                                    <tr><td colspan="8" class="text-center text-muted p-3">دستور کاری یافت نشد.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($work_orders_history as $wo): ?>
                                        <tr>
                                            <td class="p-2"><?php echo $wo['WorkOrderID']; ?> (<?php echo substr($wo['BatchGUID'] ?? 'N/A', 0, 4); ?>)</td>
                                            <td class="p-2"><?php echo to_jalali($wo['PlannedDate']); ?></td>
                                            <td class="p-2"><?php echo htmlspecialchars($wo['StationName']); ?></td>
                                            <td class="p-2"><?php echo htmlspecialchars($wo['PartName']); ?></td>
                                            <td class="p-2"><?php echo number_format($wo['Quantity'], 2); ?></td>
                                            <td class="p-2">
                                                <!-- [!!!] نمایش بارل فقط برای آبکاری -->
                                                <?php if ($wo['StationName'] === 'آبکاری' && $wo['AuxUnit'] === 'بارل'): ?>
                                                    <span class="badge bg-info text-dark"><?php echo number_format($wo['AuxQuantity']); ?> <?php echo htmlspecialchars($wo['AuxUnit']); ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-2">
                                                <?php
                                                $status_class = 'bg-secondary';
                                                if ($wo['Status'] == 'InProgress') $status_class = 'bg-info text-dark';
                                                if ($wo['Status'] == 'Completed') $status_class = 'bg-success';
                                                echo "<span class='badge {$status_class}'>{$wo['Status']}</span>";
                                                ?>
                                            </td>
                                            <td class="p-2 text-nowrap">
                                                <a href="edit_work_order.php?edit_id=<?php echo $wo['WorkOrderID']; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <!-- [!!!] دکمه حذف -->
                                                <?php if ($wo['Status'] == 'Generated'): ?>
                                                    <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $wo['WorkOrderID']; ?>" title="حذف">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <!-- [!!!] مودال حذف -->
                                        <div class="modal fade" id="deleteModal<?php echo $wo['WorkOrderID']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">تایید حذف</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>آیا از حذف دستور کار <strong>#<?php echo $wo['WorkOrderID']; ?></strong> (<?php echo htmlspecialchars($wo['PartName']); ?>) مطمئن هستید؟</p>
                                                        <p class="text-muted small">توجه: اگر این دستور کار بخشی از یک بچ آبکاری باشد، سایر قطعات بچ حذف نخواهند شد.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="delete_id" value="<?php echo $wo['WorkOrderID']; ?>">
                                                            <button type="submit" class="btn btn-danger">بله، حذف کن</button>
                                                        </form>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>

<!-- Batch Modal -->
<div class="modal fade" id="batchModal" tabindex="-1" aria-labelledby="batchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchModalLabel">ایجاد / ویرایش بچ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editing-batch-id">
                <div id="batch-modal-alert-container"></div>
                <div class="row">
                    <!-- Left Column: Available Parts -->
                    <div class="col-md-6 border-end">
                        <h6>قطعات در دسترس (انتخاب شده)</h6>
                        <div id="modal-available-parts" class="list-group">
                            <!-- Available parts to add to batch go here -->
                        </div>
                    </div>
                    <!-- Right Column: Parts in this Batch -->
                    <div class="col-md-6">
                        <h6>قطعات داخل این بچ <small class="text-muted">(برای حذف کلیک کنید)</small></h6>
                        <div id="modal-batch-parts" class="list-group">
                            <!-- Parts added to this batch go here -->
                        </div>
                    </div>
                </div>
                <hr>
                <h6>تنظیم وزن بارل (KG)</h6>
                <div id="modal-batch-weights" class="row g-2">
                    <!-- Weight inputs go here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="save-batch-btn">ذخیره بچ</button>
            </div>
        </div>
    </div>
</div>


<?php include_once __DIR__ . '/../../templates/footer.php'; ?>

<!-- MAIN Plating Schedule JS -->
<script>
$(document).ready(function() {
    
    // Global state
    let allPartsData = { // [!!!] Store data for all tabs
        plating: [],
        washing: [],
        rework: []
    };
    let selectedPartIds = []; // Stores PartIDs checked by the user (for plating)
    let batchRules = { part_details: {}, compatibility_rules: [], vibration_rules: [] };
    let batches = {}; // Main object to store all created batches, e.g., { "batch_1": { parts: [..], planned_barrels: 0 } }
    let totalCapacity = 0; // Total barrels (optional)
    let batchCounter = 0;
    
    const API = {
        GET_INPUTS: '../../api/get_plating_inputs.php',
        GET_BATCH_RULES: '../../api/get_plating_batch_rules.php',
        GET_CAPACITY: '../../api/get_plating_capacity.php',
        SAVE_PLAN: '../../api/save_plating_schedule.php'
    };

    // =================================================================
    // Utility Functions
    // =================================================================
    
    function showGeneralAlert(containerId, message, type = 'danger') {
        const $container = $(`#${containerId}`);
        $container.html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
    }

    function getPartById(partId, mode = 'plating') {
        return allPartsData[mode].find(p => p.PartID == partId);
    }
    
    function getBatchRuleDetail(partId) {
        return batchRules.part_details[partId] || { PartName: 'N/A', BarrelWeight_Solo_KG: 50 }; // Default 50kg
    }
    
    function findVibrationIncompatibility(partId1, partId2) {
        return batchRules.vibration_rules.some(rule =>
            (rule[0] == partId1 && rule[1] == partId2) || (rule[0] == partId2 && rule[1] == partId1)
        );
    }
    
    function findBatchCompatibility(partId1, partId2) {
        // Find a rule where these two parts are listed
        return batchRules.compatibility_rules.find(rule =>
            (rule[0] == partId1 && rule[1] == partId2) || (rule[0] == partId2 && rule[1] == partId1)
        );
    }

    // =================================================================
    // Loaders (Plating, Washing, Rework)
    // =================================================================

    function loadParts(mode, tbodyId) {
        const $tbody = $(`#${tbodyId}`);
        const $btn = $(`#load-${mode}-parts-btn`);
        const planningDate = $('#planning-date').val(); // [!!!] Get current date
        
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>در حال بارگذاری...');
        const colSpan = (mode === 'plating' || mode === 'washing' || mode === 'rework') ? 6 : 5;
        $tbody.html(`<tr><td colspan="${colSpan}" class="text-center p-3"><span class="spinner-border spinner-border-sm"></span></td></tr>`);

        $.ajax({
            url: API.GET_INPUTS,
            type: 'GET',
            data: { 
                mode: mode,
                planned_date: planningDate // [!!!] Send date to API
            },
            dataType: 'json',
            success: function(response) {
                $tbody.empty();
                if (response.success && response.data.length > 0) {
                    
                    allPartsData[mode] = response.data; // [!!!] Store in the correct tab
                    
                    // [!!!] FIX: Convert strings to numbers AFTER loading
                    allPartsData[mode].forEach(function(item) {
                        item.TotalWeightKG = parseFloat(item.TotalWeightKG || 0);
                        item.PlannedTodayKG = parseFloat(item.PlannedTodayKG || 0);
                    });

                    $.each(allPartsData[mode], function(i, item) { // [!!!] Iterate over the corrected data
                        const partId = item.PartID;
                        const inventoryKG = item.TotalWeightKG; // Already a number
                        const plannedTodayKG = item.PlannedTodayKG; // Already a number
                        
                        let rowHtml = `
                            <tr data-part-id="${partId}" data-status-id="${item.CurrentStatusID || ''}">
                        `;
                        
                        if (mode === 'plating') {
                            rowHtml += `
                                <td>
                                    <input class="form-check-input plating-part-select" type="checkbox" value="${partId}">
                                </td>
                            `;
                        }
                        
                        rowHtml += `
                                <td>${item.PartName}</td>
                                <td><span class="badge bg-info text-dark">${item.FamilyName}</span></td>
                                <td><span class="badge bg-secondary">${item.StatusName || 'N/A'}</span></td>
                                <td>${inventoryKG.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <!-- [!!!] ستون جدید برنامه امروز -->
                                <td>${plannedTodayKG.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        `;
                        
                        if (mode !== 'plating') {
                             // [!!!] محاسبه موجودی واقعی در دسترس
                            const availableForPlanning = Math.max(0, inventoryKG - plannedTodayKG);
                            rowHtml += `
                                <td>
                                    <input type="number" class="form-control form-control-sm planned-qty-input" min="0" step="0.01" max="${availableForPlanning.toFixed(2)}" placeholder="موجود: ${availableForPlanning.toFixed(2)}">
                                </td>
                            `;
                        }
                        
                        rowHtml += `</tr>`;
                        $tbody.append(rowHtml);
                    });
                } else {
                    $tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-muted p-3">هیچ قطعه نیمه‌ساخته‌ای برای ${mode} یافت نشد.</td></tr>`);
                }
            },
            error: function(xhr) {
                // [!!!] FIX: Changed '.' to '+' for JS string concatenation
                showGeneralAlert('capacity-alert-container', 'خطا در بارگذاری قطعات: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
                $tbody.html(`<tr><td colspan="${colSpan}" class="text-center text-danger p-3">خطا در بارگذاری.</td></tr>`);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="bi bi-box-seam me-2"></i> بارگذاری مجدد');
            }
        });
    }

    $('#load-plating-parts-btn').on('click', function() {
        loadParts('plating', 'plating-parts-tbody');
        // Reset subsequent steps
        $('#batching-container').slideUp();
        $('#plating-planning-container').slideUp();
        batches = {};
        selectedPartIds = [];
        updateBatchesListUI();
    });

    $('#load-washing-parts-btn').on('click', function() {
        loadParts('washing', 'washing-parts-tbody');
    });
    
    $('#load-rework-parts-btn').on('click', function() {
        loadParts('rework', 'rework-parts-tbody');
    });

    // =================================================================
    // Plating Step 1: Part Selection
    // =================================================================
    
    $(document).on('change', '#select-all-plating-parts', function() {
        $('.plating-part-select').prop('checked', $(this).prop('checked')).trigger('change');
    });

    $(document).on('change', '.plating-part-select, #select-all-plating-parts', function() {
        selectedPartIds = $('.plating-part-select:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedPartIds.length > 0) {
            fetchBatchRules();
            updateSelectedPartsListUI();
            $('#batching-container').slideDown();
        } else {
            $('#batching-container').slideUp();
            $('#plating-planning-container').slideUp();
        }
    });
    
    function updateSelectedPartsListUI() {
        const $list = $('#selected-parts-list');
        $list.empty();
        if (selectedPartIds.length === 0) {
            $list.html('<li class="list-group-item">هیچ قطعه‌ای انتخاب نشده.</li>');
            return;
        }
        $.each(selectedPartIds, function(i, partId) {
            const part = getPartById(partId, 'plating');
            if (part) {
                $list.append(`<li class="list-group-item" data-part-id="${partId}">${part.PartName}</li>`);
            }
        });
    }

    // =================================================================
    // Plating Step 2: Batching Logic
    // =================================================================

    function fetchBatchRules() {
        // Fetch compatibility rules for the selected parts
        $.ajax({
            url: API.GET_BATCH_RULES,
            type: 'POST',
            data: JSON.stringify({ part_ids: selectedPartIds }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    batchRules = response.data;
                } else {
                    showGeneralAlert('capacity-alert-container', 'خطا در دریافت قوانین بچینگ: ' + response.message);
                }
            },
            error: function(xhr) {
                 showGeneralAlert('capacity-alert-container', 'خطای سیستمی در دریافت قوانین بچینگ.');
            }
        });
    }

    $('#add-batch-btn').on('click', function() {
        batchCounter++;
        const newBatchId = `batch_${batchCounter}`;
        batches[newBatchId] = {
            id: newBatchId,
            name: `بچ شماره ${batchCounter}`,
            parts: [], // { part_id: 123, weight: 50, current_status_id: ... }
            planned_barrels: 0 // [!!!] تعداد بارل در خود بچ ذخیره می‌شود
        };
        openBatchModal(newBatchId);
    });
    
    $(document).on('click', '.edit-batch-btn', function() {
        const batchId = $(this).data('batch-id');
        openBatchModal(batchId);
    });
    
     $(document).on('click', '.delete-batch-btn', function() {
        const batchId = $(this).data('batch-id');
        if (confirm(`آیا از حذف ${batches[batchId].name} مطمئن هستید؟`)) {
            delete batches[batchId];
            updateBatchesListUI();
            updatePlatingPlanningUI(); // [!!!] بروزرسانی گام ۳
        }
    });

    function openBatchModal(batchId) {
        const batch = batches[batchId];
        $('#editing-batch-id').val(batchId);
        $('#batchModalLabel').text(`ویرایش ${batch.name}`);
        $('#modal-batch-alert-container').empty();
        
        const $availableList = $('#modal-available-parts');
        $availableList.empty();
        
        const $batchList = $('#modal-batch-parts');
        $batchList.empty();
        
        const batchPartIds = batch.parts.map(p => p.part_id);

        // Populate available parts list (those selected in step 1 but NOT in this batch)
        $.each(selectedPartIds, function(i, partId) {
            if (!batchPartIds.includes(partId)) {
                const part = getPartById(partId, 'plating');
                $availableList.append(`
                    <a href="#" class="list-group-item list-group-item-action modal-part-item" data-part-id="${partId}">
                        <i class="bi bi-plus-circle text-success me-2"></i> ${part.PartName}
                    </a>
                `);
            }
        });
        
        // Populate parts currently in this batch
        $.each(batch.parts, function(i, partInBatch) {
            const part = getPartById(partInBatch.part_id, 'plating');
            $batchList.append(`
                <a href="#" class="list-group-item list-group-item-action modal-part-item" data-part-id="${partInBatch.part_id}">
                    <i class="bi bi-dash-circle text-danger me-2"></i> ${part.PartName}
                </a>
            `);
        });

        updateBatchWeightUI(batchId);
        $('#batchModal').modal('show');
    }
    
    // Move part between "Available" and "In Batch" lists in modal
    $(document).on('click', '.modal-part-item', function(e) {
        e.preventDefault();
        const batchId = $('#editing-batch-id').val();
        const batch = batches[batchId];
        const partId = $(this).data('part-id');
        const $item = $(this);
        const $sourceList = $item.parent();
        const $destList = $sourceList.attr('id') === 'modal-available-parts' ? $('#modal-batch-parts') : $('#modal-available-parts');
        
        // Check for compatibility before adding
        if ($destList.attr('id') === 'modal-batch-parts') {
            // Check compatibility with all parts already in the batch
            for (const partInBatch of batch.parts) {
                const compatRule = findBatchCompatibility(partId, partInBatch.part_id);
                if (!compatRule) {
                    const partA = getPartById(partId, 'plating');
                    const partB = getPartById(partInBatch.part_id, 'plating');
                    showGeneralAlert('modal-batch-alert-container', `<b>خطای سازگاری:</b> قطعه <b>${partA.PartName}</b> طبق قوانین بچینگ، نمی‌تواند با <b>${partB.PartName}</b> در یک بارل قرار گیرد.`);
                    return; // Stop adding
                }
            }
            
            // Add to batch object
            const partData = getPartById(partId, 'plating'); // [FIX] Get full part data
            batch.parts.push({ 
                part_id: partId, 
                weight: 0, 
                current_status_id: partData.CurrentStatusID // [FIX] Store status ID
            });
        
        } else {
            // Remove from batch object
            batch.parts = batch.parts.filter(p => p.part_id != partId);
        }
        
        // Move UI element
        $item.find('i').toggleClass('bi-plus-circle text-success bi-dash-circle text-danger');
        $destList.append($item);
        
        // Update weight inputs
        updateBatchWeightUI(batchId);
    });

    function updateBatchWeightUI(batchId) {
        const batch = batches[batchId];
        const $container = $('#modal-batch-weights');
        $container.empty();
        
        if (batch.parts.length === 0) {
            $container.html('<p class="text-muted">قطعه‌ای در بچ وجود ندارد.</p>');
            return;
        }
        
        if (batch.parts.length === 1) {
            // Solo part
            const part = batch.parts[0];
            const partDetail = getBatchRuleDetail(part.part_id);
            const soloWeight = part.weight > 0 ? part.weight : (partDetail.BarrelWeight_Solo_KG || 50); // Use saved weight or default
            part.weight = soloWeight; // Update batch object
            
            $container.append(`
                <div class="col-12">
                    <label class="form-label">${partDetail.PartName} (به تنهایی)</label>
                    <div class="input-group">
                        <input type="number" class="form-control batch-weight-input" data-part-id="${part.part_id}" value="${soloWeight}" min="0" step="0.5">
                        <span class="input-group-text">KG</span>
                    </div>
                    <small class="text-muted">وزن پیشنهادی سیستم: ${partDetail.BarrelWeight_Solo_KG || 50} KG</small>
                </div>
            `);
        } else {
            // Combined parts
            // Find the compatibility rule for the *first two* parts (assuming rules are pairwise)
            const part1_id = batch.parts[0].part_id;
            const part2_id = batch.parts[1].part_id;
            const rule = findBatchCompatibility(part1_id, part2_id);
            
            let part1_weight, part2_weight, part1_name, part2_name;
            let part1_suggested, part2_suggested;
            
            if (rule) {
                // [rule[0], rule[1], weight_for_0, weight_for_1]
                part1_name = getBatchRuleDetail(part1_id).PartName;
                part2_name = getBatchRuleDetail(part2_id).PartName;

                if (rule[0] == part1_id) { // Rule matches part 1
                    part1_suggested = rule[2] || 25; // Default 25
                    part2_suggested = rule[3] || 25; // Default 25
                } else { // Rule matches part 2
                    part1_suggested = rule[3] || 25;
                    part2_suggested = rule[2] || 25;
                }
                
                // Use saved weight or default from rule
                part1_weight = batch.parts[0].weight > 0 ? batch.parts[0].weight : part1_suggested;
                part2_weight = batch.parts[1].weight > 0 ? batch.parts[1].weight : part2_suggested;
                
                // Update batch object
                batch.parts[0].weight = part1_weight;
                batch.parts[1].weight = part2_weight;

            } else {
                // Should not happen if check passed, but as a fallback
                showGeneralAlert('modal-batch-alert-container', 'خطای داخلی: قانون سازگاری یافت نشد.');
                return;
            }
            
            $container.append(`
                <div class="col-md-6">
                    <label class="form-label">${part1_name}</label>
                    <div class="input-group">
                        <input type="number" class="form-control batch-weight-input" data-part-id="${part1_id}" value="${part1_weight}" min="0" step="0.5">
                        <span class="input-group-text">KG</span>
                    </div>
                    <small class="text-muted">پیشنهادی: ${part1_suggested} KG</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">${part2_name}</label>
                    <div class="input-group">
                        <input type="number" class="form-control batch-weight-input" data-part-id="${part2_id}" value="${part2_weight}" min="0" step="0.5">
                        <span class="input-group-text">KG</span>
                    </div>
                    <small class="text-muted">پیشنهادی: ${part2_suggested} KG</small>
                </div>
            `);
        }
    }
    
    // Update batch object when weight is changed in modal
    $(document).on('change', '.batch-weight-input', function() {
        const batchId = $('#editing-batch-id').val();
        const partId = $(this).data('part-id');
        const newWeight = parseFloat($(this).val());
        
        const partInBatch = batches[batchId].parts.find(p => p.part_id == partId);
        if (partInBatch) {
            partInBatch.weight = newWeight;
        }
    });

    $('#save-batch-btn').on('click', function() {
        // Validation already happened when adding.
        // Weights are saved on-the-fly.
        updateBatchesListUI();
        updatePlatingPlanningUI(); // [!!!] بروزرسانی گام ۳
        $('#batchModal').modal('hide');
    });

    function updateBatchesListUI() {
        const $container = $('#batches-list-container');
        $container.empty();
        
        if (Object.keys(batches).length === 0) {
            $container.html('<p class="text-center text-muted" id="no-batch-placeholder">هنوز بچی ایجاد نشده است...</p>');
            $('#plating-planning-container').slideUp(); // [!!!] مخفی کردن گام ۳ اگر بچی نیست
            return;
        }
        
        $.each(batches, function(batchId, batch) {
            let partNames = batch.parts.map(p => {
                return getBatchRuleDetail(p.part_id).PartName + ` <small>(${p.weight}kg)</small>`;
            }).join(' + ');
            
            if (batch.parts.length === 0) {
                partNames = "<em class='text-muted'>خالی (جهت ویرایش کلیک کنید)</em>";
            }
            
            // [!!!] اضافه کردن فیلد تعداد بارل
            $container.append(`
                <div class="card mb-2" id="batch-card-${batchId}">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>${batch.name}</strong>
                                <div class="text-muted small">${partNames}</div>
                            </div>
                            <div>
                                <button class="btn btn-warning btn-sm edit-batch-btn py-0 px-1" data-batch-id="${batchId}" title="ویرایش بچ">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-danger btn-sm delete-batch-btn py-0 px-1" data-batch-id="${batchId}" title="حذف بچ">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </div>
                        <!-- [!!!] فیلد تعداد بارل -->
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control planned-barrels-input" min="0" step="1" placeholder="تعداد بارل..." data-batch-id="${batchId}" value="${batch.planned_barrels > 0 ? batch.planned_barrels : ''}">
                            <span class="input-group-text">بارل</span>
                        </div>
                    </div>
                </div>
            `);
        });
        
        $('#plating-planning-container').slideDown(); // [!!!] نمایش گام ۳
    }

    // =================================================================
    // Plating Step 3: Capacity Calculation & Planning
    // =================================================================

    $('#calculate-capacity-btn').on('click', function() {
        const manHours = parseFloat($('#plating-man-hours').val());
        if (isNaN(manHours) || manHours <= 0) {
            showGeneralAlert('capacity-alert-container', 'لطفاً نفر-ساعت معتبر (بیشتر از صفر) وارد کنید.');
            return;
        }
        
        const $this = $(this);
        $this.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        
        $.ajax({
            url: API.GET_CAPACITY,
            type: 'GET',
            data: { man_hours: manHours },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    totalCapacity = parseFloat(response.data.total_capacity_barrels);
                    showGeneralAlert('capacity-alert-container', `<b>ظرفیت محاسبه شد:</b> بر اساس ${manHours} نفر-ساعت، شما می‌توانید <b>${totalCapacity} بارل</b> آبکاری برنامه‌ریزی کنید.`, 'success');
                    $('#total-capacity-display').text(`${totalCapacity} بارل`);
                    $('#capacity-display-wrapper').show(); // [!!!] نمایش ظرفیت
                    updateAssignedCapacity(); // [!!!] بروزرسانی رنگ
                } else {
                    showGeneralAlert('capacity-alert-container', 'خطا در محاسبه ظرفیت: ' + response.message);
                }
            },
            error: function(xhr) {
                showGeneralAlert('capacity-alert-container', 'خطای سیستمی در محاسبه ظرفیت.');
            },
            complete: function() {
                $this.prop('disabled', false).html('<i class="bi bi-calculator me-2"></i> محاسبه ظرفیت کل (اختیاری)');
            }
        });
    });

    // [!!!] بروزرسانی در لحظه تعداد بارل و مصرف موجودی
    $(document).on('input', '.planned-barrels-input', function() {
        const batchId = $(this).data('batch-id');
        const barrels = parseFloat($(this).val()) || 0;
        if (batches[batchId]) {
            batches[batchId].planned_barrels = barrels;
        }
        updatePlatingPlanningUI();
    });

    // [!!!] این تابع اکنون هم موجودی و هم ظرفیت را بروز می‌کند
    function updatePlatingPlanningUI() {
        updateAssignedCapacity();
        updateInventorySummary();
        checkVibrationWarnings();
    }
    
    function calculateInventorySummary() {
        const inventorySummary = {};
        
        // 1. Initialize with all selected parts
        $.each(selectedPartIds, function(i, partId) {
            const part = getPartById(partId, 'plating');
            if (part) {
                inventorySummary[partId] = {
                    part: part,
                    totalPlanned: 0
                };
            }
        });

        // 2. Calculate planned consumption from all batches
        $.each(batches, function(batchId, batch) {
            const barrels = batch.planned_barrels;
            if (barrels > 0) {
                $.each(batch.parts, function(i, partInBatch) {
                    if (inventorySummary[partInBatch.part_id]) {
                        inventorySummary[partInBatch.part_id].totalPlanned += partInBatch.weight * barrels;
                    }
                });
            }
        });
        return inventorySummary;
    }

    function updateInventorySummary() {
        const $tbody = $('#plating-inventory-tbody');
        $tbody.empty();
        const inventorySummary = calculateInventorySummary();

        if (Object.keys(inventorySummary).length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center text-muted">قطعه‌ای برای بررسی موجودی انتخاب نشده است.</td></tr>');
            return;
        }

        $.each(inventorySummary, function(partId, summary) {
            const part = summary.part;
            const previouslyPlanned = parseFloat(part.PlannedTodayKG || 0); // [!!!]
            const availableInventory = part.TotalWeightKG - previouslyPlanned; // [!!!]
            const remainingInventory = availableInventory - summary.totalPlanned; // [!!!]
            
            const isOver = remainingInventory < -0.001; // [!!!] FIX: Add float tolerance
            
            const rowHtml = `
                <tr class="${isOver ? 'table-danger' : ''}">
                    <td>
                        ${part.PartName}
                        <i class="bi bi-info-circle-fill text-muted ms-1" data-bs-toggle="tooltip" 
                           title="موجودی کل: ${part.TotalWeightKG.toFixed(2)} KG&#013;برنامه قبلی: ${previouslyPlanned.toFixed(2)} KG"></i>
                    </td>
                    <td>${part.TotalWeightKG.toFixed(2)}</td>
                    <td>${previouslyPlanned.toFixed(2)}</td>
                    <td class="fw-bold">${availableInventory.toFixed(2)}</td>
                    <td>${summary.totalPlanned.toFixed(2)}</td>
                    <td class="fw-bold ${isOver ? 'text-danger' : 'text-success'}">
                        ${remainingInventory.toFixed(2)}
                        ${isOver ? '<i class="bi bi-exclamation-triangle-fill ms-1"></i>' : ''}
                    </td>
                </tr>
            `;
            $tbody.append(rowHtml);
        });
        
        // Re-initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip({
            html: true,
            placement: 'top'
        });
    }

    function checkVibrationWarnings() {
        const $vibrationWarning = $('#vibration-warning-container');
        $vibrationWarning.empty();
        let hasVibrationWarning = false;

        $.each(batches, function(batchId, batch) {
            if (batch.parts.length > 1) {
                if (findVibrationIncompatibility(batch.parts[0].part_id, batch.parts[1].part_id)) {
                    hasVibrationWarning = true;
                    $(`#batch-card-${batchId}`).addClass('border-danger');
                } else {
                     $(`#batch-card-${batchId}`).removeClass('border-danger');
                }
            }
        });
        
        if (hasVibrationWarning) {
            showGeneralAlert('vibration-warning-container', '<b>هشدار ویبره:</b> بچ‌های مشخص شده (کادر قرمز) شامل قطعات ناسازگار هستند. لطفاً بررسی کنید.', 'warning');
        }
    }

    function updateAssignedCapacity() {
        let assigned = 0;
        $.each(batches, function(batchId, batch) {
            assigned += batch.planned_barrels;
        });
        
        const $display = $('#assigned-capacity-display');
        $display.text(`${assigned} بارل`).removeClass('text-success text-danger text-warning');
        
        if (totalCapacity > 0) { // فقط در صورتی رنگی کن که ظرفیت محاسبه شده باشد
            if (assigned > totalCapacity) {
                $display.addClass('text-danger');
            } else {
                $display.addClass('text-success');
            }
        } else {
             $display.addClass('text-warning'); // رنگ هشدار (زرد) اگر محاسبه نشده
        }
    }
    
    // =================================================================
    // Save Logic (All Tabs)
    // =================================================================
    
    function getCommonData() {
        const planningDate = $('#planning-date').val();
        if (!planningDate) {
            alert('لطفاً تاریخ اجرای برنامه را انتخاب کنید.');
            $('#planning-date').addClass('is-invalid');
            return null;
        }
        $('#planning-date').removeClass('is-invalid');
        return { planning_date_jalali: planningDate };
    }

    // --- Save Plating ---
    $('#save-plating-plan-btn').on('click', function() {
        const commonData = getCommonData();
        if (!commonData) return;
        
        let assigned_barrels = 0;
        const finalBatches = [];
        let hasEmptyBatch = false;
        let inventoryCheckFailed = false;

        const inventorySummary = calculateInventorySummary();
        $.each(inventorySummary, function(partId, summary) {
             const part = summary.part;
             const previouslyPlanned = parseFloat(part.PlannedTodayKG || 0);
             const availableInventory = part.TotalWeightKG - previouslyPlanned;
             const remainingInventory = availableInventory - summary.totalPlanned;
             if (remainingInventory < -0.001) { // [!!!] Float tolerance
                 inventoryCheckFailed = true;
             }
        });

        if (inventoryCheckFailed) {
            if (!confirm('هشدار: برنامه‌ریزی شما از موجودی در دسترس برخی قطعات بیشتر است (ردیف‌های قرمز). آیا مایل به ادامه هستید؟')) {
                return;
            }
        }

        $.each(batches, function(batchId, batch) {
            if (batch.planned_barrels > 0) {
                if (batch.parts.length === 0) {
                    hasEmptyBatch = true;
                    return; // break loop
                }
                assigned_barrels += batch.planned_barrels;
                finalBatches.push({
                    batch_details: batch, // حاوی نام، قطعات و وزن‌ها
                    planned_barrels: batch.planned_barrels
                });
            }
        });

        if (hasEmptyBatch) {
            alert('خطا: شما برای یک بچ خالی (بدون قطعه) تعداد بارل مشخص کرده‌اید. لطفاً آن را اصلاح یا حذف کنید.');
            return;
        }

        if (finalBatches.length === 0) {
            alert('هیچ بارلی برای برنامه‌ریزی تخصیص داده نشده است.');
            return;
        }
        
        if (totalCapacity > 0 && assigned_barrels > totalCapacity) {
            if (!confirm(`تعداد بارل تخصیص یافته (${assigned_barrels}) از ظرفیت کل (${totalCapacity}) بیشتر است. آیا مایل به ادامه هستید؟`)) {
                return;
            }
        }
        
        const payload = {
            ...commonData,
            station_id: 4, // Plating Station
            batches: finalBatches
        };
        
        savePlan(payload, $(this));
    });

    // --- Save Washing ---
    $('#save-washing-plan-btn').on('click', function() {
        const commonData = getCommonData();
        if (!commonData) return;
        
        const partsToPlan = [];
        let validationFailed = false;
        $('#washing-parts-tbody tr').each(function() {
            const $row = $(this);
            const $input = $row.find('.planned-qty-input');
            const partId = $row.data('part-id');
            const statusId = $row.data('status-id');
            const quantity = parseFloat($input.val());
            const max = parseFloat($input.attr('max'));
            
            if (quantity > 0) {
                if (quantity > (max + 0.001)) { // [!!!] Float tolerance
                    $input.addClass('is-invalid');
                    validationFailed = true;
                } else {
                    $input.removeClass('is-invalid');
                    partsToPlan.push({
                        part_id: partId,
                        quantity: quantity,
                        current_status_id: statusId
                    });
                }
            }
        });
        
        if (validationFailed) {
            alert('خطا: مقدار برنامه‌ریزی شده از موجودی در دسترس بیشتر است. (ردیف‌های قرمز)');
            return;
        }
        
        if (partsToPlan.length === 0) {
            alert('هیچ مقداری برای شستشو وارد نشده است.');
            return;
        }
        
        const payload = {
            ...commonData,
            station_id: 1, // Washing Station
            parts: partsToPlan
        };
        
        savePlan(payload, $(this));
    });

    // --- Save Rework ---
    $('#save-rework-plan-btn').on('click', function() {
        const commonData = getCommonData();
        if (!commonData) return;
        
        const partsToPlan = [];
        let validationFailed = false;
        $('#rework-parts-tbody tr').each(function() {
            const $row = $(this);
            const $input = $row.find('.planned-qty-input');
            const partId = $row.data('part-id');
            const statusId = $row.data('status-id');
            const quantity = parseFloat($input.val());
            const max = parseFloat($input.attr('max'));

            if (quantity > 0) {
                 if (quantity > (max + 0.001)) { // [!!!] Float tolerance
                    $input.addClass('is-invalid');
                    validationFailed = true;
                } else {
                    $input.removeClass('is-invalid');
                    partsToPlan.push({
                        part_id: partId,
                        quantity: quantity,
                        current_status_id: statusId
                    });
                }
            }
        });
        
        if (validationFailed) {
            alert('خطا: مقدار برنامه‌ریزی شده از موجودی در دسترس بیشتر است. (ردیف‌های قرمز)');
            return;
        }
        
        if (partsToPlan.length === 0) {
            alert('هیچ مقداری برای دوباره‌کاری وارد نشده است.');
            return;
        }
        
        const payload = {
            ...commonData,
            station_id: 7, // Rework Station
            parts: partsToPlan
        };
        
        savePlan(payload, $(this));
    });


    function savePlan(payload, $button) {
        const btnOriginalText = $button.html();
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>در حال ذخیره...');
        
        $.ajax({
            url: API.SAVE_PLAN,
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload(); // Reload to see history
                } else {
                    alert('خطا در ذخیره: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('خطای سیستمی: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'پاسخ نامعتبر.'));
            },
            complete: function() {
                $button.prop('disabled', false).html(btnOriginalText);
            }
        });
    }

    // [!!!] Initialize tooltips on load
    $('[data-bs-toggle="tooltip"]').tooltip({
        html: true,
        placement: 'top'
    });
});
</script>

