<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.spare_parts.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_eng_spare_part_transactions';
const PRIMARY_KEY = 'TransactionID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('engineering.spare_parts.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $orderId = (!empty($_POST['order_id']) && $_POST['order_id'] != 'none') ? (int)$_POST['order_id'] : null;
            $data = [
                'TransactionDate' => to_gregorian($_POST['transaction_date']),
                'TransactionTypeID' => (int)$_POST['transaction_type_id'],
                'MoldID' => $_POST['mold_id'] ?? null, 'PartID' => $_POST['part_id'] ?? null,
                'Quantity' => (int)$_POST['quantity'], 'SenderEmployeeID' => null,
                'ReceiverEmployeeID' => !empty($_POST['receiver_employee_id']) ? (int)$_POST['receiver_employee_id'] : null,
                'UsageLocation' => $_POST['usage_location'] ?? null, 'Description' => $_POST['description'] ?? null,
                'OrderID' => $orderId,
            ];
            
            if ($data['TransactionTypeID'] == 1 && $orderId) {
                $orderInfo = find_by_id($pdo, 'tbl_spare_part_orders', $orderId, 'OrderID');
                if ($orderInfo) { $data['MoldID'] = $orderInfo['MoldID']; $data['PartID'] = $orderInfo['PartID']; }
            } else {
                if ($data['TransactionTypeID'] == 1 && $orderId === null) $data['SenderEmployeeID'] = !empty($_POST['sender_contractor_id']) ? (int)$_POST['sender_contractor_id'] : null;
                else if ($data['TransactionTypeID'] != 1) $data['SenderEmployeeID'] = !empty($_POST['sender_employee_id']) ? (int)$_POST['sender_employee_id'] : null;
            }

            if (empty($data['PartID']) || empty($data['MoldID'])) {
                 $result = ['success' => false, 'message' => 'خطای سیستمی: شناسه قالب یا قطعه ارسال نشده است.'];
            } else {
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                    if ($result['success'] && $data['TransactionTypeID'] == 1 && $data['OrderID']) {
                        update_record($pdo, 'tbl_spare_part_orders', ['OrderStatusID' => 2, 'DateReceived' => $data['TransactionDate']], $data['OrderID'], 'OrderID');
                        $result['message'] .= " وضعیت سفارش مرتبط نیز به 'تکمیل شده' تغییر یافت.";
                    }
                }
            }
        }
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header("Location: " . BASE_URL . "modules/engineering/spare_part_transactions.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.spare_parts.manage')) {
        die('شما مجوز ویرایش را ندارید.');
    }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT t.*, tt.TypeName, m.MoldName, p.PartName, p.PartCode, sender.name as SenderName, receiver.name as ReceiverName, c_ord.ContractorName as OrderContractorName, c_man.ContractorName as ManualContractorName FROM " . TABLE_NAME . " t JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID LEFT JOIN tbl_molds m ON t.MoldID = m.MoldID LEFT JOIN tbl_eng_spare_parts p ON t.PartID = p.PartID LEFT JOIN tbl_employees sender ON t.SenderEmployeeID = sender.EmployeeID AND t.TransactionTypeID != 1 LEFT JOIN tbl_contractors c_man ON t.SenderEmployeeID = c_man.ContractorID AND t.TransactionTypeID = 1 AND t.OrderID IS NULL LEFT JOIN tbl_employees receiver ON t.ReceiverEmployeeID = receiver.EmployeeID LEFT JOIN tbl_spare_part_orders ord ON t.OrderID = ord.OrderID LEFT JOIN tbl_contractors c_ord ON ord.ContractorID = c_ord.ContractorID ORDER BY t.TransactionDate DESC, t.TransactionID DESC LIMIT 50");
$transaction_types = find_all($pdo, "SELECT * FROM tbl_transaction_types ORDER BY TypeName");
$orders = find_all($pdo, "SELECT o.OrderID, p.PartName FROM tbl_spare_part_orders o JOIN tbl_eng_spare_parts p ON o.PartID = p.PartID WHERE o.OrderStatusID = 1 ORDER BY o.OrderID DESC");
$molds = find_all($pdo, "SELECT * FROM tbl_molds ORDER BY MoldName");
$employees = find_all($pdo, "SELECT * FROM tbl_employees ORDER BY name");
$contractors = find_all($pdo, "SELECT * FROM tbl_contractors ORDER BY ContractorName");

