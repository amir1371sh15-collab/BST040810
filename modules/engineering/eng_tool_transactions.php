<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.tools.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME = 'tbl_eng_tool_transactions';
const PRIMARY_KEY = 'TransactionID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('engineering.tools.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
    } else {
        if (isset($_POST['delete_id'])) {
            $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        } else {
            $data = [
                'TransactionDate' => to_gregorian($_POST['transaction_date']),
                'ToolID' => (int)$_POST['tool_id'],
                'Quantity' => (int)$_POST['quantity'],
                'TransactionTypeID' => (int)$_POST['transaction_type_id'],
                'SenderEmployeeID' => !empty($_POST['sender_employee_id']) ? (int)$_POST['sender_employee_id'] : null,
                'ReceiverEmployeeID' => !empty($_POST['receiver_employee_id']) ? (int)$_POST['receiver_employee_id'] : null,
                'Description' => trim($_POST['description']),
            ];

            if (empty($data['ToolID']) || empty($data['Quantity'])) {
                $result = ['success' => false, 'message' => 'انتخاب ابزار و تعداد الزامی است.'];
            } else {
                 if (isset($_POST['id']) && !empty($_POST['id'])) {
                    $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
            }
        }
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header("Location: " . BASE_URL . "modules/engineering/eng_tool_transactions.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    if (!has_permission('engineering.tools.manage')) die('شما مجوز ویرایش را ندارید.');
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "SELECT tt.*, t.ToolName, t.ToolCode, dept.DepartmentName, type.TypeName as ToolTypeName, sender.name as SenderName, receiver.name as ReceiverName, trantype.TypeName as TransactionTypeName FROM " . TABLE_NAME . " tt JOIN tbl_eng_tools t ON tt.ToolID = t.ToolID LEFT JOIN tbl_departments dept ON t.DepartmentID = dept.DepartmentID LEFT JOIN tbl_eng_tool_types type ON t.ToolTypeID = type.ToolTypeID LEFT JOIN tbl_employees sender ON tt.SenderEmployeeID = sender.EmployeeID LEFT JOIN tbl_employees receiver ON tt.ReceiverEmployeeID = receiver.EmployeeID JOIN tbl_transaction_types trantype ON tt.TransactionTypeID = trantype.TypeID ORDER BY tt.TransactionDate DESC LIMIT 50");
$departments = find_all($pdo, "SELECT * FROM tbl_departments ORDER BY DepartmentName");
$tool_types = find_all($pdo, "SELECT * FROM tbl_eng_tool_types ORDER BY TypeName");
$transaction_types = find_all($pdo, "SELECT * FROM tbl_transaction_types ORDER BY TypeName");
$employees = find_all($pdo, "SELECT * FROM tbl_employees ORDER BY name");

$pageTitle = "تراکنش‌های انبار ابزار";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت تراکنش ابزار</h1>
    <a href="eng_tools_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <?php if (has_permission('engineering.tools.manage')): ?>
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش تراکنش' : 'ثبت تراکنش جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="eng_tool_transactions.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3"><label class="form-label">تاریخ تراکنش</label><input type="text" class="form-control persian-date" name="transaction_date" value="<?php echo to_jalali($itemToEdit['TransactionDate'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">نوع تراکنش</label><select class="form-select" name="transaction_type_id" required><option value="">انتخاب کنید...</option><?php foreach ($transaction_types as $type): ?><option value="<?php echo $type['TypeID']; ?>" <?php echo ($editMode && $itemToEdit['TransactionTypeID'] == $type['TypeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['TypeName']); ?></option><?php endforeach; ?></select></div>
                    <hr><p class="text-muted">فیلتر ابزار</p>
                    <div class="mb-3"><label class="form-label">دپارتمان</label><select class="form-select" id="filter_department_id"><option value="">همه</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['DepartmentID']; ?>"><?php echo htmlspecialchars($dept['DepartmentName']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">نوع ابزار</label><select class="form-select" id="filter_tool_type_id"><option value="">همه</option><?php foreach ($tool_types as $type): ?><option value="<?php echo $type['ToolTypeID']; ?>"><?php echo htmlspecialchars($type['TypeName']); ?></option><?php endforeach; ?></select></div>
                    <hr>
                    <div class="mb-3"><label class="form-label">نام ابزار</label><select class="form-select" name="tool_id" id="tool_id" required disabled><option value="">ابتدا فیلتر کنید...</option></select></div>
                    <div class="mb-3"><label class="form-label">تعداد</label><input type="number" class="form-control" name="quantity" value="<?php echo htmlspecialchars($itemToEdit['Quantity'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label class="form-label">تحویل دهنده</label><select class="form-select" name="sender_employee_id"><option value="">انتخاب کنید...</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo ($editMode && $itemToEdit['SenderEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">تحویل گیرنده</label><select class="form-select" name="receiver_employee_id"><option value="">انتخاب کنید...</option><?php foreach ($employees as $employee): ?><option value="<?php echo $employee['EmployeeID']; ?>" <?php echo ($editMode && $itemToEdit['ReceiverEmployeeID'] == $employee['EmployeeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">توضیحات</label><textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($itemToEdit['Description'] ?? ''); ?></textarea></div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'ثبت'; ?></button>
                    <?php if ($editMode): ?><a href="eng_tool_transactions.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?php echo has_permission('engineering.tools.manage') ? 'col-lg-8' : 'col-lg-12'; ?>">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">آخرین تراکنش‌ها</h5></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
                <thead><tr><th class="p-3">تاریخ</th><th class="p-3">نوع تراکنش</th><th class="p-3">ابزار (کد)</th><th class="p-3">تعداد</th><th class="p-3">دهنده</th><th class="p-3">گیرنده</th><?php if (has_permission('engineering.tools.manage')): ?><th class="p-3">عملیات</th><?php endif; ?></tr></thead>
                <tbody><?php foreach ($items as $item): ?>
                    <tr>
                        <td class="p-3"><?php echo to_jalali($item['TransactionDate']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['TransactionTypeName']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['ToolName'] . ' (' . $item['ToolCode'] . ')'); ?><small class="text-muted d-block"><?php echo htmlspecialchars($item['DepartmentName'] . ' / ' . $item['ToolTypeName']); ?></small></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['Quantity']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['SenderName'] ?? '-'); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($item['ReceiverName'] ?? '-'); ?></td>
                        <?php if (has_permission('engineering.tools.manage')): ?>
                        <td class="p-3">
                            <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                            <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف این تراکنش مطمئن هستید؟</div><div class="modal-footer"><form method="POST" action="eng_tool_transactions.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button></div></div></div></div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    const deptFilter = $('#filter_department_id');
    const typeFilter = $('#filter_tool_type_id');
    const toolSelect = $('#tool_id');
    const apiToolsUrl = '<?php echo BASE_URL; ?>api/api_get_eng_tools.php';

    function fetchTools() {
        const deptId = deptFilter.val();
        const typeId = typeFilter.val();
        toolSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);

        $.ajax({
            url: apiToolsUrl, type: 'GET', data: { department_id: deptId, tool_type_id: typeId }, dataType: 'json',
            success: function(response) {
                toolSelect.html('<option value="">یک ابزار انتخاب کنید</option>');
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(i, tool) {
                        const optionText = `${tool.ToolName} (${tool.ToolCode})`;
                        const option = $('<option>', { value: tool.ToolID, text: optionText });
                        toolSelect.append(option);
                    });
                    toolSelect.prop('disabled', false);
                } else {
                    toolSelect.html('<option value="">هیچ ابزاری با این فیلترها یافت نشد</option>');
                }
            },
            error: function() { toolSelect.html('<option value="">خطا در بارگذاری ابزارها</option>'); }
        });
    }

    deptFilter.on('change', fetchTools);
    typeFilter.on('change', fetchTools);
});
</script>
