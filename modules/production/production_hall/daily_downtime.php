<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.production_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('production.production_hall.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
        header("Location: daily_downtime.php");
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, 'tbl_prod_downtime_header', (int)$_POST['delete_id'], 'HeaderID');
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        $pdo->beginTransaction();
        try {
            $log_date = to_gregorian($_POST['log_date']);
            $machine_type = $_POST['machine_type'];

            $header_stmt = $pdo->prepare("SELECT HeaderID FROM tbl_prod_downtime_header WHERE LogDate = ? AND MachineType = ?");
            $header_stmt->execute([$log_date, $machine_type]);
            $header_id = $header_stmt->fetchColumn();

            if (!$header_id) {
                $header_res = insert_record($pdo, 'tbl_prod_downtime_header', ['LogDate' => $log_date, 'MachineType' => $machine_type]);
                if (!$header_res['success']) throw new Exception("خطا در ایجاد هدر گزارش توقفات.");
                $header_id = $header_res['id'];
            }
            
            $details_stmt = $pdo->prepare("INSERT INTO tbl_prod_downtime_details (HeaderID, MachineID, MoldID, ReasonID, Duration) VALUES (?, ?, ?, ?, ?)");
            $setup_stmt = $pdo->prepare("INSERT INTO tbl_machine_current_setup (MachineID, CurrentMoldID, SetupTimestamp) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE CurrentMoldID = VALUES(CurrentMoldID), SetupTimestamp = NOW()");

            if (isset($_POST['downtime'])) {
                foreach ($_POST['downtime'] as $machine_id => $data) {
                    if (!empty($data['mold_id'])) {
                        // Update the current setup for this machine
                        $setup_stmt->execute([(int)$machine_id, (int)$data['mold_id']]);

                        if (isset($data['entries'])) {
                            foreach ($data['entries'] as $entry) {
                                if (!empty($entry['reason_id']) && !empty($entry['duration'])) {
                                    $details_stmt->execute([$header_id, (int)$machine_id, (int)$data['mold_id'], (int)$entry['reason_id'], (int)$entry['duration']]);
                                }
                            }
                        }
                    }
                }
            }
            $pdo->commit();
            $_SESSION['message'] = 'آمار توقفات با موفقیت ثبت شد.';
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'خطا در ثبت آمار: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    }
    header("Location: daily_downtime.php");
    exit;
}

// --- PAGINATION & HISTORY ---
const RECORDS_PER_PAGE_DOWNTIME = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM tbl_prod_downtime_header")->fetchColumn();
$total_pages = ceil($total_records / RECORDS_PER_PAGE_DOWNTIME);
$offset = ($current_page - 1) * RECORDS_PER_PAGE_DOWNTIME;

