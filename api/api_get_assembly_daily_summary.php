<?php
// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');
// Include the main initialization file (handles DB connection, helpers, session)
require_once __DIR__ . '/../config/init.php';
// *** REMOVED: require_once __DIR__ . '/warehouse_helpers.php'; ***

// Initialize the response array
$response = ['success' => false, 'data' => null, 'message' => 'تاریخ مشخص نشده است.'];

// --- Permission Check ---
// Ensure the user has permission to view the daily summary
if (!has_permission('production.assembly_hall.view')) {
    http_response_code(403); // Forbidden
    $response['message'] = 'شما مجوز مشاهده خلاصه روزانه را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Input Validation ---
// Get the date string (expecting Gregorian YYYY-MM-DD from the details button)
$log_date_str = $_GET['log_date'] ?? null;

// Validate the date format
if (!$log_date_str || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $log_date_str)) {
     http_response_code(400); // Bad Request
     $response['message'] = 'فرمت تاریخ نامعتبر است (YYYY-MM-DD).';
     echo json_encode($response, JSON_UNESCAPED_UNICODE);
     exit;
}
$log_date_gregorian = $log_date_str; // The input is already Gregorian

try {
    // --- Step 1: Find header for the date, include Description ---
    $header_stmt = $pdo->prepare("SELECT AssemblyHeaderID, Description, AvailableTimeMinutes, DailyProductionPlan FROM tbl_assembly_log_header WHERE LogDate = ?");
    $header_stmt->execute([$log_date_gregorian]);
    $header_info = $header_stmt->fetch(PDO::FETCH_ASSOC);

    // If no header exists for the date, return default data
    if (!$header_info) {
        $response['success'] = true; // Still a success, just no data
        $response['message'] = 'هیچ رکوردی برای این تاریخ یافت نشد.';
        $response['data'] = [
            'log_date_jalali' => to_jalali($log_date_gregorian),
            'description' => null,
            'available_time_minutes' => 0, // Add AvailableTimeMinutes
            'daily_plan' => null,         // Add DailyProductionPlan
            'active_machines_count' => 0,
            'total_duration_hours' => 0,
            'production_summary' => []
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Get header details
    $header_id = $header_info['AssemblyHeaderID'];
    $description = $header_info['Description'];
    $available_time_minutes = $header_info['AvailableTimeMinutes']; // Get AvailableTimeMinutes
    $daily_plan = $header_info['DailyProductionPlan'];             // Get DailyProductionPlan


    // --- Step 2: Fetch active break times for adjusted duration calculation ---
    $assembly_dept_id = 3; // Assuming assembly department ID is 3
    $break_times_raw = find_all($pdo, "SELECT StartTime, EndTime FROM tbl_break_times WHERE IsActive = 1 AND (DepartmentID IS NULL OR DepartmentID = ?)", [$assembly_dept_id]);
    $break_times_seconds = [];
    foreach ($break_times_raw as $break) {
        $start_secs = strtotime("1970-01-01 " . $break['StartTime'] . " UTC");
        $end_secs = strtotime("1970-01-01 " . $break['EndTime'] . " UTC");
        if ($start_secs !== false && $end_secs !== false && $end_secs > $start_secs) {
            $break_times_seconds[] = ['start' => $start_secs, 'end' => $end_secs];
        }
    }

    // --- Step 3: Calculate Total *Adjusted* Duration and Count Active Machines ---
    // Fetch entries with valid times
    $entries_stmt = $pdo->prepare("SELECT MachineID, StartTime, EndTime FROM tbl_assembly_log_entries WHERE AssemblyHeaderID = ? AND StartTime IS NOT NULL AND EndTime IS NOT NULL AND EndTime >= StartTime");
    $entries_stmt->execute([$header_id]);
    $entries = $entries_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_adjusted_seconds = 0;
    $active_machines = []; // Use an array to count unique machines

    // Ensure calculate_overlap function is available
    if (!function_exists('calculate_overlap')) {
        function calculate_overlap($entry_start, $entry_end, $break_start, $break_end) {
            $overlap_start = max($entry_start, $break_start);
            $overlap_end = min($entry_end, $break_end);
            return max(0, $overlap_end - $overlap_start);
        }
    }

    // Loop through entries to calculate adjusted time and count machines
    foreach ($entries as $entry) {
        $active_machines[$entry['MachineID']] = true; // Add machine ID to count unique ones

        $entry_start_secs = strtotime("1970-01-01 " . $entry['StartTime'] . " UTC");
        $entry_end_secs = strtotime("1970-01-01 " . $entry['EndTime'] . " UTC");

        if ($entry_start_secs === false || $entry_end_secs === false || $entry_end_secs < $entry_start_secs) {
            continue; // Skip invalid time entries
        }

        $raw_duration_secs = $entry_end_secs - $entry_start_secs;
        $total_break_overlap_secs = 0;

        // Calculate overlap with each break time
        foreach ($break_times_seconds as $break) {
            $total_break_overlap_secs += calculate_overlap($entry_start_secs, $entry_end_secs, $break['start'], $break['end']);
        }

        // Ensure break overlap doesn't exceed entry duration
        $total_break_overlap_secs = min($raw_duration_secs, $total_break_overlap_secs);
        // Add the adjusted duration (raw duration minus breaks) to the total
        $total_adjusted_seconds += ($raw_duration_secs - $total_break_overlap_secs);
    }
    // Convert total adjusted seconds to hours
    $total_duration_hours = round($total_adjusted_seconds / 3600, 2);
    // Count the number of unique active machines
    $active_machines_count = count($active_machines);

    // --- Step 4: Fetch and Process Production Summary ---
    // Fetch total KG produced per PartID for the given header
    $production_stmt = $pdo->prepare("
        SELECT e.PartID, p.PartName, SUM(e.ProductionKG) as TotalKG
        FROM tbl_assembly_log_entries e
        JOIN tbl_parts p ON e.PartID = p.PartID
        WHERE e.AssemblyHeaderID = ? AND e.ProductionKG > 0 -- Only sum positive production
        GROUP BY e.PartID, p.PartName
        ORDER BY p.PartName
    ");
    $production_stmt->execute([$header_id]);
    $production_summary_raw = $production_stmt->fetchAll(PDO::FETCH_ASSOC);

    // *** Prepare statement for fetching weight directly ***
    $weight_sql = "SELECT WeightGR FROM tbl_part_weights
                   WHERE PartID = ? AND EffectiveFrom <= ? AND (EffectiveTo IS NULL OR EffectiveTo >= ?)
                   ORDER BY EffectiveFrom DESC
                   LIMIT 1";
    $weight_stmt = $pdo->prepare($weight_sql);

    $formatted_production = [];
    // Process each part's production total
    foreach ($production_summary_raw as $item) {
        $part_id_current = $item['PartID'];
        $total_kg = (float)$item['TotalKG'];

        // *** Execute the direct query to get the weight ***
        $weight_stmt->execute([$part_id_current, $log_date_gregorian, $log_date_gregorian]);
        $weight_result = $weight_stmt->fetchColumn();
        $unit_weight_grams = ($weight_result !== false) ? (float)$weight_result : null; // Fetch weight in grams

        // Calculate total count using the fetched weight
        $total_count = ($unit_weight_grams !== null && $unit_weight_grams > 0)
                       ? round(($total_kg * 1000.0) / $unit_weight_grams) // Calculate count if weight is valid
                       : 0; // Default to 0 if weight is invalid or not found

        $formatted_production[] = [
            'part_name' => $item['PartName'],
            'total_kg' => round($total_kg, 3), // Keep total KG
            'total_count' => $total_count,    // Store calculated count
            'unit_weight' => $unit_weight_grams // Store the fetched weight (grams) for reference
        ];
    }

    // --- Step 5: Assemble Final Response ---
    $response['success'] = true;
    $response['data'] = [
        'log_date_jalali' => to_jalali($log_date_gregorian),
        'description' => $description,                  // Include description
        'available_time_minutes' => $available_time_minutes, // Include AvailableTimeMinutes
        'daily_plan' => $daily_plan,                     // Include DailyProductionPlan
        'active_machines_count' => $active_machines_count, // Use calculated count
        'total_duration_hours' => $total_duration_hours,    // Use calculated adjusted hours
        'production_summary' => $formatted_production      // Use processed summary with correct count
    ];
    $response['message'] = ''; // Clear default message on success

} catch (Throwable $e) { // Catch Throwable to handle Errors and Exceptions
    // *** Log the error WITH file and line number ***
    error_log("API Get Assembly Daily Summary Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500); // Internal Server Error
    // *** Provide the detailed error message in the JSON response FOR DEBUGGING ***
    // !!! IMPORTANT: Remove or change this for production !!!
    $response['message'] = 'خطای داخلی سرور: ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')';
}

// Send the JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

