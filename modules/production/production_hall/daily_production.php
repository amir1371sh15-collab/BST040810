<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.production_hall.view')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('production.production_hall.manage')) {
        $_SESSION['message'] = 'شما مجوز انجام این عملیات را ندارید.';
        $_SESSION['message_type'] = 'danger';
        header("Location: daily_production.php");
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, 'tbl_prod_daily_log_header', (int)$_POST['delete_id'], 'HeaderID');
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        $pdo->beginTransaction();
        try {
            $header_data = [
                'LogDate' => to_gregorian($_POST['log_date']),
                'DepartmentID' => 1, // Production Department ID is assumed to be 1
                'MachineType' => $_POST['machine_type'],
                'ManHours' => (float)$_POST['man_hours'],
                'AvailableTimeMinutes' => (int)$_POST['available_time']
            ];
            $header_res = insert_record($pdo, 'tbl_prod_daily_log_header', $header_data);
            if (!$header_res['success']) throw new Exception("خطا در ثبت هدر گزارش.");
            $header_id = $header_res['id'];

            $details_stmt = $pdo->prepare("INSERT INTO tbl_prod_daily_log_details (HeaderID, MachineID, MoldID, PartID, ProductionKG) VALUES (?, ?, ?, ?, ?)");
            if (isset($_POST['production'])) {
                foreach ($_POST['production'] as $machine_id => $entries) {
                    foreach ($entries as $entry) {
                        $mold_id = isset($entry['mold_id']) && !empty($entry['mold_id']) ? (int)$entry['mold_id'] : null;
                        if (!empty($entry['part_id']) && !empty($entry['kg'])) {
                            $details_stmt->execute([$header_id, (int)$machine_id, $mold_id, (int)$entry['part_id'], (float)$entry['kg']]);
                        }
                    }
                }
            }
            $pdo->commit();
            $_SESSION['message'] = 'آمار تولید با موفقیت ثبت شد.';
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'خطا در ثبت آمار: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    }
    header("Location: daily_production.php");
    exit;
}


const RECORDS_PER_PAGE = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$total_records = $pdo->query("SELECT COUNT(*) FROM tbl_prod_daily_log_header")->fetchColumn();
$total_pages = ceil($total_records / RECORDS_PER_PAGE);
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

