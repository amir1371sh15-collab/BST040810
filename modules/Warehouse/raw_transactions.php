<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('warehouse.raw.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_raw_transactions';
const PRIMARY_KEY = 'TransactionID';
const RECORDS_PER_PAGE = 15;
const ALLOWED_TYPE_NAMES = ['ورود به انبار', 'خروج از انبار'];

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$categories = find_all($pdo, "SELECT CategoryID, CategoryName FROM tbl_raw_categories ORDER BY CategoryName");
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees ORDER BY name");

$quotedTypeNames = implode(',', array_map(function ($name) use ($pdo) { return $pdo->quote($name); }, ALLOWED_TYPE_NAMES));
$transaction_types = find_all($pdo, "SELECT TypeID, TypeName, StockEffect FROM tbl_transaction_types WHERE TypeName IN ($quotedTypeNames) ORDER BY TypeID");
$allowed_type_ids = array_map('intval', array_column($transaction_types, 'TypeID'));

$editMode = false;
$itemToEdit = null;
$itemToEditJson = 'null';

$initial_log_date_jalali = $_SESSION['raw_transaction_date'] ?? to_jalali(date('Y-m-d'));

// --- Pagination Logic ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$total_records_query = $pdo->query("
    SELECT COUNT(t.TransactionID)
    FROM tbl_raw_transactions t
    JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
    WHERE tt.TypeName IN ($quotedTypeNames)
");
$total_records = $total_records_query ? $total_records_query->fetchColumn() : 0;
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;
// --- End Pagination Logic ---

// Handle Delete (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteResult = ['success' => false];
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);

    if (!$delete_id) {
        $_SESSION['message'] = 'شناسه نامعتبر برای حذف.';
        $_SESSION['message_type'] = 'warning';
    } else {
        $transaction = find_by_id($pdo, TABLE_NAME, $delete_id, PRIMARY_KEY);
        if (!$transaction || !in_array((int)$transaction['TransactionTypeID'], $allowed_type_ids, true)) {
            $_SESSION['message'] = 'رکورد تراکنش یافت نشد.';
            $_SESSION['message_type'] = 'warning';
        } else {
            $deleteResult = delete_record($pdo, TABLE_NAME, $delete_id, PRIMARY_KEY);
            $_SESSION['message'] = $deleteResult['message'];
            $_SESSION['message_type'] = $deleteResult['success'] ? 'success' : 'danger';
        }
    }
    $redirect_page = $current_page;
    header("Location: " . BASE_URL . "modules/Warehouse/raw_transactions.php?page=" . $redirect_page);
    exit;
}

// Handle Edit (GET)
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $transaction = find_by_id($pdo, TABLE_NAME, $edit_id, PRIMARY_KEY);
        if ($transaction && in_array((int)$transaction['TransactionTypeID'], $allowed_type_ids, true)) {
            $editMode = true;
            $itemToEdit = $transaction;
            $itemInfo = find_by_id($pdo, 'tbl_raw_items', $transaction['ItemID'], 'ItemID');
            if (!$itemInfo) {
                $_SESSION['message'] = 'اطلاعات ماده اولیه برای ویرایش یافت نشد.';
                $_SESSION['message_type'] = 'warning';
                header("Location: " . BASE_URL . "modules/Warehouse/raw_transactions.php?page=" . $current_page);
                exit;
            }
            $itemToEdit['CategoryID'] = $itemInfo['CategoryID'] ?? null;
            $itemToEdit['Quantity'] = abs((float)($transaction['Quantity'] ?? 0)); // Show positive number
            $itemToEdit['OperatorEmployeeID'] = $transaction['OperatorEmployeeID'] ?? null;
            $itemToEdit['TransactionDateJalali'] = to_jalali($transaction['TransactionDate']);
            $itemToEdit['Description'] = $transaction['Description'] ?? '';

            $_SESSION['raw_transaction_date'] = $itemToEdit['TransactionDateJalali'];
            $initial_log_date_jalali = $itemToEdit['TransactionDateJalali'];
            $itemToEditJson = json_encode($itemToEdit, JSON_UNESCAPED_UNICODE);
        } else {
            $_SESSION['message'] = 'رکورد تراکنش برای ویرایش یافت نشد.';
            $_SESSION['message_type'] = 'warning';
            header("Location: " . BASE_URL . "modules/Warehouse/raw_transactions.php?page=" . $current_page);
            exit;
        }
    }
}

