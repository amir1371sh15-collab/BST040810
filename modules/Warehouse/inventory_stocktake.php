<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('warehouse.transactions.manage')) { die('شما مجوز انجام عملیات انبارگردانی را ندارید.'); }

const TABLE_NAME = 'tbl_stock_transactions';
const PRIMARY_KEY = 'TransactionID';
const RECORDS_PER_PAGE = 15; // Define records per page for history
const STOCKTAKE_TYPE_NAMES = ['موجودی اولیه', 'کسر انبارگردانی', 'اضافه انبارگردانی'];

// Station IDs Constants
const CARTON_WAREHOUSE_STATION_ID = 11;

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
// Fetch ONLY warehouse stations for the dropdown
$warehouse_stations = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations WHERE StationID IN (8, 9, " . CARTON_WAREHOUSE_STATION_ID . ") ORDER BY StationName");
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");

$quotedTypeNames = implode(',', array_map(function ($name) use ($pdo) {
    return $pdo->quote($name);
}, STOCKTAKE_TYPE_NAMES));
$stocktake_transaction_types = find_all($pdo, "SELECT TypeID, TypeName, StockEffect FROM tbl_transaction_types WHERE TypeName IN ($quotedTypeNames) ORDER BY TypeID");
$stocktake_type_ids = array_map('intval', array_column($stocktake_transaction_types, 'TypeID'));
// Statuses are now loaded dynamically via JS based on family

$editMode = false;
$itemToEdit = null;
$itemToEditJson = 'null';

$initial_log_date_jalali = $_SESSION['stocktake_log_date'] ?? to_jalali(date('Y-m-d'));

// --- Pagination Logic ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Get total records for stocktake transactions
$total_records_query = $pdo->query("
    SELECT COUNT(t.TransactionID)
    FROM tbl_stock_transactions t
    JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
    WHERE tt.TypeName IN ($quotedTypeNames)
");
$total_records = $total_records_query ? $total_records_query->fetchColumn() : 0;
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;
// --- End Pagination Logic ---

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteResult = ['success' => false];
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);

    if (!$delete_id) {
        $_SESSION['message'] = 'شناسه نامعتبر برای حذف.';
        $_SESSION['message_type'] = 'warning';
    } else {
        $transaction = find_by_id($pdo, TABLE_NAME, $delete_id, PRIMARY_KEY);
        if (!$transaction) {
            $_SESSION['message'] = 'رکورد مورد نظر یافت نشد.';
            $_SESSION['message_type'] = 'warning';
        } elseif (!in_array((int)$transaction['TransactionTypeID'], $stocktake_type_ids, true)) {
            $_SESSION['message'] = 'این رکورد مربوط به انبارگردانی نیست و قابل حذف نمی‌باشد.';
            $_SESSION['message_type'] = 'warning';
        } else {
            $deleteResult = delete_record($pdo, TABLE_NAME, $delete_id, PRIMARY_KEY);
            $_SESSION['message'] = $deleteResult['message'];
            $_SESSION['message_type'] = $deleteResult['success'] ? 'success' : 'danger';
        }
    }

    $redirect_page = $current_page;
    if (($deleteResult['success'] ?? false) && $total_records > 0) {
        $remaining_records = $total_records - 1;
        $new_total_pages = max(ceil($remaining_records / RECORDS_PER_PAGE), 1);
        if ($redirect_page > $new_total_pages) {
            $redirect_page = $new_total_pages;
        }
    }
    header("Location: " . BASE_URL . "modules/Warehouse/inventory_stocktake.php?page=" . $redirect_page);
    exit;
}

