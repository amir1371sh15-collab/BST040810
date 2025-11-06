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

// --- Pagination Logic ---
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$total_records = $pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME)->fetchColumn();
$total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;
$offset = ($current_page - 1) * RECORDS_PER_PAGE;

// --- Handle Form Submissions (Add, Update, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle Delete
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        $_SESSION['message'] = $result['message'];

    } else {
        // Handle Add/Update
        $data = [
            'FamilyID' => (int)$_POST['family_id'],
            'FromStationID' => (int)$_POST['from_station_id'],
            'ToStationID' => (int)$_POST['to_station_id'],
            'NewStatusID' => !empty($_POST['new_status_id']) ? (int)$_POST['new_status_id'] : null,
            'StepNumber' => (int)($_POST['step_number'] ?? 99),
            'IsFinalStage' => isset($_POST['is_final_stage']) ? 1 : 0,
        ];

        // Validation
        if (empty($data['FamilyID']) || empty($data['FromStationID']) || empty($data['ToStationID']) || empty($data['NewStatusID']) || empty($data['StepNumber'])) {
             $result = ['success' => false, 'message' => 'تمام فیلدها (خانواده، مبدا، مقصد، وضعیت خروجی و شماره مرحله) الزامی هستند.'];
             $_SESSION['message_type'] = 'warning';
        } else {
            $edit_id = (int)($_POST['id'] ?? 0);
            
            // Duplicate Check Query
            $status_check_sql = $data['NewStatusID'] ? "AND NewStatusID = :NewStatusID" : "AND NewStatusID IS NULL";
            $sql = "SELECT * FROM " . TABLE_NAME . " 
                    WHERE FamilyID = :FamilyID 
                      AND FromStationID = :FromStationID 
                      AND ToStationID = :ToStationID 
                      $status_check_sql";
            $params = [
                ':FamilyID' => $data['FamilyID'],
                ':FromStationID' => $data['FromStationID'],
                ':ToStationID' => $data['ToStationID']
            ];
            if ($data['NewStatusID']) {
                $params[':NewStatusID'] = $data['NewStatusID'];
            }
            // Exclude self if editing
            if ($edit_id > 0) {
                $sql .= " AND " . PRIMARY_KEY . " != :EditID";
                $params[':EditID'] = $edit_id;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $existing = $stmt->fetch();
            
            if ($existing) {
                 $result = ['success' => false, 'message' => 'خطا: مسیری با این مشخصات (خانواده، مبدا، مقصد و وضعیت خروجی) از قبل وجود دارد.'];
                 $_SESSION['message_type'] = 'warning';
            } else {
                if ($edit_id > 0) {
                    $result = update_record($pdo, TABLE_NAME, $data, $edit_id, PRIMARY_KEY);
                } else {
                    $result = insert_record($pdo, TABLE_NAME, $data);
                }
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            }
        }
        // Set message if not already set by validation/duplicate check
        if (!isset($_SESSION['message'])) {
            $_SESSION['message'] = $result['message'] ?? 'خطای ناشناخته رخ داد.';
        }
    }
    
    // Redirect to the same page to avoid form resubmission
    header("Location: " . BASE_URL . "modules/base_info/routes.php?page=" . $current_page);
    exit;
}

// --- Handle Edit Mode ---
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

// --- Fetch Data for Display ---
$items_query = "SELECT r.*, pf.FamilyName, fs.StationName as FromStation, ts.StationName as ToStation, ps.StatusName as NewStatusName
                FROM " . TABLE_NAME . " r
                JOIN tbl_part_families pf ON r.FamilyID = pf.FamilyID
                JOIN tbl_stations fs ON r.FromStationID = fs.StationID
                JOIN tbl_stations ts ON r.ToStationID = ts.StationID
                LEFT JOIN tbl_part_statuses ps ON r.NewStatusID = ps.StatusID
                ORDER BY pf.FamilyName, r.StepNumber, r.FromStationID
                LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($items_query);
$stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

// --- Fetch Data for Dropdowns ---
$families = find_all($pdo, "SELECT * FROM tbl_part_families ORDER BY FamilyName");
$stations = find_all($pdo, "SELECT * FROM tbl_stations ORDER BY StationName");
$part_statuses = find_all($pdo, "SELECT * FROM tbl_part_statuses ORDER BY StatusName");

