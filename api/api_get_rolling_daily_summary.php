<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and DB

$response = ['success' => false, 'data' => null, 'message' => 'شناسه هدر نامعتبر است.'];

// Basic permission check
if (!has_permission('production.assembly_hall.view')) { // Use view permission
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده خلاصه روزانه را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$header_id = isset($_GET['header_id']) && is_numeric($_GET['header_id']) ? (int)$_GET['header_id'] : null;

if (!$header_id) {
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Fetch Header Info
    $header_stmt = $pdo->prepare("SELECT * FROM tbl_rolling_log_header WHERE RollingHeaderID = ?");
    $header_stmt->execute([$header_id]);
    $header = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        http_response_code(404);
        $response['message'] = 'گزارش رول برای این شناسه یافت نشد.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Fetch Production Details (Grouped by Part)
    $production_stmt = $pdo->prepare("
        SELECT
            p.PartName,
            SUM(e.ProductionKG) as TotalKG
        FROM tbl_rolling_log_entries e
        JOIN tbl_parts p ON e.PartID = p.PartID
        WHERE e.RollingHeaderID = ? AND e.ProductionKG > 0
        GROUP BY e.PartID, p.PartName
        ORDER BY p.PartName
    ");
    $production_stmt->execute([$header_id]);
    $production_summary = $production_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Operator Shifts and Calculate Total Adjusted Man-Hours
    $entries_stmt = $pdo->prepare("
        SELECT e.OperatorID, emp.name as OperatorName, e.StartTime, e.EndTime
        FROM tbl_rolling_log_entries e
        LEFT JOIN tbl_employees emp ON e.OperatorID = emp.EmployeeID
        WHERE e.RollingHeaderID = ?
        ORDER BY emp.name, e.StartTime
    ");
    $entries_stmt->execute([$header_id]);
    $entries = $entries_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_adjusted_seconds = 0;
    $shifts_display = []; // Array to store unique operator shifts for display
    $processed_operators = []; // Keep track of operators processed for shifts display

    // Fetch relevant break times (Assuming Dept ID 3, adjust if needed)
    $rolling_dept_id = 3;
    $break_times_raw_roll = find_all($pdo, "SELECT StartTime, EndTime FROM tbl_break_times WHERE IsActive = 1 AND (DepartmentID IS NULL OR DepartmentID = ?)", [$rolling_dept_id]);
    $break_times_seconds_roll = [];
    foreach ($break_times_raw_roll as $break) {
        $start_secs = strtotime("1970-01-01 " . $break['StartTime'] . " UTC");
        $end_secs = strtotime("1970-01-01 " . $break['EndTime'] . " UTC");
        if ($start_secs !== false && $end_secs !== false && $end_secs > $start_secs) {
            $break_times_seconds_roll[] = ['start' => $start_secs, 'end' => $end_secs];
        }
    }

    // Helper function (defined previously, ensure it's available or redefine)
     if (!function_exists('calculate_overlap')) {
        function calculate_overlap($entry_start, $entry_end, $break_start, $break_end) {
            $overlap_start = max($entry_start, $break_start);
            $overlap_end = min($entry_end, $break_end);
            return max(0, $overlap_end - $overlap_start);
        }
    }


    foreach ($entries as $entry) {
        // Calculate adjusted duration for total man-hours
        if ($entry['StartTime'] && $entry['EndTime']) {
            $entry_start_secs = strtotime("1970-01-01 " . $entry['StartTime'] . " UTC");
            $entry_end_secs = strtotime("1970-01-01 " . $entry['EndTime'] . " UTC");
            if ($entry_start_secs !== false && $entry_end_secs !== false && $entry_end_secs > $entry_start_secs) {
                $raw_duration_secs = $entry_end_secs - $entry_start_secs;
                $total_break_overlap_secs = 0;
                foreach ($break_times_seconds_roll as $break) {
                    $total_break_overlap_secs += calculate_overlap($entry_start_secs, $entry_end_secs, $break['start'], $break['end']);
                }
                $total_break_overlap_secs = min($raw_duration_secs, $total_break_overlap_secs);
                $adjusted_duration_secs = $raw_duration_secs - $total_break_overlap_secs;
                if($adjusted_duration_secs > 0){
                     $total_adjusted_seconds += $adjusted_duration_secs;
                 }
            }
        }

        // Collect unique shifts for display
        $operator_id = $entry['OperatorID'];
        if ($operator_id && !isset($processed_operators[$operator_id])) {
             $shifts_display[] = [
                 'OperatorName' => $entry['OperatorName'] ?? 'نامشخص',
                 'StartTimeFmt' => $entry['StartTime'] ? date('H:i', strtotime($entry['StartTime'])) : null,
                 'EndTimeFmt'   => $entry['EndTime'] ? date('H:i', strtotime($entry['EndTime'])) : null
             ];
             $processed_operators[$operator_id] = true; // Mark as processed for display list
         }
         // Note: This collects only the *first* time entry found per operator for display.
         // If an operator has multiple entries, only the first Start/End time is shown.
         // A more complex grouping might be needed if exact shift aggregation per operator is required.

    }
    $total_duration_hours = round($total_adjusted_seconds / 3600, 2);

    // 4. Format production summary (convert KG to Grams for display)
    $formatted_production = [];
    foreach ($production_summary as $item) {
        $formatted_production[] = [
            'part_name' => $item['PartName'],
            'total_kg' => round((float)$item['TotalKG'], 3), // Keep KG if needed elsewhere
            'total_grams' => round((float)$item['TotalKG'] * 1000, 1) // Calculate grams
        ];
    }

    $response['success'] = true;
    $response['data'] = [
        'log_date_jalali' => to_jalali($header['LogDate']),
        'available_time_minutes' => $header['AvailableTimeMinutes'],
        'description' => $header['Description'],
        'production_summary' => $formatted_production,
        'shifts' => $shifts_display, // Return the collected shifts
        'total_duration_hours' => $total_duration_hours // Return calculated man-hours
    ];
    $response['message'] = '';

} catch (Exception $e) {
    error_log("API Get Rolling Daily Summary Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['message'] = 'خطای داخلی سرور در محاسبه خلاصه روزانه رول.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