// Handle Edit Request
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $transaction = find_by_id($pdo, TABLE_NAME, $edit_id, PRIMARY_KEY);
        if ($transaction && in_array((int)$transaction['TransactionTypeID'], $stocktake_type_ids, true)) {
            $editMode = true;
            $itemToEdit = $transaction;

            $partInfo = find_by_id($pdo, 'tbl_parts', $transaction['PartID'], 'PartID');
            if (!$partInfo) {
                $_SESSION['message'] = 'اطلاعات قطعه برای ویرایش یافت نشد.';
                $_SESSION['message_type'] = 'warning';
                header("Location: " . BASE_URL . "modules/Warehouse/inventory_stocktake.php?page=" . $current_page);
                exit;
            }

            $itemToEdit['FamilyID'] = $partInfo['FamilyID'] ?? null;
            // Determine quantity based on unit (KG or Cartons)
            $isCartonStocktake = ($transaction['ToStationID'] == CARTON_WAREHOUSE_STATION_ID);
            $itemToEdit['Quantity'] = $isCartonStocktake
                                        ? abs((int)($transaction['CartonQuantity'] ?? 0))
                                        : number_format(abs((float)($transaction['NetWeightKG'] ?? 0)), 3, '.', '');
            $itemToEdit['Unit'] = $isCartonStocktake ? 'کارتن' : 'KG';
            $itemToEdit['StationID'] = (int)$transaction['ToStationID']; // ToStation is the warehouse
            $itemToEdit['StatusAfterID'] = $transaction['StatusAfterID'] !== null ? (int)$transaction['StatusAfterID'] : null;
            $itemToEdit['OperatorEmployeeID'] = $transaction['OperatorEmployeeID'] ?? null;
            $itemToEdit['TransactionDateJalali'] = to_jalali($transaction['TransactionDate']);
            $itemToEdit['StockEffect'] = null; // Can be derived if needed, but not directly used in form repopulation

            $_SESSION['stocktake_log_date'] = $itemToEdit['TransactionDateJalali'];
            $initial_log_date_jalali = $itemToEdit['TransactionDateJalali'];

            $itemToEditJson = json_encode($itemToEdit, JSON_UNESCAPED_UNICODE);
        } else {
            $_SESSION['message'] = 'رکورد انبارگردانی برای ویرایش یافت نشد.';
            $_SESSION['message_type'] = 'warning';
            header("Location: " . BASE_URL . "modules/Warehouse/inventory_stocktake.php?page=" . $current_page);
            exit;
        }
    }
}

// --- Updated History Query ---
$history_query = "
    SELECT t.*, p.PartName, s.StationName, tt.TypeName as TransactionTypeName, tt.StockEffect, ps.StatusName as StatusAfterName, op.name as OperatorName
    FROM tbl_stock_transactions t
    JOIN tbl_parts p ON t.PartID = p.PartID
    JOIN tbl_stations s ON t.ToStationID = s.StationID /* Assume ToStation is the relevant warehouse */
    JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
    LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
    LEFT JOIN tbl_employees op ON t.OperatorEmployeeID = op.EmployeeID
    WHERE tt.TypeName IN ($quotedTypeNames)
    ORDER BY t.TransactionDate DESC, t.TransactionID DESC
    LIMIT :limit OFFSET :offset
";
$history_stmt = $pdo->prepare($history_query);
$history_stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$history_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$history_stmt->execute();
$recentStocktakes = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
// --- End Updated History Query ---

$pageTitle = "ثبت انبارگردانی / موجودی اولیه";
include __DIR__ . '/../../templates/header.php';
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