$pageTitle = "مدیریت مسیرهای تولید";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            
            <div class="page-header d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0"><?php echo $pageTitle; ?></h1>
                <a href="parts_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right"></i> بازگشت به داشبورد قطعات</a>
            </div>
            <hr>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Add/Edit Form Column -->
                <div class="col-lg-4 mb-4">
                    <div class="card content-card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo $editMode ? 'ویرایش مسیر' : 'افزودن مسیر جدید'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="routes.php?page=<?php echo $current_page; ?>">
                                <?php if ($editMode && $itemToEdit): ?>
                                    <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="family_id">خانواده قطعه *</label>
                                    <select class="form-select" id="family_id" name="family_id" required>
                                        <option value="">انتخاب کنید</option>
                                        <?php foreach ($families as $family): ?>
                                        <option value="<?php echo $family['FamilyID']; ?>" <?php echo (isset($itemToEdit['FamilyID']) && $itemToEdit['FamilyID'] == $family['FamilyID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($family['FamilyName']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="from_station_id">از ایستگاه *</label>
                                    <select class="form-select" id="from_station_id" name="from_station_id" required>
                                        <option value="">انتخاب کنید</option>
                                        <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo $station['StationID']; ?>" <?php echo (isset($itemToEdit['FromStationID']) && $itemToEdit['FromStationID'] == $station['StationID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['StationName']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="to_station_id">به ایستگاه *</label>
                                    <select class="form-select" id="to_station_id" name="to_station_id" required>
                                        <option value="">انتخاب کنید</option>
                                        <?php foreach ($stations as $station): ?>
                                        <option value="<?php echo $station['StationID']; ?>" <?php echo (isset($itemToEdit['ToStationID']) && $itemToEdit['ToStationID'] == $station['StationID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['StationName']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="new_status_id">وضعیت خروجی *</label>
                                    <select class="form-select" id="new_status_id" name="new_status_id" required>
                                        <option value="">انتخاب کنید</option>
                                        <?php foreach ($part_statuses as $status): ?>
                                        <option value="<?php echo $status['StatusID']; ?>" <?php echo (isset($itemToEdit['NewStatusID']) && $itemToEdit['NewStatusID'] == $status['StatusID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['StatusName']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="step_number" class="form-label">شماره مرحله *</label>
                                    <input type="number" class="form-control" id="step_number" name="step_number" value="<?php echo $itemToEdit['StepNumber'] ?? 99; ?>" min="1" required>
                                    <small class="text-muted">برای مرتب‌سازی مسیرها (مثلاً: ۱، ۲، ۳). پیش‌فرض 99 است.</small>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_final_stage" id="is_final_stage" value="1" <?php echo (isset($itemToEdit['IsFinalStage']) && $itemToEdit['IsFinalStage']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_final_stage">ایستگاه پایانی مسیر است؟</label>
                                </div>
                                
                                <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>">
                                    <i class="bi <?php echo $editMode ? 'bi-check-circle' : 'bi-plus-circle'; ?> me-2"></i>
                                    <?php echo $editMode ? 'بروزرسانی' : 'افزودن'; ?>
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="routes.php?page=<?php echo $current_page; ?>" class="btn btn-secondary">
                                        <i class="bi bi-x-circle me-2"></i> لغو
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Routes List Column -->
                <div class="col-lg-8">
                    <div class="card content-card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">لیست مسیرها (صفحه <?php echo $current_page; ?> از <?php echo $total_pages; ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="p-3">خانواده قطعه</th>
                                            <th class="p-3">از ایستگاه</th>
                                            <th class="p-3">به ایستگاه</th>
                                            <th class="p-3">وضعیت خروجی</th>
                                            <th class="p-3">مرحله</th>
                                            <th class="p-3">پایانی؟</th>
                                            <th class="p-3">عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($items)): ?>
                                            <tr><td colspan="7" class="text-center p-3 text-muted">هیچ مسیری یافت نشد.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td class="p-3"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($item['FromStation']); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($item['ToStation']); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($item['NewStatusName'] ?? 'نامشخص'); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($item['StepNumber']); ?></td>
                                                <td class="p-3 text-center"><?php echo $item['IsFinalStage'] ? '<i class="bi bi-check-circle-fill text-success" title="بله"></i>' : '-'; ?></td>
                                                <td class="p-3 text-nowrap">
                                                    <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>&page=<?php echo $current_page; ?>" class="btn btn-warning btn-sm py-0 px-1" title="ویرایش">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>" title="حذف">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $item[PRIMARY_KEY]; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $item[PRIMARY_KEY]; ?>">تایید حذف</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    آیا از حذف این مسیر (<?php echo htmlspecialchars($item['FamilyName']); ?>) مطمئن هستید؟
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <form method="POST" action="routes.php?page=<?php echo $current_page; ?>">
                                                                        <input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>">
                                                                        <button type="submit" class="btn btn-danger">بله، حذف کن</button>
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
            
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

