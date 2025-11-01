<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('warehouse.view')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_stock_transactions';
const PRIMARY_KEY = 'TransactionID';
const RECORDS_PER_PAGE = 15;

// Station IDs Constants
const PACKAGING_STATION_ID = 10;
const CARTON_WAREHOUSE_STATION_ID = 11;
const CUSTOMER_STATION_ID = 13;

$editMode = false;
$itemToEdit = null;
$itemToEditJson = 'null';
$message = $_GET['message'] ?? ($_SESSION['message'] ?? ''); // Get message from GET first, then session
$message_type = $_GET['message_type'] ?? ($_SESSION['message_type'] ?? '');
unset($_SESSION['message'], $_SESSION['message_type']); // Clear session messages after reading

// Date Handling
if (isset($_GET['log_date']) && !empty($_GET['log_date'])) {
    $initial_log_date_jalali = $_GET['log_date'];
    $_SESSION['warehouse_transaction_date'] = $initial_log_date_jalali;
} elseif (isset($_SESSION['warehouse_transaction_date'])) {
    $initial_log_date_jalali = $_SESSION['warehouse_transaction_date'];
} else {
    $initial_log_date_jalali = to_jalali(date('Y-m-d'));
    $_SESSION['warehouse_transaction_date'] = $initial_log_date_jalali;
}

// Pagination Logic
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$total_records_query = $pdo->query("SELECT COUNT(*) FROM tbl_stock_transactions");
$total_records = $total_records_query ? $total_records_query->fetchColumn() : 0;
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

// Handle Delete POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!has_permission('warehouse.transactions.manage')) {
        $_SESSION['message'] = 'شما مجوز حذف تراکنش را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
        if ($delete_id) {
            $result = delete_record($pdo, TABLE_NAME, $delete_id, PRIMARY_KEY);
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        } else {
             $_SESSION['message'] = 'شناسه نامعتبر برای حذف.';
             $_SESSION['message_type'] = 'warning';
        }
    }
    $redirect_date = $_SESSION['warehouse_transaction_date'] ?? to_jalali(date('Y-m-d'));
    $redirect_page = $current_page;
    if (($result['success'] ?? false) && $total_records > 0) {
        $remaining_records = $total_records - 1;
        $new_total_pages = ceil($remaining_records / RECORDS_PER_PAGE);
        if ($current_page > $new_total_pages && $new_total_pages > 0) {
            $redirect_page = $new_total_pages;
        }
    }
    // Redirect with message parameters after delete
    $redirect_url = BASE_URL . "modules/warehouse/transactions.php?log_date=" . urlencode($redirect_date) . "&page=" . $redirect_page . "&message=" . urlencode($_SESSION['message']) . "&message_type=" . $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']); // Clear session after preparing URL
    header("Location: " . $redirect_url);
    exit;
}

// Handle Edit GET Request
if (isset($_GET['edit_id'])) {
    if (!has_permission('warehouse.transactions.manage')) {
        die('شما مجوز ویرایش تراکنش را ندارید.');
    }
    $editId = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($editId) {
        // Fetch transaction including new fields
        $itemToEdit = find_by_id($pdo, TABLE_NAME, $editId, PRIMARY_KEY);
        if ($itemToEdit) {
            $editMode = true;
            $partInfo = find_by_id($pdo, 'tbl_parts', $itemToEdit['PartID'], 'PartID');
            $itemToEdit['FamilyID'] = $partInfo['FamilyID'] ?? null;

            // Format numbers, handle both KG and Cartons
            $itemToEdit['GrossWeightKG'] = number_format((float)($itemToEdit['GrossWeightKG'] ?? 0), 3, '.', '');
            $itemToEdit['PalletWeightKG'] = number_format((float)($itemToEdit['PalletWeightKG'] ?? 0), 3, '.', '');
            $itemToEdit['NetWeightKG'] = number_format((float)($itemToEdit['NetWeightKG'] ?? 0), 3, '.', '');
            $itemToEdit['CartonQuantity'] = $itemToEdit['CartonQuantity'] !== null ? (int)$itemToEdit['CartonQuantity'] : null; // Keep carton as integer
            $itemToEdit['BaseWeightGR'] = number_format((float)($itemToEdit['BaseWeightGR'] ?? 0), 3, '.', '');
            $itemToEdit['FinalWeightGR'] = number_format((float)($itemToEdit['FinalWeightGR'] ?? 0), 3, '.', '');
            // SenderEmployeeID and ReceiverID are fetched directly

            $initial_log_date_jalali = to_jalali($itemToEdit['TransactionDate']);
            $_SESSION['warehouse_transaction_date'] = $initial_log_date_jalali;

            $itemToEditJson = json_encode($itemToEdit, JSON_UNESCAPED_UNICODE); // Ensure unicode is preserved
        } else {
             $_SESSION['message'] = 'تراکنش مورد نظر برای ویرایش یافت نشد.';
             $_SESSION['message_type'] = 'warning';
             $redirect_date = $_SESSION['warehouse_transaction_date'] ?? to_jalali(date('Y-m-d'));
             // Redirect with message parameters
             header("Location: " . BASE_URL . "modules/warehouse/transactions.php?log_date=" . urlencode($redirect_date) . "&message=" . urlencode($_SESSION['message']) . "&message_type=" . $_SESSION['message_type']);
             unset($_SESSION['message'], $_SESSION['message_type']); // Clear session after preparing URL
             exit;
        }
    }
}


// Fetch Data for Dropdowns and History
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$all_stations = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations ORDER BY StationName");
$palletTypes = find_all($pdo, "SELECT PalletTypeID, PalletName, PalletWeightKG FROM tbl_pallet_types ORDER BY PalletName");
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");
$receivers = find_all($pdo, "SELECT ReceiverID, ReceiverName FROM tbl_receivers ORDER BY ReceiverName"); // Fetch receivers

// Fetch recent transactions for history table with pagination
$history_query = "
    SELECT t.*, p.PartName, f.StationName as FromStation, ts.StationName as ToStation,
           ps.StatusName as StatusAfterName,
           u.Username as CreatorName, op.name as OperatorName,
           rec.ReceiverName -- Receiver Name
    FROM tbl_stock_transactions t
    JOIN tbl_parts p ON t.PartID = p.PartID
    JOIN tbl_stations f ON t.FromStationID = f.StationID
    JOIN tbl_stations ts ON t.ToStationID = ts.StationID
    LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
    LEFT JOIN tbl_users u ON t.CreatedBy = u.UserID
    LEFT JOIN tbl_employees op ON t.OperatorEmployeeID = op.EmployeeID
    LEFT JOIN tbl_receivers rec ON t.ReceiverID = rec.ReceiverID -- Join for receiver name
    ORDER BY t.TransactionDate DESC, t.TransactionID DESC
    LIMIT :limit OFFSET :offset