$pageTitle = "تراکنش‌های انبار قطعات یدکی";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت تراکنش انبار قطعات یدکی</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/spare_parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <?php if (has_permission('engineering.spare_parts.manage')): ?>
    <div class="col-lg-4"><div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش تراکنش' : 'ثبت تراکنش جدید'; ?></h5></div><div class="card-body">
        <form method="POST" action="spare_part_transactions.php">
            <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
            <div class="mb-3"><label class="form-label">تاریخ تراکنش</label><input type="text" class="form-control persian-date" name="transaction_date" value="<?php echo to_jalali($itemToEdit['TransactionDate'] ?? date('Y-m-d')); ?>" autocomplete="off" required></div>
            <div class="mb-3"><label class="form-label">نوع تراکنش</label><select class="form-select" id="transaction_type_id" name="transaction_type_id" <?php echo $editMode ? 'disabled' : ''; ?> required><option value="">انتخاب کنید</option><?php foreach ($transaction_types as $type): ?><option value="<?php echo $type['TypeID']; ?>" <?php echo ($editMode && $itemToEdit['TransactionTypeID'] == $type['TypeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['TypeName']); ?></option><?php endforeach; ?></select><?php if ($editMode) : ?><input type="hidden" name="transaction_type_id" value="<?php echo $itemToEdit['TransactionTypeID']; ?>" /><?php endif; ?></div>
            <div id="order_fields_container" style="display: none;"><div class="mb-3"><label class="form-label">شماره سفارش</label><select class="form-select" id="order_id" name="order_id"><option value="">انتخاب کنید</option><option value="none">هیچکدام (ورود دستی)</option><?php foreach ($orders as $order): ?><option value="<?php echo $order['OrderID']; ?>" <?php echo ($editMode && $itemToEdit['OrderID'] == $order['OrderID']) ? 'selected' : ''; ?>><?php echo 'سفارش #' . htmlspecialchars($order['OrderID']) . ' (' . htmlspecialchars($order['PartName']) . ')'; ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">پیمانکار</label><input type="text" class="form-control" id="contractor_name" readonly></div></div>
            <div id="manual_fields_container"><div class="mb-3"><label class="form-label">قالب</label><select class="form-select" id="mold_id" name="mold_id" required><option value="">انتخاب کنید</option><?php foreach ($molds as $mold): ?><option value="<?php echo $mold['MoldID']; ?>" <?php echo ($editMode && $itemToEdit['MoldID'] == $mold['MoldID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mold['MoldName']); ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">قطعه</label><select class="form-select" id="part_id" name="part_id" required disabled><option value="">ابتدا قالب را انتخاب کنید</option></select></div></div>
            <div class="mb-3"><label class="form-label">تعداد</label><input type="number" class="form-control" name="quantity" value="<?php echo htmlspecialchars($itemToEdit['Quantity'] ?? ''); ?>" required></div>
            <div id="sender_contractor_row" class="mb-3" style="display: none;"><label class="form-label">تحویل دهنده (پیمانکار)</label><select class="form-select" name="sender_contractor_id"><option value="">انتخاب کنید</option><?php foreach ($contractors as $contractor): ?><option value="<?php echo $contractor['ContractorID']; ?>" <?php echo ($editMode && $itemToEdit['SenderEmployeeID'] == $contractor['ContractorID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($contractor['ContractorName']); ?></option><?php endforeach; ?></select></div>
            <div id="sender_employee_row" class="mb-3" style="display: none;"><label class="form-label">تحویل دهنده</label><select class="form-select" name="sender_employee_id"><option value="">انتخاب کنید</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo ($editMode && $itemToEdit['SenderEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
            <div id="receiver_employee_row" class="mb-3" style="display: none;"><label class="form-label">تحویل گیرنده</label><select class="form-select" name="receiver_employee_id"><option value="">انتخاب کنید</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo ($editMode && $itemToEdit['ReceiverEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">محل مصرف</label><input type="text" class="form-control" name="usage_location" value="<?php echo htmlspecialchars($itemToEdit['UsageLocation'] ?? ''); ?>"></div>
            <div class="mb-3"><label class="form-label">توضیحات</label><textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($itemToEdit['Description'] ?? ''); ?></textarea></div>
            <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><i class="bi <?php echo $editMode ? 'bi-check2-circle' : 'bi-plus-circle'; ?>"></i> <?php echo $editMode ? 'بروزرسانی' : 'ثبت'; ?></button>
            <?php if ($editMode): ?><a href="spare_part_transactions.php" class="btn btn-secondary">لغو</a><?php endif; ?>
        </form>
    </div></div></div>
    <?php endif; ?>
    <div class="<?php echo has_permission('engineering.spare_parts.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">آخرین تراکنش‌ها</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">تاریخ</th><th class="p-3">نوع</th><th class="p-3">قالب</th><th class="p-3">قطعه</th><th class="p-3">تعداد</th><th class="p-3">تحویل دهنده</th><th class="p-3">گیرنده</th><?php if (has_permission('engineering.spare_parts.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-3"><?php echo to_jalali($item['TransactionDate']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['TypeName']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['MoldName'] ?? '-'); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['PartName'] . ' (' . $item['PartCode'] . ')'); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['Quantity']); ?></td>
                <td class="p-3"><?php if ($item['TransactionTypeID'] == 1 && $item['OrderID']) echo htmlspecialchars($item['OrderContractorName'] ?? '-'); else if ($item['TransactionTypeID'] == 1 && !$item['OrderID']) echo htmlspecialchars($item['ManualContractorName'] ?? '-'); else echo htmlspecialchars($item['SenderName'] ?? '-'); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['ReceiverName'] ?? '-'); ?></td>
                <?php if (has_permission('engineering.spare_parts.manage')): ?>
                <td class="p-3">
                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف این تراکنش مطمئن هستید؟</div>
                        <div class="modal-footer">
                            <form method="POST" action="spare_part_transactions.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                        </div>
                    </div></div></div>
                </td>
                <?php endif; ?>
            </tr><?php endforeach; ?></tbody>
        </table></div></div></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    const transactionTypeSelect = $('#transaction_type_id'), orderFieldsContainer = $('#order_fields_container'), orderSelect = $('#order_id'), contractorNameInput = $('#contractor_name'), manualFieldsContainer = $('#manual_fields_container'), moldSelect = $('#mold_id'), partSelect = $('#part_id'), senderEmployeeRow = $('#sender_employee_row'), senderContractorRow = $('#sender_contractor_row'), receiverEmployeeRow = $('#receiver_employee_row');
    const apiOrderUrl = '<?php echo BASE_URL; ?>api/api_get_order_details.php', apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_spare_parts.php';
    const isInEditMode = <?php echo $editMode ? 'true' : 'false'; ?>, initialPartId = '<?php echo $itemToEdit['PartID'] ?? ''; ?>';

    function fetchParts(moldId, selectedPartId = null) {
        partSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!moldId) { partSelect.html('<option value="">لطفا ابتدا قالب را انتخاب کنید</option>'); return; }
        $.ajax({
            url: apiPartsUrl, type: 'GET', data: { mold_id: moldId }, dataType: 'json',
            success: function(response) {
                partSelect.html('<option value="">یک قطعه انتخاب کنید</option>');
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(i, part) {
                        const option = $('<option>', { value: part.PartID, text: `${part.PartName} (${part.PartCode})` });
                        if (part.PartID == selectedPartId) option.prop('selected', true);
                        partSelect.append(option);
                    });
                    partSelect.prop('disabled', false);
                } else { partSelect.html('<option value="">هیچ قطعه‌ای یافت نشد</option>'); }
            },
            error: function() { partSelect.html('<option value="">خطا در بارگذاری</option>'); }
        });
    }

    function updateFormUI() {
        const type = transactionTypeSelect.val(), order = orderSelect.val();
        orderFieldsContainer.hide(); manualFieldsContainer.hide(); senderEmployeeRow.hide(); senderContractorRow.hide(); receiverEmployeeRow.hide();
        if (isInEditMode) {
            manualFieldsContainer.show(); receiverEmployeeRow.show();
            const item = <?php echo json_encode($itemToEdit); ?>;
            if (item.TransactionTypeID == 1 && !item.OrderID) senderContractorRow.show();
            else if (item.TransactionTypeID != 1) senderEmployeeRow.show();
            return;
        }
        if (!type) return;
        if (type == '1') {
            orderFieldsContainer.show(); receiverEmployeeRow.show();
            if (order && order !== 'none') {
                manualFieldsContainer.show(); moldSelect.prop('disabled', true); partSelect.prop('disabled', true);
            } else if (order === 'none') {
                manualFieldsContainer.show(); moldSelect.prop('disabled', false); partSelect.prop('disabled', !moldSelect.val()); senderContractorRow.show();
            }
        } else {
            manualFieldsContainer.show(); moldSelect.prop('disabled', false); partSelect.prop('disabled', !moldSelect.val());
            if (type == '2') { senderEmployeeRow.show(); receiverEmployeeRow.show(); }
        }
    }

    transactionTypeSelect.on('change', updateFormUI);
    orderSelect.on('change', function() {
        const orderId = $(this).val();
        updateFormUI();
        if (orderId && orderId !== 'none') {
            $.ajax({
                url: apiOrderUrl, type: 'GET', data: { order_id: orderId }, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        contractorNameInput.val(response.data.ContractorName);
                        moldSelect.val(response.data.MoldID);
                        partSelect.html(`<option value="${response.data.PartID}" selected>${response.data.PartName} (${response.data.PartCode})</option>`);
                    }
                }
            });
        } else {
            contractorNameInput.val(''); moldSelect.val('');
            partSelect.html('<option value="">لطفا ابتدا قالب را انتخاب کنید</option>').prop('disabled', true);
        }
    });
    moldSelect.on('change', function() { if (!$(this).is(':disabled')) fetchParts($(this).val()); });

    if (isInEditMode) {
        if (moldSelect.val()) fetchParts(moldSelect.val(), initialPartId);
        updateFormUI();
    } else {
        updateFormUI();
    }
});
</script>