// --- History Query ---
$history_query = "
    SELECT t.*, mi.ItemName, u.Symbol as UnitSymbol, tt.TypeName as TransactionTypeName, tt.StockEffect, op.name as OperatorName
    FROM tbl_raw_transactions t
    JOIN tbl_raw_items mi ON t.ItemID = mi.ItemID
    JOIN tbl_units u ON mi.UnitID = u.UnitID
    JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
    LEFT JOIN tbl_employees op ON t.OperatorEmployeeID = op.EmployeeID
    WHERE tt.TypeName IN ($quotedTypeNames)
    ORDER BY t.TransactionDate DESC, t.TransactionID DESC
    LIMIT :limit OFFSET :offset
";
$history_stmt = $pdo->prepare($history_query);
$history_stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$history_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$history_stmt->execute();
$recentTransactions = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
// --- End History Query ---

$pageTitle = "ثبت تراکنش مواد اولیه";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="raw_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div id="flash-message" class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form id="transaction-form">
    <div class="card content-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?php echo $editMode ? 'ویرایش تراکنش' : 'ثبت تراکنش جدید'; ?></h5>
            <?php if ($editMode): ?>
                <a href="raw_transactions.php?page=<?php echo $current_page; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> لغو ویرایش
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <input type="hidden" id="transaction_id" name="transaction_id" value="<?php echo $editMode ? (int)$itemToEdit['TransactionID'] : ''; ?>">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="transaction_date" class="form-label">تاریخ ثبت *</label>
                    <input type="text" id="transaction_date" name="transaction_date" class="form-control persian-date persistent-input" data-session-key="raw_transaction_date"
                           value="<?php echo $initial_log_date_jalali; ?>" required>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="transaction_type_id" class="form-label">نوع تراکنش *</label>
                    <select id="transaction_type_id" name="transaction_type_id" class="form-select" required>
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($transaction_types as $type): ?>
                            <option value="<?php echo $type['TypeID']; ?>" data-effect="<?php echo $type['StockEffect']; ?>">
                                <?php echo htmlspecialchars($type['TypeName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="operator_employee_id" class="form-label">نام عامل</label>
                    <select id="operator_employee_id" name="operator_employee_id" class="form-select">
                        <option value="">-- انتخاب --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['EmployeeID']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="description" class="form-label">توضیحات (اختیاری)</label>
                    <input type="text" id="description" name="description" class="form-control" placeholder="مثلاً: مصرف در خط تولید ۲">
                </div>
            </div>
            <div class="row align-items-end">
                 <div class="col-md-4 mb-3">
                     <label for="category_id" class="form-label">دسته‌بندی ماده *</label>
                     <select id="category_id" name="category_id" class="form-select" required>
                         <option value="">-- انتخاب --</option>
                         <?php foreach ($categories as $cat): ?>
                             <option value="<?php echo $cat['CategoryID']; ?>"><?php echo htmlspecialchars($cat['CategoryName']); ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="col-md-4 mb-3">
                      <label for="item_id" class="form-label">نام ماده *</label>
                      <select id="item_id" name="item_id" class="form-select" required disabled>
                         <option value="">-- ابتدا دسته‌بندی --</option>
                      </select>
                 </div>
                 <div class="col-md-2 mb-3">
                     <label for="quantity" class="form-label" id="quantity_label">مقدار *</label>
                     <div class="input-group">
                        <input type="number" step="0.01" id="quantity" name="quantity" class="form-control" placeholder="مقدار" required>
                        <span class="input-group-text" id="quantity-unit">--</span>
                     </div>
                 </div>
                 <div class="col-md-2 mb-3 text-end">
                     <button type="submit" id="submit-button" class="btn <?php echo $editMode ? 'btn-warning' : 'btn-primary'; ?>">
                         <i class="bi <?php echo $editMode ? 'bi-save' : 'bi-check-lg'; ?>"></i> <?php echo $editMode ? 'ذخیره تغییرات' : 'ثبت'; ?>
                     </button>
                 </div>
            </div>
            <div id="submit-feedback" class="mt-2 small"></div>
        </div>
    </div>
</form>

<div class="card content-card mt-4">
    <div class="card-header"><h5 class="mb-0">آخرین تراکنش‌ها</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">نوع</th>
                        <th class="p-2">نام ماده</th>
                        <th class="p-2">مقدار</th>
                        <th class="p-2">واحد</th>
                        <th class="p-2">عامل ثبت</th>
                        <th class="p-2">توضیحات</th>
                        <th class="p-2 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <?php if (empty($recentTransactions)): ?>
                        <tr><td colspan="8" class="text-center p-3 text-muted">هنوز رکوردی ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach($recentTransactions as $tx):
                             $quantity = (float)($tx['Quantity'] ?? 0);
                             $effect = (int)($tx['StockEffect'] ?? 0);
                             $displayQuantity = number_format(abs($quantity), 3);
                             $quantityClass = ($effect > 0) ? 'text-success' : (($effect < 0) ? 'text-danger' : '');
                             $quantityPrefix = ($effect < 0) ? '-' : '+';
                        ?>
                        <tr>
                            <td class="p-2"><?php echo to_jalali($tx['TransactionDate']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['TransactionTypeName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['ItemName']); ?></td>
                            <td class="p-2 fw-bold <?php echo $quantityClass; ?>"><?php echo $quantityPrefix . $displayQuantity; ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['UnitSymbol']); ?></td>
                            <td class="p-2 small"><?php echo htmlspecialchars($tx['OperatorName'] ?? '-'); ?></td>
                            <td class="p-2 small"><?php echo htmlspecialchars($tx['Description'] ?? '-'); ?></td>
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
                <?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
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
      <div class="modal-header"><h5 class="modal-title">حذف رکورد</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">آیا از حذف این تراکنش مطمئن هستید؟</div>
      <div class="modal-footer">
          <form id="delete-form" method="POST" action="raw_transactions.php?page=<?php echo $current_page; ?>" class="d-inline">
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
    const apiRecordUrl = '<?php echo BASE_URL; ?>api/api_raw_record_transaction.php';
    const apiGetItemsUrl = '<?php echo BASE_URL; ?>api/api_raw_get_items.php';
    
    const categorySelect = $('#category_id');
    const itemSelect = $('#item_id');
    const transactionDateInput = $('#transaction_date');
    const transactionTypeSelect = $('#transaction_type_id');
    const operatorSelect = $('#operator_employee_id');
    const descriptionInput = $('#description');
    const quantityInput = $('#quantity');
    const quantityUnitSpan = $('#quantity-unit');
    const submitButton = $('#submit-button');
    const submitFeedback = $('#submit-feedback');
    const transactionIdInput = $('#transaction_id');
    const deleteTransactionIdInput = $('#delete-transaction-id');
    
    const stocktakePageUrl = 'raw_transactions.php';
    const currentPageNumber = <?php echo $current_page; ?>;
    const editMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    const itemToEdit = <?php echo $itemToEditJson; ?>;

    let itemDataCache = {}; // Cache for item units {ItemID: 'Symbol'}

    // --- Event Listeners ---
    categorySelect.on('change', async function() {
        itemSelect.prop('disabled', true).html('<option value="">...</option>');
        const categoryId = $(this).val();
        if (categoryId) {
            await populateItems(categoryId);
        } else {
            itemSelect.html('<option value="">-- ابتدا دسته‌بندی --</option>');
        }
    });

    transactionDateInput.on('change', function() {
        const newDate = $(this).val();
        $.post('<?php echo BASE_URL; ?>api/api_update_session.php', { key: 'raw_transaction_date', value: newDate });
    });

    itemSelect.on('change', updateQuantityUnit);

    $(document).on('click', '.delete-btn', function() {
        const txId = $(this).data('tx-id');
        deleteTransactionIdInput.val(txId);
    });

    quantityInput.on('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#transaction-form').trigger('submit');
        }
    });

    // --- Form Submission ---
    $('#transaction-form').on('submit', async function(e) {
        e.preventDefault();
        submitFeedback.text('در حال ثبت...').removeClass('text-success text-danger').addClass('text-info');
        submitButton.prop('disabled', true);

        const formData = $(this).serializeArray();
        let dataToSend = {};
        formData.forEach(item => { dataToSend[item.name] = item.value; });

        const quantity = parseFloat(dataToSend['quantity']);
        if (isNaN(quantity) || quantity <= 0) {
             submitFeedback.text('مقدار باید یک عدد مثبت باشد.').removeClass('text-info').addClass('text-danger');
             submitButton.prop('disabled', false);
             return;
        }
        
        console.log("Data Sent to API:", dataToSend);

        try {
            const recordResponse = await $.post(apiRecordUrl, dataToSend);
            if (recordResponse.success) {
                submitFeedback.text(recordResponse.message).removeClass('text-info text-danger').addClass('text-success');
                
                let redirectUrl = stocktakePageUrl;
                if (editMode) {
                    redirectUrl += `?page=${currentPageNumber}`;
                } else {
                    redirectUrl += `?focus_transaction_type=true`;
                }
                setTimeout(() => { window.location.href = redirectUrl; }, 800);
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

    // --- Helper Functions ---
    async function populateItems(categoryId, selectedItemId = null) {
        itemSelect.html('<option value="">در حال بارگذاری...</option>');
        itemDataCache = {};
        quantityUnitSpan.text('--');
        try {
            const response = await $.getJSON(apiGetItemsUrl, { category_id: categoryId });
            itemSelect.html('<option value="">-- انتخاب ماده --</option>');
            if (response.success && response.data.length > 0) {
                response.data.forEach(item => {
                    itemDataCache[item.ItemID] = item.UnitSymbol || '??';
                    itemSelect.append($('<option>', { value: item.ItemID, text: item.ItemName }));
                });
                itemSelect.prop('disabled', false);
                if (selectedItemId) {
                    itemSelect.val(selectedItemId);
                    updateQuantityUnit();
                }
            } else {
                itemSelect.html('<option value="">ماده‌ای یافت نشد</option>');
            }
        } catch (error) {
            console.error("Error fetching items:", error);
            itemSelect.html('<option value="">خطا در بارگذاری</option>');
        }
    }
    
    function updateQuantityUnit() {
        const selectedItemId = itemSelect.val();
        const unit = itemDataCache[selectedItemId] || '--';
        quantityUnitSpan.text(unit);
    }

    async function initializeEditMode() {
        if (!editMode || !itemToEdit) return;

        transactionIdInput.val(itemToEdit.TransactionID ?? '');
        transactionDateInput.val(itemToEdit.TransactionDateJalali ?? transactionDateInput.val());
        operatorSelect.val(itemToEdit.OperatorEmployeeID ? String(itemToEdit.OperatorEmployeeID) : '');
        quantityInput.val(itemToEdit.Quantity ?? '');
        descriptionInput.val(itemToEdit.Description ?? '');
        
        submitButton.removeClass('btn-primary').addClass('btn-warning');
        submitFeedback.text('در حال ویرایش رکورد انتخاب‌شده.').removeClass('text-danger text-success').addClass('text-info');

        const categoryId = itemToEdit.CategoryID ? String(itemToEdit.CategoryID) : '';
        if (categoryId) {
            categorySelect.val(categoryId);
            await populateItems(categoryId, itemToEdit.ItemID ? String(itemToEdit.ItemID) : null);
        }
        
        transactionTypeSelect.val(itemToEdit.TransactionTypeID ? String(itemToEdit.TransactionTypeID) : '');
    }
    
    // --- Initial Load ---
    initializeEditMode();
    setTimeout(() => { $('#flash-message').alert('close'); }, 5000);
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('focus_transaction_type') === 'true' && !editMode) {
        transactionTypeSelect.focus();
        urlParams.delete('focus_transaction_type');
        let newQuery = urlParams.toString().replace(/&$/, '');
        const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
        history.replaceState({}, document.title, newUrl);
    }
});
</script>
