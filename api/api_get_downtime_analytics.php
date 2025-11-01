<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => []];

try {
    // --- Build WHERE clause from filters ---
    $where_clauses = [];
    $params = [];
    $shift_duration = !empty($_GET['shift_duration']) && is_numeric($_GET['shift_duration']) ? (int)$_GET['shift_duration'] : 0;
    define('NO_PLAN_REASON_ID', 6);

    if (!empty($_GET['start_date'])) { $where_clauses[] = "h.LogDate >= ?"; $params[] = to_gregorian($_GET['start_date']); }
    if (!empty($_GET['end_date'])) { $where_clauses[] = "h.LogDate <= ?"; $params[] = to_gregorian($_GET['end_date']); }
    if (!empty($_GET['machine_type'])) { $where_clauses[] = "h.MachineType = ?"; $params[] = $_GET['machine_type']; }
    if (!empty($_GET['machine_id'])) { $where_clauses[] = "d.MachineID = ?"; $params[] = (int)$_GET['machine_id']; }
    if (!empty($_GET['mold_id'])) { $where_clauses[] = "d.MoldID = ?"; $params[] = (int)$_GET['mold_id']; }
    $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // --- 1. Pareto Analysis by Reason ---
    $pareto_sql = "SELECT dr.ReasonDescription, SUM(d.Duration) as TotalDuration FROM tbl_prod_downtime_details d JOIN tbl_prod_downtime_header h ON d.HeaderID = h.HeaderID JOIN tbl_downtimereasons dr ON d.ReasonID = dr.ReasonID {$where_sql} GROUP BY dr.ReasonDescription ORDER BY TotalDuration DESC";
    $stmt = $pdo->prepare($pareto_sql);
    $stmt->execute($params);
    $pareto_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_downtime_all_reasons = array_sum(array_column($pareto_data_raw, 'TotalDuration'));
    $pareto_analysis = []; $cumulative_percentage = 0;
    foreach ($pareto_data_raw as $row) {
        $percentage = ($total_downtime_all_reasons > 0) ? ($row['TotalDuration'] / $total_downtime_all_reasons) * 100 : 0;
        $cumulative_percentage += $percentage;
        $pareto_analysis[] = ['reason' => $row['ReasonDescription'], 'duration' => (int)$row['TotalDuration'], 'percentage' => round($percentage, 2), 'cumulative_percentage' => round($cumulative_percentage, 2)];
    }
    $response['data']['pareto_by_reason'] = $pareto_analysis;

    // --- 2. Downtime by Machine ---
    $machine_sql = "SELECT m.MachineName, SUM(d.Duration) as TotalDuration FROM tbl_prod_downtime_details d JOIN tbl_prod_downtime_header h ON d.HeaderID = h.HeaderID JOIN tbl_machines m ON d.MachineID = m.MachineID {$where_sql} GROUP BY m.MachineName ORDER BY TotalDuration DESC";
    $stmt = $pdo->prepare($machine_sql); $stmt->execute($params);
    $response['data']['by_machine'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- 3. Downtime by Mold ---
    $mold_sql = "SELECT mo.MoldName, SUM(d.Duration) as TotalDuration FROM tbl_prod_downtime_details d JOIN tbl_prod_downtime_header h ON d.HeaderID = h.HeaderID JOIN tbl_molds mo ON d.MoldID = mo.MoldID {$where_sql} GROUP BY mo.MoldName ORDER BY TotalDuration DESC";
    $stmt = $pdo->prepare($mold_sql); $stmt->execute($params);
    $response['data']['by_mold'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. Downtime Trend by Day ---
    $trend_sql = "SELECT h.LogDate, SUM(d.Duration) as TotalDuration FROM tbl_prod_downtime_details d JOIN tbl_prod_downtime_header h ON d.HeaderID = h.HeaderID {$where_sql} GROUP BY h.LogDate ORDER BY h.LogDate ASC";
    $stmt = $pdo->prepare($trend_sql); $stmt->execute($params);
    $response['data']['trend_by_day'] = array_map(fn($row) => ['date' => to_jalali($row['LogDate']), 'duration' => (int)$row['TotalDuration']], $stmt->fetchAll(PDO::FETCH_ASSOC));

    // --- 5 & 6. Efficiency Calculations (if shift duration is provided) ---
    if ($shift_duration > 0) {
        // --- 5. Efficiency by Machine (Table) ---
        $eff_sql = "SELECT m.MachineName, SUM(d.Duration) as TotalDowntime, SUM(CASE WHEN d.ReasonID = ? THEN d.Duration ELSE 0 END) as NoPlanDowntime, COUNT(DISTINCT h.LogDate) as LoggedDays FROM tbl_prod_downtime_details d JOIN tbl_prod_downtime_header h ON d.HeaderID = h.HeaderID JOIN tbl_machines m ON d.MachineID = m.MachineID {$where_sql} GROUP BY m.MachineName ORDER BY m.MachineName";
        $eff_params = array_merge([NO_PLAN_REASON_ID], $params);
        $stmt = $pdo->prepare($eff_sql); $stmt->execute($eff_params);
        $eff_analysis = [];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total_available = $row['LoggedDays'] * $shift_duration;
            $effective_downtime = $row['TotalDowntime'] - $row['NoPlanDowntime'];
            $effective_work_time = $total_available - $effective_downtime;
            $efficiency = ($total_available > 0) ? ($effective_work_time / $total_available) * 100 : 0;
            $eff_analysis[] = ['machine_name' => $row['MachineName'], 'logged_days' => (int)$row['LoggedDays'], 'total_available_time' => $total_available, 'total_downtime' => (int)$row['TotalDowntime'], 'no_plan_downtime' => (int)$row['NoPlanDowntime'], 'effective_downtime' => $effective_downtime, 'effective_working_time' => $effective_work_time, 'efficiency' => round(max(0, $efficiency), 2)];
        }
        $response['data']['efficiency_by_machine'] = $eff_analysis;

        // --- 6. Efficiency Trend for Presses (Chart) ---
        $trend_where_clauses = $where_clauses; $trend_where_clauses[] = "m.MachineType = 'پرس'";
        $trend_where_sql = 'WHERE ' . implode(' AND ', $trend_where_clauses);
        $trend_eff_sql = "SELECT h.LogDate, m.MachineName, SUM(d.Duration) as TotalDowntime, SUM(CASE WHEN d.ReasonID = ? THEN d.Duration ELSE 0 END) as NoPlanDowntime FROM tbl_prod_downtime_details d JOIN tbl_prod_downtime_header h ON d.HeaderID = h.HeaderID JOIN tbl_machines m ON d.MachineID = m.MachineID {$trend_where_sql} GROUP BY h.LogDate, m.MachineName ORDER BY h.LogDate, m.MachineName";
        $stmt = $pdo->prepare($trend_eff_sql); $stmt->execute($eff_params);
        
        $trend_by_machine_date = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $eff_downtime = max(0, $row['TotalDowntime'] - $row['NoPlanDowntime']);
            $efficiency = (($shift_duration - $eff_downtime) / $shift_duration) * 100;
            $trend_by_machine_date[$row['MachineName']][to_jalali($row['LogDate'])] = round(max(0, min(100, $efficiency)), 2);
        }
        
        $all_dates = empty($trend_by_machine_date) ? [] : array_keys(array_reduce($trend_by_machine_date, 'array_merge', []));
        sort($all_dates);
        $datasets = [];
        foreach(array_keys($trend_by_machine_date) as $machine) {
            $data_points = [];
            foreach($all_dates as $date) { $data_points[] = $trend_by_machine_date[$machine][$date] ?? null; }
            $datasets[] = ['label' => $machine, 'data' => $data_points];
        }
        $response['data']['efficiency_trend'] = ['labels' => $all_dates, 'datasets' => $datasets];
    }
    
    $response['success'] = true;

} catch (PDOException $e) {
    $response['message'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
