<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for DB, helpers, auth

$response = ['success' => false, 'data' => null, 'message' => 'تاریخ مشخص نشده است.'];

// Basic permission check
if (!has_permission('production.assembly_hall.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده این گزارش را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$report_date_str = $_GET['report_date'] ?? null;

if (!$report_date_str) {
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$report_date_gregorian = to_gregorian($report_date_str);
if (!$report_date_gregorian) {
    http_response_code(400);
    $response['message'] = 'فرمت تاریخ نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result_data = [
        'assembly' => ['details' => [], 'summary' => [], 'operator_summary' => [], 'description' => null],
        'rolling' => ['details' => [], /* Removed operators array, info is in details */ 'summary' => ['TotalKG' => 0, 'TotalManHours' => 0], 'description' => null],
        'packaging' => ['details' => [], 'operators' => [], 'summary' => [], 'description' => null],
        'descriptions' => ['assembly' => null, 'rolling' => null, 'packaging' => null]
    ];

    // Helper function to calculate overlap (defined once)
    if (!function_exists('calculate_overlap')) {
        function calculate_overlap($entry_start, $entry_end, $break_start, $break_end) {
            $overlap_start = max($entry_start, $break_start);
            $overlap_end = min($entry_end, $break_end);
            return max(0, $overlap_end - $overlap_start);
        }
    }

    // --- Fetch Assembly Data ---
    // ... (Assembly data fetching remains the same) ...
    $asm_header_stmt = $pdo->prepare("SELECT * FROM tbl_assembly_log_header WHERE LogDate = ?");
    $asm_header_stmt->execute([$report_date_gregorian]);
    $asm_header = $asm_header_stmt->fetch(PDO::FETCH_ASSOC);
    $result_data['descriptions']['assembly'] = $asm_header['Description'] ?? null;

    if ($asm_header) {
        $asm_header_id = $asm_header['AssemblyHeaderID'];
        $asm_entries_sql = "
            SELECT
                e.AssemblyEntryID, e.MachineID, m.MachineName, e.PartID, p.PartName, e.ProductionKG,
                e.Operator1ID, op1.name as Operator1Name,
                e.Operator2ID, op2.name as Operator2Name,
                e.StartTime, e.EndTime
            FROM tbl_assembly_log_entries e
            JOIN tbl_machines m ON e.MachineID = m.MachineID
            JOIN tbl_parts p ON e.PartID = p.PartID
            LEFT JOIN tbl_employees op1 ON e.Operator1ID = op1.EmployeeID
            LEFT JOIN tbl_employees op2 ON e.Operator2ID = op2.EmployeeID
            WHERE e.AssemblyHeaderID = ?
            ORDER BY m.MachineName, e.StartTime, e.AssemblyEntryID";
        $asm_entries = find_all($pdo, $asm_entries_sql, [$asm_header_id]);
        $result_data['assembly']['details'] = $asm_entries;
        $operator1_stats = []; $total_assembly_kg = 0; $active_assembly_machines = []; $total_assembly_seconds_overall = 0;
        $assembly_dept_id = 3;
        $break_times_raw_asm = find_all($pdo, "SELECT StartTime, EndTime FROM tbl_break_times WHERE IsActive = 1 AND (DepartmentID IS NULL OR DepartmentID = ?)", [$assembly_dept_id]);
        $break_times_seconds_asm = [];
        foreach ($break_times_raw_asm as $break) { $start_secs = strtotime("1970-01-01 " . $break['StartTime'] . " UTC"); $end_secs = strtotime("1970-01-01 " . $break['EndTime'] . " UTC"); if ($start_secs !== false && $end_secs !== false && $end_secs > $start_secs) { $break_times_seconds_asm[] = ['start' => $start_secs, 'end' => $end_secs]; } }
        foreach ($asm_entries as $entry) {
            $production_kg = (float)($entry['ProductionKG'] ?? 0); $total_assembly_kg += $production_kg; $active_assembly_machines[$entry['MachineID']] = true; $adjusted_duration_secs = 0;
            if ($entry['StartTime'] && $entry['EndTime']) { $entry_start_secs = strtotime("1970-01-01 " . $entry['StartTime'] . " UTC"); $entry_end_secs = strtotime("1970-01-01 " . $entry['EndTime'] . " UTC"); if ($entry_start_secs !== false && $entry_end_secs !== false && $entry_end_secs >= $entry_start_secs) { $raw_duration_secs = $entry_end_secs - $entry_start_secs; $total_break_overlap_secs = 0; foreach ($break_times_seconds_asm as $break) { $total_break_overlap_secs += calculate_overlap($entry_start_secs, $entry_end_secs, $break['start'], $break['end']); } $total_break_overlap_secs = min($raw_duration_secs, $total_break_overlap_secs); $adjusted_duration_secs = $raw_duration_secs - $total_break_overlap_secs; $total_assembly_seconds_overall += $adjusted_duration_secs; } }
            if ($entry['Operator1ID']) { $op1_id = $entry['Operator1ID']; if (!isset($operator1_stats[$op1_id])) { $operator1_stats[$op1_id] = ['name' => $entry['Operator1Name'], 'total_seconds' => 0, 'total_kg' => 0]; } $operator1_stats[$op1_id]['total_seconds'] += $adjusted_duration_secs; $operator1_stats[$op1_id]['total_kg'] += $production_kg; }
        }
        foreach ($operator1_stats as $op_id => $stats) { $result_data['assembly']['operator_summary'][] = [ 'OperatorName' => $stats['name'], 'TotalHours' => round($stats['total_seconds'] / 3600, 1), 'TotalKG' => round($stats['total_kg'], 1) ]; }
        usort($result_data['assembly']['operator_summary'], function ($a, $b) { return strcmp($a['OperatorName'], $b['OperatorName']); });
        $result_data['assembly']['summary'] = [ 'TotalKG' => round($total_assembly_kg, 1), 'ActiveMachines' => count($active_assembly_machines), 'TotalManHours' => round($total_assembly_seconds_overall / 3600, 1) ];
    }

    // --- Fetch Rolling Data ---
    $roll_header_stmt = $pdo->prepare("SELECT RollingHeaderID, Description FROM tbl_rolling_log_header WHERE LogDate = ?");
    $roll_header_stmt->execute([$report_date_gregorian]);
    $roll_header = $roll_header_stmt->fetch(PDO::FETCH_ASSOC);
    $result_data['descriptions']['rolling'] = $roll_header['Description'] ?? null;

    if ($roll_header) {
        $roll_header_id = $roll_header['RollingHeaderID'];

        // Fetch Rolling Entries with ALL details needed
        // *** Corrected to fetch from tbl_rolling_log_entries ***
        $roll_entries_sql = "
            SELECT
                e.RollingEntryID, e.MachineID, m.MachineName, e.PartID, p.PartName, e.ProductionKG,
                e.OperatorID, emp.name as OperatorName, e.StartTime, e.EndTime
            FROM tbl_rolling_log_entries e
            JOIN tbl_machines m ON e.MachineID = m.MachineID
            JOIN tbl_parts p ON e.PartID = p.PartID
            LEFT JOIN tbl_employees emp ON e.OperatorID = emp.EmployeeID
            WHERE e.RollingHeaderID = ?
            ORDER BY m.MachineName, p.PartName, e.StartTime";
        $rolling_entries = find_all($pdo, $roll_entries_sql, [$roll_header_id]);

        $result_data['rolling']['details'] = $rolling_entries; // Return raw entries

        // Calculate Rolling Man-Hours and Total KG
        $total_rolling_kg = 0;
        $total_rolling_seconds = 0;

        // Fetch rolling break times (Assuming Dept ID 3)
        $rolling_dept_id = 3; // Or adjust if different
        $break_times_raw_roll = find_all($pdo, "SELECT StartTime, EndTime FROM tbl_break_times WHERE IsActive = 1 AND (DepartmentID IS NULL OR DepartmentID = ?)", [$rolling_dept_id]);
        $break_times_seconds_roll = [];
         foreach ($break_times_raw_roll as $break) {
            $start_secs = strtotime("1970-01-01 " . $break['StartTime'] . " UTC");
            $end_secs = strtotime("1970-01-01 " . $break['EndTime'] . " UTC");
            if ($start_secs !== false && $end_secs !== false && $end_secs > $start_secs) {
                 $break_times_seconds_roll[] = ['start' => $start_secs, 'end' => $end_secs];
            }
        }

        foreach ($rolling_entries as $entry) {
            $total_rolling_kg += (float)($entry['ProductionKG'] ?? 0);

            // Calculate adjusted duration for this entry
            // *** Calculation logic moved here from shift query ***
            if ($entry['StartTime'] && $entry['EndTime']) {
                $entry_start_secs = strtotime("1970-01-01 " . $entry['StartTime'] . " UTC");
                $entry_end_secs = strtotime("1970-01-01 " . $entry['EndTime'] . " UTC");
                if ($entry_start_secs !== false && $entry_end_secs !== false && $entry_end_secs >= $entry_start_secs) {
                    $raw_duration_secs = $entry_end_secs - $entry_start_secs;
                    $total_break_overlap_secs = 0;
                    foreach ($break_times_seconds_roll as $break) { // Use rolling break times
                        $total_break_overlap_secs += calculate_overlap($entry_start_secs, $entry_end_secs, $break['start'], $break['end']);
                    }
                    $total_break_overlap_secs = min($raw_duration_secs, $total_break_overlap_secs);
                    $adjusted_duration_secs = $raw_duration_secs - $total_break_overlap_secs;
                    // Add adjusted seconds only if there's an operator associated
                    if ($entry['OperatorID']) {
                       $total_rolling_seconds += $adjusted_duration_secs;
                    }
                }
            }
        }

        // Finalize rolling summary
        $result_data['rolling']['summary']['TotalManHours'] = round($total_rolling_seconds / 3600, 1);
        $result_data['rolling']['summary']['TotalKG'] = round($total_rolling_kg, 1);

    } else {
         $result_data['rolling'] = ['details' => [], 'summary' => ['TotalKG' => 0, 'TotalManHours' => 0], 'description' => null];
    }


    // --- Fetch Packaging Data ---
    // ... (Packaging data fetching remains the same) ...
    $pkg_header_stmt = $pdo->prepare("SELECT * FROM tbl_packaging_log_header WHERE LogDate = ?");
    $pkg_header_stmt->execute([$report_date_gregorian]);
    $pkg_header = $pkg_header_stmt->fetch(PDO::FETCH_ASSOC);
    $result_data['descriptions']['packaging'] = $pkg_header['Description'] ?? null;
    if ($pkg_header) {
        $pkg_header_id = $pkg_header['PackagingHeaderID'];
        $pkg_details_sql = " SELECT p.PartName, SUM(d.CartonsPackaged) as TotalCartonsPackaged FROM tbl_packaging_log_details d JOIN tbl_parts p ON d.PartID = p.PartID WHERE d.PackagingHeaderID = ? AND d.CartonsPackaged > 0 GROUP BY p.PartID, p.PartName ORDER BY p.PartName";
        $result_data['packaging']['details'] = find_all($pdo, $pkg_details_sql, [$pkg_header_id]);
        $pkg_ops_sql = " SELECT e.name as EmployeeName, s.StartTime, s.EndTime FROM tbl_packaging_log_shifts s JOIN tbl_employees e ON s.EmployeeID = e.EmployeeID WHERE s.PackagingHeaderID = ? ORDER BY e.name";
        $pkg_operators = find_all($pdo, $pkg_ops_sql, [$pkg_header_id]);
        if($pkg_operators){ foreach($pkg_operators as &$op) { $op['StartTimeFmt'] = $op['StartTime'] ? date('H:i', strtotime($op['StartTime'])) : null; $op['EndTimeFmt'] = $op['EndTime'] ? date('H:i', strtotime($op['EndTime'])) : null; } unset($op); $result_data['packaging']['operators'] = $pkg_operators; } else { $result_data['packaging']['operators'] = []; }
        $pkg_hours_sql = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(IFNULL(EndTime, '00:00:00'), IFNULL(StartTime, '00:00:00')))) / 3600 as TotalHours FROM tbl_packaging_log_shifts WHERE PackagingHeaderID = ? AND StartTime IS NOT NULL AND EndTime IS NOT NULL AND EndTime >= StartTime";
        $pkg_hours_result = find_all($pdo, $pkg_hours_sql, [$pkg_header_id]);
        $result_data['packaging']['summary']['TotalManHours'] = isset($pkg_hours_result[0]['TotalHours']) && $pkg_hours_result[0]['TotalHours'] !== null ? round((float)$pkg_hours_result[0]['TotalHours'], 1) : 0;
        $result_data['packaging']['summary']['TotalCartons'] = array_sum(array_column($result_data['packaging']['details'] ?? [], 'TotalCartonsPackaged'));
    } else { $result_data['packaging'] = ['details' => [], 'operators' => [], 'summary' => ['TotalCartons' => 0, 'TotalManHours' => 0], 'description' => null]; }

    $response['success'] = true;
    $response['data'] = $result_data;
    $response['message'] = '';

} catch (Exception $e) {
    error_log("Assembly Daily Summary Report API Error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
?>

