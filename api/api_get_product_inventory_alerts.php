<?php
header('Content-Type: application/json; charset=utf-8');
// آدرس‌دهی صحیح به فایل init.php
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!has_permission('planning.view_alerts')) { // Check correct permission
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده این گزارش را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    // --- Define Warehouse Station IDs ---
    $warehouse_station_ids = [8, 9, 11]; // 8: منفصله, 9: نهایی, 11: کارتن
    $carton_warehouse_id = 11; // انبار کارتن
    $warehouse_station_placeholders = implode(',', $warehouse_station_ids);

    // --- کوئری نهایی و اصلاح شده ---
    // این کوئری ابتدا موجودی واقعی را در یک CTE محاسبه می‌کند (منطبق با داشبورد انبار)
    // سپس آن را به قوانین نقطه سفارش جوین می‌زند
    $sql = "
        WITH current_stock AS (
            -- This CTE calculates the true current stock, identical to api_get_current_inventory.php
            SELECT
                t.PartID,
                t.StatusAfterID,
                -- Determine the station where the stock resides
                CASE 
                    WHEN t.FromStationID = t.ToStationID THEN t.ToStationID -- Stocktake (location doesn't change)
                    WHEN t.ToStationID IN ($warehouse_station_placeholders) THEN t.ToStationID -- Inflow
                    WHEN t.FromStationID IN ($warehouse_station_placeholders) THEN t.FromStationID -- Outflow
                END as StationID,
                
                -- 1. KG Balance Calculation
                COALESCE(SUM(
                    CASE
                        -- Inflow (KG)
                        WHEN t.ToStationID IN ($warehouse_station_placeholders) AND t.ToStationID != ? AND t.FromStationID != t.ToStationID AND t.CartonQuantity IS NULL THEN t.NetWeightKG
                        -- Outflow (KG)
                        WHEN t.FromStationID IN ($warehouse_station_placeholders) AND t.FromStationID != ? AND t.FromStationID != t.ToStationID AND t.CartonQuantity IS NULL THEN -t.NetWeightKG
                        -- Stocktake (KG) - [FIXED LOGIC] Summing the value which already has the sign
                        WHEN t.FromStationID = t.ToStationID AND t.ToStationID IN ($warehouse_station_placeholders) AND t.ToStationID != ? AND t.CartonQuantity IS NULL THEN t.NetWeightKG 
                        ELSE 0
                    END
                ), 0) as CurrentBalanceKG,
                
                -- 2. Carton Balance Calculation
                COALESCE(SUM(
                    CASE
                        -- Inflow (Carton)
                        WHEN t.ToStationID = ? AND t.FromStationID != t.ToStationID AND t.CartonQuantity IS NOT NULL THEN t.CartonQuantity
                        -- Outflow (Carton)
                        WHEN t.FromStationID = ? AND t.FromStationID != t.ToStationID AND t.CartonQuantity IS NOT NULL THEN -t.CartonQuantity
                        -- Stocktake (Carton) - [FIXED LOGIC] Summing the value which already has the sign
                        WHEN t.FromStationID = t.ToStationID AND t.ToStationID = ? AND t.CartonQuantity IS NOT NULL THEN t.CartonQuantity 
                        ELSE 0
                    END
                ), 0) as CurrentBalanceCarton
                
            FROM tbl_stock_transactions t
            LEFT JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
            WHERE 
                (t.ToStationID IN ($warehouse_station_placeholders) OR t.FromStationID IN ($warehouse_station_placeholders))
            GROUP BY t.PartID, t.StatusAfterID, StationID
        )
        -- Main query: Select rules and LEFT JOIN the calculated stock
        SELECT 
            ss.PartID, ss.StationID, ss.StatusID, 
            ss.SafetyStockValue, ss.Unit,
            p.PartName, s.StationName, ps.StatusName,
            -- COALESCE ensures that if a part has a rule but NO stock (NULL), it's treated as 0
            COALESCE(cs.CurrentBalanceKG, 0.0) as CurrentBalanceKG,
            COALESCE(cs.CurrentBalanceCarton, 0) as CurrentBalanceCarton
        FROM tbl_inventory_safety_stock ss
        JOIN tbl_parts p ON ss.PartID = p.PartID
        JOIN tbl_stations s ON ss.StationID = s.StationID
        LEFT JOIN tbl_part_statuses ps ON ss.StatusID = ps.StatusID
        -- LEFT JOIN our correct stock calculation
        LEFT JOIN current_stock cs ON ss.PartID = cs.PartID 
                                  AND ss.StationID = cs.StationID
                                  -- Handle NULL StatusID join
                                  AND (ss.StatusID = cs.StatusAfterID OR (ss.StatusID IS NULL AND cs.StatusAfterID IS NULL))
        WHERE ss.SafetyStockValue > 0 -- Only check active rules
        -- HAVING clause now compares numbers (e.g., 0 <= 50) and will work correctly
        HAVING 
            (ss.Unit = 'KG' AND CurrentBalanceKG <= ss.SafetyStockValue)
            OR
            (ss.Unit = 'Carton' AND CurrentBalanceCarton <= ss.SafetyStockValue)
    ";
    
    $stmt = $pdo->prepare($sql);
    // Execute with all 6 parameters for the CTE
    $stmt->execute([
        $carton_warehouse_id, // Param 1 (KG Inflow)
        $carton_warehouse_id, // Param 2 (KG Outflow)
        $carton_warehouse_id, // Param 3 (KG Stocktake)
        $carton_warehouse_id, // Param 4 (Carton Inflow)
        $carton_warehouse_id, // Param 5 (Carton Outflow)
        $carton_warehouse_id  // Param 6 (Carton Stocktake)
    ]);
    
    $all_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Step 4: Format data for the (already correct) JS file ---
    $alerts_formatted = [];
    foreach ($all_alerts as $alert) {
        $alerts_formatted[] = [
            'PartName' => $alert['PartName'],
            'StationName' => $alert['StationName'],
            'StatusName' => $alert['StatusName'] ?? '-- بدون وضعیت --',
            'Unit' => $alert['Unit'],
            // [اصلاح نهایی] ارسال مقادیر عددی صحیح
            'CurrentValue' => ($alert['Unit'] == 'KG') ? (float)$alert['CurrentBalanceKG'] : (int)$alert['CurrentBalanceCarton'],
            'SafetyStockValue' => ($alert['Unit'] == 'KG') ? (float)$alert['SafetyStockValue'] : (int)$alert['SafetyStockValue']
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $alerts_formatted; // ارسال آرایه واحد که JS منتظر آن است

} catch (Exception $e) {
    error_log("API Get Product Inventory Alerts Error: " . $e->getMessage() . "\nSQL: " . ($sql ?? 'N/A'));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500); // Send 500 on exception
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

