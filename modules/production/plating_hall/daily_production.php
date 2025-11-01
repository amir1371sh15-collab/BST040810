<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.plating_hall.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// Fetch employees
$plating_dept_id = 2; // Department ID for Plating
$plating_staff = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees WHERE DepartmentID = ? ORDER BY name", [$plating_dept_id]);
$all_staff = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$part_families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
// Fetch chemicals of type 'Plating Additives'
$additive_type_id = find_one_by_field($pdo, 'tbl_chemical_types', 'TypeName', 'افزودنی های وان آبکاری')['ChemicalTypeID'] ?? 0;
$plating_chemicals_raw = find_all($pdo, "SELECT c.ChemicalID, c.ChemicalName, u.Symbol as DefaultUnit FROM tbl_chemicals c LEFT JOIN tbl_units u ON c.UnitID = u.UnitID WHERE c.ChemicalTypeID = ? ORDER BY c.ChemicalName", [$additive_type_id]);
// Fetch active vats
$active_vats = find_all($pdo, "SELECT VatID, VatName FROM tbl_plating_vats WHERE IsActive = 1 ORDER BY VatName");

// Re-index chemicals array for easier JS/PHP access
$plating_chemicals = [];
$chemical_units_map = []; // Map for PHP side
foreach($plating_chemicals_raw as $chem) {
    $plating_chemicals[$chem['ChemicalID']] = $chem;
    $chemical_units_map[$chem['ChemicalID']] = $chem['DefaultUnit'] ?? 'N/A';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Deletion first if delete_header_id is set
    if (isset($_POST['delete_header_id'])) {
         if (!has_permission('production.plating_hall.manage')) {
            $_SESSION['message'] = 'شما مجوز حذف را ندارید.';
            $_SESSION['message_type'] = 'danger';
        } else {
            $delete_id = (int)$_POST['delete_header_id'];
            $result = delete_record($pdo, 'tbl_plating_log_header', $delete_id, 'PlatingHeaderID');
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        header("Location: daily_production.php");
        exit;
    }

    // --- Handle New Record Submission ---
    $pdo->beginTransaction();
    try {
        // 1. Insert Header
        $header_data = [
            'LogDate' => to_gregorian($_POST['log_date']),
            'NumberOfBarrels' => !empty($_POST['number_of_barrels']) ? (int)$_POST['number_of_barrels'] : null,
            'Description' => trim($_POST['description'])
        ];
        $header_res = insert_record($pdo, 'tbl_plating_log_header', $header_data);
        if (!$header_res['success']) throw new Exception("خطا در ثبت هدر گزارش آبکاری.");
        $header_id = $header_res['id'];

        // 2. Insert Shifts
        $shift_stmt = $pdo->prepare("INSERT INTO tbl_plating_log_shifts (PlatingHeaderID, EmployeeID, StartTime, EndTime) VALUES (?, ?, ?, ?)");
        foreach ($_POST['shifts'] ?? [] as $shift) {
            if (!empty($shift['employee_id'])) {
                $start_time = !empty($shift['start_time']) ? $shift['start_time'] . ':00' : null;
                $end_time = !empty($shift['end_time']) ? $shift['end_time'] . ':00' : null;
                $shift_stmt->execute([$header_id, (int)$shift['employee_id'], $start_time, $end_time]);
            }
        }
        if (!empty($_POST['shift_optional']['employee_id'])) {
             $start_time_opt = !empty($_POST['shift_optional']['start_time']) ? $_POST['shift_optional']['start_time'] . ':00' : null;
             $end_time_opt = !empty($_POST['shift_optional']['end_time']) ? $_POST['shift_optional']['end_time'] . ':00' : null;
             $shift_stmt->execute([$header_id, (int)$_POST['shift_optional']['employee_id'], $start_time_opt, $end_time_opt]);
        }

        // 3. Insert Production Details
        $details_stmt = $pdo->prepare("INSERT INTO tbl_plating_log_details (PlatingHeaderID, PartID, WashedKG, PlatedKG, ReworkedKG) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['parts'] ?? [] as $part_id => $data) {
             if (isset($data['selected'])) {
                $details_stmt->execute([
                    $header_id, (int)$part_id,
                    (float)($data['washed'] ?? 0), (float)($data['plated'] ?? 0), (float)($data['reworked'] ?? 0)
                ]);
             }
        }

        // 4. Insert Chemical Additions (Revised: Uses VatID)
        $addition_stmt = $pdo->prepare("INSERT INTO tbl_plating_log_additions (PlatingHeaderID, VatID, ChemicalID, Quantity, Unit) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['additions'] ?? [] as $addition) {
             if (!empty($addition['vat_id']) && !empty($addition['chemical_id']) && isset($addition['quantity']) && $addition['quantity'] !== '') {
                 $chemical_id = (int)$addition['chemical_id'];
                 $unit = $chemical_units_map[$chemical_id] ?? 'N/A'; // Get unit from base info map
                 $vat_id = (int)$addition['vat_id']; // Get VatID from dropdown
                 $addition_stmt->execute([
                     $header_id,
                     $vat_id, // Store VatID
                     $chemical_id,
                     (float)$addition['quantity'],
                     $unit
                 ]);
             }
        }

        $pdo->commit();
        $_SESSION['message'] = 'آمار آبکاری با موفقیت ثبت شد.';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Plating log submission error: " . $e->getMessage() . " | Data: " . print_r($_POST, true));
        $_SESSION['message'] = 'خطا در ثبت آمار: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: daily_production.php");
    exit;
}

// --- History Section ---
const RECORDS_PER_PAGE = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records_q = $pdo->query("SELECT COUNT(DISTINCT h.PlatingHeaderID) FROM tbl_plating_log_header h");
$total_records = $total_records_q ? $total_records_q->fetchColumn() : 0;
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

$history_logs = find_all($pdo, "
    SELECT
        h.PlatingHeaderID, h.LogDate, h.NumberOfBarrels,
        COALESCE((SELECT SUM(d.WashedKG + d.PlatedKG + d.ReworkedKG) FROM tbl_plating_log_details d WHERE d.PlatingHeaderID = h.PlatingHeaderID), 0) as TotalKG,
        (SELECT COUNT(s.ShiftID) FROM tbl_plating_log_shifts s WHERE s.PlatingHeaderID = h.PlatingHeaderID) as StaffCount
    FROM tbl_plating_log_header h
    ORDER BY h.LogDate DESC, h.PlatingHeaderID DESC
    LIMIT :limit OFFSET :offset
", [':limit' => RECORDS_PER_PAGE, ':offset' => $offset]);


$pageTitle = "ثبت تولید روزانه آبکاری";
include __DIR__ . '/../../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت تولید روزانه آبکاری</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<form method="POST">
    <!-- Section 1: Date & Personnel -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۱. تاریخ و پرسنل</h5></div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">تاریخ</label>
                    <input type="text" name="log_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required>
                </div>
            </div>
            <h6>پرسنل شیفت</h6>
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="row align-items-center mb-2">
                <div class="col-md-5">
                    <label class="form-label small">نفر <?php echo $i + 1; ?> (آبکاری)</label>
                    <select name="shifts[<?php echo $i; ?>][employee_id]" class="form-select form-select-sm" <?php echo $i == 0 ? 'required' : ''; ?>>
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($plating_staff as $staff): ?>
                            <option value="<?php echo $staff['EmployeeID']; ?>"><?php echo htmlspecialchars($staff['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">ساعت شروع</label>
                    <input type="time" name="shifts[<?php echo $i; ?>][start_time]" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">ساعت پایان</label>
                    <input type="time" name="shifts[<?php echo $i; ?>][end_time]" class="form-control form-control-sm">
                </div>
            </div>
            <?php endfor; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="add-optional-staff-btn">+ افزودن نفر چهارم (اختیاری)</button>
            <div id="optional-staff-row" class="row align-items-center mt-2" style="display: none;">
                <div class="col-md-5">
                    <label class="form-label small">نفر ۴ (همه)</label>
                    <select name="shift_optional[employee_id]" class="form-select form-select-sm">
                        <option value="">-- انتخاب کنید --</option>
                         <?php foreach ($all_staff as $staff): ?>
                            <option value="<?php echo $staff['EmployeeID']; ?>"><?php echo htmlspecialchars($staff['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">ساعت شروع</label>
                    <input type="time" name="shift_optional[start_time]" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">ساعت پایان</label>
                    <input type="time" name="shift_optional[end_time]" class="form-control form-control-sm">
                </div>
                 <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger" id="remove-optional-staff-btn">&times;</button>
                 </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Product Selection -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۲. انتخاب محصولات تولیدی</h5></div>
        <div class="card-body">
             <div class="row">
                <div class="col-md-5">
                    <h6>خانواده محصول</h6>
                    <div id="family-checklist" class="list-group">
                        <?php foreach ($part_families as $family): ?>
                            <label class="list-group-item list-group-item-action">
                                <input class="form-check-input me-1 family-checkbox" type="checkbox" value="<?php echo $family['FamilyID']; ?>">
                                <?php echo htmlspecialchars($family['FamilyName']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-7">
                     <h6>محصولات</h6>
                     <div id="part-checklist" class="border rounded p-2" style="height: 250px; overflow-y: auto;">
                        <small class="text-muted">ابتدا یک یا چند خانواده را انتخاب کنید.</small>
                     </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Production Data Table -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۳. ثبت آمار تولید</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead><tr><th>قطعه</th><th>شستشو (KG)</th><th>آبکاری (KG)</th><th>دوباره کاری (KG)</th></tr></thead>
                    <tbody id="production-table-body">
                        <tr id="no-product-selected-row"><td colspan="4" class="text-center text-muted">هنوز محصولی انتخاب نشده است.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td class="text-end">مجموع:</td>
                            <td id="total-washed">0.0 KG</td>
                            <td id="total-plated">0.0 KG</td>
                            <td id="total-reworked">0.0 KG</td>
                        </tr>
                        <tr class="table-primary fw-bold">
                            <td class="text-end">مجموع کل: <span id="total-all" class="ms-2">0.0 KG</span></td>
                            <td colspan="3">
                                <label for="number_of_barrels" class="form-label mb-0 me-2">تعداد بارل:</label>
                                <input type="number" name="number_of_barrels" id="number_of_barrels" class="form-control form-control-sm d-inline-block" style="width: 80px;">
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

     <!-- Section 4: Chemical Additions Table -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۴. ثبت افزودنی های وان آبکاری</h5></div>
        <div class="card-body">
             <div class="row mb-2 text-muted small">
                <div class="col-md-3">نام وان</div>
                <div class="col-md-5">ماده شیمیایی</div>
                <div class="col-md-3">مقدار</div>
                <div class="col-md-1"></div>
            </div>
            <div id="chemical-additions-container">
                {/* Rows added dynamically */}
            </div>
            <button type="button" class="btn btn-outline-success btn-sm mt-2" id="add-chemical-row-btn" style="display: none;">+ افزودن ماده مصرفی</button>
        </div>
    </div>

    <!-- Section 5: Description -->
     <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۵. توضیحات</h5></div>
        <div class="card-body">
            <textarea name="description" class="form-control" rows="3" placeholder="توضیحات یا نکات مربوط به این روز کاری..."></textarea>
        </div>
     </div>


    <div class="text-end mt-4 mb-5">
        <button type="submit" class="btn btn-primary">ثبت نهایی آمار آبکاری</button>
    </div>
</form>

<!-- History Section -->
<div class="card content-card mt-5">
    <div class="card-header"><h5 class="mb-0">تاریخچه ثبت تولید آبکاری</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead><tr><th class="p-3">تاریخ</th><th class="p-3">تعداد پرسنل</th><th class="p-3">تعداد بارل</th><th class="p-3">مجموع تولید (KG)</th><th class="p-3">عملیات</th></tr></thead>
                <tbody>
                    <?php if (empty($history_logs)): ?>
                        <tr><td colspan="5" class="text-center p-3 text-muted">موردی برای نمایش یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach($history_logs as $log): ?>
                        <tr>
                            <td class="p-3"><?php echo to_jalali($log['LogDate']); ?></td>
                            <td class="p-3"><?php echo $log['StaffCount']; ?></td>
                            <td class="p-3"><?php echo $log['NumberOfBarrels'] ?? '-'; ?></td>
                            <td class="p-3"><?php echo number_format($log['TotalKG'], 1); ?></td>
                            <td class="p-3">
                                <button class="btn btn-info btn-sm view-details-btn" data-header-id="<?php echo $log['PlatingHeaderID']; ?>" data-bs-toggle="modal" data-bs-target="#detailsModal" title="مشاهده جزئیات"><i class="bi bi-eye"></i></button>
                                <?php if (has_permission('production.plating_hall.manage')): ?>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $log['PlatingHeaderID']; ?>" title="حذف رکورد روز"><i class="bi bi-trash"></i></button>
                                <div class="modal fade" id="deleteModal<?php echo $log['PlatingHeaderID']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">آیا از حذف کامل گزارش روز <?php echo to_jalali($log['LogDate']); ?> مطمئن هستید؟</div>
                                    <div class="modal-footer">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="delete_header_id" value="<?php echo $log['PlatingHeaderID']; ?>">
                                            <button type="submit" class="btn btn-danger">بله، حذف کن</button>
                                        </form>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                    </div>
                                </div></div></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-center">
        <nav><ul class="pagination mb-0">
            <li class="page-item <?php if($current_page <= 1) echo 'disabled';?>"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">قبلی</a></li>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php if($i == $current_page) echo 'active'; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?php if($current_page >= $total_pages) echo 'disabled';?>"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a></li>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">جزئیات ثبت تولید آبکاری</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailsModalBody"><div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button></div>
</div></div></div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    let selectedParts = {};
    let chemicalRowIndex = 0;
    const initialChemicalRows = 4;
    const platingChemicalsData = <?php echo json_encode($plating_chemicals); ?>;
    const activeVatsData = <?php echo json_encode($active_vats); ?>; // Pass vat data to JS

    // --- Personnel Section ---
    $('#add-optional-staff-btn').on('click', function() { $('#optional-staff-row').show(); $(this).hide(); });
    $('#remove-optional-staff-btn').on('click', function() { $('#optional-staff-row').hide().find('select, input').val(''); $('#add-optional-staff-btn').show(); });

    // --- Product Selection Logic ---
    let loadedParts = {};
    $('.family-checkbox').on('change', function() {
        // ... (product selection logic remains the same) ...
        const familyId = $(this).val();
        const familyName = $(this).parent().text().trim();
        if ($(this).is(':checked')) { /* Load or display parts */ 
            if (loadedParts[familyId]) { displayParts(familyId, familyName, loadedParts[familyId]); } 
            else { 
                $('#part-checklist').append(`<div id="loading-family-${familyId}" class="text-muted small">درحال بارگذاری قطعات ${familyName}...</div>`);
                $.getJSON('<?php echo BASE_URL; ?>api/api_get_parts_by_family.php', { family_id: familyId })
                 .done(function(response) { if (response.success) { loadedParts[familyId] = response.data; displayParts(familyId, familyName, response.data); } else { $('#part-checklist').append(`<div class="text-danger small">خطا در بارگذاری قطعات ${familyName}.</div>`); } })
                 .fail(function() { $('#part-checklist').append(`<div class="text-danger small">خطای شبکه در بارگذاری قطعات ${familyName}.</div>`); })
                 .always(function() { $(`#loading-family-${familyId}`).remove(); checkEmptyPartList(); });
            }
        } 
        else { /* Remove parts */ 
            $(`.part-family-group[data-family-id="${familyId}"]`).remove();
            Object.keys(selectedParts).forEach(partId => { const pC = $(`#part_${partId}`); if (pC.length && pC.data('family-id') == familyId) { delete selectedParts[partId]; removeProductionRow(partId); } });
            checkEmptyPartList();
        }
    });
    function displayParts(familyId, familyName, parts) { /* ... same ... */ 
        let partsHtml = `<fieldset class="part-family-group mb-2 border-top pt-2" data-family-id="${familyId}"><legend class="h6 small text-muted">${familyName}</legend>`;
        if (parts.length > 0) {
            $.each(parts, function(i, part) {
                const isSelected = selectedParts[part.PartID] !== undefined;
                partsHtml += `<div class="form-check form-check-inline"><input class="form-check-input part-checkbox" type="checkbox" value="${part.PartID}" id="part_${part.PartID}" data-part-name="${part.PartName}" data-family-id="${familyId}" ${isSelected ? 'checked' : ''}><label class="form-check-label small" for="part_${part.PartID}">${part.PartName}</label></div>`;
            });
        } else { partsHtml += `<small class="text-muted">قطعه‌ای یافت نشد.</small>`; }
        partsHtml += `</fieldset>`;
        $('#part-checklist').append(partsHtml);
        checkEmptyPartList();
    }
    function checkEmptyPartList(){ /* ... same ... */ 
        if ($('#part-checklist').find('.part-family-group').length === 0) { if($('#part-checklist').find('.text-muted').length === 0){ $('#part-checklist').html('<small class="text-muted">ابتدا یک یا چند خانواده را انتخاب کنید.</small>'); } } else { $('#part-checklist').find('.text-muted').remove(); }
    }
    $(document).on('change', '.part-checkbox', function() { /* ... same ... */ 
        const partId = $(this).val(); const partName = $(this).data('part-name');
        if ($(this).is(':checked')) { selectedParts[partId] = partName; addProductionRow(partId, partName); } else { delete selectedParts[partId]; removeProductionRow(partId); }
    });
    function addProductionRow(partId, partName) { /* ... same ... */ 
         $('#no-product-selected-row').hide();
        const newRow = `<tr id="prod_row_${partId}"><td> ${partName} <input type="hidden" name="parts[${partId}][selected]" value="1"> </td><td><input type="number" step="0.1" name="parts[${partId}][washed]" class="form-control form-control-sm calc-input washed-input"></td><td><input type="number" step="0.1" name="parts[${partId}][plated]" class="form-control form-control-sm calc-input plated-input"></td><td><input type="number" step="0.1" name="parts[${partId}][reworked]" class="form-control form-control-sm calc-input reworked-input"></td></tr>`;
        $('#production-table-body').append(newRow);
    }
    function removeProductionRow(partId) { /* ... same ... */ 
         $(`#prod_row_${partId}`).remove();
        if ($('#production-table-body tr').length === 1 && $('#no-product-selected-row').is(':hidden')) { $('#no-product-selected-row').show(); }
        calculateTotals(); 
    }
    $(document).on('input', '.calc-input', calculateTotals);
    function calculateTotals() { /* ... same ... */ 
        let totalWashed = 0, totalPlated = 0, totalReworked = 0;
        $('.washed-input').each(function() { totalWashed += parseFloat($(this).val()) || 0; });
        $('.plated-input').each(function() { totalPlated += parseFloat($(this).val()) || 0; });
        $('.reworked-input').each(function() { totalReworked += parseFloat($(this).val()) || 0; });
        $('#total-washed').text(totalWashed.toFixed(1) + ' KG'); $('#total-plated').text(totalPlated.toFixed(1) + ' KG'); $('#total-reworked').text(totalReworked.toFixed(1) + ' KG');
        $('#total-all').text((totalWashed + totalPlated + totalReworked).toFixed(1) + ' KG');
    }

    // --- Chemical Addition Logic (Revised) ---
    function addChemicalRow() {
        chemicalRowIndex++;
        const chemicalOptions = Object.values(platingChemicalsData).map(chem =>
            `<option value="${chem.ChemicalID}">${chem.ChemicalName}</option>`
        ).join('');
        const vatOptions = activeVatsData.map(vat =>
            `<option value="${vat.VatID}">${vat.VatName}</option>`
        ).join('');

        const newRow = `
            <div class="row align-items-center mb-2 chemical-addition-row" data-row-index="${chemicalRowIndex}">
                <div class="col-md-3">
                    <select name="additions[${chemicalRowIndex}][vat_id]" class="form-select form-select-sm vat-select">
                        <option value="">-- انتخاب وان --</option>
                        ${vatOptions}
                    </select>
                </div>
                <div class="col-md-5">
                     <select name="additions[${chemicalRowIndex}][chemical_id]" class="form-select form-select-sm chemical-select">
                        <option value="">-- انتخاب ماده --</option>
                        ${chemicalOptions}
                     </select>
                </div>
                <div class="col-md-3">
                     <input type="number" step="0.001" name="additions[${chemicalRowIndex}][quantity]" class="form-control form-control-sm" placeholder="مقدار">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger remove-chemical-row-btn" style="display: none;">&times;</button>
                </div>
            </div>
        `;
        $('#chemical-additions-container').append(newRow);
        updateAddChemicalButtonVisibility();
    }

    $('#add-chemical-row-btn').on('click', addChemicalRow);

    $(document).on('click', '.remove-chemical-row-btn', function() {
        $(this).closest('.chemical-addition-row').remove();
        updateAddChemicalButtonVisibility();
    });

    function updateAddChemicalButtonVisibility() {
        const rowCount = $('.chemical-addition-row').length;
        $('#add-chemical-row-btn').toggle(rowCount >= initialChemicalRows);
        // Hide remove buttons for the first 'initialChemicalRows' rows
        $('.chemical-addition-row').each(function(index) {
            $(this).find('.remove-chemical-row-btn').toggle(index >= initialChemicalRows);
        });
    }

    // Add initial chemical rows
    for(let i = 0; i < initialChemicalRows; i++) { addChemicalRow(); }
    updateAddChemicalButtonVisibility(); // Initial check


    // --- Details Modal Logic ---
    $('.view-details-btn').on('click', function() { /* ... same as before ... */ 
        const headerId = $(this).data('header-id');
        const modalBody = $('#detailsModalBody');
        modalBody.html('<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_plating_details.php', { header_id: headerId }) 
          .done(function(response) {
            if (response.success) {
                const details = response.data;
                let content = `<div class="row"><div class="col-md-6"><h6>اطلاعات کلی</h6>`;
                content += `<p class="mb-1 small"><strong>تاریخ:</strong> ${details.LogDateJalali}</p>`;
                content += `<p class="mb-1 small"><strong>تعداد بارل:</strong> ${details.NumberOfBarrels || '-'}</p>`;
                content += `<h6>پرسنل شیفت:</h6><ul class="list-unstyled mb-3">`;
                if(details.shifts && details.shifts.length > 0){ $.each(details.shifts, function(i, shift){ content += `<li class="small">${shift.EmployeeName} (شروع: ${shift.StartTimeFmt || '-'} پایان: ${shift.EndTimeFmt || '-'})</li>`; }); } else { content += `<li class="small text-muted">ثبت نشده</li>`; }
                content += `</ul>`;
                content += `<h6>توضیحات:</h6><p class="small border p-2 rounded bg-light">${details.Description || '-'}</p>`;
                content += `</div><div class="col-md-6">`;
                content += `<h6>آمار تولید:</h6>`;
                if(details.production && details.production.length > 0){ content += `<table class="table table-sm table-bordered small mb-3"><thead><tr><th>قطعه</th><th>شستشو</th><th>آبکاری</th><th>دوباره کاری</th></tr></thead><tbody>`; $.each(details.production, function(i, prod){ content += `<tr><td>${prod.PartName}</td><td>${prod.WashedKG}</td><td>${prod.PlatedKG}</td><td>${prod.ReworkedKG}</td></tr>`; }); content += `</tbody></table>`; } else { content += `<p class="small text-muted">ثبت نشده</p>`; }
                content += `<h6>مواد افزودنی:</h6>`;
                 if(details.additions && details.additions.length > 0){ content += `<table class="table table-sm table-bordered small"><thead><tr><th>وان</th><th>ماده</th><th>مقدار</th><th>واحد</th></tr></thead><tbody>`; $.each(details.additions, function(i, add){ content += `<tr><td>${add.VatName}</td><td>${add.ChemicalName}</td><td>${add.Quantity}</td><td>${add.Unit}</td></tr>`; }); content += `</tbody></table>`; } else { content += `<p class="small text-muted">ثبت نشده</p>`; }
                content += `</div></div>`; 
                modalBody.html(content);
            } else { modalBody.html(`<div class="alert alert-danger">${response.message || 'خطا در دریافت اطلاعات.'}</div>`); }
          })
          .fail(function() { modalBody.html('<div class="alert alert-danger">خطا در برقراری ارتباط با سرور.</div>'); });
    });

});
</script>

