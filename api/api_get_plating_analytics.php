<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => []];

try {
    // --- Get Factors (with defaults) ---
    $wash_factor = isset($_GET['wash_factor']) && is_numeric($_GET['wash_factor']) ? (float)$_GET['wash_factor'] : 0.57; // Default 20/35
    $rework_factor = isset($_GET['rework_factor']) && is_numeric($_GET['rework_factor']) ? (float)$_GET['rework_factor'] : 0.29; // Default 10/35

    // --- Build WHERE clause from filters ---
    $params = [];
    $where_clauses = [];

    $start_date_filter = !empty($_GET['start_date']) ? to_gregorian($_GET['start_date']) : null;
    $end_date_filter = !empty($_GET['end_date']) ? to_gregorian($_GET['end_date']) : null;

    if ($start_date_filter) { $where_clauses[] = "h.LogDate >= ?"; $params[] = $start_date_filter; }
    if ($end_date_filter) { $where_clauses[] = "h.LogDate <= ?"; $params[] = $end_date_filter; }
    if (!empty($_GET['part_family_id'])) { $where_clauses[] = "p.FamilyID = ?"; $params[] = (int)$_GET['part_family_id']; }
    if (!empty($_GET['part_id'])) { $where_clauses[] = "d.PartID = ?"; $params[] = (int)$_GET['part_id']; }

    $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // --- Base query FROM clause ---
    $base_query_from = "FROM tbl_plating_log_details d
                        JOIN tbl_plating_log_header h ON d.PlatingHeaderID = h.PlatingHeaderID
                        LEFT JOIN tbl_parts p ON d.PartID = p.PartID
                        LEFT JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
                        {$where_sql}";

    // --- Report Queries ---

    // 1. Total Production Trend (KG), Barrel Trend, and Process Breakdown (using factors)
    // CORRECTED: Use a 2-step aggregation to prevent multiplying barrel counts
    $trend_sql = "
        SELECT 
            LogDate,
            SUM(EquivalentTotalKG) as EquivalentTotalKG,
            SUM(TotalBarrels) as TotalBarrels,
            SUM(TotalWashed) as TotalWashed,
            SUM(TotalPlated) as TotalPlated,
            SUM(TotalReworked) as TotalReworked
        FROM (
            SELECT 
                h.LogDate,
                h.PlatingHeaderID,
                h.NumberOfBarrels as TotalBarrels, 
                COALESCE(SUM((d.PlatedKG * 1) + (d.WashedKG * ?) + (d.ReworkedKG * ?)), 0) as EquivalentTotalKG,
                COALESCE(SUM(d.WashedKG), 0) as TotalWashed,
                COALESCE(SUM(d.PlatedKG), 0) as TotalPlated,
                COALESCE(SUM(d.ReworkedKG), 0) as TotalReworked
            FROM tbl_plating_log_header h
            LEFT JOIN tbl_plating_log_details d ON h.PlatingHeaderID = d.PlatingHeaderID
            LEFT JOIN tbl_parts p ON d.PartID = p.PartID -- For filtering
            {$where_sql}
            GROUP BY h.PlatingHeaderID, h.LogDate, h.NumberOfBarrels
        ) as subquery
        GROUP BY LogDate
        ORDER BY LogDate ASC
    ";
    // Add factors to the beginning of the params array for the inner query
    $trend_params = array_merge([$wash_factor, $rework_factor], $params);
    $trend_data = find_all($pdo, $trend_sql, $trend_params);
    $response['data']['total_trend_breakdown'] = $trend_data; 


    // 2. Production by Product (Equivalent KG)
    $by_part_sql = "SELECT p.PartName, 
                           SUM((d.PlatedKG * 1) + (d.WashedKG * ?) + (d.ReworkedKG * ?)) as EquivalentTotalKG
                    {$base_query_from}
                    GROUP BY p.PartName HAVING EquivalentTotalKG > 0 ORDER BY EquivalentTotalKG DESC";
    $response['data']['by_part'] = find_all($pdo, $by_part_sql, $trend_params); // Use params with factors

    // 3. Production by Family (Equivalent KG)
    $by_family_sql = "SELECT pf.FamilyName, 
                             SUM((d.PlatedKG * 1) + (d.WashedKG * ?) + (d.ReworkedKG * ?)) as EquivalentTotalKG
                      {$base_query_from}
                      GROUP BY pf.FamilyName HAVING EquivalentTotalKG > 0 ORDER BY EquivalentTotalKG DESC";
    $response['data']['by_family'] = find_all($pdo, $by_family_sql, $trend_params); // Use params with factors

    // 4. Calculate daily Avg Plated KG per Barrel
    // CORRECTED: Use a 2-step aggregation here as well
    $avg_barrel_sql = "
        SELECT
            LogDate,
            SUM(TotalPlatedKG) as TotalPlatedKG_Sum,
            SUM(TotalBarrels) as TotalBarrels_Sum
        FROM (
            SELECT
                h.LogDate,
                h.PlatingHeaderID,
                h.NumberOfBarrels as TotalBarrels,
                COALESCE(SUM(d.PlatedKG), 0) as TotalPlatedKG
            FROM tbl_plating_log_header h
            LEFT JOIN tbl_plating_log_details d ON h.PlatingHeaderID = d.PlatingHeaderID
            LEFT JOIN tbl_parts p ON d.PartID = p.PartID -- For filtering
            {$where_sql}
            GROUP BY h.PlatingHeaderID, h.LogDate, h.NumberOfBarrels
        ) as subquery
        GROUP BY LogDate
        ORDER BY LogDate ASC
    ";
    
    $avg_barrel_data = find_all($pdo, $avg_barrel_sql, $params); // Use original params here

    $daily_avg_kg_barrel = [];
    foreach($avg_barrel_data as $day_data) {
        $avg = ($day_data['TotalBarrels_Sum'] > 0) ? round((float)$day_data['TotalPlatedKG_Sum'] / (int)$day_data['TotalBarrels_Sum'], 2) : 0;
        $daily_avg_kg_barrel[] = [
            'LogDate' => $day_data['LogDate'],
            'AvgKGPerBarrel' => $avg
        ];
    }
    $response['data']['daily_avg_kg_barrel'] = $daily_avg_kg_barrel;


    // 5. Labor Productivity (Equivalent KG / Hour)
    $shift_params = [];
    $shift_where_clauses = [];
    if ($start_date_filter) { $shift_where_clauses[] = "h.LogDate >= ?"; $shift_params[] = $start_date_filter; }
    if ($end_date_filter) { $shift_where_clauses[] = "h.LogDate <= ?"; $shift_params[] = $end_date_filter; }
    $shift_where_sql = count($shift_where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $shift_where_clauses) : '';

    $hours_sql = "SELECT h.LogDate, 
                         SUM(TIME_TO_SEC(TIMEDIFF(s.EndTime, s.StartTime))) / 3600 as TotalHours
                  FROM tbl_plating_log_shifts s
                  JOIN tbl_plating_log_header h ON s.PlatingHeaderID = h.PlatingHeaderID
                  {$shift_where_sql}
                  AND s.StartTime IS NOT NULL AND s.EndTime IS NOT NULL AND s.EndTime >= s.StartTime
                  GROUP BY h.LogDate ORDER BY h.LogDate ASC";
    $hours_data_raw = find_all($pdo, $hours_sql, $shift_params);
    $hours_by_date = array_column($hours_data_raw, 'TotalHours', 'LogDate');

    $productivity_trend = [];
    // Use trend_data which already has EquivalentTotalKG calculated per day
    $eq_kg_by_date = array_column($trend_data, 'EquivalentTotalKG', 'LogDate'); 
    
    $all_dates = array_unique(array_merge(array_keys($eq_kg_by_date), array_keys($hours_by_date)));
    sort($all_dates);

    foreach ($all_dates as $date) {
        $totalEqKG = (float)($eq_kg_by_date[$date] ?? 0);
        $totalHours = (float)($hours_by_date[$date] ?? 0);
        $productivity = ($totalHours > 0) ? round($totalEqKG / $totalHours, 2) : 0;
        $productivity_trend[] = [
            'LogDate' => $date,
            'Productivity' => $productivity
        ];
    }
    $response['data']['productivity_trend'] = $productivity_trend;
    
    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = 'Server Error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

