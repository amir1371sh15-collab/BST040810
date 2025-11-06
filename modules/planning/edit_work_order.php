<?php
require_once __DIR__ . '/../../config/init.php';

// --- Permission Check ---
if (!has_permission('planning.production_schedule.save')) { // یا دسترسی مجزا
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "ویرایش دستور کار";
$edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
$work_order = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if (!$edit_id) {
    $_SESSION['message'] = 'شناسه دستور کار نامعتبر است.';
    $_SESSION['message_type'] = 'danger';
    header("Location: pressing_schedule.php");
    exit;
}

// --- Handle Form Submission (Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_to_update = [
        'Quantity' => (float)$_POST['quantity'],
        'PlannedDate' => to_gregorian($_POST['planned_date']),
        'Priority' => (int)$_POST['priority'],
        'Status' => $_POST['status']
    ];
    
    $result = update_record($pdo, 'tbl_planning_work_orders', $data_to_update, $edit_id, 'WorkOrderID');
    
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    
    header("Location: pressing_schedule.php"); // بازگشت به لیست
    exit;
}

// --- Fetch Data for Form ---
$work_order = find_by_id($pdo, 'tbl_planning_work_orders', $edit_id, 'WorkOrderID');

if (!$work_order) {
    $_SESSION['message'] = 'دستور کار مورد نظر یافت نشد.';
    $_SESSION['message_type'] = 'danger';
    header("Location: pressing_schedule.php");
    exit;
}

// واکشی اطلاعات تکمیلی برای نمایش
$part_info = find_by_id($pdo, 'tbl_parts', $work_order['PartID'], 'PartID');
$station_info = find_by_id($pdo, 'tbl_stations', $work_order['StationID'], 'StationID');
$machine_info = $work_order['MachineID'] ? find_by_id($pdo, 'tbl_machines', $work_order['MachineID'], 'MachineID') : null;

$all_statuses = ['Generated', 'InProgress', 'Completed', 'Cancelled'];

include_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?> #<?php echo $edit_id; ?></h1>
                <a href="pressing_schedule.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i> انصراف و بازگشت
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card content-card">
                        <div class="card-header">
                            <h5 class="mb-0">ویرایش دستور کار</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="edit_work_order.php?edit_id=<?php echo $edit_id; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>قطعه:</strong> <?php echo htmlspecialchars($part_info['PartName'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>ایستگاه:</strong> <?php echo htmlspecialchars($station_info['StationName'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="col-md-6 mt-2">
                                        <strong>دستگاه:</strong> <?php echo htmlspecialchars($machine_info['MachineName'] ?? '---'); ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="quantity" class="form-label">تعداد / مقدار *</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" 
                                                   value="<?php echo htmlspecialchars($work_order['Quantity']); ?>" required>
                                            <span class="input-group-text"><?php echo htmlspecialchars($work_order['Unit']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="planned_date" class="form-label">تاریخ برنامه *</label>
                                        <input type="text" class="form-control persian-date" id="planned_date" name="planned_date" 
                                               value="<?php echo to_jalali($work_order['PlannedDate']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="priority" class="form-label">اولویت</label>
                                        <input type="number" class="form-control" id="priority" name="priority" 
                                               value="<?php echo htmlspecialchars($work_order['Priority']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">وضعیت *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <?php foreach ($all_statuses as $status): ?>
                                                <option value="<?php echo $status; ?>" <?php echo ($work_order['Status'] == $status) ? 'selected' : ''; ?>>
                                                    <?php echo $status; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save-fill me-2"></i> ذخیره تغییرات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>

