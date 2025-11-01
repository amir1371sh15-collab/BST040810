<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    // --- Filters ---
    $params_header = [];
    $where_clauses_header = ["1=1"];
    $start_date_str = $_GET['start_date'] ?? null;
    $end_date_str = $_GET['end_date'] ?? null;

    if (empty($start_date_str) || empty($end_date_str)) {
        throw new Exception("بازه زمانی (از تاریخ و تا تاریخ) الزامی است.");
    }

    $start_date = to_gregorian($start_date_str);
    $end_date = to_gregorian($end_date_str);

    if (!$start_date || !$end_date) {
        throw new Exception("فرمت تاریخ نامعتبر است.");
    }
    if ($start_date > $end_date) {
        throw new Exception("تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.");
    }

    $where_clauses_header[] = "h.LogDate >= ?"; $params_header[] = $start_date;
    $where_clauses_header[] = "h.LogDate <= ?"; $params_header[] = $end_date;
    $where_sql_header = 'WHERE ' . implode(' AND ', $where_clauses_header);

    // --- Fetch active break times ---
    $assembly_dept_id = 3; // Assuming assembly department ID is 3
    $break_times_raw = find_all(
        $pdo,
        "SELECT StartTime, EndTime FROM tbl_break_times WHERE IsActive = 1 AND (DepartmentID IS NULL OR DepartmentID = ?)",
        [$assembly_dept_id]
    );
    $break_times_seconds = [];
    foreach ($break_times_raw as $break) {
        $start_secs = strtotime("1970-01-01 " . $break['StartTime'] . " UTC");
        $end_secs = strtotime("1970-01-01 " . $break['EndTime'] . " UTC");
        if ($start_secs !== false && $end_secs !== false && $end_secs > $start_secs) {
            $break_times_seconds[] = ['start' => $start_secs, 'end' => $end_secs];
        }
    }

    // --- Query for Daily Aggregated Data ---
     $sql_daily_agg = "
        SELECT
            h.LogDate,
            h.AvailableTimeMinutes,
            h.DailyProductionPlan,
            COUNT(DISTINCT e.MachineID) as ActiveMachines,
            COALESCE(SUM(CASE WHEN p.UnitWeight > 0 THEN (e.ProductionKG * 1000 / p.UnitWeight) ELSE 0 END), 0) as ActualCount
        FROM tbl_assembly_log_header h
        LEFT JOIN tbl_assembly_log_entries e ON h.AssemblyHeaderID = e.AssemblyHeaderID
        LEFT JOIN tbl_parts p ON e.PartID = p.PartID
        {$where_sql_header}
        -- Note: We group by header info here, entries check comes later for active time
        GROUP BY h.LogDate, h.AvailableTimeMinutes, h.DailyProductionPlan
        ORDER BY h.LogDate ASC
    ";

    $daily_data_raw = find_all($pdo, $sql_daily_agg, $params_header);
    $daily_data_agg = array_column($daily_data_raw, null, 'LogDate'); // Index by date


    // --- Query for individual entries to calculate adjusted active time ---
     $sql_entries = "
        SELECT
            h.LogDate,
            e.StartTime,
            e.EndTime
        FROM tbl_assembly_log_header h
        JOIN tbl_assembly_log_entries e ON h.AssemblyHeaderID = e.AssemblyHeaderID
        {$where_sql_header}
        AND e.StartTime IS NOT NULL AND e.EndTime IS NOT NULL AND e.EndTime >= e.StartTime
        ORDER BY h.LogDate
    ";
    $all_entries = find_all($pdo, $sql_entries, $params_header);

    // --- Calculate Adjusted Active Time (considering breaks) ---
    $adjusted_active_seconds_per_day = [];

    if (!function_exists('calculate_overlap')) {
        function calculate_overlap($entry_start, $entry_end, $break_start, $break_end) {
            $overlap_start = max($entry_start, $break_start);
            $overlap_end = min($entry_end, $break_end);
            return max(0, $overlap_end - $overlap_start);
        }
    }

    foreach ($all_entries as $entry) {
        $log_date = $entry['LogDate'];
        if (!isset($adjusted_active_seconds_per_day[$log_date])) {
            $adjusted_active_seconds_per_day[$log_date] = 0;
        }

        $entry_start_secs = strtotime("1970-01-01 " . $entry['StartTime'] . " UTC");
        $entry_end_secs = strtotime("1970-01-01 " . $entry['EndTime'] . " UTC");

        if ($entry_start_secs === false || $entry_end_secs === false || $entry_end_secs < $entry_start_secs) {
            continue; // Skip invalid entry times
        }

        $raw_duration_secs = $entry_end_secs - $entry_start_secs;
        $total_break_overlap_secs = 0;

        foreach ($break_times_seconds as $break) {
            $total_break_overlap_secs += calculate_overlap($entry_start_secs, $entry_end_secs, $break['start'], $break['end']);
        }

        $total_break_overlap_secs = min($raw_duration_secs, $total_break_overlap_secs);
        $adjusted_duration_secs = $raw_duration_secs - $total_break_overlap_secs;

        $adjusted_active_seconds_per_day[$log_date] += $adjusted_duration_secs;
    }


    // --- Combine data and fill missing days ---
    $result_data = [];
    $current_date = new DateTime($start_date);
    $end_date_dt = new DateTime($end_date);

    while ($current_date <= $end_date_dt) {
        $date_str = $current_date->format('Y-m-d');
        $dayOfWeek = $current_date->format('w'); // For potential future use

        $adjusted_hours = isset($adjusted_active_seconds_per_day[$date_str])
                            ? round($adjusted_active_seconds_per_day[$date_str] / 3600, 2)
                            : 0;

        if (isset($daily_data_agg[$date_str])) {
            $day_info = $daily_data_agg[$date_str];
            $result_data[$date_str] = [
                'LogDate' => $date_str,
                'LogDateJalali' => to_jalali($date_str),
                'AvailableTimeMinutes' => (int)($day_info['AvailableTimeMinutes'] ?? 0),
                'DailyProductionPlan' => (int)($day_info['DailyProductionPlan'] ?? 0),
                'ActiveMachines' => (int)$day_info['ActiveMachines'],
                'ActualCount' => round((float)$day_info['ActualCount']),
                'ActualActiveHours' => $adjusted_hours,
            ];
        } else {
             // Try to fetch header info even for days with no production entries
             $header_for_empty_day = find_one_by_field($pdo, 'tbl_assembly_log_header', 'LogDate', $date_str);
            $result_data[$date_str] = [
                'LogDate' => $date_str,
                'LogDateJalali' => to_jalali($date_str),
                'AvailableTimeMinutes' => (int)($header_for_empty_day['AvailableTimeMinutes'] ?? 0),
                'DailyProductionPlan' => (int)($header_for_empty_day['DailyProductionPlan'] ?? 0),
                'ActiveMachines' => 0,
                'ActualCount' => 0,
                'ActualActiveHours' => 0, // No entries means 0 adjusted hours
            ];
        }
        $current_date->modify('+1 day');
    }

    $response['success'] = true;
    // Return as indexed array
    $response['data'] = array_values($result_data);

} catch (Exception $e) {
    error_log("Assembly Analytics API Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
