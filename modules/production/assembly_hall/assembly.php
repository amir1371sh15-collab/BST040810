<?php
require_once __DIR__ . '/../../../config/init.php';

// Permission check
if (!has_permission('production.assembly_hall.manage')) {
    die('شما مجوز ثبت یا ویرایش آمار مونتاژ را ندارید.');
}

// Constants
const ENTRIES_TABLE = 'tbl_assembly_log_entries';
const ENTRIES_PK = 'AssemblyEntryID';
const HEADER_TABLE = 'tbl_assembly_log_header';
const HEADER_PK = 'AssemblyHeaderID';
const RECORDS_PER_PAGE = 20; // For history table pagination

// *** Read persistent values from session FIRST, initialize if not set ***
$sess_avail_time = $_SESSION['assembly_available_time'] ?? 480;
$sess_daily_plan = $_SESSION['assembly_daily_plan'] ?? '';
$sess_description = $_SESSION['assembly_description'] ?? ''; // Read description

// These variables will hold the values actually displayed in the fields
$display_available_time = $sess_avail_time;
$display_daily_plan = $sess_daily_plan;
$display_description = $sess_description; // Description display variable

// Session Messages & Edit Mode Handling
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$editMode = false;
$entryToEdit = null;
$log_date_for_edit = null;

// Preserve the date from GET parameter if it exists, otherwise use session, otherwise use today
if (isset($_GET['log_date']) && !empty($_GET['log_date'])) {
    $initial_log_date_jalali = $_GET['log_date'];
    $_SESSION['assembly_log_date'] = $initial_log_date_jalali; // Keep session date updated
} elseif (isset($_SESSION['assembly_log_date'])) {
    $initial_log_date_jalali = $_SESSION['assembly_log_date'];
} else {
    $initial_log_date_jalali = to_jalali(date('Y-m-d')); // Default to today
    $_SESSION['assembly_log_date'] = $initial_log_date_jalali;
}


// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry_id'])) {
    $delete_id = (int)$_POST['delete_entry_id'];
    $result = delete_record($pdo, ENTRIES_TABLE, $delete_id, ENTRIES_PK);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    // Redirect back to the current page in history after delete
    $page = $_GET['page'] ?? 1;
    $redirect_url = "assembly.php?page=" . $page;
    if (isset($_SESSION['assembly_log_date'])) {
        $redirect_url .= "&log_date=" . urlencode($_SESSION['assembly_log_date']);
    }
    header("Location: " . $redirect_url);
    exit;
}

// Handle Edit Request (to load data for editing)
if (isset($_GET['edit_entry_id']) && is_numeric($_GET['edit_entry_id'])) {
    $editMode = true;
    $entryToEdit = find_by_id($pdo, ENTRIES_TABLE, (int)$_GET['edit_entry_id'], ENTRIES_PK);
    if ($entryToEdit) {
        $headerInfo = find_by_id($pdo, HEADER_TABLE, $entryToEdit['AssemblyHeaderID'], HEADER_PK);
        if ($headerInfo) {
            $log_date_for_edit = $headerInfo['LogDate'];
            $initial_log_date_jalali = to_jalali($log_date_for_edit); // Set date field for edit
            $_SESSION['assembly_log_date'] = $initial_log_date_jalali; // Update session with edit date

            // Load header info for edit mode into DISPLAY variables
            $display_available_time = $headerInfo['AvailableTimeMinutes'] ?? ($_SESSION['assembly_available_time'] ?? 480);
            $display_daily_plan = $headerInfo['DailyProductionPlan'] ?? ($_SESSION['assembly_daily_plan'] ?? '');
            $display_description = $headerInfo['Description'] ?? ($_SESSION['assembly_description'] ?? ''); // Load description for edit
        }
        $entryToEdit['ProductionGrams'] = round($entryToEdit['ProductionKG'] * 1000, 1);
    } else {
        $editMode = false;
        $_SESSION['message'] = 'رکورد مورد نظر برای ویرایش یافت نشد.';
        $_SESSION['message_type'] = 'warning';
        header("Location: assembly.php");
        exit;
    }
} else {
     // If NOT in edit mode, ensure display variables use the latest session values
     $display_available_time = $_SESSION['assembly_available_time'] ?? 480;
     $display_daily_plan = $_SESSION['assembly_daily_plan'] ?? '';
     $display_description = $_SESSION['assembly_description'] ?? ''; // Use session description
}