";
$history_stmt = $pdo->prepare($history_query);
$history_stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$history_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$history_stmt->execute();
$recentTransactions = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Page Title
$pageTitle = $editMode ? "ویرایش تراکنش انبار" : "ثبت تراکنش انبار";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div id="flash-message" class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars(urldecode($message)); // Decode URL encoded message from redirect ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form id="transaction-form">
     <?php if ($editMode && $itemToEdit): ?>
        <input type="hidden" name="transaction_id" id="transaction_id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
    <?php endif; ?>
    <div class="row">
        <!-- Left Column: Transaction Info -->
        <div class="col-lg-7">
            <div class="card content-card mb-4">
                <div class="card-header"><h5 class="mb-0">اطلاعات تراکنش و مقدار</h5></div>
                <div class="card-body">
                    <!-- Row 1: Date, Operator, Family -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="transaction_date" class="form-label">تاریخ *</label>
                            <input type="text" id="transaction_date" name="transaction_date" class="form-control persian-date"
                                   value="<?php echo $initial_log_date_jalali; ?>" required <?php echo $editMode ? 'readonly' : ''; ?>>
                             <?php if ($editMode): ?>
                                <small class="text-muted">تاریخ در حالت ویرایش ثابت است.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="operator_employee_id" class="form-label">نام عامل</label>
                            <select id="operator_employee_id" name="operator_employee_id" class="form-select">
                                <option value="">-- انتخاب --</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="col-md-4 mb-3">
                             <label for="family_id" class="form-label">خانواده قطعه *</label>
                             <select id="family_id" name="family_id" class="form-select" required>
                                 <option value="">-- انتخاب --</option>
                                 <?php foreach ($families as $family): ?>
                                     <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                    </div>
                    <!-- Row 2: Part, From Station -->
                     <div class="row">
                         <div class="col-md-6 mb-3">
                              <label for="part_id" class="form-label">قطعه *</label>
                              <select id="part_id" name="part_id" class="form-select" required disabled>
                                 <option value="">-- ابتدا خانواده --</option>
                              </select>
                         </div>
                         <div class="col-md-6 mb-3">
                            <label for="from_station_id" class="form-label">از ایستگاه (مبدا) *</label>
                            <select id="from_station_id" name="from_station_id" class="form-select station-select" required disabled>
                                <option value="">-- ابتدا خانواده --</option>
                            </select>
                         </div>
                    </div>
                    <!-- Row 3: To Station, Output Status -->
                    <div class="row align-items-center">
                         <div class="col-md-6 mb-3">
                             <label for="to_station_id" class="form-label">به ایستگاه (مقصد) *</label>
                             <select id="to_station_id" name="to_station_id" class="form-select station-select" required disabled>
                                <option value="">-- ابتدا خانواده --</option>
                             </select>
                         </div>
                         <div class="col-md-6 mb-3">
                            <label class="form-label">وضعیت خروجی قطعه</label>
                             <div>
                                 <span id="status_after_display" class="form-control-plaintext" style="display: none;">-</span>
                                 <select id="status_after_select" name="status_after_select" class="form-select d-inline-block w-auto" style="display: none;" tabindex="-1">
                                     <option value="">-- در حال بارگذاری --</option>
                                 </select>
                                 <input type="hidden" id="status_after_id" name="status_after_id">
                             </div>
                        </div>
                    </div>

                    <!-- Row 4: Receiver (Hidden initially) -->
                     <div class="row" id="sender_receiver_container" style="display: none;">
                         <div class="col-md-12 mb-3">
                              <label for="receiver_id" class="form-label">تحویل گیرنده *</label>
                              <div class="input-group">
                                 <select id="receiver_id" name="receiver_id" class="form-select">
                                     <option value="">-- انتخاب --</option>
                                      <?php foreach ($receivers as $receiver): ?>
                                         <option value="<?php echo $receiver['ReceiverID']; ?>"><?php echo htmlspecialchars($receiver['ReceiverName']); ?></option>
                                     <?php endforeach; ?>
                                 </select>
                                  <button class="btn btn-outline-secondary" type="button" id="add-receiver-btn-trigger" title="افزودن تحویل گیرنده جدید" tabindex="-1"><i class="bi bi-plus-lg"></i></button>
                             </div>
                         </div>
                    </div>

                    <!-- Row 5: Quantity (Weight/Carton), Pallet, Net Weight (if applicable) -->
                     <div class="row">
                         <div class="col-md-4 mb-3" id="quantity_input_container">
                             <label for="gross_weight_kg" class="form-label" id="quantity_label">وزن ناخالص (KG) *</label>
                             <input type="number" step="0.001" id="gross_weight_kg" name="gross_weight_kg" class="form-control quantity-input" placeholder="وزن کل با پالت">
                             <input type="number" step="1" min="1" id="carton_quantity" name="carton_quantity" class="form-control quantity-input" placeholder="تعداد کارتن" style="display: none;">
                         </div>
                        <div class="col-md-4 mb-3" id="pallet_container">
                             <label for="pallet_type_id" class="form-label">نوع پالت</label>
                             <select id="pallet_type_id" name="pallet_type_id" class="form-select">
                                 <option value="" data-weight-kg="0">-- بدون پالت --</option>
                                 <?php foreach ($palletTypes as $pallet): ?>
                                     <option value="<?php echo $pallet['PalletTypeID']; ?>" data-weight-kg="<?php echo $pallet['PalletWeightKG'] ?? 0; ?>"><?php echo htmlspecialchars($pallet['PalletName']); ?></option>
                                 <?php endforeach; ?>
                             </select>
                             <input type="hidden" id="pallet_weight_kg" name="pallet_weight_kg" value="0">
                             <small id="pallet-weight-display" class="form-text text-muted"></small>
                        </div>
                         <div class="col-md-4 mb-3" id="net_weight_container">
                            <label for="net_weight_kg_display" class="form-label">وزن خالص محاسبه شده (KG)</label>
                            <input type="text" id="net_weight_kg_display" name="net_weight_kg_display" class="form-control" readonly tabindex="-1">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Calculated Details & Submit -->
        <div class="col-lg-5">
             <div class="card content-card mb-4" id="calculation_details_card">
                 <div class="card-header"><h5 class="mb-0">جزئیات محاسبه شده (برای تراکنش وزنی)</h5></div>
                 <div class="card-body">
                     <!-- Row 1: Base Weight, Change Percent -->
                     <div class="row">
                         <div class="col-md-6 mb-3">
                            <label class="form-label">وزن پایه قطعه (gr)</label>
                            <input type="text" id="base_weight_gr_display" class="form-control" readonly tabindex="-1">
                            <input type="hidden" id="base_weight_gr" name="base_weight_gr">
                         </div>
                          <div class="col-md-6 mb-3">
                            <label class="form-label">درصد تغییر وزن (%)</label>
                            <input type="text" id="applied_weight_change_percent_display" class="form-control" readonly tabindex="-1">
                             <input type="hidden" id="applied_weight_change_percent" name="applied_weight_change_percent">
                         </div>
                    </div>
                    <!-- Row 2: Final Weight -->
                     <div class="mb-3">
                        <label class="form-label">وزن نهایی قطعه (gr)</label>
                        <input type="text" id="final_weight_gr_display" class="form-control" readonly tabindex="-1">
                        <input type="hidden" id="final_weight_gr" name="final_weight_gr">
                    </div>
                    <!-- Row 3: Route Status -->
                     <div class="mb-3">
                        <label class="form-label">وضعیت مسیر</label>
                        <input type="text" id="route_status_display" class="form-control" readonly tabindex="-1">
                        <input type="hidden" id="route_status" name="route_status">
                        <div id="route-error-msg" class="text-danger small mt-1"></div>
                     </div>
                 </div>
            </div>
             <div class="text-end">
                <button type="submit" id="submit-button" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> <?php echo $editMode ? 'ویرایش تراکنش' : 'ثبت تراکنش'; ?>
                </button>
                 <?php if ($editMode): ?>
                    <a href="transactions.php?log_date=<?php echo urlencode($_SESSION['warehouse_transaction_date'] ?? ''); ?>&page=<?php echo $current_page; ?>" class="btn btn-secondary btn-lg">لغو ویرایش</a>
                 <?php endif; ?>
                <div id="submit-feedback" class="mt-2 small"></div>
             </div>
        </div>
    </div>
