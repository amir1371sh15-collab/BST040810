<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers like to_jalali

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    // --- Filters ---
    $params = [];
    $where_clauses = ["1=1"]; // Start with a base condition

    $start_date = !empty($_GET['start_date']) ? to_gregorian($_GET['start_date']) : null;
    $end_date = !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : null;
    $machine_type = !empty($_GET['machine_type']) ? $_GET['machine_type'] : null;
    $machine_id = !empty($_GET['machine_id']) ? (int)$_GET['machine_id'] : null;
    $part_family_id = !empty($_GET['part_family_id']) ? (int)$_GET['part_family_id'] : null;
    $part_id = !empty($_GET['part_id']) ? (int)$_GET['part_id'] : null;

    // --- Build WHERE clause ---
    if ($start_date) { $where_clauses[] = "h.LogDate >= ?"; $params[] = $start_date; }
    if ($end_date) { $where_clauses[] = "h.LogDate <= ?"; $params[] = $end_date; }
    if ($machine_type) { $where_clauses[] = "m.MachineType = ?"; $params[] = $machine_type; } // Filter based on machine table
    if ($machine_id) { $where_clauses[] = "d.MachineID = ?"; $params[] = $machine_id; }
    if ($part_id) {
        $where_clauses[] = "d.PartID = ?"; $params[] = $part_id;
    } elseif ($part_family_id) {
        $where_clauses[] = "p.FamilyID = ?"; $params[] = $part_family_id; // Filter based on parts table
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    // --- Query ---
    // Fetch all details first, then group in PHP for better structure control
    $sql = "
        SELECT
            h.LogDate,
            h.ManHours,
            m.MachineName,
            p.PartName,
            d.ProductionKG
        FROM tbl_prod_daily_log_details d
        JOIN tbl_prod_daily_log_header h ON d.HeaderID = h.HeaderID
        JOIN tbl_machines m ON d.MachineID = m.MachineID
        JOIN tbl_parts p ON d.PartID = p.PartID
        -- Optional join for family filtering
        LEFT JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
        {$where_sql}
        ORDER BY h.LogDate ASC, m.MachineName ASC, p.PartName ASC
    ";

    $data = find_all($pdo, $sql, $params);

    // --- Group data by date in PHP ---
    $grouped_data = [];
    foreach ($data as $row) {
        $date = $row['LogDate']; // Use Gregorian date as key
        if (!isset($grouped_data[$date])) {
            $grouped_data[$date] = [
                'LogDateJalali' => to_jalali($row['LogDate']),
                'ManHours' => (float)$row['ManHours'],
                'entries' => []
            ];
        }
        // Only add entry if ProductionKG is greater than 0
        if ((float)$row['ProductionKG'] > 0) {
            $grouped_data[$date]['entries'][] = [
                'MachineName' => $row['MachineName'],
                'PartName' => $row['PartName'],
                'ProductionKG' => (float)$row['ProductionKG']
            ];
        }
    }

    // Filter out dates with no entries (after filtering out 0 KG production)
    $filtered_grouped_data = array_filter($grouped_data, function($dayData) {
        return !empty($dayData['entries']);
    });


    // Convert associative array to indexed array for JSON output
    $final_data = array_values($filtered_grouped_data);

    $response['success'] = true;
    $response['data'] = $final_data; // Return the grouped and filtered data

} catch (Exception $e) {
    error_log("Production Log Report API Error: " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A'));
    $response['message'] = 'Server Error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