// --- Data Fetching for Dropdowns ---
$assembly_machines = find_all($pdo, "SELECT MachineID, MachineName FROM tbl_machines WHERE MachineType = 'مونتاژ' ORDER BY MachineName");
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
// Parts are fetched via JS API

// --- PAGINATION & HISTORY QUERY (Shows ALL entries) ---
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
        op1.name as Operator1Name,
        op2.name as Operator2Name
    FROM " . ENTRIES_TABLE . " e
    JOIN " . HEADER_TABLE . " h ON e.AssemblyHeaderID = h.AssemblyHeaderID
    JOIN tbl_machines m ON e.MachineID = m.MachineID
    JOIN tbl_parts p ON e.PartID = p.PartID
    LEFT JOIN tbl_employees op1 ON e.Operator1ID = op1.EmployeeID
    LEFT JOIN tbl_employees op2 ON e.Operator2ID = op2.EmployeeID
    ORDER BY h.LogDate DESC, e.AssemblyEntryID DESC
    LIMIT :limit OFFSET :offset
";
$history_stmt = $pdo->prepare($history_sql);
$history_stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$history_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$history_stmt->execute();
$history_entries = $history_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- Page Setup ---
$pageTitle = $editMode ? "ویرایش آمار مونتاژ" : "ثبت آمار مونتاژ";
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
         <div class="row">
             <div class="col-md-3 mb-3">
                 <label class="form-label">تاریخ</label>
                 <input type="text" id="log_date_field" class="form-control persian-date"
                        value="<?php echo $initial_log_date_jalali; ?>" required <?php echo $editMode ? 'readonly' : ''; ?>>
                 <?php if ($editMode): ?>
                    <small class="text-muted">تاریخ برای حالت ویرایش ثابت است.</small>
                    <input type="hidden" id="entry_id_for_update" value="<?php echo $entryToEdit[ENTRIES_PK]; ?>">
                 <?php endif; ?>
             </div>
             <div class="col-md-2 mb-3">
                 <label class="form-label">زمان در دسترس (دقیقه)</label>
                 <input type="number" id="available_time_field" class="form-control persistent-input" data-session-key="assembly_available_time"
                        value="<?php echo htmlspecialchars($display_available_time); ?>">
             </div>
             <div class="col-md-4 mb-3">
                 <label class="form-label">برنامه تولید روز</label>
                 <input type="text" id="daily_plan_field" class="form-control persistent-input" data-session-key="assembly_daily_plan"
                        value="<?php echo htmlspecialchars($display_daily_plan); ?>">
             </div>
              <div class="col-md-3 mb-3 text-end align-self-end">
                   <?php if ($editMode): ?>
                      <button type="button" id="save-edit-btn" class="btn btn-success"><i class="bi bi-check-lg"></i> ذخیره تغییرات</button>
                      <a href="assembly.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> لغو ویرایش</a>
                  <?php else: ?>
                     <span class="text-muted me-2 small d-block">برای ثبت هر ردیف، Enter بزنید.</span>
                  <?php endif; ?>
             </div>
             <div class="col-md-12 mb-2"> {/* Description field added */}
                 <label class="form-label">توضیحات روز</label>
                 <textarea id="description_field" class="form-control persistent-input" data-session-key="assembly_description" rows="2" placeholder="توضیحات کلی مربوط به این روز کاری..."><?php echo htmlspecialchars($display_description); ?></textarea>
             </div>
        </div>
    </div>
</div>

