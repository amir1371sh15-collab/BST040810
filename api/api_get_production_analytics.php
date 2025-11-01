<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Includes to_jalali function

$response = ['success' => false, 'data' => []];

try {
    // --- Build WHERE clause from filters for Production Logs ---
    $prod_params = [];
    $prod_where_clauses = [];

    $start_date_filter = !empty($_GET['start_date']) ? to_gregorian($_GET['start_date']) : null;
    $end_date_filter = !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : null;

    if ($start_date_filter) { $prod_where_clauses[] = "h.LogDate >= ?"; $prod_params[] = $start_date_filter; }
    if ($end_date_filter) { $prod_where_clauses[] = "h.LogDate <= ?"; $prod_params[] = $end_date_filter; }
    if (!empty($_GET['machine_type'])) { $prod_where_clauses[] = "h.MachineType = ?"; $prod_params[] = $_GET['machine_type']; }
    if (!empty($_GET['machine_id'])) { $prod_where_clauses[] = "d.MachineID = ?"; $prod_params[] = (int)$_GET['machine_id']; }
    if (!empty($_GET['mold_id'])) { $prod_where_clauses[] = "d.MoldID = ?"; $prod_params[] = (int)$_GET['mold_id']; }
    if (!empty($_GET['part_family_id'])) { $prod_where_clauses[] = "p.FamilyID = ?"; $prod_params[] = (int)$_GET['part_family_id']; }

    $prod_where_sql = count($prod_where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $prod_where_clauses) : '';

    $base_query_from = "FROM tbl_prod_daily_log_details d
                        JOIN tbl_prod_daily_log_header h ON d.HeaderID = h.HeaderID
                        LEFT JOIN tbl_parts p ON d.PartID = p.PartID
                        LEFT JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
                        LEFT JOIN tbl_machines m ON d.MachineID = m.MachineID
                        LEFT JOIN tbl_molds mo ON d.MoldID = mo.MoldID
                        {$prod_where_sql}";

    // --- Standard Report Queries ---
    // Production Trend (Add Jalali Date)
    $prod_trend_raw = find_all($pdo, "SELECT h.LogDate, SUM(d.ProductionKG) as TotalKG {$base_query_from} GROUP BY h.LogDate ORDER BY h.LogDate ASC", $prod_params);
    $response['data']['production_trend'] = array_map(function($row) {
        $row['LogDateJalali'] = to_jalali($row['LogDate']);
        return $row;
    }, $prod_trend_raw);

    $response['data']['by_part'] = find_all($pdo, "SELECT p.PartName, SUM(d.ProductionKG) as TotalKG {$base_query_from} GROUP BY p.PartName HAVING TotalKG > 0 ORDER BY TotalKG DESC", $prod_params);
    $response['data']['by_family'] = find_all($pdo, "SELECT pf.FamilyName, SUM(d.ProductionKG) as TotalKG {$base_query_from} GROUP BY pf.FamilyName HAVING TotalKG > 0 ORDER BY TotalKG DESC", $prod_params);
    $response['data']['by_machine'] = find_all($pdo, "SELECT m.MachineName, SUM(d.ProductionKG) as TotalKG {$base_query_from} GROUP BY m.MachineName HAVING TotalKG > 0 ORDER BY TotalKG DESC", $prod_params);
    $mold_where_sql = $prod_where_sql . (count($prod_where_clauses) > 0 ? " AND " : "WHERE ") . "d.MoldID IS NOT NULL";
    $response['data']['by_mold'] = find_all($pdo, "SELECT mo.MoldName, SUM(d.ProductionKG) as TotalKG FROM tbl_prod_daily_log_details d JOIN tbl_prod_daily_log_header h ON d.HeaderID = h.HeaderID LEFT JOIN tbl_molds mo ON d.MoldID = mo.MoldID LEFT JOIN tbl_parts p ON d.PartID = p.PartID {$mold_where_sql} GROUP BY mo.MoldName HAVING TotalKG > 0 ORDER BY TotalKG DESC", $prod_params);

    // Productivity Trend (Add Jalali Date)
    $productivity_query = "SELECT LogDate, CASE WHEN SUM(ManHours) > 0 THEN SUM(TotalKG) / SUM(ManHours) ELSE 0 END as Productivity FROM (SELECT h.LogDate, h.ManHours, SUM(d.ProductionKG) as TotalKG {$base_query_from} GROUP BY h.HeaderID) as daily_summary GROUP BY LogDate HAVING SUM(ManHours) > 0 ORDER BY LogDate ASC";
    $productivity_trend_raw = find_all($pdo, $productivity_query, $prod_params);
    $response['data']['productivity_trend'] = array_map(function($row) {
        $row['LogDateJalali'] = to_jalali($row['LogDate']);
        return $row;
    }, $productivity_trend_raw);


    // --- OEE and Loss Analysis ---
    $T_rec_per_day = 540;

    $prod_sql = "SELECT d.MachineID, m.MachineName, m.strokes_per_minute,
                        SUM(h.AvailableTimeMinutes) as TotalAvailableTime,
                        SUM((d.ProductionKG * 1000) / p.UnitWeight) as TotalActualPieces,
                        COUNT(DISTINCT h.LogDate) as ProductionDays
                 FROM tbl_prod_daily_log_details d
                 JOIN tbl_prod_daily_log_header h ON d.HeaderID = h.HeaderID
                 JOIN tbl_machines m ON d.MachineID = m.MachineID
                 JOIN tbl_parts p ON d.PartID = p.PartID
                 {$prod_where_sql}
                 AND m.strokes_per_minute > 0 AND p.UnitWeight > 0 AND h.AvailableTimeMinutes > 0
                 GROUP BY d.MachineID, m.MachineName, m.strokes_per_minute";
    $prod_data = find_all($pdo, $prod_sql, $prod_params);

    $downtime_where_sql = preg_replace('/\b(h|d|p|pf|m|mo)\./', 'dwh.', $prod_where_sql);
    $downtime_sql = "SELECT dt.MachineID, SUM(dt.Duration) as TotalRecordedDowntime
                 FROM tbl_prod_downtime_details dt
                 JOIN tbl_prod_downtime_header dwh ON dt.HeaderID = dwh.HeaderID
                 " . $downtime_where_sql . "
                 GROUP BY dt.MachineID";
    $downtime_params = $prod_params;
    $downtime_data = find_all($pdo, $downtime_sql, $downtime_params);
    $downtimes = array_column($downtime_data, 'TotalRecordedDowntime', 'MachineID');

    $final_analysis = [];
    foreach($prod_data as $row) {
        $machine_id = $row['MachineID'];
        $D_rec = (float)($downtimes[$machine_id] ?? 0);
        $Total_T_rec = (int)$row['ProductionDays'] * $T_rec_per_day;

        $scale = ($Total_T_rec > 0) ? ((float)$row['TotalAvailableTime'] / $Total_T_rec) : 1;

        $D_est = $D_rec * $scale;

        $TheoreticalFullPieces = (int)$row['strokes_per_minute'] * (float)$row['TotalAvailableTime'];
        $ActualPieces = (float)$row['TotalActualPieces'];

        $Loss_Total_Performance = $TheoreticalFullPieces - $ActualPieces;
        $Loss_Avail = (int)$row['strokes_per_minute'] * $D_est;
        $Hidden_Loss = $Loss_Total_Performance - $Loss_Avail;

        $Performance_Percent = $TheoreticalFullPieces > 0 ? ($ActualPieces / $TheoreticalFullPieces) * 100 : 0;

        $final_analysis[] = [
            'machine_name' => $row['MachineName'],
            'total_available_time' => round((float)$row['TotalAvailableTime']),
            'theoretical_full_pieces' => round($TheoreticalFullPieces),
            'actual_pieces' => round($ActualPieces),
            'loss_total_performance' => round(max(0, $Loss_Total_Performance)),
            'performance_percent' => round(max(0, $Performance_Percent), 2),

            'downtime_estimated' => round($D_est),
            'loss_avail_pieces' => round(max(0, $Loss_Avail)),

            'hidden_loss_pieces' => round(max(0, $Hidden_Loss))
        ];
    }

    $response['data']['loss_analysis'] = $final_analysis;
    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = 'Server Error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
