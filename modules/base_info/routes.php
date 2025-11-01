<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('base_info.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_routes';
const PRIMARY_KEY = 'RouteID';
const RECORDS_PER_PAGE = 20;

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1; // Avoid division by zero
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'FamilyID' => (int)$_POST['family_id'],
            'FromStationID' => (int)$_POST['from_station_id'],
            'ToStationID' => (int)$_POST['to_station_id'],
            'NewStatusID' => !empty($_POST['new_status_id']) ? (int)$_POST['new_status_id'] : null, // Added
            'IsFinalStage' => isset($_POST['is_final_stage']) ? 1 : 0,
        ];
        // Updated validation to check NewStatusID
        if (empty($data['FamilyID']) || empty($data['FromStationID']) || empty($data['ToStationID']) || empty($data['NewStatusID'])) {
             $result = ['success' => false, 'message' => 'خانواده، ایستگاه مبدا/مقصد و وضعیت خروجی الزامی هستند.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                // Check for duplicates before inserting
                $existing = find_one_by_field($pdo, TABLE_NAME, 'FamilyID', $data['FamilyID'], "AND FromStationID = {$data['FromStationID']} AND ToStationID = {$data['ToStationID']}");
                if ($existing) {
                     $result = ['success' => false, 'message' => 'خطا: مسیری با این مشخصات (خانواده، مبدا، مقصد) از قبل وجود دارد.'];
                     $_SESSION['message_type'] = 'warning';
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
            }
            // Only set message type if not already set by validation/duplicate check
             if (!isset($_SESSION['message_type'])) {
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
             }
        }
        // Only set message if not already set by validation/duplicate check
        if (!isset($_SESSION['message'])) {
            $_SESSION['message'] = $result['message'] ?? 'خطای ناشناخته رخ داد.';
        }
    }
    header("Location: " . BASE_URL . "modules/base_info/routes.php?page=" . $current_page); // Redirect with page number
    exit;
}


if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$items_query = "SELECT r.*, pf.FamilyName, fs.StationName as FromStation, ts.StationName as ToStation, ps.StatusName as NewStatusName /* Added Status Name */
                FROM " . TABLE_NAME . " r
                JOIN tbl_part_families pf ON r.FamilyID = pf.FamilyID
                JOIN tbl_stations fs ON r.FromStationID = fs.StationID
                JOIN tbl_stations ts ON r.ToStationID = ts.StationID
                LEFT JOIN tbl_part_statuses ps ON r.NewStatusID = ps.StatusID /* Join with status table */
                ORDER BY pf.FamilyName, r.FromStationID, r.ToStationID /* Changed ordering slightly */
                LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($items_query);
$stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$families = find_all($pdo, "SELECT * FROM tbl_part_families ORDER BY FamilyName");
$stations = find_all($pdo, "SELECT * FROM tbl_stations ORDER BY StationName");
$part_statuses = find_all($pdo, "SELECT * FROM tbl_part_statuses ORDER BY StatusName"); // Fetch statuses for dropdown

$pageTitle = "مدیریت مسیرهای تولید";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="parts_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card content-card"><div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش مسیر' : 'افزودن مسیر جدید'; ?></h5></div><div class="card-body">
            <form method="POST" action="routes.php?page=<?php echo $current_page; ?>">
                <?php if ($editMode && $itemToEdit): ?><input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">خانواده قطعه</label><select class="form-select" name="family_id" required><option value="">انتخاب کنید</option><?php foreach ($families as $family): ?><option value="<?php echo $family['FamilyID']; ?>" <?php echo (isset($itemToEdit['FamilyID']) && $itemToEdit['FamilyID'] == $family['FamilyID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($family['FamilyName']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">از ایستگاه</label><select class="form-select" name="from_station_id" required><option value="">انتخاب کنید</option><?php foreach ($stations as $station): ?><option value="<?php echo $station['StationID']; ?>" <?php echo (isset($itemToEdit['FromStationID']) && $itemToEdit['FromStationID'] == $station['StationID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($station['StationName']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">به ایستگاه</label><select class="form-select" name="to_station_id" required><option value="">انتخاب کنید</option><?php foreach ($stations as $station): ?><option value="<?php echo $station['StationID']; ?>" <?php echo (isset($itemToEdit['ToStationID']) && $itemToEdit['ToStationID'] == $station['StationID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($station['StationName']); ?></option><?php endforeach; ?></select></div>

                <!-- Changed input to select for NewStatus -->
                <div class="mb-3">
                    <label class="form-label">وضعیت خروجی</label>
                    <select class="form-select" name="new_status_id" required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($part_statuses as $status): ?>
                        <option value="<?php echo $status['StatusID']; ?>" <?php echo (isset($itemToEdit['NewStatusID']) && $itemToEdit['NewStatusID'] == $status['StatusID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status['StatusName']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_final_stage" id="is_final_stage" value="1" <?php echo (isset($itemToEdit['IsFinalStage']) && $itemToEdit['IsFinalStage']) ? 'checked' : ''; ?>><label class="form-check-label" for="is_final_stage">ایستگاه پایانی مسیر است؟</label></div>
                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?></button>
                <?php if ($editMode): ?><a href="routes.php?page=<?php echo $current_page; ?>" class="btn btn-secondary">لغو</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست مسیرها (صفحه <?php echo $current_page; ?> از <?php echo $total_pages; ?>)</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">خانواده قطعه</th><th class="p-3">از ایستگاه</th><th class="p-3">به ایستگاه</th><th class="p-3">وضعیت خروجی</th><th class="p-3">پایانی؟</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="6" class="text-center p-3 text-muted">هیچ مسیری یافت نشد.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="p-3"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($item['FromStation']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($item['ToStation']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($item['NewStatusName'] ?? 'نامشخص'); /* Display Status Name */ ?></td>
                                    <td class="p-3"><?php echo $item['IsFinalStage'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '-'; ?></td>
                                    <td class="p-3">
                                        <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm" title="ویرایش"><i class="bi bi-pencil-square"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف"><i class="bi bi-trash-fill"></i></button>
                                        <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">آیا از حذف این مسیر مطمئن هستید؟</div>
                                                    <div class="modal-footer">
                                                        <form method="POST" action="routes.php?page=<?php echo $current_page; ?>">
                                                            <input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>">
                                                            <button type="submit" class="btn btn-danger">بله</button>
                                                        </form>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                        <?php
                            // Simplified pagination display (show current, prev, next, first, last)
                            if ($current_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                if ($current_page > 3) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            if ($current_page > 2) echo '<li class="page-item"><a class="page-link" href="?page='.($current_page-1).'">'.($current_page-1).'</a></li>';
                            echo '<li class="page-item active"><span class="page-link">'.$current_page.'</span></li>';
                            if ($current_page < $total_pages - 1) echo '<li class="page-item"><a class="page-link" href="?page='.($current_page+1).'">'.($current_page+1).'</a></li>';
                            if ($current_page < $total_pages) {
                                if ($current_page < $total_pages - 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'">'.$total_pages.'</a></li>';
                            }
                        ?>
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">بعدی</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

