<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('quality.overrides.manage')) { die('شما مجوز مدیریت مسیرهای غیراستاندارد را ندارید.'); }

const TABLE_NAME = 'tbl_route_overrides';
const PRIMARY_KEY = 'OverrideID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $deviationId = filter_input(INPUT_POST, 'deviation_id', FILTER_VALIDATE_INT);
        $data = [
            'FamilyID' => filter_input(INPUT_POST, 'family_id', FILTER_VALIDATE_INT),
            'FromStationID' => filter_input(INPUT_POST, 'from_station_id', FILTER_VALIDATE_INT),
            'ToStationID' => filter_input(INPUT_POST, 'to_station_id', FILTER_VALIDATE_INT),
            'OutputStatusID' => !empty($_POST['output_status_id']) ? (int)$_POST['output_status_id'] : null, // Added
            'DeviationID' => $deviationId ?: null,
            'IsActive' => isset($_POST['is_active']) ? 1 : 0,
            'Description' => trim($_POST['description'] ?? ''),
        ];

        // Updated validation
        if (empty($data['FamilyID']) || empty($data['FromStationID']) || empty($data['ToStationID']) || empty($data['OutputStatusID'])) {
             $result = ['success' => false, 'message' => 'انتخاب خانواده، ایستگاه مبدا، ایستگاه مقصد و وضعیت خروجی الزامی است.'];
             $_SESSION['message_type'] = 'warning';
        } else {
             // Check for duplicates before inserting/updating
             $existing_query = "SELECT OverrideID FROM " . TABLE_NAME . " WHERE FamilyID = ? AND FromStationID = ? AND ToStationID = ?";
             $existing_params = [$data['FamilyID'], $data['FromStationID'], $data['ToStationID']];
             if (isset($_POST['id']) && !empty($_POST['id'])) {
                 $existing_query .= " AND OverrideID != ?";
                 $existing_params[] = (int)$_POST['id'];
             }
             $existing = find_all($pdo, $existing_query, $existing_params);

             if (!empty($existing)) {
                 $result = ['success' => false, 'message' => 'خطا: مسیر غیراستاندارد با این مشخصات (خانواده، مبدا، مقصد) از قبل وجود دارد.'];
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
        // Only set message if not already set by validation/duplicate check
        if (!isset($_SESSION['message'])) {
             $_SESSION['message'] = $result['message'] ?? 'خطای ناشناخته رخ داد.';
        }
    }
    header("Location: " . BASE_URL . "modules/quality/route_overrides.php");
    exit;
}