$history_logs = find_all($pdo, "
    SELECT h.*, d.DepartmentName
    FROM tbl_prod_daily_log_header h
    JOIN tbl_departments d ON h.DepartmentID = d.DepartmentID
    ORDER BY h.LogDate DESC, h.HeaderID DESC
    LIMIT :limit OFFSET :offset
", [':limit' => RECORDS_PER_PAGE, ':offset' => $offset]);

$machine_types = $pdo->query("SELECT DISTINCT MachineType FROM tbl_machines WHERE MachineType IS NOT NULL AND MachineType != '' ORDER BY MachineType")->fetchAll(PDO::FETCH_COLUMN);
$part_families = find_all($pdo, "SELECT * FROM tbl_part_families ORDER BY FamilyName");

$pageTitle = "ثبت تولید روزانه";
include __DIR__ . '/../../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت تولید روزانه</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (has_permission('production.production_hall.manage')): ?>
<form method="POST">
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">فرم ثبت آمار جدید</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3"><label class="form-label">تاریخ</label><input type="text" name="log_date" class="form-control persian-date" value="<?php echo to_jalali(date('Y-m-d')); ?>" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">نفر ساعت</label><input type="number" step="0.1" name="man_hours" class="form-control" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">زمان در دسترس (دقیقه)</label><input type="number" name="available_time" class="form-control" placeholder="مثال: 480" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">نوع دستگاه</label><select id="machine_type_selector" name="machine_type" class="form-select" required><option value="">انتخاب کنید...</option><?php foreach($machine_types as $type):?><option value="<?php echo $type;?>"><?php echo $type;?></option><?php endforeach;?></select></div>
            </div>
        </div>
    </div>

    <div id="machines_production_container"></div>
    
    <div id="submission_footer" class="text-end mt-4" style="display: none;">
        <button type="submit" class="btn btn-primary">ثبت نهایی آمار</button>
    </div>
</form>
<?php endif; ?>


<div class="card content-card mt-4">
    <div class="card-header"><h5 class="mb-0">تاریخچه ثبت تولید</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table table-striped table-hover mb-0">
            <thead><tr><th class="p-3">تاریخ</th><th class="p-3">نوع دستگاه</th><th class="p-3">نفر ساعت</th><th class="p-3">زمان در دسترس</th><th class="p-3">عملیات</th></tr></thead>
            <tbody>
                <?php foreach($history_logs as $log): ?>
                <tr>
                    <td class="p-3"><?php echo to_jalali($log['LogDate']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($log['MachineType']); ?></td>
                    <td class="p-3"><?php echo $log['ManHours']; ?></td>
                    <td class="p-3"><?php echo $log['AvailableTimeMinutes']; ?> دقیقه</td>
                    <td class="p-3">
                        <button class="btn btn-info btn-sm view-details-btn" data-header-id="<?php echo $log['HeaderID']; ?>" data-bs-toggle="modal" data-bs-target="#detailsModal"><i class="bi bi-eye"></i> جزئیات</button>
                        <?php if (has_permission('production.production_hall.manage')): ?>
                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $log['HeaderID']; ?>"><i class="bi bi-trash"></i> حذف</button>
                        <div class="modal fade" id="deleteModal<?php echo $log['HeaderID']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف این گزارش تولید مطمئن هستید؟</div><div class="modal-footer"><form method="POST"><input type="hidden" name="delete_id" value="<?php echo $log['HeaderID']; ?>"><button type="submit" class="btn btn-danger">بله</button></form><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button></div></div></div></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
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

<div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">جزئیات ثبت تولید</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailsModalBody"><div class="text-center">در حال بارگذاری...</div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button></div>
</div></div></div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<script>
// The Javascript logic remains the same as the previous correct version.
// No changes are needed here.
$(document).ready(function() {
    let rowIndex = 0;

    $('#machine_type_selector').on('change', function() {
        const machineType = $(this).val();
        const container = $('#machines_production_container');
        container.html('<p class="text-center">در حال بارگذاری...</p>');
        $('#submission_footer').hide();

        if (!machineType) {
            container.html('');
            return;
        }

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_machines_by_type.php', { machine_type: machineType }, function(response) {
            container.empty();
            if (response.success && response.data.length > 0) {
                $.each(response.data, function(i, machine) {
                    const machineCardHtml = `
                        <div class="card content-card mb-3">
                            <div class="card-header">${machine.MachineName}</div>
                            <div class="card-body">
                                <div class="production-rows-container" data-machine-id="${machine.MachineID}"></div>
                                <button type="button" class="btn btn-sm btn-outline-success add-production-row mt-2" data-machine-id="${machine.MachineID}" data-machine-type="${machineType}">+ افزودن ردیف تولید</button>
                            </div>
                        </div>`;
                    container.append(machineCardHtml);
                    addProductionRow(machine.MachineID, machineType);
                });
                $('#submission_footer').show();
            } else {
                container.html('<p class="text-muted text-center">دستگاهی از این نوع یافت نشد.</p>');
            }
        });
    });

    function addProductionRow(machineId, machineType) {
        rowIndex++;
        const rowContainer = $(`.production-rows-container[data-machine-id="${machineId}"]`);
        let newRowHtml = '';

        if (machineType === 'پیچ سازی') {
            newRowHtml = `
                <div class="row production-entry-row mb-2 align-items-center">
                    <div class="col-md-4">
                        <select class="form-select part-family-selector" data-row-id="${rowIndex}">
                            <option value="">-- انتخاب خانواده --</option>
                            <?php foreach($part_families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>"><?php echo $family['FamilyName']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="production[${machineId}][${rowIndex}][part_id]" id="part-name-for-${rowIndex}" class="form-select part-selector" disabled>
                            <option value="">-- انتخاب قطعه --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.1" name="production[${machineId}][${rowIndex}][kg]" class="form-control" placeholder="تولید (KG)">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-danger remove-row-btn">&times;</button>
                    </div>
                </div>`;
        } else { // Mold-based machines
            newRowHtml = `
                <div class="row production-entry-row mb-2 align-items-center">
                    <div class="col-md-4">
                        <select name="production[${machineId}][${rowIndex}][mold_id]" class="form-select mold-selector" data-machine-id="${machineId}">
                            <option value="">-- انتخاب قالب --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="production[${machineId}][${rowIndex}][part_id]" class="form-select part-selector" disabled>
                            <option value="">-- انتخاب قطعه --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.1" name="production[${machineId}][${rowIndex}][kg]" class="form-control" placeholder="تولید (KG)">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-danger remove-row-btn">&times;</button>
                    </div>
                </div>`;
        }
        
        const newRow = $(newRowHtml);
        rowContainer.append(newRow);

        if (machineType !== 'پیچ سازی') {
            fetchCompatibleMolds(newRow.find('.mold-selector'));
        }
    }

    $(document).on('click', '.add-production-row', function() {
        addProductionRow($(this).data('machine-id'), $(this).data('machine-type'));
    });

    $(document).on('click', '.remove-row-btn', function() {
        $(this).closest('.production-entry-row').remove();
    });

    $(document).on('change', '.mold-selector', function() {
        const moldId = $(this).val();
        const partSelector = $(this).closest('.production-entry-row').find('.part-selector');
        fetchProducibleParts(moldId, partSelector);
    });
    
    $(document).on('change', '.part-family-selector', function() {
        const familyId = $(this).val();
        const rowId = $(this).data('row-id');
        const partSelector = $(`#part-name-for-${rowId}`);
        fetchPartsByFamily(familyId, partSelector);
    });

    function fetchCompatibleMolds(selector) {
        const machineId = selector.data('machine-id');
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_compatible_molds.php', { machine_id: machineId }, function(response) {
            if (response.success) {
                $.each(response.data, function(i, mold) {
                    selector.append($('<option>', { value: mold.MoldID, text: mold.MoldName }));
                });
            }
        });
    }

    function fetchProducibleParts(moldId, selector) {
        selector.html('<option value="">بارگذاری...</option>').prop('disabled', true);
        if (!moldId) {
            selector.html('<option value="">-- انتخاب قطعه --</option>'); return;
        }
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_producible_parts_by_mold.php', { mold_id: moldId }, function(response) {
            selector.html('<option value="">-- انتخاب قطعه --</option>');
            if (response.success && response.data.length > 0) {
                $.each(response.data, function(i, part) {
                    selector.append($('<option>', { value: part.PartID, text: part.PartName }));
                });
                selector.prop('disabled', false);
            } else {
                 selector.html('<option value="">قطعه‌ای یافت نشد</option>');
            }
        });
    }

    function fetchPartsByFamily(familyId, selector) {
        selector.html('<option value="">بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            selector.html('<option value="">-- انتخاب قطعه --</option>'); return;
        }
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_parts_by_family.php', { family_id: familyId }, function(response) {
            selector.html('<option value="">-- انتخاب قطعه --</option>');
            if (response.success && response.data.length > 0) {
                $.each(response.data, function(i, part) {
                    selector.append($('<option>', { value: part.PartID, text: part.PartName }));
                });
                selector.prop('disabled', false);
            } else {
                 selector.html('<option value="">قطعه‌ای یافت نشد</option>');
            }
        });
    }
    
    $('.view-details-btn').on('click', function() {
        const headerId = $(this).data('header-id');
        const modalBody = $('#detailsModalBody');
        modalBody.html('<div class="text-center">در حال بارگذاری...</div>');
        
        $.getJSON('<?php echo BASE_URL; ?>api/api_get_production_details.php', { header_id: headerId }, function(response) {
            if (response.success && response.data.length > 0) {
                let detailsHtml = '<table class="table table-bordered"><thead><tr><th>دستگاه</th><th>قالب</th><th>قطعه</th><th>میزان تولید (KG)</th></tr></thead><tbody>';
                $.each(response.data, function(i, detail) {
                    detailsHtml += `<tr><td>${detail.MachineName}</td><td>${detail.MoldName || '-'}</td><td>${detail.PartName}</td><td>${detail.ProductionKG}</td></tr>`;
                });
                detailsHtml += '</tbody></table>';
                modalBody.html(detailsHtml);
            } else {
                modalBody.html('<div class="alert alert-warning">هیچ جزئیاتی برای این گزارش ثبت نشده است.</div>');
            }
        }).fail(function() {
            modalBody.html('<div class="alert alert-danger">خطا در دریافت اطلاعات.</div>');
        });
    });
});
</script>