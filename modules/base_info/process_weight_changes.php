<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('base_info.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_process_weight_changes';
const PRIMARY_KEY = 'ProcessWeightID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($current_page - 1) * $records_per_page;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'PartID' => (int)$_POST['part_id'],
            'FromStationID' => (int)$_POST['from_station_id'],
            'ToStationID' => (int)$_POST['to_station_id'],
            'WeightChangePercent' => (float)$_POST['weight_change_percent'],
            'EffectiveFrom' => to_gregorian($_POST['effective_from']),
            'EffectiveTo' => !empty($_POST['effective_to']) ? to_gregorian($_POST['effective_to']) : null,
            'Notes' => trim($_POST['notes'])
        ];

        if (empty($data['PartID']) || empty($data['FromStationID']) || empty($data['ToStationID']) || empty($data['EffectiveFrom'])) {
            $result = ['success' => false, 'message' => 'قطعه، ایستگاه مبدا/مقصد و تاریخ شروع الزامی است.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        }
        $_SESSION['message'] = $result['message'];
    }
    header("Location: " . BASE_URL . "modules/base_info/process_weight_changes.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

$items_query = "SELECT pwc.*, p.PartName, fs.StationName as FromStationName, ts.StationName as ToStationName
                FROM " . TABLE_NAME . " pwc
                JOIN tbl_parts p ON pwc.PartID = p.PartID
                JOIN tbl_stations fs ON pwc.FromStationID = fs.StationID
                JOIN tbl_stations ts ON pwc.ToStationID = ts.StationID
                ORDER BY pwc.EffectiveFrom DESC, p.PartName
                LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($items_query);
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$stations = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations ORDER BY StationName");

$pageTitle = "مدیریت تغییرات وزن فرآیند";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش رکورد' : 'افزودن رکورد جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="process_weight_changes.php">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">خانواده قطعه</label><select class="form-select" id="family_id_selector"><option value="">-- برای فیلتر قطعات --</option><?php foreach ($families as $family): ?><option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">قطعه</label><select class="form-select" id="part_id" name="part_id" required disabled><option value="">-- ابتدا خانواده را انتخاب کنید --</option></select></div>
                <div class="mb-3"><label class="form-label">از ایستگاه</label><select class="form-select" name="from_station_id" required><option value="">انتخاب کنید</option><?php foreach ($stations as $station): ?><option value="<?php echo $station['StationID']; ?>" <?php echo (isset($itemToEdit['FromStationID']) && $itemToEdit['FromStationID'] == $station['StationID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($station['StationName']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">به ایستگاه</label><select class="form-select" name="to_station_id" required><option value="">انتخاب کنید</option><?php foreach ($stations as $station): ?><option value="<?php echo $station['StationID']; ?>" <?php echo (isset($itemToEdit['ToStationID']) && $itemToEdit['ToStationID'] == $station['StationID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($station['StationName']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">درصد تغییر وزن (%)</label><input type="number" step="0.01" class="form-control" name="weight_change_percent" value="<?php echo htmlspecialchars($itemToEdit['WeightChangePercent'] ?? '0.00'); ?>" required></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">از تاریخ</label><input type="text" class="form-control persian-date" name="effective_from" value="<?php echo to_jalali($itemToEdit['EffectiveFrom'] ?? date('Y-m-d')); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">تا تاریخ (اختیاری)</label><input type="text" class="form-control persian-date" name="effective_to" value="<?php echo to_jalali($itemToEdit['EffectiveTo'] ?? ''); ?>"></div>
                </div>
                <div class="mb-3"><label class="form-label">ملاحظات</label><textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($itemToEdit['Notes'] ?? ''); ?></textarea></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="process_weight_changes.php" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0">تاریخچه تغییرات وزن (صفحه <?php echo $current_page; ?> از <?php echo $total_pages; ?>)</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover table-sm mb-0">
            <thead><tr><th class="p-2">قطعه</th><th class="p-2">از</th><th class="p-2">به</th><th class="p-2">تغییر (%)</th><th class="p-2">شروع</th><th class="p-2">پایان</th><th class="p-2">عملیات</th></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td class="p-2"><?php echo htmlspecialchars($item['PartName']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($item['FromStationName']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($item['ToStationName']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($item['WeightChangePercent']); ?>%</td>
                <td class="p-2"><?php echo to_jalali($item['EffectiveFrom']); ?></td>
                <td class="p-2"><?php echo to_jalali($item['EffectiveTo']); ?></td>
                <td class="p-2">
                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                    <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">آیا از حذف این رکورد تغییر وزن مطمئن هستید؟</div>
                        <div class="modal-footer">
                            <form method="POST" action="process_weight_changes.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                        </div>
                    </div></div></div>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer d-flex justify-content-center">
            <nav><ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">قبلی</a></li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a></li>
            </ul></nav>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$(document).ready(function() {
    const familySelect = $('#family_id_selector');
    const partSelect = $('#part_id');
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_by_family.php';
    const initialPartId = <?php echo $editMode && isset($itemToEdit['PartID']) ? $itemToEdit['PartID'] : 'null'; ?>;
    const initialFamilyId = <?php echo $editMode && isset($itemToEdit['PartID']) ? find_by_id($pdo, 'tbl_parts', $itemToEdit['PartID'], 'PartID')['FamilyID'] ?? 'null' : 'null'; ?>;

    function populateParts(familyId, selectedPartId = null) {
        partSelect.html('<option value="">در حال بارگذاری...</option>').prop('disabled', true);
        if (!familyId) {
            partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }
        $.getJSON(apiPartsUrl, { family_id: familyId })
            .done(function(response) {
                partSelect.html('<option value="">-- انتخاب قطعه --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(part) {
                        const option = $('<option>', { value: part.PartID, text: part.PartName });
                        if (part.PartID == selectedPartId) {
                            option.prop('selected', true);
                        }
                        partSelect.append(option);
                    });
                    partSelect.prop('disabled', false);
                } else {
                    partSelect.html('<option value="">-- قطعه‌ای یافت نشد --</option>');
                }
            })
            .fail(function() {
                partSelect.html('<option value="">-- خطا در بارگذاری --</option>');
            });
    }

    familySelect.on('change', function() {
        populateParts($(this).val());
    });

    if (initialFamilyId) {
        familySelect.val(initialFamilyId);
        populateParts(initialFamilyId, initialPartId);
    }
});
</script>
