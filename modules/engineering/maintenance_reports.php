<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.maintenance.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('engineering.maintenance.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.'; $_SESSION['message_type'] = 'danger';
        header("Location: maintenance_reports.php"); exit;
    }
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, 'tbl_maintenance_reports', (int)$_POST['delete_id'], 'ReportID');
    } else {
        $pdo->beginTransaction();
        try {
            $report_data = [
                'ReportDate' => to_gregorian($_POST['report_date']) . ' ' . date('H:i:s'),
                'MoldID' => (int)$_POST['mold_id'],
                'RestartDate' => !empty($_POST['restart_date']) ? to_gregorian($_POST['restart_date']) . ' ' . date('H:i:s') : null,
                'RepairDurationMinutes' => !empty($_POST['repair_duration_minutes']) ? (int)$_POST['repair_duration_minutes'] : null
            ];
            $report_result = insert_record($pdo, 'tbl_maintenance_reports', $report_data);
            if (!$report_result['success']) throw new Exception("Failed to create report header.");
            $report_id = $report_result['id'];

            $entries = $_POST['actions'] ?? []; // We only need the lowest level (actions) which contains all parent IDs
            $entry_stmt = $pdo->prepare("INSERT INTO tbl_maintenance_report_entries (ReportID, BreakdownTypeID, CauseID, ActionID) VALUES (?, ?, ?, ?)");
            
            if (!empty($entries)) {
                foreach ($entries as $breakdown_id => $causes) {
                    foreach ($causes as $cause_id => $actions) {
                        foreach ($actions as $action_id) {
                            $entry_stmt->execute([$report_id, (int)$breakdown_id, (int)$cause_id, (int)$action_id]);
                        }
                    }
                }
            }
            $pdo->commit();
            $result = ['success' => true, 'message' => 'گزارش نت با موفقیت ثبت شد.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $result = ['success' => false, 'message' => 'خطا در ثبت گزارش: ' . $e->getMessage()];
        }
    }
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    header("Location: maintenance_reports.php");
    exit;
}

$molds = find_all($pdo, "SELECT MoldID, MoldName FROM tbl_molds ORDER BY MoldName");
$breakdowns = find_all($pdo, "SELECT * FROM tbl_maintenance_breakdown_types ORDER BY Description");
$reports = find_all($pdo, "SELECT r.*, m.MoldName, (SELECT GROUP_CONCAT(DISTINCT bt.Description SEPARATOR ', ') FROM tbl_maintenance_report_entries re JOIN tbl_maintenance_breakdown_types bt ON re.BreakdownTypeID = bt.BreakdownTypeID WHERE re.ReportID = r.ReportID) as BreakdownsSummary FROM tbl_maintenance_reports r JOIN tbl_molds m ON r.MoldID = m.MoldID ORDER BY r.ReportDate DESC LIMIT 50");