<?php // Input row container - always visible ?>
<div class="card content-card <?php echo $editMode ? 'border-warning' : ''; ?>" id="input-row-card">
    <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش رکورد (برای ذخیره روی دکمه کلیک کنید)' : 'ورود اطلاعات (برای ثبت هر ردیف Enter بزنید)'; ?></h5></div>
    <div class="card-body">
         <div class="table-responsive">
             <table class="table table-bordered table-sm align-middle input-table mb-0">
                 <thead>
                    <tr class="text-center small">
                        <th>اپراتور مونتاژ *</th>
                        <th>دستگاه *</th>
                        <th>نام قطعه *</th>
                        <th>اپراتور پیچ‌انداز</th>
                        <th>تولید (گرم) *</th>
                        <th>شروع</th>
                        <th>پایان</th>
                    </tr>
                </thead>
                <tbody id="assembly-entry-tbody">
                     <tr id="input-row" class="assembly-entry-row">
                          <td>
                            <select id="input_operator1_id" class="form-select form-select-sm operator1-select" required>
                                <option value="">--</option>
                                <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['EmployeeID']; ?>" <?php echo ($editMode && isset($entryToEdit['Operator1ID']) && $entryToEdit['Operator1ID'] == $emp['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                             <select id="input_machine_id" class="form-select form-select-sm machine-select" required>
                                  <option value="">--</option>
                                 <?php foreach($assembly_machines as $m): ?>
                                 <option value="<?php echo $m['MachineID']; ?>" <?php echo ($editMode && isset($entryToEdit['MachineID']) && $entryToEdit['MachineID'] == $m['MachineID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['MachineName']); ?></option>
                                 <?php endforeach; ?>
                             </select>
                        </td>
                        <td>
                            <select id="input_part_id" class="form-select form-select-sm part-selector" data-selected-part-id="<?php echo $editMode ? ($entryToEdit['PartID'] ?? '') : ''; ?>" required <?php echo !$editMode || !isset($entryToEdit['MachineID']) ? 'disabled' : ''; ?>>
                                 <option value="">-- دستگاه ؟ --</option>
                            </select>
                        </td>
                        <td>
                             <select id="input_operator2_id" class="form-select form-select-sm operator2-select">
                                 <option value="">--</option>
                                 <?php foreach($employees as $emp): ?>
                                 <option value="<?php echo $emp['EmployeeID']; ?>" <?php echo ($editMode && isset($entryToEdit['Operator2ID']) && $entryToEdit['Operator2ID'] == $emp['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                                 <?php endforeach; ?>
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
        <h5 class="mb-0">تاریخچه ثبت آمار مونتاژ (صفحه <?php echo $current_page; ?> از <?php echo $total_pages; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
             <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr class="small">
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">دستگاه</th>
                        <th class="p-2">اپراتور ۱</th>
                        <th class="p-2">اپراتور ۲</th>
                        <th class="p-2">زمان</th>
                        <th class="p-2">قطعه</th>
                        <th class="p-2">تولید (KG)</th>
                        <th class="p-2">عملیات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                     <?php if (empty($history_entries)): ?>
                         <tr><td colspan="8" class="text-center p-3 text-muted">هیچ رکوردی یافت نشد.</td></tr>
                     <?php else: ?>
                         <?php foreach($history_entries as $entry): ?>
                            <?php
                                $startTime = $entry['StartTime'] ? date('H:i', strtotime($entry['StartTime'])) : '-';
                                $endTime = $entry['EndTime'] ? date('H:i', strtotime($entry['EndTime'])) : '-';
                            ?>
                            <tr class="small history-row-<?php echo $entry[ENTRIES_PK]; ?>">
                                <td class="p-2"><?php echo to_jalali($entry['LogDate']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['MachineName']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['Operator1Name'] ?? '-'); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['Operator2Name'] ?? '-'); ?></td>
                                <td class="p-2"><?php echo $startTime . ' الی ' . $endTime; ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($entry['PartName']); ?></td>
                                <td class="p-2"><?php echo number_format($entry['ProductionKG'], 3); ?></td>
                                <td class="p-2">
                                     <button type="button" class="btn btn-info btn-sm py-0 px-1 details-btn"
                                             data-log-date="<?php echo $entry['LogDate']; ?>"
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
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_SESSION['assembly_log_date']) ? '&log_date=' . urlencode($_SESSION['assembly_log_date']) : ''; ?>">قبلی</a>
                </li>
                <?php
                    // Simplified pagination display
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . (isset($_SESSION['assembly_log_date']) ? '&log_date=' . urlencode($_SESSION['assembly_log_date']) : '') . '">1</a></li>';
                        if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_SESSION['assembly_log_date']) ? '&log_date=' . urlencode($_SESSION['assembly_log_date']) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php
                    endfor;
                     if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (isset($_SESSION['assembly_log_date']) ? '&log_date=' . urlencode($_SESSION['assembly_log_date']) : '') . '">' . $total_pages . '</a></li>';
                    }
                ?>
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_SESSION['assembly_log_date']) ? '&log_date=' . urlencode($_SESSION['assembly_log_date']) : ''; ?>">بعدی</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">تایید حذف</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        آیا از حذف این رکورد آمار مونتاژ مطمئن هستید؟
      </div>
      <div class="modal-footer">
        <form id="deleteForm" method="POST" action="assembly.php" class="d-inline">
            <input type="hidden" name="delete_entry_id" id="deleteEntryIdInput">
            <button type="submit" class="btn btn-danger">بله، حذف کن</button>
        </form>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
      </div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailsModalLabel">خلاصه آمار روزانه</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detailsModalBody">
        <div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
      </div>
    </div>
  </div>
