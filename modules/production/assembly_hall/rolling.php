<?php
require_once __DIR__ . '/../../../config/init.php';

// Permission check
if (!has_permission('production.assembly_hall.manage')) {
    die('شما مجوز ثبت یا ویرایش آمار رول را ندارید.');
}

// Constants
const ENTRIES_TABLE = 'tbl_rolling_log_entries';
const ENTRIES_PK = 'RollingEntryID';
const HEADER_TABLE = 'tbl_rolling_log_header';
const HEADER_PK = 'RollingHeaderID';
const RECORDS_PER_PAGE = 20;

// Session and Persistent Values
$sess_avail_time = $_SESSION['rolling_available_time'] ?? 480;
$sess_description = $_SESSION['rolling_description'] ?? '';
$sess_log_date = $_SESSION['rolling_log_date'] ?? to_jalali(date('Y-m-d'));

$display_available_time = $sess_avail_time;
$display_description = $sess_description;
$initial_log_date_jalali = $sess_log_date;

// Session Messages & Edit Mode Handling
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$editMode = false;
$entryToEdit = null;
$log_date_for_edit = null;

// Preserve date from GET or use session/today
if (isset($_GET['log_date']) && !empty($_GET['log_date'])) {
    $initial_log_date_jalali = $_GET['log_date'];
    $_SESSION['rolling_log_date'] = $initial_log_date_jalali;
}

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry_id'])) {
    $delete_id = (int)$_POST['delete_entry_id'];
    $result = delete_record($pdo, ENTRIES_TABLE, $delete_id, ENTRIES_PK);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    $page = $_GET['page'] ?? 1;
    $redirect_url = "rolling.php?page=" . $page . "&log_date=" . urlencode($_SESSION['rolling_log_date']);
    header("Location: " . $redirect_url);
    exit;
}

// Handle Edit Request
if (isset($_GET['edit_entry_id']) && is_numeric($_GET['edit_entry_id'])) {
    $editMode = true;
    $entryToEdit = find_by_id($pdo, ENTRIES_TABLE, (int)$_GET['edit_entry_id'], ENTRIES_PK);
    if ($entryToEdit) {
        $headerInfo = find_by_id($pdo, HEADER_TABLE, $entryToEdit['RollingHeaderID'], HEADER_PK);
        if ($headerInfo) {
            $log_date_for_edit = $headerInfo['LogDate'];
            $initial_log_date_jalali = to_jalali($log_date_for_edit);
            $_SESSION['rolling_log_date'] = $initial_log_date_jalali;
            $display_available_time = $headerInfo['AvailableTimeMinutes'] ?? $sess_avail_time;
            $display_description = $headerInfo['Description'] ?? $sess_description;
        }
        // Convert ProductionKG to Grams for editing
        $entryToEdit['ProductionGrams'] = round($entryToEdit['ProductionKG'] * 1000, 1);
    } else {
        $editMode = false;
        $_SESSION['message'] = 'رکورد مورد نظر برای ویرایش یافت نشد.';
        $_SESSION['message_type'] = 'warning';
        header("Location: rolling.php");
        exit;
    }
} else {
     $display_available_time = $sess_avail_time;
     $display_description = $sess_description;
}

// --- Data Fetching for Dropdowns ---
$rolling_machines = find_all($pdo, "SELECT MachineID, MachineName FROM tbl_machines WHERE MachineType = 'رول کن' ORDER BY MachineName"); // Filter machines
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
// Parts are fetched via JS API based on machine compatibility

// --- PAGINATION & HISTORY QUERY ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM " . ENTRIES_TABLE)->fetchColumn();
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

$history_sql = "
    SELECT
        e.*,
        h.LogDate,
        m.MachineName,
        p.PartName,
        op.name as OperatorName
    FROM " . ENTRIES_TABLE . " e
    JOIN " . HEADER_TABLE . " h ON e.RollingHeaderID = h.RollingHeaderID
    JOIN tbl_machines m ON e.MachineID = m.MachineID
    JOIN tbl_parts p ON e.PartID = p.PartID
    LEFT JOIN tbl_employees op ON e.OperatorID = op.EmployeeID
    ORDER BY h.LogDate DESC, e.RollingEntryID DESC
    LIMIT :limit OFFSET :offset