$pageTitle = "گزارشات نگهداری و تعمیرات";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">گزارشات نگهداری و تعمیرات</h1>
    <a href="maintenance_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <?php if (has_permission('engineering.maintenance.manage')): ?>
    <div class="col-lg-12">
        <form method="POST" action="maintenance_reports.php">
        <div class="card content-card mb-4">
            <div class="card-header"><h5 class="mb-0">فرم ثبت گزارش خرابی</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">تاریخ گزارش</label><input type="text" name="report_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required></div>
                    <div class="col-md-3 mb-3"><label class="form-label">نام قالب</label><select name="mold_id" class="form-select" required><option value="">-- انتخاب کنید --</option><?php foreach($molds as $mold): ?><option value="<?php echo $mold['MoldID']; ?>"><?php echo htmlspecialchars($mold['MoldName']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">تاریخ راه‌اندازی مجدد</label><input type="text" name="restart_date" class="form-control persian-date"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">زمان تعمیر (دقیقه)</label><input type="number" name="repair_duration_minutes" class="form-control"></div>
                </div><hr>
                <div class="mb-3">
                    <label class="form-label fw-bold">۱. شرح خرابی</label>
                    <div id="breakdowns-list" class="list-group mb-2">
                        <?php foreach($breakdowns as $b): ?>
                        <label class="list-group-item"><input class="form-check-input me-2 breakdown-checkbox" type="checkbox" value="<?php echo $b['BreakdownTypeID']; ?>"><?php echo htmlspecialchars($b['Description']); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="input-group"><input type="text" class="form-control" placeholder="یا نام خرابی جدید را وارد کنید..."><button class="btn btn-outline-success save-new-item-btn" type="button" data-item-type="breakdown">✓ ذخیره</button></div>
                </div>
                <div id="dynamic_entries_container" class="mt-4"></div>
            </div>
            <div class="card-footer text-end"><button type="submit" class="btn btn-primary">ثبت نهایی گزارش</button></div>
        </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="col-lg-12">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">تاریخچه گزارشات نت</h5></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
                <thead><tr><th class="p-3">#</th><th class="p-3">تاریخ</th><th class="p-3">قالب</th><th class="p-3">شرح خرابی‌ها</th><th class="p-3">زمان تعمیر</th><th class="p-3">عملیات</th></tr></thead>
                <tbody>
                    <?php foreach($reports as $report): ?>
                    <tr>
                        <td class="p-3"><?php echo $report['ReportID']; ?></td>
                        <td class="p-3"><?php echo to_jalali($report['ReportDate']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($report['MoldName']); ?></td>
                        <td class="p-3 small"><?php echo htmlspecialchars($report['BreakdownsSummary']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($report['RepairDurationMinutes'] ?? '-'); ?> دقیقه</td>
                        <td class="p-3">
                            <button class="btn btn-info btn-sm view-details-btn" data-report-id="<?php echo $report['ReportID']; ?>" data-bs-toggle="modal" data-bs-target="#detailsModal" title="مشاهده جزئیات"><i class="bi bi-eye"></i></button>
                            <?php if (has_permission('engineering.maintenance.manage')): ?>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $report['ReportID']; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                            <div class="modal fade" id="deleteModal<?php echo $report['ReportID']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف گزارش شماره <?php echo $report['ReportID']; ?> مطمئن هستید؟</div><div class="modal-footer"><form method="POST"><input type="hidden" name="delete_id" value="<?php echo $report['ReportID']; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button></div></div></div></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div></div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">جزئیات گزارش نت</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailsModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button></div></div></div></div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    const api_url_maintenance = '<?php echo BASE_URL; ?>api/api_get_maintenance_data.php';
    const api_url_add_item = '<?php echo BASE_URL; ?>api/api_add_maintenance_item.php';

    // --- LIVE ITEM CREATION (EVENT DELEGATION) ---
    $(document).on('click', '.save-new-item-btn', function() {
        const btn = $(this);
        const inputField = btn.prev('input[type="text"]');
        const itemName = inputField.val().trim();
        const itemType = btn.data('item-type');
        const parentId = btn.data('parent-id');
        const container = btn.closest('.input-group').prev('.list-group');

        if (!itemName) { alert('لطفا نام مورد جدید را وارد کنید.'); return; }
        
        btn.prop('disabled', true).text('...');
        
        $.post(api_url_add_item, { name: itemName, item_type: itemType, parent_id: parentId })
            .done(function(response) {
                if (response.success) {
                    let newElementHtml = '';
                    if (itemType === 'breakdown') {
                        newElementHtml = `<label class="list-group-item"><input class="form-check-input me-2 breakdown-checkbox" type="checkbox" value="${response.new_id}" checked>${response.new_name}</label>`;
                    } else if (itemType === 'cause') {
                        // BUG FIX: The HTML for a new cause must include its own actions container
                        newElementHtml = `
                            <label class="list-group-item">
                                <input class="form-check-input me-2 cause-checkbox" type="checkbox" value="${response.new_id}" data-breakdown-id="${parentId}" checked>
                                ${response.new_name}
                            </label>
                            <div class="actions-container ps-4 pt-2 pb-2" style="display:none;" id="actions-for-${parentId}-${response.new_id}"></div>
                        `;
                    } else if (itemType === 'action') {
                        const breakdownId = btn.data('breakdown-id');
                        newElementHtml = `<label class="list-group-item list-group-item-light"><input class="form-check-input me-2" type="checkbox" name="actions[${breakdownId}][${parentId}][]" value="${response.new_id}" checked>${response.new_name}</label>`;
                    }
                    
                    const newElement = $(newElementHtml);
                    container.append(newElement);
                    
                    newElement.find('input[type="checkbox"]').trigger('change');
                    inputField.val('');
                } else {
                    alert('خطا: ' + response.message);
                }
            })
            .fail(function() { alert('خطای سرور در هنگام ثبت آیتم جدید.'); })
            .always(function() { btn.prop('disabled', false).text('✓ ذخیره'); });
    });

    // --- HIERARCHICAL FORM LOGIC (EVENT DELEGATION) ---
    $(document).on('change', '.breakdown-checkbox', function() {
        const breakdownId = $(this).val();
        const breakdownName = $(this).parent().text().trim();
        if ($(this).is(':checked')) {
            const breakdownHtml = `<div class="card mb-3" id="breakdown-card-${breakdownId}"><div class="card-header bg-light"><strong>خرابی: ${breakdownName}</strong></div><div class="card-body"><label class="form-label fw-bold">۲. علل خرابی</label><div class="causes-container list-group mb-2"></div><div class="input-group"><input type="text" class="form-control" placeholder="یا نام علت جدید را وارد کنید..."><button class="btn btn-outline-success save-new-item-btn" type="button" data-item-type="cause" data-parent-id="${breakdownId}">✓ ذخیره</button></div></div></div>`;
            $('#dynamic_entries_container').append(breakdownHtml);
            const causesContainer = $(`#breakdown-card-${breakdownId} .causes-container`);
            $.getJSON(api_url_maintenance, { type: 'get_causes', id: breakdownId }, function(res) {
                if (res.success && res.data.length > 0) {
                    $.each(res.data, function(i, cause) {
                        causesContainer.append(`<label class="list-group-item"><input class="form-check-input me-2 cause-checkbox" type="checkbox" value="${cause.CauseID}" data-breakdown-id="${breakdownId}">${cause.CauseDescription}</label><div class="actions-container ps-4 pt-2 pb-2" style="display:none;" id="actions-for-${breakdownId}-${cause.CauseID}"></div>`);
                    });
                } else {
                    causesContainer.append('<p class="text-muted small no-causes-msg">هیچ علتی برای این خرابی تعریف نشده است.</p>');
                }
            });
        } else {
            $(`#breakdown-card-${breakdownId}`).remove();
        }
    });

    $(document).on('change', '.cause-checkbox', function() {
        const causeId = $(this).val();
        const breakdownId = $(this).data('breakdown-id');
        const actionsContainer = $(`#actions-for-${breakdownId}-${causeId}`);
        if ($(this).is(':checked')) {
            actionsContainer.show().html('<p class="text-muted small">در حال بارگذاری...</p>');
            $.getJSON(api_url_maintenance, { type: 'get_actions', id: causeId }, function(res) {
                let actionsHtml = '<label class="form-label fw-bold">۳. اقدامات انجام شده</label><div class="list-group actions-list mb-2">';
                if (res.success && res.data.length > 0) {
                    $.each(res.data, function(i, action) {
                        actionsHtml += `<label class="list-group-item list-group-item-light"><input class="form-check-input me-2" type="checkbox" name="actions[${breakdownId}][${causeId}][]" value="${action.ActionID}">${action.ActionDescription}</label>`;
                    });
                } else {
                    actionsHtml += '<p class="text-muted small no-actions-msg mb-0">هیچ اقدامی برای این علت تعریف نشده.</p>';
                }
                actionsHtml += `</div><div class="input-group"><input type="text" class="form-control" placeholder="یا نام اقدام جدید را وارد کنید..."><button class="btn btn-outline-success save-new-item-btn" type="button" data-item-type="action" data-parent-id="${causeId}" data-breakdown-id="${breakdownId}">✓ ذخیره</button></div>`;
                actionsContainer.html(actionsHtml);
            });
        } else {
            actionsContainer.hide().empty();
        }
    });

    // --- DETAILS MODAL LOGIC ---
    $('.view-details-btn').on('click', function() {
        const reportId = $(this).data('report-id');
        const modalBody = $('#detailsModalBody');
        modalBody.html('<div class="text-center">در حال بارگذاری...</div>');
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_maintenance_report_details.php', { report_id: reportId }, function(res) {
            if (res.success) {
                let content = '';
                if (Object.keys(res.data).length > 0) {
                    $.each(res.data, function(breakdown, causes) {
                        content += `<div class="card mb-3"><div class="card-header"><strong>خرابی: ${breakdown}</strong></div><div class="card-body">`;
                        $.each(causes, function(cause, actions) {
                            content += `<p class="mb-1"><strong>- علت:</strong> ${cause}</p><ul class="list-group list-group-flush mb-2">`;
                            $.each(actions, function(i, action) { content += `<li class="list-group-item small py-1">${action}</li>`; });
                            content += '</ul>';
                        });
                        content += '</div></div>';
                    });
                } else {
                    content = '<div class="alert alert-warning">هیچ جزئیاتی برای این گزارش ثبت نشده است.</div>';
                }
                modalBody.html(content);
            } else {
                modalBody.html('<div class="alert alert-danger">خطا در دریافت اطلاعات.</div>');
            }
        });
    });
});
</script>