<form id="stocktake-form">
    <div class="card content-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?php echo $editMode ? 'ویرایش رکورد انبارگردانی' : 'ثبت رکورد جدید'; ?></h5>
            <?php if ($editMode): ?>
                <a href="inventory_stocktake.php?page=<?php echo $current_page; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> لغو ویرایش
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <input type="hidden" id="transaction_id" name="transaction_id" value="<?php echo $editMode ? (int)$itemToEdit[PRIMARY_KEY] : ''; ?>">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="transaction_date" class="form-label">تاریخ ثبت *</label>
                    <input type="text" id="transaction_date" name="transaction_date" class="form-control persian-date persistent-input" data-session-key="stocktake_log_date"
                           value="<?php echo $initial_log_date_jalali; ?>" required>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="transaction_type_id" class="form-label">نوع عملیات *</label>
                    <select id="transaction_type_id" name="transaction_type_id" class="form-select" required>
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($stocktake_transaction_types as $type): ?>
                            <option value="<?php echo $type['TypeID']; ?>" data-effect="<?php echo $type['StockEffect']; ?>">
                                <?php echo htmlspecialchars($type['TypeName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="operator_employee_id" class="form-label">نام عامل ثبت</label>
                    <select id="operator_employee_id" name="operator_employee_id" class="form-select">
                        <option value="">-- انتخاب --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                 <div class="col-md-4 mb-3">
                     <label for="family_id" class="form-label">خانواده قطعه *</label>
                     <select id="family_id" name="family_id" class="form-select" required>
                         <option value="">-- انتخاب --</option>
                         <?php foreach ($families as $family): ?>
                             <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="col-md-4 mb-3">
                      <label for="part_id" class="form-label">قطعه *</label>
                      <select id="part_id" name="part_id" class="form-select" required disabled>
                         <option value="">-- ابتدا خانواده --</option>
                      </select>
                 </div>
                 <div class="col-md-4 mb-3">
                    <label for="station_id" class="form-label">انبار *</label>
                    <select id="station_id" name="station_id" class="form-select" required>
                        <option value="">-- انتخاب انبار --</option>
                        <?php foreach ($warehouse_stations as $station): ?>
                            <option value="<?php echo $station['StationID']; ?>" data-is-carton="<?php echo $station['StationID'] == CARTON_WAREHOUSE_STATION_ID ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($station['StationName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

             <!-- Container for Current Balances -->
            <div id="current-balances-container" class="mt-3 mb-3" style="display: none;">
                <h6>موجودی فعلی (تا تاریخ <span id="balance-date-display"></span>) در <span id="balance-station-display"></span> برای <span id="balance-part-display"></span>:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="current-balances-table">
                        <thead><tr class="table-secondary small"><th class="text-center">وضعیت</th><th class="text-center" id="balance-unit-header">موجودی (?)</th></tr></thead>
                        <tbody><!-- Rows added by JS --></tbody>
                    </table>
                </div>
            </div>
            <!-- End Current Balances Container -->

            <div class="row align-items-end">
                 <!-- Moved Status Field Here -->
                 <div class="col-md-4 mb-3">
                    <label for="status_after_id" class="form-label">وضعیت قطعه *</label>
                    <select id="status_after_id" name="status_after_id" class="form-select" required disabled>
                         <option value="">-- ابتدا خانواده --</option>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                     <label for="quantity_kg" class="form-label" id="quantity_label">مقدار (KG) *</label>
                     <input type="number" step="0.001" id="quantity_kg" name="quantity_kg" class="form-control" placeholder="مقدار اولیه یا مقدار تعدیل" required>
                      <small id="quantity-help" class="form-text text-muted"></small>
                 </div>
                 <div class="col-md-5 mb-3 text-end">
                     <button type="submit" id="submit-button" class="btn <?php echo $editMode ? 'btn-warning' : 'btn-primary'; ?>">
                         <i class="bi <?php echo $editMode ? 'bi-save' : 'bi-check-lg'; ?>"></i> <?php echo $editMode ? 'ذخیره تغییرات' : 'ثبت'; ?>
                     </button>
                     <div id="submit-feedback" class="mt-2 small d-inline-block me-3"></div>
                 </div>
            </div>
        </div>
    </div>
</form>

<div class="card content-card mt-4">
    <div class="card-header"><h5 class="mb-0">آخرین ثبت‌های انبارگردانی / موجودی اولیه</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">نوع</th>
                        <th class="p-2">قطعه</th>
                        <th class="p-2">انبار</th>
                        <th class="p-2">وضعیت</th>
                        <th class="p-2">مقدار</th>
                        <th class="p-2">واحد</th>
                        <th class="p-2">عامل ثبت</th>
                        <th class="p-2 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <?php if (empty($recentStocktakes)): ?>
                        <tr><td colspan="9" class="text-center p-3 text-muted">هنوز رکوردی ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach($recentStocktakes as $tx):
                             $isCartonHistory = ($tx['ToStationID'] == CARTON_WAREHOUSE_STATION_ID); // ToStation is the warehouse for stocktake
                             $quantity = $isCartonHistory ? (int)($tx['CartonQuantity'] ?? 0) : (float)($tx['NetWeightKG'] ?? 0);
                             $effect = (int)($tx['StockEffect'] ?? 0);
                             // Display absolute value, determine sign/color from effect
                             $displayQuantity = $isCartonHistory ? abs($quantity) : number_format(abs($quantity), 3);
                             $quantityClass = ($effect > 0) ? 'text-success' : (($effect < 0) ? 'text-danger' : '');
                             $quantityPrefix = ($effect < 0) ? '-' : (($effect > 0 && $tx['TransactionTypeName'] != 'موجودی اولیه') ? '+' : '');
                             $unitDisplay = $isCartonHistory ? 'کارتن' : 'KG';
                        ?>
                        <tr>
                            <td class="p-2"><?php echo to_jalali($tx['TransactionDate']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['TransactionTypeName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['PartName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['StationName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['StatusAfterName'] ?: '-- بدون وضعیت --'); ?></td>
                            <td class="p-2 fw-bold <?php echo $quantityClass; ?>"><?php echo $quantityPrefix . $displayQuantity; ?></td>
                            <td class="p-2"><?php echo $unitDisplay; ?></td>
                            <td class="p-2 small"><?php echo htmlspecialchars($tx['OperatorName'] ?? '-'); ?></td>
                            <td class="p-2 text-center text-nowrap">
                                <a href="?edit_id=<?php echo (int)$tx['TransactionID']; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm me-1" title="ویرایش">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm delete-btn" data-tx-id="<?php echo (int)$tx['TransactionID']; ?>" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" title="حذف">
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
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">قبلی</a>
                </li>
                <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                        if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php
                    endfor;
                     if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                    }
                ?>
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title">حذف رکورد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          آیا از حذف این رکورد انبارگردانی مطمئن هستید؟
      </div>
      <div class="modal-footer">
          <form id="delete-form" method="POST" action="inventory_stocktake.php?page=<?php echo $current_page; ?>" class="d-inline">
              <input type="hidden" name="delete_id" id="delete-transaction-id">
              <button type="submit" class="btn btn-danger">بله، حذف شود</button>
          </form>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const apiRecordUrl = '<?php echo BASE_URL; ?>api/record_transaction.php';
    const apiGetStatusesUrl = '<?php echo BASE_URL; ?>api/api_get_statuses_by_family.php';
    const apiGetInventoryUrl = '<?php echo BASE_URL; ?>api/api_get_inventory_report.php';
    const familySelect = $('#family_id');
    const partSelect = $('#part_id');
    const transactionDateInput = $('#transaction_date');
    const transactionTypeSelect = $('#transaction_type_id');
    const stationSelect = $('#station_id');
    const statusSelect = $('#status_after_id');
    const operatorSelect = $('#operator_employee_id');
    const quantityInput = $('#quantity_kg');
    const quantityLabel = $('#quantity_label'); // Get the label element
    const quantityHelp = $('#quantity-help');
    const submitButton = $('#submit-button');
    const submitFeedback = $('#submit-feedback');
    const transactionIdInput = $('#transaction_id');
    const balanceContainer = $('#current-balances-container');
    const balanceTableBody = $('#current-balances-table tbody');
    const balanceUnitHeader = $('#balance-unit-header'); // Get balance unit header
    const deleteTransactionIdInput = $('#delete-transaction-id');
    const stocktakePageUrl = 'inventory_stocktake.php';
    const currentPageNumber = <?php echo $current_page; ?>;
    const editMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const itemToEdit = <?php echo $itemToEditJson; ?>;
    const CARTON_WAREHOUSE_ID = <?php echo CARTON_WAREHOUSE_STATION_ID; ?>; // Make ID available to JS

    // --- Event Listeners ---
    familySelect.on('change', async function() {
        partSelect.prop('disabled', true).html('<option value="">...</option>');
        statusSelect.prop('disabled', true).html('<option value="">-- ابتدا خانواده --</option>');
        balanceContainer.hide();
        const familyId = $(this).val();
        if (familyId) {
            await populateParts(familyId);
            await populateStatusDropdown(familyId); // Load statuses based on family
        } else {
            partSelect.html('<option value="">-- ابتدا خانواده --</option>');
        }
    });

    transactionDateInput.on('change', function() {
        const newDate = $(this).val();
        $.post('<?php echo BASE_URL; ?>api/api_update_session.php', { key: 'stocktake_log_date', value: newDate });
        checkAndLoadBalances(); // Reload balances if needed
    });

    transactionTypeSelect.on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const typeName = selectedOption.text();
        if (typeName.includes('کسر') || typeName.includes('اضافه')) {
            quantityHelp.text('مقدار مغایرت را وارد کنید (عدد مثبت).');
            checkAndLoadBalances();
        } else {
            quantityHelp.text('مقدار موجودی اولیه را وارد کنید.');
            balanceContainer.hide();
        }
    }).trigger('change'); // Trigger on load

    partSelect.on('change', checkAndLoadBalances);
    stationSelect.on('change', function() {
        updateQuantityInput(); // Update label and step based on station
        checkAndLoadBalances();
    });

    $(document).on('click', '.delete-btn', function() {
        const txId = $(this).data('tx-id');
        deleteTransactionIdInput.val(txId);
    });

    $('#deleteConfirmModal').on('hidden.bs.modal', function () {
        deleteTransactionIdInput.val('');
    });

    // REQ 2: Submit on Enter in quantity field
    quantityInput.on('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#stocktake-form').trigger('submit');
        }
    });

    // --- Form Submission ---
    $('#stocktake-form').on('submit', async function(e) {
        e.preventDefault();
        submitFeedback.text('در حال ثبت...').removeClass('text-success text-danger').addClass('text-info');
        submitButton.prop('disabled', true);

        const formData = $(this).serializeArray();
        let dataToSend = {};
        formData.forEach(item => { dataToSend[item.name] = item.value; });

        const quantity = parseFloat(dataToSend['quantity_kg']);
        const stationId = parseInt(dataToSend['station_id']);

        if (isNaN(quantity) || quantity < 0) {
             submitFeedback.text('مقدار باید یک عدد مثبت باشد.').removeClass('text-info').addClass('text-danger');
             submitButton.prop('disabled', false);
             return;
        }

        // Prepare data for record_transaction.php API
        const transactionData = {
            transaction_date: dataToSend['transaction_date'],
            part_id: dataToSend['part_id'],
            from_station_id: stationId,
            to_station_id: stationId,
            gross_weight_kg: quantity, 
            pallet_type_id: null,
            pallet_weight_kg: 0,
            quantity_kg: quantity, 
            status_after_id: dataToSend['status_after_id'] === 'NULL' ? null : dataToSend['status_after_id'], 
            operator_employee_id: dataToSend['operator_employee_id'] || null,
            transaction_type_id: dataToSend['transaction_type_id'], 
            transaction_id: dataToSend['transaction_id'] || '', 
        };
        console.log("Data Sent to record_transaction API:", transactionData);

        try {
            const recordResponse = await $.post(apiRecordUrl, transactionData);
            if (recordResponse.success) {
                submitFeedback.text(recordResponse.message).removeClass('text-info text-danger').addClass('text-success');
                
                // REQ 3: Modify redirect logic
                let redirectUrl = stocktakePageUrl;
                if (editMode) {
                    redirectUrl += `?page=${currentPageNumber}`; // Go back to current page if editing
                } else {
                    redirectUrl += `?focus_transaction_type=true`; // Focus type field on new entry
                }

                setTimeout(() => {
                    window.location.href = redirectUrl; 
                }, 800);
            } else {
                 submitFeedback.text(recordResponse.message || 'خطا در ثبت.').removeClass('text-info').addClass('text-danger');
                 submitButton.prop('disabled', false);
            }
        } catch (error) {
            console.error("Submit error:", error);
            const errorMsg = error.responseJSON?.message || error.responseText || error.message || 'خطای ناشناخته.';
            submitFeedback.text(`خطا: ${errorMsg}`).removeClass('text-info').addClass('text-danger');
            submitButton.prop('disabled', false);
        }
    });

    async function initializeEditMode() {
        if (!editMode || !itemToEdit) {
            return; // Exit if not in edit mode
        }

        transactionIdInput.val(itemToEdit.TransactionID ?? '');
        transactionDateInput.val(itemToEdit.TransactionDateJalali ?? transactionDateInput.val());
        operatorSelect.val(itemToEdit.OperatorEmployeeID ? String(itemToEdit.OperatorEmployeeID) : '');
        stationSelect.val(itemToEdit.StationID ? String(itemToEdit.StationID) : '');
        quantityInput.val(itemToEdit.Quantity ?? ''); // Quantity is absolute value
        
        submitButton.removeClass('btn-primary').addClass('btn-warning');
        submitFeedback.text('در حال ویرایش رکورد انتخاب‌شده.').removeClass('text-danger text-success').addClass('text-info');

        const familyId = itemToEdit.FamilyID ? String(itemToEdit.FamilyID) : '';
        if (familyId) {
            familySelect.val(familyId);
            // Wait for parts and statuses to populate before setting their values
            await populateParts(familyId, itemToEdit.PartID ? String(itemToEdit.PartID) : null);
            await populateStatusDropdown(familyId, itemToEdit.StatusAfterID === null ? 'NULL' : String(itemToEdit.StatusAfterID));
        }

        transactionTypeSelect.val(itemToEdit.TransactionTypeID ? String(itemToEdit.TransactionTypeID) : '');
        
        // Trigger necessary updates after populating
        updateQuantityInput(); // Update label/step based on loaded station
        transactionTypeSelect.trigger('change'); // Update help text and load balance
    }

    // --- Helper Functions ---
    async function populateParts(familyId, selectedPartId = null) {
        partSelect.html('<option value="">در حال بارگذاری...</option>');
        try {
            const response = await $.getJSON('<?php echo BASE_URL; ?>api/api_get_parts_by_family.php', { family_id: familyId });
            partSelect.html('<option value="">-- انتخاب قطعه --</option>');
            if (response.success && response.data.length > 0) {
                response.data.forEach(part => {
                    partSelect.append($('<option>', { value: part.PartID, text: part.PartName }));
                });
                partSelect.prop('disabled', false);
                if (selectedPartId) {
                    partSelect.val(selectedPartId);
                }
            } else {
                partSelect.html('<option value="">قطعه‌ای یافت نشد</option>');
            }
        } catch (error) {
            console.error("Error fetching parts:", error);
            partSelect.html('<option value="">خطا در بارگذاری</option>');
        }
    }

    async function populateStatusDropdown(familyId, selectedStatusId = null) {
        statusSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            statusSelect.html('<option value="">-- ابتدا خانواده --</option>');
            return;
        }
        try {
            const response = await $.getJSON(apiGetStatusesUrl, { family_id: familyId });
            statusSelect.html('<option value="">-- انتخاب وضعیت --</option>');
            statusSelect.append('<option value="NULL">-- بدون وضعیت --</option>'); // Add NULL option
            if (response.success && response.data.length > 0) {
                response.data.forEach(status => {
                    statusSelect.append($('<option>', { value: status.StatusID, text: status.StatusName }));
                });
                statusSelect.prop('disabled', false);
                if (selectedStatusId !== null && selectedStatusId !== undefined) {
                    statusSelect.val(selectedStatusId === null ? 'NULL' : String(selectedStatusId));
                }
            } else {
                 statusSelect.append('<option value="" disabled>وضعیتی یافت نشد</option>');
                 statusSelect.prop('disabled', false); 
            }
        } catch (error) {
            console.error("Error fetching statuses:", error);
            statusSelect.html('<option value="">خطا در بارگذاری</option>');
        }
    }

     function updateQuantityInput() {
        const selectedStationId = parseInt(stationSelect.val() || 0);
        if (selectedStationId === CARTON_WAREHOUSE_ID) {
            quantityLabel.text('مقدار (کارتن) *');
            quantityInput.attr('step', '1').attr('placeholder', 'تعداد کارتن اولیه یا تعدیل');
        } else {
            quantityLabel.text('مقدار (KG) *');
            quantityInput.attr('step', '0.001').attr('placeholder', 'مقدار اولیه یا مقدار تعدیل');
        }
    }

     function checkAndLoadBalances() {
        const transactionTypeName = transactionTypeSelect.find('option:selected').text();
        const partId = partSelect.val();
        const stationId = stationSelect.val();
        const dateJalali = transactionDateInput.val();

        if ((transactionTypeName.includes('کسر') || transactionTypeName.includes('اضافه')) && partId && stationId && dateJalali) {
            loadCurrentBalances(partId, stationId, dateJalali);
        } else {
            balanceContainer.hide();
        }
    }

    async function loadCurrentBalances(partId, stationId, dateJalali) {
        balanceContainer.show();
        balanceTableBody.html('<tr><td colspan="2" class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr>');
        const stationName = stationSelect.find('option:selected').text();
        const partName = partSelect.find('option:selected').text();
        const isCarton = parseInt(stationId) === CARTON_WAREHOUSE_ID;
        const unitDisplay = isCarton ? 'کارتن' : 'KG';

        $('#balance-date-display').text(dateJalali);
        $('#balance-station-display').text(stationName);
        $('#balance-part-display').text(partName);
        balanceUnitHeader.text(`موجودی (${unitDisplay})`); // Update header unit

        try {
            const response = await $.getJSON(apiGetInventoryUrl, {
                mode: 'stocktake_balance',
                part_id: partId,
                station_id: stationId,
                end_date: dateJalali
            });
             balanceTableBody.empty();
             if (response.success && response.data && Array.isArray(response.data)) {
                 let hasBalance = false;
                 response.data.forEach(item => {
                      // API returns BalanceValue (in older version)
                      const balanceValue = item.BalanceValue;
                      
                      if (Math.abs(balanceValue) > (isCarton ? 0 : 0.0001)) {
                          hasBalance = true;
                          const formattedBalance = isCarton ? parseInt(balanceValue) : parseFloat(balanceValue).toFixed(3);
                          balanceTableBody.append(`<tr><td class="text-center small">${item.StatusName || '-- بدون وضعیت --'}</td><td class="text-center small">${formattedBalance}</td></tr>`);
                      }
                 });
                 if (!hasBalance) {
                     balanceTableBody.html(`<tr><td colspan="2" class="text-center text-muted small">موجودی برای تمام وضعیت‌ها صفر است.</td></tr>`);
                 }
             } else if(response.success) {
                  balanceTableBody.html('<tr><td colspan="2" class="text-center text-muted small">موجودی برای این قطعه/انبار/تاریخ یافت نشد.</td></tr>');
             } else {
                  balanceTableBody.html(`<tr><td colspan="2" class="text-center text-warning small">${response.message || 'خطا در واکشی موجودی.'}</td></tr>`);
             }
        } catch (error) {
            console.error("Error fetching current balances:", error);
            balanceTableBody.html('<tr><td colspan="2" class="text-center text-danger small">خطای شبکه در دریافت موجودی.</td></tr>');
        }
    }

    // --- Initial Load ---
    setTimeout(() => { $('#flash-message').alert('close'); }, 5000);

    // Call initialize for edit mode
    // This will populate fields if in edit mode
    initializeEditMode();
    
    // Set initial quantity label/step (if not in edit mode, this sets it)
    if (!editMode) {
        updateQuantityInput();
    }
    
    // REQ 3: Focus logic on page load
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('focus_transaction_type') === 'true') {
        transactionTypeSelect.focus();
        // Clean up the URL
        urlParams.delete('focus_transaction_type');
        let newQuery = urlParams.toString().replace(/&$/, '');
        const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
        history.replaceState({}, document.title, newUrl);
    }
});
</script>