";
$history_stmt = $pdo->prepare($history_sql);
$history_stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$history_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$history_stmt->execute();
$history_entries = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Page Setup ---
$pageTitle = $editMode ? "ویرایش آمار رول" : "ثبت آمار رول";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div id="flash-message" class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card content-card mb-4">
    <div class="card-body">
         <div class="row align-items-end">
             <div class="col-md-3 mb-3">
                 <label class="form-label">تاریخ</label>
                 <input type="text" id="log_date_field" class="form-control persian-date persistent-input" data-session-key="rolling_log_date"
                        value="<?php echo $initial_log_date_jalali; ?>" required <?php echo $editMode ? 'readonly' : ''; ?>>
                 <?php if ($editMode): ?>
                    <small class="text-muted">تاریخ برای حالت ویرایش ثابت است.</small>
                    <input type="hidden" id="entry_id_for_update" value="<?php echo $entryToEdit[ENTRIES_PK]; ?>">
                 <?php endif; ?>
             </div>
             <div class="col-md-3 mb-3">
                 <label class="form-label">زمان در دسترس (دقیقه)</label>
                 <input type="number" id="available_time_field" class="form-control persistent-input" data-session-key="rolling_available_time"
                        value="<?php echo htmlspecialchars($display_available_time); ?>">
             </div>
              <div class="col-md-4 mb-3">
                 <label class="form-label">توضیحات روز</label>
                 <textarea id="description_field" class="form-control persistent-input" data-session-key="rolling_description" rows="1"><?php echo htmlspecialchars($display_description); ?></textarea>
             </div>
              <div class="col-md-2 mb-3 text-end">
                   <?php if ($editMode): ?>
                      <button type="button" id="save-edit-btn" class="btn btn-success"><i class="bi bi-check-lg"></i> ذخیره</button>
                      <a href="rolling.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> لغو</a>
                  <?php else: ?>
                     <span class="text-muted me-2 small d-block">برای ثبت ردیف Enter بزنید.</span>
                  <?php endif; ?>
             </div>
        </div>
    </div>
</div>