</div>


<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const isEditMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const logDateField = $('#log_date_field');
    const availableTimeField = $('#available_time_field');
    const dailyPlanField = $('#daily_plan_field');
    const descriptionField = $('#description_field'); // Added description field
    const inputRow = $('#input-row');
    const machineSelect = $('#input_machine_id');
    const partSelect = $('#input_part_id');
    const historyTbody = $('#history-tbody');
    const feedbackDiv = $('#input-row-feedback');
    const apiSaveEntryUrl = '<?php echo BASE_URL; ?>api/api_save_assembly_entry.php';
    const apiGetDailySummaryUrl = '<?php echo BASE_URL; ?>api/api_get_assembly_daily_summary.php';
    const apiProduciblePartsUrl = '<?php echo BASE_URL; ?>api/api_get_producible_parts_by_machine.php';
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
        if(isEditMode) return; // Don't reset in edit mode

        const currentMachineId = !clearMachine ? machineSelect.val() : '';
        const currentOp1Id = !clearMachine ? inputRow.find('.operator1-select').val() : '';

        inputRow.find('.operator1-select').val(currentOp1Id);
        machineSelect.val(currentMachineId);
        partSelect.html('<option value="">-- قطعه --</option>').prop('disabled', !currentMachineId);
        inputRow.find('.operator2-select').val('');
        inputRow.find('.production-grams-input').val('');
        inputRow.find('.start-time-input').val('');
        inputRow.find('.end-time-input').val('');
        feedbackDiv.text('').removeClass('alert alert-danger alert-success alert-info p-2');
        inputRow.removeClass('table-warning');

        if(currentMachineId){
             populatePartOptions(currentMachineId, null); // Refresh parts list for the machine
        }

        // Focus logic
        if (clearMachine || !currentMachineId) {
             machineSelect.focus();
        } else if (!currentOp1Id) {
             inputRow.find('.operator1-select').focus();
        } else {
             partSelect.focus();
        }
    }

    // --- Submit Logic (AJAX) ---
    async function submitEntry() {
        feedbackDiv.text('در حال ثبت...').removeClass('alert-danger alert-success').addClass('alert alert-info p-2');
        inputRow.addClass('pe-none'); // Disable input row

        const entryData = {
            log_date: logDateField.val(), // Jalali date from field
            available_time: availableTimeField.val(),
            daily_plan: dailyPlanField.val(),
            description: descriptionField.val(), // Add description
            machine_id: machineSelect.val(),
            operator1_id: inputRow.find('.operator1-select').val(),
            part_id: partSelect.val(),
            operator2_id: inputRow.find('.operator2-select').val(),
            production_grams: inputRow.find('.production-grams-input').val(),
            start_time: inputRow.find('.start-time-input').val(),
            end_time: inputRow.find('.end-time-input').val(),
            entry_id: isEditMode ? $('#entry_id_for_update').val() : null
        };

        // Client-side Validation (remains the same)
        let errors = [];
        if (!entryData.operator1_id) errors.push("اپراتور مونتاژ");
        if (!entryData.machine_id) errors.push("دستگاه");
        if (!entryData.part_id) errors.push("نام قطعه");
        if (entryData.production_grams === '' || !$.isNumeric(entryData.production_grams)) errors.push("میزان تولید (باید عدد باشد)");
        else if (parseFloat(entryData.production_grams) < 0) errors.push("میزان تولید (منفی نباشد)");
        if (entryData.available_time === '' || !$.isNumeric(entryData.available_time) || parseInt(entryData.available_time) <=0) errors.push("زمان در دسترس (باید عدد مثبت باشد)");

        if (errors.length > 0) {
            feedbackDiv.text('خطا: ' + errors.join('، ') + ' الزامی است/نامعتبر است.').removeClass('alert-info').addClass('alert-danger');
            inputRow.removeClass('pe-none'); // Re-enable on validation error
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
                    // Redirect after successful save/update
                    // Use replace to avoid issues with back button adding duplicates if user goes back
                    window.location.replace(`assembly.php?page=${currentPage}&log_date=${encodeURIComponent(currentDate)}&message=${encodeURIComponent(response.message)}&message_type=success`);
                }, 800); // Slightly shorter delay
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
        const logDateGregorian = $(this).data('log-date'); // Expecting YYYY-MM-DD
        detailsModalLabel.text('خلاصه آمار روزانه'); // Reset title
        detailsModalBody.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');

        try {
            const response = await $.getJSON(apiGetDailySummaryUrl, { log_date: logDateGregorian });
            if (response.success && response.data) {
                const data = response.data;
                detailsModalLabel.text(`خلاصه آمار روز ${data.log_date_jalali}`);
                let content = `<p><strong>تعداد دستگاه‌های فعال:</strong> ${data.active_machines_count}</p>`;
                content += `<p><strong>مجموع ساعات کار واقعی:</strong> ${data.total_duration_hours} ساعت</p>`;
                // Display Description
                content += `<h6>توضیحات روز:</h6><p class="small border p-2 rounded bg-light">${data.description || '-'}</p>`;
                content += `<h6>مجموع تولید روزانه (بر اساس قطعه):</h6>`;

                if (data.production_summary && data.production_summary.length > 0) {
                    let totalKgSum = 0;
                    let totalCountSum = 0;

                    content += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    content += '<thead><tr><th>قطعه</th><th class="text-center">وزن کل (KG)</th><th class="text-center">تعداد کل</th></tr></thead><tbody>';

                    data.production_summary.forEach(item => {
                        totalKgSum += parseFloat(item.total_kg);
                        totalCountSum += parseInt(item.total_count);
                        content += `<tr>
                                        <td>${item.part_name}</td>
                                        <td class="text-center">${item.total_kg}</td>
                                        <td class="text-center">${item.total_count.toLocaleString()}</td>
                                    </tr>`;
                    });

                    content += '</tbody><tfoot class="table-light fw-bold">';
                    content += `<tr>
                                    <td>مجموع</td>
                                    <td class="text-center">${totalKgSum.toFixed(3)}</td>
                                    <td class="text-center">${totalCountSum.toLocaleString()}</td>
                                </tr>`;
                    content += '</tfoot></table></div>';
                } else {
                    content += '<p class="text-muted small">تولیدی برای این روز ثبت نشده است.</p>';
                }
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

        console.log(`Persistent input blurred. Saving ${sessionKey} = ${value}`);

        $.post(apiUpdateSessionUrl, { key: sessionKey, value: value })
            .done(function(response) {
                if (response.success) {
                    console.log(`Session key '${sessionKey}' updated successfully via blur.`);
                } else {
                    console.error(`Failed to update session key '${sessionKey}' on blur:`, response.message);
                }
            })
            .fail(function(jqXHR) {
                console.error(`AJAX error updating session key '${sessionKey}' on blur:`, jqXHR.responseText);
            });
    });


    // --- Initial Load ---
    async function initializeForm() {
         const initialMachineId = machineSelect.val();
         const initialSelectedPartId = partSelect.data('selected-part-id');
        if (initialMachineId) {
             await populatePartOptions(initialMachineId, initialSelectedPartId);
        }
         if(!isEditMode) {
            // Focus logic if needed
            if(!inputRow.find('.operator1-select').val()) {
                inputRow.find('.operator1-select').focus();
            }
         }

        // Date Change Listener - Simpler reload
         if (!isEditMode) {
             logDateField.on('change', function() {
                 const newDate = $(this).val();
                 console.log("Date changed to:", newDate, ". Saving persistent fields and reloading page.");
                 $('.persistent-input').trigger('blur'); // Save persistent fields first
                 setTimeout(() => {
                     $.post(apiUpdateSessionUrl, { key: 'assembly_log_date', value: newDate })
                         .always(function(){
                              window.location.href = `assembly.php?log_date=${encodeURIComponent(newDate)}`;
                         });
                 }, 150);
             });
         }
    }

    initializeForm();

}); // End of document ready
</script>
<style>
.input-table td { vertical-align: middle; }
.input-table .form-select-sm, .input-table .form-control-sm { padding-top: 0.2rem; padding-bottom: 0.2rem; font-size: 0.8rem; }
.pe-none { pointer-events: none; opacity: 0.7; }
#input-row-feedback { padding: 0.25rem 0.5rem; margin-top: 5px; border-radius: 0.2rem; min-height: 20px;}
</style>