$history_logs = find_all($pdo, "
    SELECT 
        h.HeaderID, h.LogDate, h.MachineType,
        (SELECT SUM(d.Duration) FROM tbl_prod_downtime_details d WHERE d.HeaderID = h.HeaderID) as TotalDuration,
        (SELECT COUNT(DISTINCT d.MachineID) FROM tbl_prod_downtime_details d WHERE d.HeaderID = h.HeaderID) as MachineCount
    FROM tbl_prod_downtime_header h
    ORDER BY h.LogDate DESC, h.HeaderID DESC
    LIMIT :limit OFFSET :offset
", [':limit' => RECORDS_PER_PAGE_DOWNTIME, ':offset' => $offset]);

$downtime_reasons = find_all($pdo, "SELECT * FROM tbl_downtimereasons ORDER BY ReasonDescription");
$machine_types = $pdo->query("SELECT DISTINCT MachineType FROM tbl_machines WHERE MachineType IS NOT NULL AND MachineType != '' ORDER BY MachineType")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "ثبت توقفات روزانه";
include __DIR__ . '/../../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت توقفات روزانه</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (has_permission('production.production_hall.manage')): ?>
<form method="POST">
    <div class="card content-card">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-4 mb-3"><label class="form-label">تاریخ</label><input type="text" name="log_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">نوع دستگاه</label><select id="machine_type_selector" name="machine_type" class="form-select" required><option value="">انتخاب کنید...</option><?php foreach($machine_types as $type):?><option value="<?php echo $type;?>"><?php echo $type;?></option><?php endforeach;?></select></div>
            </div>
        </div>
    </div>
    <div id="downtime_form_container" class="mt-4"></div>
    <div class="text-end mt-4"><button type="submit" class="btn btn-primary">ثبت نهایی توقفات</button></div>
</form>
<?php endif; ?>

<div class="card content-card mt-4">
    <div class="card-header"><h5 class="mb-0">تاریخچه ثبت توقفات</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead><tr><th class="p-3">تاریخ</th><th class="p-3">نوع دستگاه</th><th class="p-3">تعداد دستگاه</th><th class="p-3">مجموع زمان توقف (دقیقه)</th><th class="p-3">عملیات</th></tr></thead>
                <tbody>
                    <?php foreach($history_logs as $log): ?>
                    <tr>
                        <td class="p-3"><?php echo to_jalali($log['LogDate']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($log['MachineType']); ?></td>
                        <td class="p-3"><?php echo $log['MachineCount']; ?></td>
                        <td class="p-3"><?php echo $log['TotalDuration'] ?? 0; ?></td>
                        <td class="p-3">
                            <button class="btn btn-info btn-sm view-details-btn" data-header-id="<?php echo $log['HeaderID']; ?>" data-bs-toggle="modal" data-bs-target="#detailsModal"><i class="bi bi-eye"></i> جزئیات</button>
                            <?php if (has_permission('production.production_hall.manage')): ?>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $log['HeaderID']; ?>"><i class="bi bi-trash"></i> حذف</button>
                            <div class="modal fade" id="deleteModal<?php echo $log['HeaderID']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف این گزارش توقف مطمئن هستید؟ (تمام رکوردهای آن حذف خواهد شد)</div><div class="modal-footer"><form method="POST"><input type="hidden" name="delete_id" value="<?php echo $log['HeaderID']; ?>"><button type="submit" class="btn btn-danger">بله، حذف کن</button></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button></div></div></div></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
<div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">جزئیات توقفات ثبت شده</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailsModalBody"><div class="text-center">در حال بارگذاری...</div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button></div>
</div></div></div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    $('#machine_type_selector').on('change', function() {
        const machineType = $(this).val();
        const container = $('#downtime_form_container');
        container.html('<p class="text-center">در حال بارگذاری...</p>');
        if (!machineType) { container.html(''); return; }
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_machines_by_type.php', { machine_type: machineType }, function(response) {
            if (response.success && response.data.length > 0) {
                let formHtml = '';
                $.each(response.data, function(i, machine) {
                    formHtml += `
                        <div class="card content-card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">${machine.MachineName}</h5>
                                <div>
                                    <label class="form-label me-2 mb-0">قالب:</label>
                                    <select name="downtime[${machine.MachineID}][mold_id]" class="form-select form-select-sm d-inline-block w-auto compatible-mold-selector" data-machine-id="${machine.MachineID}" data-current-mold-id="${machine.CurrentMoldID || ''}"><option value="">...بارگذاری</option></select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="downtime-entries-container" data-machine-id="${machine.MachineID}">
                                    <div class="row align-items-end mb-2 downtime-entry">
                                        <div class="col-md-5"><label class="form-label small">علت توقف</label><select name="downtime[${machine.MachineID}][entries][0][reason_id]" class="form-select form-select-sm"><option value="">-</option><?php foreach($downtime_reasons as $reason): ?><option value="<?php echo $reason['ReasonID']; ?>"><?php echo $reason['ReasonDescription']; ?></option><?php endforeach; ?></select></div>
                                        <div class="col-md-5"><label class="form-label small">زمان (دقیقه)</label><input type="number" name="downtime[${machine.MachineID}][entries][0][duration]" class="form-control form-control-sm"></div>
                                        <div class="col-md-2"><button type="button" class="btn btn-sm btn-danger remove-downtime-btn" style="display:none;">-</button></div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success add-downtime-btn" data-machine-id="${machine.MachineID}">+ افزودن توقف</button>
                            </div>
                        </div>`;
                });
                container.html(formHtml);
                $('.compatible-mold-selector').each(function() { fetchCompatibleMolds($(this)); });
            } else {
                container.html('<p class="text-muted text-center">دستگاهی از این نوع یافت نشد.</p>');
            }
        });
    });

    $(document).on('click', '.add-downtime-btn', function() {
        const machineId = $(this).data('machine-id');
        const container = $(this).prev('.downtime-entries-container');
        const entryCount = container.find('.downtime-entry').length;
        const newEntryHtml = `<div class="row align-items-end mb-2 downtime-entry"><div class="col-md-5"><select name="downtime[${machineId}][entries][${entryCount}][reason_id]" class="form-select form-select-sm"><option value="">-</option><?php foreach($downtime_reasons as $reason): ?><option value="<?php echo $reason['ReasonID']; ?>"><?php echo $reason['ReasonDescription']; ?></option><?php endforeach; ?></select></div><div class="col-md-5"><input type="number" name="downtime[${machineId}][entries][${entryCount}][duration]" class="form-control form-control-sm"></div><div class="col-md-2"><button type="button" class="btn btn-sm btn-danger remove-downtime-btn">-</button></div></div>`;
        container.append(newEntryHtml);
        updateRemoveButtons(container);
    });

    $(document).on('click', '.remove-downtime-btn', function() {
        const container = $(this).closest('.downtime-entries-container');
        $(this).closest('.downtime-entry').remove();
        updateRemoveButtons(container);
    });

    function updateRemoveButtons(container) {
        const entries = container.find('.downtime-entry');
        entries.length > 1 ? entries.find('.remove-downtime-btn').show() : entries.find('.remove-downtime-btn').hide();
    }

    function fetchCompatibleMolds(selector) {
        const machineId = selector.data('machine-id');
        const currentMoldId = selector.data('current-mold-id');
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_compatible_molds.php', { machine_id: machineId }, function(response) {
            if (response.success) {
                let optionsHtml = '<option value="">انتخاب قالب...</option>';
                $.each(response.data, function(i, mold) { optionsHtml += `<option value="${mold.MoldID}">${mold.MoldName}</option>`; });
                selector.html(optionsHtml);
                if (currentMoldId) {
                    selector.val(currentMoldId);
                }
            } else {
                selector.html('<option value="">خطا</option>');
            }
        });
    }

    $('.view-details-btn').on('click', function() {
        const headerId = $(this).data('header-id');
        const modalBody = $('#detailsModalBody');
        modalBody.html('<div class="text-center">در حال بارگذاری...</div>');
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_downtime_details.php', { header_id: headerId }, function(response) {
            if (response.success) {
                let details = response.data;
                let content = `<p><strong>تاریخ:</strong> ${details.log_date}</p><p><strong>نوع دستگاه:</strong> ${details.machine_type}</p><hr><h6>جزئیات توقفات:</h6>`;
                if(details.downtimes.length > 0) {
                    let groupedByMachine = {};
                    $.each(details.downtimes, function(i, dt) {
                        if (!groupedByMachine[dt.MachineName]) {
                            groupedByMachine[dt.MachineName] = { mold: dt.MoldName, entries: [] };
                        }
                        groupedByMachine[dt.MachineName].entries.push({ reason: dt.ReasonDescription, duration: dt.Duration });
                    });
                    
                    $.each(groupedByMachine, function(machineName, data) {
                        content += `<div class="card mb-3"><div class="card-header"><strong>${machineName}</strong> (قالب: ${data.mold})</div><div class="card-body p-0"><table class="table table-sm mb-0">`;
                        $.each(data.entries, function(i, entry) {
                            content += `<tr><td>${entry.reason}</td><td class="text-end">${entry.duration} دقیقه</td></tr>`;
                        });
                        content += '</table></div></div>';
                    });
                } else {
                    content += '<p class="text-muted">هیچ توقفی برای این گزارش ثبت نشده است.</p>';
                }
                modalBody.html(content);
            } else {
                modalBody.html(`<div class="alert alert-danger">${response.message}</div>`);
            }
        }).fail(function() {
            modalBody.html('<div class="alert alert-danger">خطا در برقراری ارتباط با سرور.</div>');
        });
    });
});
</script>