<?php // Input row container ?>
<div class="card content-card <?php echo $editMode ? 'border-warning' : ''; ?>" id="input-row-card">
    <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش رکورد' : 'ورود اطلاعات'; ?></h5></div>
    <div class="card-body">
         <div class="table-responsive">
             <table class="table table-bordered table-sm align-middle input-table mb-0">
                 <thead>
                    <tr class="text-center small">
                        <th>اپراتور *</th>
                        <th>دستگاه *</th>
                        <th>نام قطعه *</th>
                        <th>تولید (گرم) *</th>
                        <th>شروع</th>
                        <th>پایان</th>
                    </tr>
                </thead>
                <tbody id="rolling-entry-tbody">
                     <tr id="input-row" class="rolling-entry-row">
                          <td>
                            <select id="input_operator_id" class="form-select form-select-sm operator-select" required>
                                <option value="">--</option>
                                <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['EmployeeID']; ?>" <?php echo ($editMode && isset($entryToEdit['OperatorID']) && $entryToEdit['OperatorID'] == $emp['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                             <select id="input_machine_id" class="form-select form-select-sm machine-select" required>
                                  <option value="">--</option>
                                 <?php foreach($rolling_machines as $m): // Use filtered machines ?>
                                 <option value="<?php echo $m['MachineID']; ?>" <?php echo ($editMode && isset($entryToEdit['MachineID']) && $entryToEdit['MachineID'] == $m['MachineID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['MachineName']); ?></option>
                                 <?php endforeach; ?>
                             </select>
                        </td>
                        <td>
                            <select id="input_part_id" class="form-select form-select-sm part-selector" data-selected-part-id="<?php echo $editMode ? ($entryToEdit['PartID'] ?? '') : ''; ?>" required <?php echo !$editMode || !isset($entryToEdit['MachineID']) ? 'disabled' : ''; ?>>
                                 <option value="">-- دستگاه ؟ --</option>
                            </select>
                        </td>
                         <td><input type="number" step="1" id="input_production_grams" class="form-control form-control-sm production-grams-input" placeholder="گرم" value="<?php echo htmlspecialchars($editMode ? ($entryToEdit['ProductionGrams'] ?? '') : ''); ?>" required></td>
                        <td><input type="time" id="input_start_time" class="form-control form-control-sm start-time-input" value="<?php echo $editMode && !empty($entryToEdit['StartTime']) ? date('H:i', strtotime($entryToEdit['StartTime'])) : ''; ?>"></td>
                        <td><input type="time" id="input_end_time" class="form-control form-control-sm end-time-input" value="<?php echo $editMode && !empty($entryToEdit['EndTime']) ? date('H:i', strtotime($entryToEdit['EndTime'])) : ''; ?>"></td>
                   </tr>
                </tbody>
             </table>
        </div>
        <div id="input-row-feedback" class="mt-2 small"></div>
    </div>
</div>

<!-- History Section -->
<div class="card content-card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">تاریخچه ثبت آمار رول (صفحه <?php echo $current_page; ?> از <?php echo $total_pages; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
             <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr class="small">
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">دستگاه</th>
                        <th class="p-2">اپراتور</th>
                        <th class="p-2">زمان</th>
                        <th class="p-2">قطعه</th>
                        <th class="p-2">تولید (گرم)</th>
                        <th class="p-2">عملیات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                     <?php if (empty($history_entries)): ?>
                         <tr><td colspan="7" class="text-center p-3 text-muted">هیچ رکوردی یافت نشد.</td></tr>
                     <?php else: ?>
                         <?php foreach($history_entries as $entry): ?>
                            <?php
                                $startTime = $entry['StartTime'] ? date('H:i', strtotime($entry['StartTime'])) : '-';
                                $endTime = $entry['EndTime'] ? date('H:i', strtotime($entry['EndTime'])) : '-';
                                $productionGrams = round($entry['ProductionKG'] * 1000, 1); // Convert KG to Grams
                            ?>
                            <tr class="small history-row-<?php echo $entry[ENTRIES_PK]; ?>">
                                <td class="p-2"><?php echo to_jalali($entry['LogDate']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['MachineName']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['OperatorName'] ?? '-'); ?></td>
                                <td class="p-2"><?php echo $startTime . ' - ' . $endTime; ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['PartName']); ?></td>
                                <td class="p-2"><?php echo number_format($productionGrams, 1); ?></td>
                                <td class="p-2">
                                     <button type="button" class="btn btn-info btn-sm py-0 px-1 details-btn"
                                             data-header-id="<?php echo $entry['RollingHeaderID']; ?>"
                                             data-log-date-jalali="<?php echo to_jalali($entry['LogDate']); ?>"
                                             data-bs-toggle="modal" data-bs-target="#detailsModal" title="جزئیات روز">
                                         <i class="bi bi-info-circle"></i>
                                     </button>
                                    <a href="?edit_entry_id=<?php echo $entry[ENTRIES_PK]; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm py-0 px-1 delete-btn" data-entry-id="<?php echo $entry[ENTRIES_PK]; ?>" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" title="حذف">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
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
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&log_date=<?php echo urlencode($_SESSION['rolling_log_date']); ?>">قبلی</a>
                </li>
                <?php $start_page = max(1, $current_page - 2); $end_page = min($total_pages, $current_page + 2);
                    if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="?page=1&log_date=' . urlencode($_SESSION['rolling_log_date']) . '">1</a></li>'; if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } }
                    for ($i = $start_page; $i <= $end_page; $i++): ?> <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&log_date=<?php echo urlencode($_SESSION['rolling_log_date']); ?>"><?php echo $i; ?></a></li> <?php endfor;
                     if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&log_date=' . urlencode($_SESSION['rolling_log_date']) . '">' . $total_pages . '</a></li>'; } ?>
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&log_date=<?php echo urlencode($_SESSION['rolling_log_date']); ?>">بعدی</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1"> <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف این رکورد آمار رول مطمئن هستید؟</div>
    <div class="modal-footer">
        <form id="deleteForm" method="POST" action="rolling.php" class="d-inline">
            <input type="hidden" name="delete_entry_id" id="deleteEntryIdInput">
            <button type="submit" class="btn btn-danger">بله، حذف کن</button>
        </form>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
    </div>
</div></div></div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1"> <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="detailsModalLabel">خلاصه آمار روزانه رول</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailsModalBody"><div class="text-center p-4"><div class="spinner-border text-primary"></div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button></div>
</div></div></div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const isEditMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const logDateField = $('#log_date_field');
    const availableTimeField = $('#available_time_field');
    const descriptionField = $('#description_field'); // Added description field
    const inputRow = $('#input-row');
    const machineSelect = $('#input_machine_id');
    const partSelect = $('#input_part_id');
    const historyTbody = $('#history-tbody');
    const feedbackDiv = $('#input-row-feedback');
    const apiSaveEntryUrl = '<?php echo BASE_URL; ?>api/api_save_rolling_entry.php';
    const apiGetDailySummaryUrl = '<?php echo BASE_URL; ?>api/api_get_rolling_daily_summary.php';
    const apiProduciblePartsUrl = '<?php echo BASE_URL; ?>api/api_get_producible_parts_for_rolling.php'; // Correct API endpoint
    const apiUpdateSessionUrl = '<?php echo BASE_URL; ?>api/api_update_session.php';
    const detailsModalBody = $('#detailsModalBody');
    const detailsModalLabel = $('#detailsModalLabel');

    // --- Fetch Part Options ---
    async function populatePartOptions(machineId, selectedPartId = null) {
        partSelect.prop('disabled', true).html('<option value="">...بارگذاری</option>');
        if (!machineId) {
            partSelect.html('<option value="">-- دستگاه ؟ --</option>');
            return false;
        }
        try {
            // *** Pass machine_id to the correct API endpoint ***
            const response = await $.getJSON(apiProduciblePartsUrl, { machine_id: machineId });
            partSelect.html('<option value="">-- قطعه --</option>');
            if (response.success && response.data && response.data.length > 0) {
                 let groupedParts = {};
                response.data.forEach(part => {
                    const family = part.FamilyName || 'سایر';
                    if (!groupedParts[family]) groupedParts[family] = [];
                    groupedParts[family].push(part);
                });
                for (const family in groupedParts) {
                    partSelect.append(`<optgroup label="${family}">`);
                    groupedParts[family].forEach(part => {
                         partSelect.append(`<option value="${part.PartID}" ${selectedPartId == part.PartID ? 'selected' : ''}>${part.PartName}</option>`);
                    });
                    partSelect.append(`</optgroup>`);
                }
                partSelect.prop('disabled', false);
                if (selectedPartId && partSelect.find(`option[value="${selectedPartId}"]`).length > 0) {
                     partSelect.val(selectedPartId);
                }
                return true;
            } else {
                 partSelect.html('<option value="" disabled>-- قطعه‌ای یافت نشد --</option>');
                 return false;
            }
        } catch (error) {
            console.error("Error fetching parts:", error);
            partSelect.html('<option value="">-- خطا --</option>');
            return false;
        }
    }

    // --- Reset Input Row ---
    function resetInputRow(clearMachine = true) {
        if(isEditMode) return;
        const currentMachineId = !clearMachine ? machineSelect.val() : '';
        const currentOpId = !clearMachine ? inputRow.find('.operator-select').val() : '';

        inputRow.find('.operator-select').val(currentOpId);
        machineSelect.val(currentMachineId);
        partSelect.html('<option value="">-- قطعه --</option>').prop('disabled', !currentMachineId);
        inputRow.find('.production-grams-input').val('');
        inputRow.find('.start-time-input').val('');
        inputRow.find('.end-time-input').val('');
        feedbackDiv.text('').removeClass('alert alert-danger alert-success alert-info p-2');
        inputRow.removeClass('table-warning');

        if(currentMachineId){
             populatePartOptions(currentMachineId, null);
        }
        if (clearMachine || !currentMachineId) {
             machineSelect.focus();
        } else if (!currentOpId) {
             inputRow.find('.operator-select').focus();
        } else {
             partSelect.focus();
        }
    }

    // --- Submit Logic (AJAX) ---
    async function submitEntry() {
        feedbackDiv.text('در حال ثبت...').removeClass('alert-danger alert-success').addClass('alert alert-info p-2');
        inputRow.addClass('pe-none');

        // *** Get value in grams ***
        const productionGrams = inputRow.find('.production-grams-input').val();
        // *** Convert to KG before sending ***
        const productionKg = !isNaN(parseFloat(productionGrams)) ? parseFloat(productionGrams) / 1000.0 : null;

        const entryData = {
            log_date: logDateField.val(),
            available_time: availableTimeField.val(),
            description: descriptionField.val(), // Include description
            machine_id: machineSelect.val(),
            operator_id: inputRow.find('.operator-select').val(),
            part_id: partSelect.val(),
            production_kg: productionKg, // *** Send KG to API ***
            start_time: inputRow.find('.start-time-input').val(),
            end_time: inputRow.find('.end-time-input').val(),
            entry_id: isEditMode ? $('#entry_id_for_update').val() : null
        };

        // Client-side Validation
        let errors = [];
        if (!entryData.operator_id) errors.push("اپراتور");
        if (!entryData.machine_id) errors.push("دستگاه");
        if (!entryData.part_id) errors.push("نام قطعه");
        // *** Validate grams input ***
        if (productionGrams === '' || !$.isNumeric(productionGrams)) errors.push("میزان تولید (گرم باید عدد باشد)");
        else if (parseFloat(productionGrams) < 0) errors.push("میزان تولید (گرم منفی نباشد)");
        if (entryData.available_time === '' || !$.isNumeric(entryData.available_time) || parseInt(entryData.available_time) <=0) errors.push("زمان در دسترس (باید عدد مثبت باشد)");

        if (errors.length > 0) {
            feedbackDiv.text('خطا: ' + errors.join('، ') + ' الزامی/نامعتبر است.').removeClass('alert-info').addClass('alert-danger');
            inputRow.removeClass('pe-none');
            inputRow.addClass('table-warning');
            return;
        }
        // End Validation

        try {
            const response = await $.post(apiSaveEntryUrl, entryData);
            if (response.success) {
                feedbackDiv.text(response.message).removeClass('alert-info alert-danger').addClass('alert-success');
                const currentPage = new URLSearchParams(window.location.search).get('page') || 1;
                const currentDate = logDateField.val();
                setTimeout(() => {
                    // Redirect back, preserving page and date, and showing success message
                    window.location.href = `rolling.php?page=${currentPage}&log_date=${encodeURIComponent(currentDate)}&message=${encodeURIComponent(response.message)}&message_type=success`;
                }, 1000); // Short delay to show success
            } else {
                feedbackDiv.text('خطا در ثبت: ' + response.message).removeClass('alert-info').addClass('alert-danger');
                 inputRow.removeClass('pe-none');
            }
        } catch (jqXHR) {
            console.error("Error submitting entry:", jqXHR);
            let errorMsg = 'خطا در ارتباط با سرور.';
            if(jqXHR.responseJSON && jqXHR.responseJSON.message){ errorMsg = jqXHR.responseJSON.message; }
            else if (jqXHR.responseText){ try { errorMsg = JSON.parse(jqXHR.responseText).message || errorMsg } catch(e){} }
            feedbackDiv.text(errorMsg).removeClass('alert-info').addClass('alert-danger');
             inputRow.removeClass('pe-none');
        }
    }

    // --- Event Listeners ---

    // Update Part options when Machine changes
    machineSelect.on('change', async function() {
        const machineId = $(this).val();
        await populatePartOptions(machineId, null);
    });

   // Submit on Enter key press in the last input field (End Time)
    inputRow.on('keydown', '.end-time-input', function(e) {
        if (!isEditMode && (e.key === 'Enter' || e.keyCode === 13)) {
            e.preventDefault();
            submitEntry();
        }
    });

    // Submit on Save Edit button click
    $('#save-edit-btn').on('click', function() {
        submitEntry();
    });

     // Delegate delete button click for modal
     historyTbody.on('click', '.delete-btn', function() {
        const entryId = $(this).data('entry-id');
        $('#deleteEntryIdInput').val(entryId);
     });

    // Delegate details button click
     historyTbody.on('click', '.details-btn', async function() {
        const headerId = $(this).data('header-id');
        const logDateJalali = $(this).data('log-date-jalali');
        detailsModalLabel.text(`خلاصه آمار رول روز ${logDateJalali}`);
        detailsModalBody.html('<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>');

        try {
            const response = await $.getJSON(apiGetDailySummaryUrl, { header_id: headerId }); // Use correct header_id
            if (response.success && response.data) {
                const data = response.data;
                let content = `<p><strong>زمان در دسترس:</strong> ${data.available_time_minutes || '-'} دقیقه</p>`;
                 content += `<p><strong>توضیحات:</strong> ${data.description || '-'}</p><hr>`;
                 content += `<h6>مجموع تولید روزانه (بر اساس قطعه):</h6>`;

                if (data.production_summary && data.production_summary.length > 0) {
                     content += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                     content += '<thead><tr><th>قطعه</th><th class="text-center">وزن کل (گرم)</th></tr></thead><tbody>'; // Changed label to Grams
                     let totalGramsSum = 0;
                     data.production_summary.forEach(item => {
                         const totalGrams = parseFloat(item.total_kg) * 1000; // Convert KG to Grams
                         totalGramsSum += totalGrams;
                         content += `<tr><td>${item.part_name}</td><td class="text-center">${totalGrams.toFixed(1)}</td></tr>`; // Display Grams
                     });
                     content += '</tbody><tfoot class="table-light fw-bold">';
                     content += `<tr><td>مجموع</td><td class="text-center">${totalGramsSum.toFixed(1)}</td></tr>`; // Display total Grams
                     content += '</tfoot></table></div>';
                } else {
                     content += '<p class="text-muted small">تولیدی برای این روز ثبت نشده است.</p>';
                }
                content += `<hr><h6>پرسنل و زمان کاری:</h6>`;
                 if (data.shifts && data.shifts.length > 0) {
                     content += '<ul class="list-unstyled small">';
                     data.shifts.forEach(shift => {
                          content += `<li>${shift.OperatorName} (شروع: ${shift.StartTimeFmt || '-'} پایان: ${shift.EndTimeFmt || '-'})</li>`;
                     });
                      content += '</ul>';
                 } else { content += '<p class="small text-muted">ثبت نشده</p>'; }

                detailsModalBody.html(content);
            } else {
                detailsModalBody.html(`<div class="alert alert-warning">${response.message || 'خطا در دریافت خلاصه.'}</div>`);
            }
        } catch (error) {
             console.error("Error fetching daily summary:", error);
             detailsModalBody.html('<div class="alert alert-danger">خطا در ارتباط با سرور برای دریافت خلاصه روزانه.</div>');
        }
     });

    // Update session via AJAX on persistent input BLUR
    $('.persistent-input').on('blur', function() {
        const input = $(this);
        const sessionKey = input.data('session-key');
        const value = input.val();
        $.post(apiUpdateSessionUrl, { key: sessionKey, value: value })
            .fail(function(jqXHR) {
                console.error(`AJAX error updating session key '${sessionKey}' on blur:`, jqXHR.responseText);
            });
    });

    // Update session date on date change and potentially reload/update history
     if (!isEditMode) {
         logDateField.on('change', function() {
             const newDate = $(this).val();
             // Save other persistent fields before reload
             $('#available_time_field, #description_field').trigger('blur');
             setTimeout(() => { // Small delay to allow blur AJAX
                 $.post(apiUpdateSessionUrl, { key: 'rolling_log_date', value: newDate })
                     .always(function(){
                          window.location.href = `rolling.php?log_date=${encodeURIComponent(newDate)}`;
                     });
             }, 150);
         });
     }

    // --- Initial Load ---
    async function initializeForm() {
         const initialMachineId = machineSelect.val();
         const initialSelectedPartId = partSelect.data('selected-part-id');
        if (initialMachineId) {
             await populatePartOptions(initialMachineId, initialSelectedPartId);
        }
         if(!isEditMode) {
            if(!inputRow.find('.operator-select').val()) { // Focus operator if not set
                inputRow.find('.operator-select').focus();
            } else if (!machineSelect.val()) { // Else focus machine if not set
                 machineSelect.focus();
            } else if (!partSelect.val()) { // Else focus part if not set
                 partSelect.focus();
            }
         }
        // Initialize Flash message auto-hide
        setTimeout(() => { $('#flash-message').alert('close'); }, 5000);
    }

    initializeForm();

}); // End of document ready
</script>
<style>
/* Styles remain the same */
.input-table td { vertical-align: middle; }
.input-table .form-select-sm, .input-table .form-control-sm { padding-top: 0.2rem; padding-bottom: 0.2rem; font-size: 0.8rem; }
.pe-none { pointer-events: none; opacity: 0.7; }
#input-row-feedback { padding: 0.25rem 0.5rem; margin-top: 5px; border-radius: 0.2rem; min-height: 20px;}
</style>