</form>

<!-- History Table -->
<div class="card content-card mt-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">تاریخچه تراکنش‌ها</h5>
        <span>صفحه <?php echo $current_page; ?> از <?php echo $total_pages; ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th class="p-2">#</th>
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">قطعه</th>
                        <th class="p-2">از</th>
                        <th class="p-2">به</th>
                        <th class="p-2">مقدار</th>
                        <th class="p-2">واحد</th>
                        <th class="p-2">وضعیت خروجی</th>
                        <th class="p-2">مسیر</th>
                        <th class="p-2">عامل</th>
                        <th class="p-2">تحویل گیرنده</th>
                        <th class="p-2">ثبت کننده</th>
                        <th class="p-2">عملیات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                     <?php if (empty($recentTransactions)): ?>
                        <tr><td colspan="13" class="text-center p-3 text-muted">هنوز تراکنشی ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach($recentTransactions as $tx):
                            $status_map_display = [
                                'Standard' => 'استاندارد',
                                'NonStandardPending' => '<span class="badge bg-warning text-dark">انتظار</span>',
                                'NonStandardApproved' => '<span class="badge bg-success">مجاز</span>',
                                'RequiresSelection' => '<span class="badge bg-info text-dark">انتخاب</span>'
                            ];
                            $routeStatusDisplay = $status_map_display[$tx['RouteStatus']] ?? $tx['RouteStatus'];

                            // Determine value and unit based on stations involved
                            $isCartonHistory = (
                                ($tx['FromStationID'] == PACKAGING_STATION_ID && $tx['ToStationID'] == CARTON_WAREHOUSE_STATION_ID) ||
                                ($tx['FromStationID'] == CARTON_WAREHOUSE_STATION_ID && $tx['ToStationID'] == CUSTOMER_STATION_ID) ||
                                ($tx['FromStationID'] == CARTON_WAREHOUSE_STATION_ID && $tx['ToStationID'] == CARTON_WAREHOUSE_STATION_ID && $tx['TransactionTypeID'] != null) // Stocktake
                            );
                            $displayValue = $isCartonHistory
                                                ? (($tx['CartonQuantity'] !== null) ? (int)$tx['CartonQuantity'] : '-')
                                                : (($tx['NetWeightKG'] !== null) ? number_format((float)$tx['NetWeightKG'], 3) : '-');
                            $displayUnit = $isCartonHistory ? 'کارتن' : 'KG';

                            // For stocktake, show +/- based on effect
                            if ($tx['TransactionTypeID'] != null && $tx['FromStationID'] == $tx['ToStationID']) {
                                $st_type = find_by_id($pdo, 'tbl_transaction_types', $tx['TransactionTypeID'], 'TypeID');
                                if ($st_type && $st_type['StockEffect'] < 0) {
                                    $displayValue = '-' . abs((int)$displayValue);
                                } elseif ($st_type && $st_type['StockEffect'] > 0 && $st_type['TypeName'] != 'موجودی اولیه') {
                                     $displayValue = '+' . abs((int)$displayValue);
                                } else {
                                     // For 'موجودی اولیه', just show the number
                                     $displayValue = abs((int)$displayValue);
                                }
                            }
                        ?>
                        <tr>
                            <td class="p-2"><?php echo $tx['TransactionID']; ?></td>
                            <td class="p-2"><?php echo to_jalali($tx['TransactionDate']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['PartName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['FromStation']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['ToStation']); ?></td>
                            <td class="p-2"><?php echo $displayValue; ?></td>
                            <td class="p-2"><?php echo $displayUnit; ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['StatusAfterName'] ?? '-- بدون وضعیت --'); ?></td>
                            <td class="p-2"><?php echo $routeStatusDisplay; ?></td>
                            <td class="p-2 small"><?php echo htmlspecialchars($tx['OperatorName'] ?? '-'); ?></td>
                             <td class="p-2 small"><?php echo htmlspecialchars($tx['ReceiverName'] ?? '-'); ?></td>
                            <td class="p-2 small"><?php echo htmlspecialchars($tx['CreatorName'] ?? '-'); ?></td>
                            <td class="p-2 text-nowrap">
                                <?php if (has_permission('warehouse.transactions.manage')): // Only show buttons if user can manage ?>
                                    <a href="?edit_id=<?php echo $tx['TransactionID']; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm py-0 px-1 delete-btn" data-tx-id="<?php echo $tx['TransactionID']; ?>" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-center">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&log_date=<?php echo urlencode($initial_log_date_jalali); ?>">قبلی</a>
                </li>
                <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&log_date=' . urlencode($initial_log_date_jalali) . '">1</a></li>';
                        if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&log_date=<?php echo urlencode($initial_log_date_jalali); ?>"><?php echo $i; ?></a>
                    </li>
                <?php
                    endfor;
                     if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&log_date=' . urlencode($initial_log_date_jalali) . '">' . $total_pages . '</a></li>';
                    }
                ?>
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&log_date=<?php echo urlencode($initial_log_date_jalali); ?>">بعدی</a>
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
      <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">آیا از حذف این تراکنش مطمئن هستید؟ این عمل غیرقابل بازگشت است.</div>
      <div class="modal-footer">
        <form id="deleteForm" method="POST" action="transactions.php?page=<?php echo $current_page; ?>" class="d-inline">
            <input type="hidden" name="delete_id" id="deleteTransactionIdInput">
            <button type="submit" class="btn btn-danger">بله، حذف کن</button>
        </form>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Receiver Modal -->
