<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.spare_parts.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_spare_part_orders';
const PRIMARY_KEY = 'OrderID';

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
            $data = [
                'OrderDate' => to_gregorian($_POST['order_date']),
                'MoldID' => (int)$_POST['mold_id'],
                'PartID' => (int)$_POST['part_id'],
                'QuantityOrdered' => (int)$_POST['quantity_ordered'],
                'ContractorID' => !empty($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : null,
                'OrderStatusID' => (int)$_POST['order_status_id'],
                'DateReceived' => ($_POST['order_status_id'] == 2 && !empty($_POST['date_received'])) ? to_gregorian($_POST['date_received']) : null
            ];

            if (empty($data['PartID']) || empty($data['QuantityOrdered'])) {
                 $result = ['success' => false, 'message' => 'انتخاب قطعه و تعداد اجباری است.'];
                 $_SESSION['message_type'] = 'warning';
            } else {
                if (isset($_POST['id']) && !empty($_POST['id'])) {
                    $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/engineering/spare_part_orders.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.spare_parts.manage')) {
        die('شما مجوز ویرایش را ندارید.');
    }
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT o.*, p.PartName, p.PartCode, m.MoldName, c.ContractorName, os.StatusName FROM " . TABLE_NAME . " o JOIN tbl_eng_spare_parts p ON o.PartID = p.PartID LEFT JOIN tbl_molds m ON o.MoldID = m.MoldID LEFT JOIN tbl_contractors c ON o.ContractorID = c.ContractorID JOIN tbl_order_statuses os ON o.OrderStatusID = os.OrderStatusID ORDER BY o.OrderDate DESC, o.OrderID DESC");
$molds = find_all($pdo, "SELECT MoldID, MoldName FROM tbl_molds ORDER BY MoldName");
$contractors = find_all($pdo, "SELECT ContractorID, ContractorName FROM tbl_contractors ORDER BY ContractorName");
$order_statuses = find_all($pdo, "SELECT OrderStatusID, StatusName FROM tbl_order_statuses");

$pageTitle = "مدیریت سفارشات قطعات یدکی";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت سفارشات قطعات یدکی</h1>
    <a href="<?php echo BASE_URL; ?>modules/engineering/spare_parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <?php if (has_permission('engineering.spare_parts.manage')): ?>
    <div class="col-lg-4"><div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش سفارش' : 'ثبت سفارش جدید'; ?></h5></div><div class="card-body">
        <form method="POST" action="spare_part_orders.php">
            <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
            <div class="mb-3"><label class="form-label">تاریخ سفارش</label><input type="text" class="form-control persian-date" name="order_date" value="<?php echo to_jalali($itemToEdit['OrderDate'] ?? date('Y-m-d')); ?>" autocomplete="off" required></div>
            <div class="mb-3"><label class="form-label">قالب</label><select class="form-select" id="mold_id" name="mold_id" required><option value="">ابتدا یک قالب انتخاب کنید</option><?php foreach ($molds as $mold): ?><option value="<?php echo $mold['MoldID']; ?>" <?php echo ($editMode && $itemToEdit['MoldID'] == $mold['MoldID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mold['MoldName']); ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">قطعه</label><select class="form-select" id="part_id" name="part_id" required <?php echo $editMode ? '' : 'disabled'; ?>><option value="">لطفا ابتدا قالب را انتخاب کنید</option></select></div>
            <div class="mb-3"><label class="form-label">تعداد</label><input type="number" class="form-control" name="quantity_ordered" value="<?php echo htmlspecialchars($itemToEdit['QuantityOrdered'] ?? ''); ?>" required></div>
            <div class="mb-3"><label class="form-label">پیمانکار</label><select class="form-select" name="contractor_id"><option value="">انتخاب کنید</option><?php foreach ($contractors as $contractor): ?><option value="<?php echo $contractor['ContractorID']; ?>" <?php echo ($editMode && $itemToEdit['ContractorID'] == $contractor['ContractorID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($contractor['ContractorName']); ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">وضعیت سفارش</label><select class="form-select" id="order_status_id" name="order_status_id" required><?php foreach ($order_statuses as $status): ?><option value="<?php echo $status['OrderStatusID']; ?>" <?php echo ($editMode && $itemToEdit['OrderStatusID'] == $status['OrderStatusID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status['StatusName']); ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">تاریخ دریافت</label><input type="text" class="form-control persian-date" id="date_received" name="date_received" value="<?php echo to_jalali($itemToEdit['DateReceived'] ?? ''); ?>" autocomplete="off"></div>
            <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'ثبت سفارش'; ?></button>
            <?php if ($editMode): ?><a href="spare_part_orders.php" class="btn btn-secondary">لغو</a><?php endif; ?>
        </form>
    </div></div></div>
    <?php endif; ?>
    <div class="<?php echo has_permission('engineering.spare_parts.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">لیست سفارشات</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">تاریخ</th><th class="p-3">قالب</th><th class="p-3">پیمانکار</th><th class="p-3">قطعه (کد)</th><th class="p-3">تعداد</th><th class="p-3">وضعیت</th><th class="p-3">تاریخ دریافت</th><?php if (has_permission('engineering.spare_parts.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-3"><?php echo to_jalali($item['OrderDate']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['MoldName'] ?? 'N/A'); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['ContractorName'] ?? '-'); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['PartName']) . ' (' . htmlspecialchars($item['PartCode']) . ')'; ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['QuantityOrdered']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($item['StatusName']); ?></td>
                <td class="p-3"><?php echo ($item['OrderStatusID'] == 2) ? to_jalali($item['DateReceived']) : '-'; ?></td>
                <?php if (has_permission('engineering.spare_parts.manage')): ?>
                <td class="p-3">
                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف سفارش برای قطعه "<?php echo htmlspecialchars($item['PartName']); ?>" مطمئن هستید؟</div>
                        <div class="modal-footer">
                          <form method="POST" action="spare_part_orders.php" class="d-inline"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form>
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
    const moldSelect = $('#mold_id'), partSelect = $('#part_id'), apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_spare_parts.php', initialPartId = '<?php echo $itemToEdit['PartID'] ?? ''; ?>', isInEditMode = <?php echo $editMode ? 'true' : 'false'; ?>;
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
                } else { partSelect.html('<option value="">هیچ قطعه‌ای برای این قالب یافت نشد</option>'); }
            },
            error: function() { partSelect.html('<option value="">خطا در بارگذاری قطعات</option>'); }
        });
    }
    if (isInEditMode && moldSelect.val()) { fetchParts(moldSelect.val(), initialPartId); }
    moldSelect.on('change', function() { fetchParts($(this).val()); });
    const statusSelect = $('#order_status_id'), dateReceivedInput = $('#date_received');
    function toggleDateReceived() {
        if (statusSelect.val() == '2') dateReceivedInput.prop('disabled', false);
        else { dateReceivedInput.prop('disabled', true); dateReceivedInput.val(''); }
    }
    toggleDateReceived();
    statusSelect.on('change', toggleDateReceived);
});
</script>

