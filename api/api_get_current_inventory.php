<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!has_permission('warehouse.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده این گزارش را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    $response['message'] = 'فقط متد GET مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    // --- Get Optional Filters ---
    $family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT) ?: null;
    $part_id = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT) ?: null;
    $status_id = $_GET['status_id'] ?? null; // Can be 'NULL', numeric, or empty string

    // --- Define Warehouse Station IDs ---
    $warehouse_station_ids = [8, 9, 11]; // 8: منفصله, 9: نهایی, 11: کارتن
    $carton_warehouse_id = 11; // انبار کارتن
    $warehouse_station_placeholders = implode(',', $warehouse_station_ids);

    // --- Build WHERE clause and Params ---
    $where_clauses = ["t.TransactionDate <= NOW()"];
    $params = [];

    if ($part_id) {
        $where_clauses[] = "t.PartID = ?";
        $params[] = $part_id;
    } elseif ($family_id) {
        $where_clauses[] = "p.FamilyID = ?";
        $params[] = $family_id;
    }
    // If neither is provided, no part/family filter is applied.

    if ($status_id === 'NULL') {
        $where_clauses[] = "t.StatusAfterID IS NULL";
    } elseif (is_numeric($status_id)) {
        $where_clauses[] = "t.StatusAfterID = ?";
        $params[] = (int)$status_id;
    }
    // If $status_id is empty string, no status filter is applied.

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    // --- Build SQL Query ---
    $sql = "
        SELECT
            t.PartID,
            p.PartName,
            t.StatusAfterID, /* Group by ID */
            ps.StatusName,   /* Select Status Name */
            
            -- Calculate KG Balance (Only for KG transactions, NOT at carton warehouse)
            COALESCE(SUM(
                CASE
                    -- Inflow (KG)
                    WHEN t.ToStationID IN ($warehouse_station_placeholders) AND t.ToStationID != ? AND t.CartonQuantity IS NULL THEN t.NetWeightKG
                    -- Outflow (KG)
                    WHEN t.FromStationID IN ($warehouse_station_placeholders) AND t.FromStationID != ? AND t.CartonQuantity IS NULL THEN -t.NetWeightKG
                    ELSE 0
                END
            ), 0) as CurrentBalanceKG,
            
            -- Calculate Carton Balance (Only for Carton transactions at Carton Warehouse)
            COALESCE(SUM(
                CASE
                    -- Inflow (Carton)
                    WHEN t.ToStationID = ? THEN t.CartonQuantity
                    -- Outflow (Carton)
                    WHEN t.FromStationID = ? THEN -t.CartonQuantity
                    ELSE 0
                END
            ), 0) as CurrentBalanceCarton
            
        FROM tbl_stock_transactions t
        JOIN tbl_parts p ON t.PartID = p.PartID
        LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
        $where_sql
        GROUP BY t.PartID, p.PartName, t.StatusAfterID, ps.StatusName
        ORDER BY p.PartName, ps.StatusName
    ";
    
    // Add Carton Warehouse ID 4 times (2 for KG exclusion, 2 for Carton inclusion)
    array_unshift($params, $carton_warehouse_id, $carton_warehouse_id, $carton_warehouse_id, $carton_warehouse_id);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add display name for NULL status and cast types
    foreach($inventory_data as &$item) {
        if ($item['StatusAfterID'] === null && $item['StatusName'] === null) {
            $item['StatusName'] = '-- بدون وضعیت --';
        }
        $item['CurrentBalanceKG'] = (float)$item['CurrentBalanceKG'];
        $item['CurrentBalanceCarton'] = (int)$item['CurrentBalanceCarton'];
    }
    unset($item);

    $response['success'] = true;
    $response['data'] = $inventory_data; // Return all data
    $response['message'] = 'موجودی فعلی با موفقیت محاسبه شد.';

} catch (Exception $e) {
    error_log("API Get Current Inventory Error: " . $e->getMessage() . " | Input: " . print_r($_GET, true));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

