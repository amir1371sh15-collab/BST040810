<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => []];

try {
    // --- Filters ---
    $params = [];
    $where_clauses = [];
    $d_sub_params = []; // Params for Details subquery
    $d_sub_where_clauses = []; // Where for Details subquery

    $start_date = !empty($_GET['start_date']) ? to_gregorian($_GET['start_date']) : null;
    $end_date = !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : null;
    $part_family_id = !empty($_GET['part_family_id']) ? (int)$_GET['part_family_id'] : null;
    $part_id = !empty($_GET['part_id']) ? (int)$_GET['part_id'] : null;

    // --- Build Date filters for main query (h) ---
    if ($start_date) { $where_clauses[] = "h.LogDate >= ?"; $params[] = $start_date; }
    if ($end_date) { $where_clauses[] = "h.LogDate <= ?"; $params[] = $end_date; }
    $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // --- Build filters for d_sub (details) subquery ---
    $d_sub_where_clauses[] = "1=1"; // Base clause
    if ($part_id) {
        $d_sub_where_clauses[] = "d.PartID = ?";
        $d_sub_params[] = $part_id;
    } elseif ($part_family_id) {
        $d_sub_where_clauses[] = "p.FamilyID = ?";
        $d_sub_params[] = $part_family_id;
    }
    $d_sub_where_sql = 'WHERE ' . implode(' AND ', $d_sub_where_clauses);
    
    // --- Header filtering subquery (if part/family is selected) ---
    $header_filter_subquery = "";
    if ($part_id || $part_family_id) {
        $header_filter_subquery = "AND h.PlatingHeaderID IN (
            SELECT DISTINCT d.PlatingHeaderID 
            FROM tbl_plating_log_details d
            LEFT JOIN tbl_parts p ON d.PartID = p.PartID
            {$d_sub_where_sql} 
        )";
        $params = array_merge($params, $d_sub_params);
    }
    
    $where_sql .= " " . $header_filter_subquery; 

    // --- Query ---
    $sql = "
        SELECT 
            h.LogDate,
            h.NumberOfBarrels,
            d_sub.TotalWashed,
            d_sub.TotalPlated,
            d_sub.TotalReworked,
            s_sub.TotalHours,
            e_sub.StaffNames
        FROM tbl_plating_log_header h
        LEFT JOIN (
            SELECT 
                d.PlatingHeaderID,
                SUM(d.WashedKG) as TotalWashed,
                SUM(d.PlatedKG) as TotalPlated,
                SUM(d.ReworkedKG) as TotalReworked
            FROM tbl_plating_log_details d
            LEFT JOIN tbl_parts p ON d.PartID = p.PartID
            {$d_sub_where_sql} 
            GROUP BY d.PlatingHeaderID
        ) as d_sub ON h.PlatingHeaderID = d_sub.PlatingHeaderID
        LEFT JOIN (
            SELECT 
                PlatingHeaderID,
                SUM(TIME_TO_SEC(TIMEDIFF(EndTime, StartTime))) / 3600 as TotalHours
            FROM tbl_plating_log_shifts
            WHERE StartTime IS NOT NULL AND EndTime IS NOT NULL AND EndTime >= StartTime
            GROUP BY PlatingHeaderID
        ) as s_sub ON h.PlatingHeaderID = s_sub.PlatingHeaderID
        LEFT JOIN (
            SELECT 
                s.PlatingHeaderID,
                GROUP_CONCAT(e.name SEPARATOR ' - ') as StaffNames
            FROM tbl_plating_log_shifts s
            JOIN tbl_employees e ON s.EmployeeID = e.EmployeeID
            GROUP BY s.PlatingHeaderID
        ) as e_sub ON h.PlatingHeaderID = e_sub.PlatingHeaderID
        {$where_sql} 
        ORDER BY h.LogDate ASC
    ";

    $final_params = array_merge($d_sub_params, $params);

    $data = find_all($pdo, $sql, $final_params); 

    // Format data for output
    $formatted_data = [];
    foreach ($data as $row) {
        $formatted_data[] = [
            'LogDateJalali' => to_jalali($row['LogDate']),
            'NumberOfBarrels' => $row['NumberOfBarrels'] ?? 0,
            'TotalWashed' => (float)($row['TotalWashed'] ?? 0),
            'TotalPlated' => (float)($row['TotalPlated'] ?? 0),
            'TotalReworked' => (float)($row['TotalReworked'] ?? 0),
            'TotalHours' => round((float)($row['TotalHours'] ?? 0), 2),
            'StaffNames' => $row['StaffNames'] ?? null
        ];
    }

    $response['success'] = true;
    $response['data'] = $formatted_data;

} catch (Exception $e) {
    error_log("Plating Log Report API Error: " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A'));
    $response['message'] = 'Server Error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);