<div class="modal fade" id="addReceiverModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">افزودن تحویل گیرنده جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <form id="add-receiver-form">
              <div class="mb-3">
                  <label for="new_receiver_name" class="form-label">نام تحویل گیرنده (شرکت یا شخص)</label>
                  <input type="text" class="form-control" id="new_receiver_name" name="receiver_name" required>
              </div>
              <div id="add-receiver-feedback" class="small mt-2"></div>
          </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="save-new-receiver-btn">ذخیره</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
      </div>
    </div>
  </div>
</div>


<?php include __DIR__ . '/../../templates/footer.php'; ?>

<!-- Include the updated JavaScript -->
<script>
$(document).ready(function() {
    
    // --- *** GLOBAL AJAX FIX FOR BOM *** ---
    $.ajaxSetup({
        dataFilter: function (data, type) {
            if (type === 'json') {
                // Remove potential BOM character \uFEFF at the start
                if (data.charCodeAt(0) === 0xFEFF) {
                    console.log("BOM detected in JSON response, stripping.");
                    return data.substring(1);
                }
            }
            return data;
        }
    });
    // --- *** END GLOBAL AJAX FIX *** ---


    const apiHelperUrl = '<?php echo BASE_URL; ?>api/get_warehouse_calculations.php';
    const apiRecordUrl = '<?php echo BASE_URL; ?>api/record_transaction.php';
    const apiGetStatusesUrl = '<?php echo BASE_URL; ?>api/api_get_statuses_by_family.php';
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php';
    const apiUpdateSessionUrl = '<?php echo BASE_URL; ?>api/api_update_session.php';
    const apiAddReceiverUrl = '<?php echo BASE_URL; ?>api/api_add_receiver.php'; // Correct API URL

    // Station IDs from PHP Constants
    const PACKAGING_STATION_ID = <?php echo PACKAGING_STATION_ID; ?>;
    const CARTON_WAREHOUSE_STATION_ID = <?php echo CARTON_WAREHOUSE_STATION_ID; ?>;
    const CUSTOMER_STATION_ID = <?php echo CUSTOMER_STATION_ID; ?>;

    const familySelect = $('#family_id');
    const partSelect = $('#part_id');
    const fromStationSelect = $('#from_station_id');
    const toStationSelect = $('#to_station_id');
    const transactionDateInput = $('#transaction_date');
    const operatorSelect = $('#operator_employee_id');
    const receiverSelect = $('#receiver_id');
    const addReceiverBtnTrigger = $('#add-receiver-btn-trigger');
    const senderReceiverContainer = $('#sender_receiver_container');
    const palletSelect = $('#pallet_type_id');
    const palletWeightHidden = $('#pallet_weight_kg');
    const palletWeightDisplay = $('#pallet-weight-display');
    const quantityInputContainer = $('#quantity_input_container');
    const quantityLabel = $('#quantity_label');
    const grossWeightInput = $('#gross_weight_kg');
    const cartonQuantityInput = $('#carton_quantity');
    const palletContainer = $('#pallet_container');
    const netWeightContainer = $('#net_weight_container');
    const netWeightInput = $('#net_weight_kg_display');
    const calculationDetailsCard = $('#calculation_details_card');
    const baseWeightDisplay = $('#base_weight_gr_display');
    const baseWeightHidden = $('#base_weight_gr');
    const changePercentDisplay = $('#applied_weight_change_percent_display');
    const changePercentHidden = $('#applied_weight_change_percent');
    const finalWeightDisplay = $('#final_weight_gr_display');
    const finalWeightHidden = $('#final_weight_gr');
    const routeStatusDisplay = $('#route_status_display');
    const routeStatusHidden = $('#route_status');
    const statusAfterDisplaySpan = $('#status_after_display');
    const statusAfterSelect = $('#status_after_select');
    const statusAfterHidden = $('#status_after_id');
    const routeErrorMsg = $('#route-error-msg');
    const submitButton = $('#submit-button');
    const submitFeedback = $('#submit-feedback');
    const transactionIdInput = $('#transaction_id');
    const addReceiverModal = new bootstrap.Modal(document.getElementById('addReceiverModal'));
    const addReceiverForm = $('#add-receiver-form');
    const newReceiverNameInput = $('#new_receiver_name');
    const addReceiverFeedback = $('#add-receiver-feedback');
    const saveNewReceiverBtn = $('#save-new-receiver-btn');

    const allStationsData = <?php echo json_encode($all_stations); ?>;
    const editMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const itemToEdit = <?php echo $itemToEditJson; ?>;
    const currentPageNumber = <?php echo $current_page; ?>; // Get current page number

    let calculationDetailsCache = null;

    // --- Event Listeners ---
    familySelect.on('change', handleFamilyChange);
    partSelect.on('change', handlePartChange);
    fromStationSelect.on('change', handleStationChange);
    toStationSelect.on('change', handleStationChange);
    transactionDateInput.on('change', handleDateChange);
    palletSelect.on('change', updatePalletWeight);
    grossWeightInput.on('input', calculateNetWeight);
    grossWeightInput.on('keydown', handleQuantityEnter);
    cartonQuantityInput.on('keydown', handleQuantityEnter);
    $('#transaction-form').on('submit', handleSubmit);
    $(document).on('click', '.delete-btn', handleDeleteClick);
    // REMOVED: receiverSelect.on('change', handleReceiverChange);
    addReceiverBtnTrigger.on('click', () => addReceiverModal.show());
    saveNewReceiverBtn.on('click', saveNewReceiver);

    // --- Initialization ---
    initializeForm();

    // --- Event Handler Functions ---
    async function handleFamilyChange() {
        partSelect.prop('disabled', true).html('<option value="">...</option>');
        fromStationSelect.prop('disabled', true).html('<option value="">-- ابتدا قطعه --</option>');
        toStationSelect.prop('disabled', true).html('<option value="">-- ابتدا قطعه --</option>');
        resetCalculationFields();
        const familyId = $(this).val();
        if (familyId) {
            await populateParts(familyId);
            await filterStations(familyId); // Fetch stations based on family
            await populateStatusDropdown(familyId); // Populate statuses based on family
        } else {
            partSelect.html('<option value="">-- ابتدا خانواده --</option>');
            await populateStatusDropdown(null); // Clear/disable status dropdown
        }
        updateFormVisibility(); // Update visibility after family change
    }

    function handlePartChange() {
        if(fromStationSelect.val() && toStationSelect.val() && transactionDateInput.val()) {
           fetchCalculationDetails();
        } else {
            resetCalculationFields(false); // Keep status dropdown content
        }
        updateFormVisibility(); // Check visibility on part change
    }

    function handleStationChange() {
        updateFormVisibility();
        fetchCalculationDetails();
    }

    function handleDateChange() {
        const newDate = $(this).val();
        // Save date to session via AJAX
        $.post(apiUpdateSessionUrl, { key: 'warehouse_transaction_date', value: newDate })
            .fail(function(jqXHR) {
                console.error(`AJAX error updating session key 'warehouse_transaction_date':`, jqXHR.responseText);
            });
        fetchCalculationDetails(); // Fetch details for the new date
    }


    function handleQuantityEnter(e) {
        if ((e.key === 'Enter' || e.keyCode === 13)) {
            e.preventDefault();
            $('#transaction-form').trigger('submit');
        }
    }

    function handleDeleteClick() {
        const txId = $(this).data('tx-id');
        $('#deleteTransactionIdInput').val(txId);
    }

    // --- Data Fetching and Population ---
    async function populateParts(familyId, selectedPartId = null) {
        partSelect.html('<option value="">در حال بارگذاری...</option>');
        try {
            const response = await $.ajax({
                url: apiPartsUrl,
                data: { family_id: familyId },
                dataType: 'json'
            });
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

    async function filterStations(familyId, selectedFromId = null, selectedToId = null) {
        fromStationSelect.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
        toStationSelect.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
        if (!familyId) {
             fromStationSelect.html('<option value="">-- ابتدا خانواده --</option>');
             toStationSelect.html('<option value="">-- ابتدا خانواده --</option>');
            return;
        }
        try {
            const response = await $.ajax({
                url: apiHelperUrl,
                data: { action: 'get_relevant_stations', family_id: familyId },
                dataType: 'json'
            });
            fromStationSelect.html('<option value="">-- انتخاب مبدا --</option>');
            toStationSelect.html('<option value="">-- انتخاب مقصد --</option>');
            if (response.success && response.data && Array.isArray(response.data)) {
                 const relevantStationIds = response.data.map(id => String(id));
                 allStationsData.forEach(station => {
                     if (relevantStationIds.includes(String(station.StationID))) {
                         const optionHtml = `<option value="${station.StationID}">${station.StationName}</option>`;
                         fromStationSelect.append(optionHtml);
                         toStationSelect.append(optionHtml);
                     }
                 });
                fromStationSelect.prop('disabled', false);
                toStationSelect.prop('disabled', false);
                if (selectedFromId) fromStationSelect.val(selectedFromId);
                if (selectedToId) toStationSelect.val(selectedToId);
            } else {
                 fromStationSelect.html('<option value="">خطا یا ایستگاه ناموجود</option>');
                 toStationSelect.html('<option value="">خطا یا ایستگاه ناموجود</option>');
            }
        } catch (error) {
            console.error("Error fetching relevant stations:", error);
            fromStationSelect.html('<option value="">خطا در بارگذاری</option>');
            toStationSelect.html('<option value="">خطا در بارگذاری</option>');
        }
    }

    async function populateStatusDropdown(familyId, selectedStatusId = null) {
        statusAfterSelect.prop('disabled', true).html('<option value="">در حال بارگذاری وضعیت‌ها...</option>');
        statusAfterDisplaySpan.hide();
        statusAfterHidden.val('');

        try {
             const response = await $.ajax({
                url: apiGetStatusesUrl,
                data: { family_id: familyId },
                dataType: 'json'
            });
            statusAfterSelect.empty().append('<option value="">-- انتخاب وضعیت --</option>');

            if (response.success && response.data.length > 0) {
                response.data.forEach(status => {
                    statusAfterSelect.append($('<option>', { value: status.StatusID, text: status.StatusName }));
                });
                statusAfterSelect.append('<option value="NULL">-- بدون وضعیت --</option>');
                statusAfterSelect.prop('disabled', false);
                if (selectedStatusId !== null && selectedStatusId !== undefined) {
                    statusAfterSelect.val(selectedStatusId === null ? 'NULL' : String(selectedStatusId));
                }
            } else {
                 statusAfterSelect.append('<option value="" disabled>-- وضعیتی یافت نشد --</option>');
                 statusAfterSelect.append('<option value="NULL">-- بدون وضعیت --</option>');
                 statusAfterSelect.prop('disabled', false);
            }
        } catch (error) {
            console.error("Error fetching statuses:", error);
            statusAfterSelect.html('<option value="">-- خطا در بارگذاری --</option>');
        }
    }

     async function populateReceivers(selectedReceiverId = null) {
        receiverSelect.html('<option value="">...بارگذاری</option>');
        try {
            const response = await $.ajax({
                url: apiHelperUrl,
                data: { action: 'get_receivers' },
                dataType: 'json'
            });
            receiverSelect.html('<option value="">-- انتخاب --</option>');
            // REMOVED: receiverSelect.append('<option value="add_new">-- افزودن جدید --</option>');
            if (response.success && response.data.length > 0) {
                response.data.forEach(receiver => {
                    receiverSelect.append($('<option>', { value: receiver.ReceiverID, text: receiver.ReceiverName }));
                });
                if (selectedReceiverId) {
                    receiverSelect.val(selectedReceiverId);
                }
            } else {
                 receiverSelect.append('<option value="" disabled>گیرنده‌ای یافت نشد</option>');
            }
        } catch (error) {
            console.error("Error fetching receivers:", error);
            receiverSelect.html('<option value="">خطا</option>');
        }
    }


    // --- Calculation and Display Logic ---
    async function fetchCalculationDetails() {
        const partId = partSelect.val();
        const fromStationId = fromStationSelect.val();
        const toStationId = toStationSelect.val();
        const transactionDate = transactionDateInput.val();
        resetCalculationFields(false); // Keep status dropdown content

        if (!partId || !fromStationId || !toStationId || !transactionDate) return;

        baseWeightDisplay.val('...');
        calculationDetailsCard.show();

        try {
            const response = await $.ajax({
                url: apiHelperUrl,
                data: {
                    action: 'calculate_details',
                    part_id: partId,
                    from_station_id: fromStationId,
                    to_station_id: toStationId,
                    transaction_date: transactionDate
                },
                dataType: 'json'
            });
            calculationDetailsCache = null;
            if (response.success && response.data) {
                calculationDetailsCache = response.data;
                await displayCalculationDetails(response.data); // Pass the whole data object
            } else {
                routeErrorMsg.text(response.message || 'خطا در دریافت جزئیات محاسبه.');
                await displayCalculationDetails({});
            }
        } catch (error) {
            console.error("Error fetching calculation details:", error);
            routeErrorMsg.text('خطای شبکه در ارتباط با سرور.');
            await displayCalculationDetails({});
        }
    }

    async function displayCalculationDetails(details) {
         if (!details.is_carton_transaction) {
             baseWeightDisplay.val(details.base_weight_gr !== null ? parseFloat(details.base_weight_gr).toFixed(3) : '');
             baseWeightHidden.val(details.base_weight_gr ?? '');
             changePercentDisplay.val(details.change_percent !== null && details.change_percent !== undefined ? parseFloat(details.change_percent).toFixed(2) + '%' : '0.00%');
             changePercentHidden.val(details.change_percent ?? 0);
             finalWeightDisplay.val(details.final_weight_gr !== null ? parseFloat(details.final_weight_gr).toFixed(3) : '');
             finalWeightHidden.val(details.final_weight_gr ?? '');
             calculationDetailsCard.show();
         } else {
             baseWeightDisplay.val(''); baseWeightHidden.val('');
             changePercentDisplay.val(''); changePercentHidden.val('');
             finalWeightDisplay.val(''); finalWeightHidden.val('');
             calculationDetailsCard.hide();
         }

         routeStatusDisplay.val(details.route_status_display || '');
         routeStatusHidden.val(details.route_status || '');
         routeErrorMsg.text(details.error || '');

         statusAfterDisplaySpan.hide().text('-');
         statusAfterSelect.hide().attr('tabindex', '-1');
         statusAfterHidden.val('');

         const currentFamilyId = familySelect.val();
         let statusIdToSelect = details.StatusAfterID;
         // In edit mode, prioritize the value from itemToEdit if it exists, but ONLY if the route logic hasn't already determined a specific status
         if (editMode && itemToEdit && details.route_status !== 'RequiresSelection' && details.StatusAfterID === null) {
              statusIdToSelect = itemToEdit.StatusAfterID;
         }

         if (!statusAfterSelect.find('option').length > 1 || statusAfterSelect.data('current-family') != currentFamilyId) {
            await populateStatusDropdown(currentFamilyId, statusIdToSelect);
            statusAfterSelect.data('current-family', currentFamilyId);
         } else if (statusIdToSelect !== null && statusIdToSelect !== undefined) {
            statusAfterSelect.val(statusIdToSelect === null ? 'NULL' : String(statusIdToSelect));
         }

         // Handle status display/selection logic
         if(details.needs_selection && details.possible_statuses && details.possible_statuses.length > 0) {
             statusAfterSelect.find('option').each(function() {
                 const optionValue = $(this).val();
                 // Keep "", "NULL", and the possible IDs visible
                 const keep = optionValue === "" || optionValue === "NULL" || details.possible_statuses.includes(parseInt(optionValue));
                 $(this).toggle(keep);
                 // Deselect if current selection is no longer valid
                 if (!keep && $(this).is(':selected')) {
                     statusAfterSelect.val("");
                 }
             });

            const visibleOptions = statusAfterSelect.find('option:visible').not('[value=""], [value="NULL"]');
            const currentSelected = statusAfterSelect.val();

             // In edit mode, if the original status is among the possible ones, select it
            if (editMode && itemToEdit && details.possible_statuses.includes(parseInt(itemToEdit.StatusAfterID)) && statusAfterSelect.find(`option[value="${itemToEdit.StatusAfterID}"]`).is(':visible')) {
                 statusAfterSelect.val(itemToEdit.StatusAfterID === null ? 'NULL' : itemToEdit.StatusAfterID);
            }
             // If only one valid option remains and nothing is selected, select it
            else if (visibleOptions.length === 1 && !currentSelected) {
                  statusAfterSelect.val(visibleOptions.first().val());
            }
             // If current selection is invalid (hidden), reset
            else if (currentSelected && !statusAfterSelect.find(`option[value="${currentSelected}"]`).is(':visible')) {
                statusAfterSelect.val("");
            }
             statusAfterSelect.show().removeAttr('tabindex');
             statusAfterDisplaySpan.hide();

         } else if (details.StatusAfterID !== null && details.StatusAfterID !== undefined) {
             const statusName = details.possible_status_names ? details.possible_status_names[details.StatusAfterID] : null;

             if (statusName) {
                 statusAfterDisplaySpan.text(statusName).show();
                 statusAfterHidden.val(details.StatusAfterID);
             } else {
                 statusAfterDisplaySpan.text(`-- وضعیت (ID: ${details.StatusAfterID}) --`).show();
                 statusAfterHidden.val(details.StatusAfterID);
                 console.warn(`Status ID ${details.StatusAfterID} determined, but name not found in possible_status_names:`, details.possible_status_names || "Not Provided");
             }
             statusAfterSelect.hide().attr('tabindex', '-1');
         } else {
             statusAfterDisplaySpan.text('-- بدون وضعیت --').show();
             statusAfterHidden.val("NULL"); // Explicitly set hidden value to "NULL" string
             statusAfterSelect.hide().attr('tabindex', '-1');
         }
    }


    function resetCalculationFields(clearStatus = true) {
        calculationDetailsCache = null;
        baseWeightDisplay.val(''); baseWeightHidden.val('');
        changePercentDisplay.val(''); changePercentHidden.val('');
        finalWeightDisplay.val(''); finalWeightHidden.val('');
        routeStatusDisplay.val(''); routeStatusHidden.val('');
        routeErrorMsg.text('');
        if (clearStatus) {
            statusAfterDisplaySpan.hide().text('-');
            statusAfterSelect.hide().empty().append('<option value="">-- ابتدا خانواده --</option>').attr('tabindex', '-1').prop('disabled', true);
            statusAfterHidden.val('');
        }
    }

    // --- UI Update Functions ---
    function updateFormVisibility() {
        const fromStationId = parseInt(fromStationSelect.val() || 0);
        const toStationId = parseInt(toStationSelect.val() || 0);
        const isCartonTx = (fromStationId === PACKAGING_STATION_ID && toStationId === CARTON_WAREHOUSE_STATION_ID) ||
                           (fromStationId === CARTON_WAREHOUSE_STATION_ID && toStationId === CUSTOMER_STATION_ID);
        const isToCustomer = fromStationId === CARTON_WAREHOUSE_STATION_ID && toStationId === CUSTOMER_STATION_ID;

        // Toggle Quantity Input Type
        if (isCartonTx) {
            quantityLabel.text('تعداد کارتن *');
            grossWeightInput.hide().prop('required', false).val('');
            cartonQuantityInput.show().prop('required', true);
            palletContainer.hide();
            netWeightContainer.hide();
            calculationDetailsCard.hide();
        } else {
            quantityLabel.text('وزن ناخالص (KG) *');
            grossWeightInput.show().prop('required', true);
            cartonQuantityInput.hide().prop('required', false).val('');
            palletContainer.show();
            netWeightContainer.show();
            // Show calculation card only if needed data is available
            calculationDetailsCard.toggle(!!(partSelect.val() && fromStationId && toStationId));
        }

        // Toggle Sender/Receiver
        senderReceiverContainer.toggle(isToCustomer);
        receiverSelect.prop('required', isToCustomer);
        addReceiverBtnTrigger.prop('disabled', !isToCustomer);

        if (!isCartonTx) { calculateNetWeight(); }
         else { netWeightInput.val(''); }
    }

    function updatePalletWeight() {
        const selectedOption = palletSelect.find('option:selected');
        const weightKg = parseFloat(selectedOption.data('weight-kg') || 0);
        palletWeightHidden.val(weightKg.toFixed(3));
        palletWeightDisplay.text(weightKg > 0 ? `(${weightKg.toFixed(3)} KG)` : '');
        calculateNetWeight();
    }

    function calculateNetWeight() {
        const fromStationId = parseInt(fromStationSelect.val() || 0);
        const toStationId = parseInt(toStationSelect.val() || 0);
        if (!((fromStationId === PACKAGING_STATION_ID && toStationId === CARTON_WAREHOUSE_STATION_ID) ||
              (fromStationId === CARTON_WAREHOUSE_STATION_ID && toStationId === CUSTOMER_STATION_ID)))
        {
            const gross = parseFloat(grossWeightInput.val()) || 0;
            const pallet = parseFloat(palletWeightHidden.val()) || 0;
            const net = gross - pallet;
            netWeightInput.val(net.toFixed(3));
        } else {
            netWeightInput.val('');
        }
    }

    // --- Add New Receiver Logic (MODIFIED) ---
     async function saveNewReceiver() {
        const newName = newReceiverNameInput.val().trim();
        addReceiverFeedback.text('').removeClass('text-danger text-success text-warning'); // Reset classes
        saveNewReceiverBtn.prop('disabled', true);

        if (!newName) {
            addReceiverFeedback.text('نام تحویل گیرنده الزامی است.').addClass('text-danger');
            saveNewReceiverBtn.prop('disabled', false);
            return;
        }

        try {
            const response = await $.ajax({
                url: apiAddReceiverUrl,
                type: 'POST',
                data: { receiver_name: newName },
                dataType: 'json'
            });

            // --- *** Handle Both Success and Duplicate Cases *** ---
            if (response.success && response.new_receiver_id) {
                // Successfully added new receiver
                receiverSelect.append($('<option>', {
                    value: response.new_receiver_id,
                    text: response.receiver_name,
                    selected: true // Select the newly added option
                }));
                addReceiverModal.hide();
                addReceiverForm[0].reset();
                addReceiverFeedback.text('');
            } else if (!response.success && response.existing_receiver_id) {
                // Receiver already exists, select the existing one
                addReceiverFeedback.text(response.message).addClass('text-warning'); // Show warning
                receiverSelect.val(response.existing_receiver_id); // Select existing
                addReceiverModal.hide(); // Close modal
                addReceiverForm[0].reset();
            } else {
                // Other errors
                 addReceiverFeedback.text(response.message || 'خطا در ثبت.').addClass('text-danger');
            }
            // --- *** End Handling *** ---

        } catch (error) {
             console.error("Save receiver error:", error);
             let errorMsg = 'خطای ناشناخته در ارتباط با سرور.';
             if (error.status === 409) { // Handle 409 Conflict specifically
                 errorMsg = error.responseJSON?.message || 'گیرنده با این نام موجود است.';
                 addReceiverFeedback.text(errorMsg).addClass('text-warning');
                 if (error.responseJSON?.existing_receiver_id) {
                     receiverSelect.val(error.responseJSON.existing_receiver_id);
                     addReceiverModal.hide();
                     addReceiverForm[0].reset();
                 }
             } else if (error.status === 404) {
                 errorMsg = `خطای 404: فایل API (${apiAddReceiverUrl}) یافت نشد.`;
                  addReceiverFeedback.text(`خطا: ${errorMsg}`).addClass('text-danger');
             } else if (error.responseJSON && error.responseJSON.message) {
                 errorMsg = error.responseJSON.message;
                  addReceiverFeedback.text(`خطا: ${errorMsg}`).addClass('text-danger');
             } else if (error.responseText) {
                 errorMsg = `خطای سرور: ${error.status} - ${error.responseText.substring(0, 100)}`;
                  addReceiverFeedback.text(`خطا: ${errorMsg}`).addClass('text-danger');
             } else {
                 addReceiverFeedback.text(`خطا: ${errorMsg}`).addClass('text-danger');
             }
        } finally {
            saveNewReceiverBtn.prop('disabled', false);
        }
     }


    // --- Form Submit Handler ---
    async function handleSubmit(e) {
        e.preventDefault();
        submitFeedback.text('در حال ثبت...').removeClass('text-success text-danger').addClass('text-info');
        submitButton.prop('disabled', true);

        const formData = $(this).serializeArray();
        let dataToSend = {};
        formData.forEach(item => {
            if (!item.name.endsWith('_display')) {
                 dataToSend[item.name] = item.value;
            }
        });

        // Determine quantity field based on visibility
        const isCartonVisible = cartonQuantityInput.is(':visible');
        if (isCartonVisible) {
            dataToSend['carton_quantity'] = cartonQuantityInput.val();
             if (!dataToSend['carton_quantity'] || parseInt(dataToSend['carton_quantity']) <= 0) {
                 submitFeedback.text('خطا: تعداد کارتن باید عدد مثبت باشد.').removeClass('text-info').addClass('text-danger');
                 submitButton.prop('disabled', false);
                 return;
             }
            delete dataToSend['gross_weight_kg']; // Remove weight field if carton is used
        } else {
            dataToSend['gross_weight_kg'] = grossWeightInput.val();
             if (!dataToSend['gross_weight_kg'] || parseFloat(dataToSend['gross_weight_kg']) < 0) {
                 submitFeedback.text('خطا: وزن ناخالص باید عدد غیرمنفی باشد.').removeClass('text-info').addClass('text-danger');
                 submitButton.prop('disabled', false);
                 return;
             }
            delete dataToSend['carton_quantity']; // Remove carton field if weight is used
        }

        // Handle Status ID based on display/select visibility
        if (statusAfterSelect.is(':visible')) {
           dataToSend['status_after_select'] = statusAfterSelect.val();
            // Allow "NULL" string as a valid selection
            if (!dataToSend['status_after_select']) {
                submitFeedback.text('خطا: لطفاً وضعیت خروجی قطعه را انتخاب کنید.').removeClass('text-info').addClass('text-danger');
                submitButton.prop('disabled', false);
                return;
            }
            delete dataToSend['status_after_id']; // Remove hidden input value
        } else {
             dataToSend['status_after_id'] = statusAfterHidden.val();
             delete dataToSend['status_after_select']; // Remove select value
        }
        // Ensure "NULL" string is handled correctly before sending to API
        if (dataToSend['status_after_id'] === 'NULL') dataToSend['status_after_id'] = '';
        if (dataToSend['status_after_select'] === 'NULL') dataToSend['status_after_select'] = '';


        delete dataToSend['family_id'];
        delete dataToSend['net_weight_kg_display'];
        // Note: sender_employee_id is not in the form, so it won't be in dataToSend

        console.log("Data to send:", dataToSend); // DEBUG

        try {
            const recordResponse = await $.ajax({
                url: apiRecordUrl,
                type: 'POST',
                data: dataToSend,
                dataType: 'json'
            });

            console.log("API Response Parsed:", recordResponse); // DEBUG

            if (recordResponse.success) {
                 const logDate = encodeURIComponent(transactionDateInput.val());
                 const message = encodeURIComponent(recordResponse.message);
                 const messageType = 'success';
                 let redirectUrl = `transactions.php?log_date=${logDate}&message=${message}&message_type=${messageType}`;
                 // Go back to the correct page if editing
                 if (editMode) {
                     redirectUrl += `&page=${currentPageNumber}`;
                 } else {
                     redirectUrl += `&page=1`; // Go to page 1 for new entries
                     redirectUrl += `&focus_operator=true`; // *** REQ 5: Add focus flag ***
                 }
                  window.location.href = redirectUrl;
                  return; // Exit function after redirect call

            } else {
                 submitFeedback.text(recordResponse.message || 'خطا در ثبت.').removeClass('text-info text-danger').addClass('text-danger');
                 submitButton.prop('disabled', false);
            }
        } catch (error) { // Catch AJAX or JSON parse errors
            console.error("Submit error:", error);
            let errorMsg = 'خطای ناشناخته در ارتباط با سرور.';
            if (error.responseJSON && error.responseJSON.message) {
                 errorMsg = error.responseJSON.message;
            } else if (error.responseText) {
                 errorMsg = `خطای سرور: ${error.status} - ${error.responseText.substring(0, 150)}`;
            } else if (error.statusText) {
                errorMsg = `خطای شبکه: ${error.statusText}`;
            }
            submitFeedback.text(`خطا: ${errorMsg}`).removeClass('text-info').addClass('text-danger');
            submitButton.prop('disabled', false);
        }
    }


    // --- Initialization and Edit Mode Population ---
    async function populateFormForEdit() {
        if (!itemToEdit) return;
        operatorSelect.val(itemToEdit.OperatorEmployeeID || "");
        familySelect.val(itemToEdit.FamilyID);
        await populateParts(itemToEdit.FamilyID, itemToEdit.PartID);
        await filterStations(itemToEdit.FamilyID, itemToEdit.FromStationID, itemToEdit.ToStationID);
        await populateReceivers(itemToEdit.ReceiverID); // Load and select receiver

        const isCartonEdit = (itemToEdit.FromStationID == PACKAGING_STATION_ID && itemToEdit.ToStationID == CARTON_WAREHOUSE_STATION_ID) ||
                             (itemToEdit.FromStationID == CARTON_WAREHOUSE_STATION_ID && itemToEdit.ToStationID == CUSTOMER_STATION_ID);

        if (isCartonEdit) {
            cartonQuantityInput.val(itemToEdit.CartonQuantity);
            grossWeightInput.val('');
            palletSelect.val('');
            updatePalletWeight();
        } else {
            grossWeightInput.val(itemToEdit.GrossWeightKG);
            palletSelect.val(itemToEdit.PalletTypeID || "");
            updatePalletWeight();
            cartonQuantityInput.val('');
        }
        calculateNetWeight();

        if (itemToEdit.FromStationID == CARTON_WAREHOUSE_STATION_ID && itemToEdit.ToStationID == CUSTOMER_STATION_ID) {
            // REMOVED: senderSelect.val(itemToEdit.SenderEmployeeID || "");
            receiverSelect.val(itemToEdit.ReceiverID || "");
        } else {
            // REMOVED: senderSelect.val("");
            receiverSelect.val("");
        }

        updateFormVisibility();
        // Delay fetching calculation details slightly to ensure dependent dropdowns are populated
        setTimeout(fetchCalculationDetails, 300); // Increased delay slightly
    }

    async function initializeForm() {
        updatePalletWeight();
        await populateReceivers(); // Populate receivers on initial load
        if (editMode && itemToEdit) {
            await populateFormForEdit();
        } else {
            const initialFamilyId = familySelect.val();
            if (initialFamilyId) {
                await populateParts(initialFamilyId);
                await filterStations(initialFamilyId);
                await populateStatusDropdown(initialFamilyId);
                partSelect.prop('disabled', false); // Enable part select if family is pre-selected
                statusAfterSelect.prop('disabled', false); // Enable status select if family is pre-selected
                 // Enable station selects if family is pre-selected
                fromStationSelect.prop('disabled', false);
                toStationSelect.prop('disabled', false);
            }
            updateFormVisibility();
            if (fromStationSelect.val() && toStationSelect.val() && partSelect.val()) {
                fetchCalculationDetails();
            }
        }

        // *** REQ 5: Logic to focus operator on page load ***
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('focus_operator') === 'true') {
            operatorSelect.focus();
            // Clean up the URL
            urlParams.delete('focus_operator');
            urlParams.delete('message'); // Also remove message
            urlParams.delete('message_type'); // Also remove message type
            // Build new URL string, remove trailing '&' if it exists
            let newQuery = urlParams.toString().replace(/&$/, '');
            const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
            history.replaceState({}, document.title, newUrl);
        }
        
        setTimeout(() => { $('#flash-message').alert('close'); }, 5000);
    }

    // Call initialization
    initializeForm();

});
</script>