if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items = find_all($pdo, "
    SELECT ro.*, pf.FamilyName, fs.StationName as FromStationName, ts.StationName as ToStationName, qd.DeviationCode, ps.StatusName as OutputStatusName /* Added Status Name */
    FROM " . TABLE_NAME . " ro
    JOIN tbl_part_families pf ON ro.FamilyID = pf.FamilyID
    JOIN tbl_stations fs ON ro.FromStationID = fs.StationID
    JOIN tbl_stations ts ON ro.ToStationID = ts.StationID
    LEFT JOIN tbl_quality_deviations qd ON ro.DeviationID = qd.DeviationID
    LEFT JOIN tbl_part_statuses ps ON ro.OutputStatusID = ps.StatusID /* Join with status table */
    ORDER BY pf.FamilyName, fs.StationName, ts.StationName
");

$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$stations = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations ORDER BY StationName");
$all_deviations = find_all($pdo, "SELECT DeviationID, DeviationCode, Reason, Status FROM tbl_quality_deviations ORDER BY DeviationCode");
$part_statuses = find_all($pdo, "SELECT * FROM tbl_part_statuses ORDER BY StatusName"); // Fetch statuses for dropdown

$pageTitle = "مدیریت مسیرهای غیراستاندارد (Overrides)";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت به داشبورد کیفیت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش مسیر غیراستاندارد' : 'افزودن مسیر غیراستاندارد'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="route_overrides.php">
                    <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label for="family_id" class="form-label">خانواده قطعه</label>
                        <select class="form-select" id="family_id" name="family_id" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($families as $family): ?>
                                <option value="<?php echo $family['FamilyID']; ?>" <?php echo (isset($itemToEdit['FamilyID']) && $itemToEdit['FamilyID'] == $family['FamilyID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label for="from_station_id" class="form-label">از ایستگاه (مبدا)</label>
                        <select class="form-select" id="from_station_id" name="from_station_id" required>
                            <option value="">-- انتخاب کنید --</option>
                             <?php foreach($stations as $s): ?>
                                 <option value="<?php echo $s['StationID']; ?>" <?php echo (isset($itemToEdit['FromStationID']) && $itemToEdit['FromStationID'] == $s['StationID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['StationName']); ?></option>
                             <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label for="to_station_id" class="form-label">به ایستگاه (مقصد)</label>
                        <select class="form-select" id="to_station_id" name="to_station_id" required>
                            <option value="">-- انتخاب کنید --</option>
                             <?php foreach($stations as $s): ?>
                                 <option value="<?php echo $s['StationID']; ?>" <?php echo (isset($itemToEdit['ToStationID']) && $itemToEdit['ToStationID'] == $s['StationID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['StationName']); ?></option>
                             <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Changed input to select for OutputStatus -->
                    <div class="mb-3">
                        <label for="output_status_id" class="form-label">وضعیت خروجی</label>
                        <select class="form-select" id="output_status_id" name="output_status_id" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($part_statuses as $status): ?>
                            <option value="<?php echo $status['StatusID']; ?>" <?php echo (isset($itemToEdit['OutputStatusID']) && $itemToEdit['OutputStatusID'] == $status['StatusID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['StatusName']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="deviation_id" class="form-label">مجوز ارفاقی مرتبط (اختیاری)</label>
                        <select class="form-select" id="deviation_id" name="deviation_id">
                             <option value="">-- هیچکدام --</option>
                             <?php foreach($all_deviations as $d): ?>
                                <option value="<?php echo $d['DeviationID']; ?>" <?php echo (isset($itemToEdit['DeviationID']) && $itemToEdit['DeviationID'] == $d['DeviationID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['DeviationCode'] . ' (' . $d['Status'] . ') - ' . mb_substr($d['Reason'], 0, 40) . '...'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="mb-3">
                        <label for="description" class="form-label">توضیحات/دلیل</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($itemToEdit['Description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($itemToEdit['IsActive']) || $itemToEdit['IsActive'] == 1) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="is_active">
                        این مسیر فعال باشد؟
                      </label>
                    </div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                    <?php if ($editMode): ?><a href="route_overrides.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست مسیرهای غیراستاندارد ثبت شده</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="p-2">خانواده</th>
                                <th class="p-2">از</th>
                                <th class="p-2">به</th>
                                <th class="p-2">وضعیت خروجی</th>
                                <th class="p-2">مجوز مرتبط</th>
                                <th class="p-2">فعال</th>
                                <th class="p-2">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($items)): ?>
                                <tr><td colspan="7" class="text-center p-3 text-muted">هیچ مسیر غیراستانداردی ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td class="p-2"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['FromStationName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['ToStationName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['OutputStatusName'] ?? 'نامشخص'); /* Display Status Name */ ?></td>
                                    <td class="p-2 small"><?php echo $item['DeviationCode'] ? htmlspecialchars($item['DeviationCode']) : '-'; ?></td>
                                    <td class="p-2"><?php echo $item['IsActive'] ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>'; ?></td>
                                    <td class="p-2 text-nowrap">
                                        <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                        <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                            <div class="modal-dialog"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">آیا از حذف این مسیر غیراستاندارد مطمئن هستید؟</div>
                                            <div class="modal-footer">
                                                <form method="POST" action="route_overrides.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                            </div>
                                            </div></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

