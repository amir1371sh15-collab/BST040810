<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks
if (!has_permission('planning.mrp.run')) { // یا یک دسترسی جدید
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "لیست دستور کارها (فاز ۲)";
include_once __DIR__ . '/../../templates/header.php';

// --- فیلترها ---
$filter_run_id = filter_input(INPUT_GET, 'filter_run_id', FILTER_VALIDATE_INT);
$filter_station_id = filter_input(INPUT_GET, 'filter_station_id', FILTER_VALIDATE_INT);
$filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_SANITIZE_STRING);

$where_clauses = [];
$params = [];

if ($filter_run_id) {
    $where_clauses[] = "wo.RunID = ?";
    $params[] = $filter_run_id;
}
if ($filter_station_id) {
    $where_clauses[] = "wo.StationID = ?";
    $params[] = $filter_station_id;
}
if ($filter_status) {
    $where_clauses[] = "wo.Status = ?";
    $params[] = $filter_status;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// --- واکشی اطلاعات ---
$work_orders_raw = find_all($pdo, "
    SELECT 
        wo.*,
        s.StationName,
        p.PartName,
        status_in.StatusName as InputStatusName,
        status_out.StatusName as TargetStatusName,
        run.RunDate as RunDate,
        run.SelectedOrderIDs -- To show what triggered this run
    FROM tbl_planning_work_orders wo
    JOIN tbl_stations s ON wo.StationID = s.StationID
    JOIN tbl_parts p ON wo.PartID = p.PartID
    JOIN tbl_planning_mrp_run run ON wo.RunID = run.RunID
    LEFT JOIN tbl_part_statuses status_in ON wo.RequiredStatusID = status_in.StatusID
    LEFT JOIN tbl_part_statuses status_out ON wo.TargetStatusID = status_out.StatusID
    $where_sql
    ORDER BY s.StationName, wo.DueDate, wo.Priority
", $params);

// گروه‌بندی بر اساس ایستگاه
$work_orders_grouped = [];
foreach ($work_orders_raw as $wo) {
    $work_orders_grouped[$wo['StationName']][] = $wo;
}

// Data for filters
$all_runs = find_all($pdo, "SELECT RunID, RunDate FROM tbl_planning_mrp_run ORDER BY RunDate DESC LIMIT 100"); // Get last 100 runs
$all_stations = find_all($pdo, "SELECT StationID, StationName FROM tbl_stations WHERE StationType = 'Production' ORDER BY StationName");
$all_statuses = ['Generated', 'InProgress', 'Completed'];

?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <!-- Filter Card -->
            <div class="card content-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">فیلتر دستور کارها</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="filter_run_id" class="form-label">بر اساس اجرا (RunID)</label>
                                <select class="form-select" id="filter_run_id" name="filter_run_id">
                                    <option value="">-- همه اجراها --</option>
                                    <?php foreach ($all_runs as $run): ?>
                                        <option value="<?php echo $run['RunID']; ?>" <?php echo ($filter_run_id == $run['RunID']) ? 'selected' : ''; ?>>
                                            اجرای <?php echo $run['RunID']; ?> (<?php echo to_jalali($run['RunDate']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_station_id" class="form-label">بر اساس ایستگاه</label>
                                <select class="form-select" id="filter_station_id" name="filter_station_id">
                                    <option value="">-- همه ایستگاه‌ها --</option>
                                    <?php foreach ($all_stations as $station): ?>
                                        <option value="<?php echo $station['StationID']; ?>" <?php echo ($filter_station_id == $station['StationID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($station['StationName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_status" class="form-label">بر اساس وضعیت</label>
                                <select class="form-select" id="filter_status" name="filter_status">
                                    <option value="">-- همه وضعیت‌ها --</option>
                                    <?php foreach ($all_statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">فیلتر</button>
                                <a href="work_order_list.php" class="btn btn-outline-secondary ms-2">پاک کردن</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Work Orders List -->
            <?php if (empty($work_orders_grouped)): ?>
                <div class="alert alert-warning text-center">
                    هیچ دستور کاری با فیلترهای انتخابی یافت نشد.
                </div>
            <?php else: ?>
                <?php foreach ($work_orders_grouped as $station_name => $work_orders): ?>
                    <div class="card content-card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>ایستگاه: <?php echo htmlspecialchars($station_name); ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="p-2">ID دستور کار</th>
                                            <th class="p-2">RunID</th>
                                            <th class="p-2">قطعه</th>
                                            <th class="p-2">وضعیت ورودی</th>
                                            <th class="p-2">وضعیت خروجی</th>
                                            <th class="p-2">تعداد</th>
                                            <th class="p-2">واحد</th>
                                            <th class="p-2">تاریخ تحویل</th>
                                            <th class="p-2">اولویت</th>
                                            <th class="p-2">وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($work_orders as $wo): ?>
                                            <tr>
                                                <td class="p-2"><?php echo $wo['WorkOrderID']; ?></td>
                                                <td class="p-2"><?php echo $wo['RunID']; ?></td>
                                                <td class="p-2"><?php echo htmlspecialchars($wo['PartName']); ?></td>
                                                <td class="p-2"><span class="badge bg-light text-dark"><?php echo htmlspecialchars($wo['InputStatusName'] ?? '---'); ?></span></td>
                                                <td class="p-2"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($wo['TargetStatusName'] ?? '---'); ?></span></td>
                                                <td class="p-2"><?php echo number_format($wo['Quantity'], 2); ?></td>
                                                <td class="p-2"><?php echo htmlspecialchars($wo['Unit']); ?></td>
                                                <td class="p-2"><?php echo to_jalali($wo['DueDate']); ?></td>
                                                <td class="p-2"><?php echo $wo['Priority']; ?></td>
                                                <td class="p-2">
                                                    <?php
                                                    $status_class = 'bg-secondary';
                                                    if ($wo['Status'] == 'InProgress') $status_class = 'bg-info text-dark';
                                                    if ($wo['Status'] == 'Completed') $status_class = 'bg-success';
                                                    echo "<span class='badge {$status_class}'>{$wo['Status']}</span>";
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>

