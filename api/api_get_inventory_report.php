<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => ''];

error_log("Inventory Report API Request GET: " . print_r($_GET, true));

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
    // --- Get and Validate Filters ---
    $start_date_jalali = $_GET['start_date'] ?? null;
    $end_date_jalali = $_GET['end_date'] ?? null;
    $employee_id = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT) ?: null;
    $family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);
    $part_id = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT) ?: null;
    $status_after_filter_id = $_GET['status_after'] ?? null; // Expecting StatusID, 'NULL', or empty string
    $mode = $_GET['mode'] ?? 'report'; // 'report' or 'stocktake_balance'
    $station_id_for_balance = filter_input(INPUT_GET, 'station_id', FILTER_VALIDATE_INT) ?: null;

    // Station IDs Constants
    define('PACKAGING_STATION_ID', 10);
    define('CARTON_WAREHOUSE_STATION_ID', 11);
    define('CUSTOMER_STATION_ID', 13);
    $warehouse_station_ids = [8, 9, 11]; // ID 8: انبار منفصله, 9: انبار نهایی, 11: انبار کارتن
    $warehouse_station_placeholders = implode(',', $warehouse_station_ids);


    // --- Logic for 'stocktake_balance' mode ---
    if ($mode === 'stocktake_balance') {
        if (!$part_id || !$station_id_for_balance || !$end_date_jalali) {
            throw new Exception('برای محاسبه موجودی انبارگردانی، قطعه، انبار و تاریخ الزامی است.');
        }
        $as_of_date_gregorian = to_gregorian($end_date_jalali);
        if (!$as_of_date_gregorian) {
            throw new Exception('فرمت تاریخ نامعتبر است.');
        }
        $as_of_datetime_gregorian = $as_of_date_gregorian . ' 23:59:59';

        if (!in_array($station_id_for_balance, $warehouse_station_ids)) {
             throw new Exception('ایستگاه انتخاب شده یک انبار معتبر نیست.');
        }

        // --- Determine if balance should be in KG or Cartons ---
        $is_carton_balance = ($station_id_for_balance == CARTON_WAREHOUSE_STATION_ID);
        
        if ($is_carton_balance) {
            // Calculate balance based on CartonQuantity
            $sql_stocktake_balance = "
                SELECT
                    t.StatusAfterID,
                    ps.StatusName,
                    COALESCE(SUM(
                        CASE
                            WHEN t.ToStationID = :station_id1 THEN t.CartonQuantity
                            WHEN t.FromStationID = :station_id2 THEN -t.CartonQuantity
                            ELSE 0
                        END
                    ), 0) as BalanceValue
                FROM tbl_stock_transactions t
                LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
                WHERE t.PartID = :part_id
                  AND (t.FromStationID = :station_id3 OR t.ToStationID = :station_id1_again)
                  AND t.TransactionDate <= :as_of_date
                GROUP BY t.StatusAfterID, ps.StatusName
                ORDER BY ps.StatusName
            ";
        } else {
            // Calculate balance based on NetWeightKG
            $sql_stocktake_balance = "
                SELECT
                    t.StatusAfterID,
                    ps.StatusName,
                    COALESCE(SUM(
                        CASE
                            WHEN t.ToStationID = :station_id1 THEN t.NetWeightKG
                            WHEN t.FromStationID = :station_id2 THEN -t.NetWeightKG
                            ELSE 0
                        END
                    ), 0) as BalanceValue
                FROM tbl_stock_transactions t
                LEFT JOIN tbl_part_statuses ps ON t.StatusAfterID = ps.StatusID
                WHERE t.PartID = :part_id
                  AND (t.FromStationID = :station_id3 OR t.ToStationID = :station_id1_again)
                  AND t.TransactionDate <= :as_of_date
                GROUP BY t.StatusAfterID, ps.StatusName
                ORDER BY ps.StatusName
            ";
        }
        
        $stmt_stocktake = $pdo->prepare($sql_stocktake_balance);
        $stmt_stocktake->execute([
            ':station_id1' => $station_id_for_balance,
            ':station_id2' => $station_id_for_balance,
            ':part_id' => $part_id,
            ':station_id3' => $station_id_for_balance,
            ':station_id1_again' => $station_id_for_balance,
            ':as_of_date' => $as_of_datetime_gregorian
        ]);
        $balances = $stmt_stocktake->fetchAll(PDO::FETCH_ASSOC);

        foreach($balances as &$item) {
            if ($item['StatusAfterID'] === null && $item['StatusName'] === null) {
                $item['StatusName'] = '-- بدون وضعیت --';
            }
            $item['StatusAfterID'] = $item['StatusAfterID'] === null ? null : (int)$item['StatusAfterID'];
            // Value formatting (int for cartons, float for KG)
            $item['BalanceValue'] = $is_carton_balance ? (int)$item['BalanceValue'] : (float)$item['BalanceValue'];
        }
        unset($item);

        $response['success'] = true;
        $response['data'] = $balances;
        $response['message'] = 'موجودی‌های وضعیت محاسبه شد.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;

    } // --- End stocktake_balance mode ---


    // --- Original Report Mode Logic ---
    // Validation for report mode
     if (!$start_date_jalali || !$end_date_jalali) {
        error_log("Report Validation Failed: Missing required fields.");
        throw new Exception('بازه زمانی برای گزارش الزامی است.');
    }

    $start_date_gregorian = to_gregorian($start_date_jalali);
    $end_date_gregorian = to_gregorian($end_date_jalali);

    if (!$start_date_gregorian || !$end_date_gregorian) {
        throw new Exception('فرمت تاریخ نامعتبر است یا تبدیل ناموفق بود.');
    }
    if (strtotime($start_date_gregorian) > strtotime($end_date_gregorian)) {
         throw new Exception('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.');
    }

    $end_datetime_gregorian = $end_date_gregorian . ' 23:59:59';
    $start_datetime_gregorian_exclusive = $start_date_gregorian . ' 00:00:00';

    $part_info = null;
    if ($family_id) {
        $family_info = find_by_id($pdo, 'tbl_part_families', $family_id, 'FamilyID');
        if (!$family_info) {
            throw new Exception('خانواده قطعه انتخاب شده یافت نشد.');
        }
        if ($part_id) {
            $part_info = find_by_id($pdo, 'tbl_parts', $part_id, 'PartID');
            if (!$part_info || $part_info['FamilyID'] != $family_id) {
                throw new Exception('قطعه انتخاب شده یافت نشد یا به این خانواده تعلق ندارد.');
            }
        }
    }

    // --- Build Base WHERE Clause and Params ---
    $base_select_from = "FROM tbl_stock_transactions t JOIN tbl_parts p ON t.PartID = p.PartID";
    $base_where_clauses = [];
    $base_params = [];

    if ($part_id) {
        $base_where_clauses[] = "t.PartID = ?";
        $base_params[] = $part_id;
    } elseif ($family_id) { // Only add family if part is not specified
        $base_where_clauses[] = "p.FamilyID = ?";
        $base_params[] = $family_id;
    }
    // If neither is provided, no Part/Family clause is added.

    // --- Correctly handle StatusAfterID filter (optional) ---
    if ($status_after_filter_id === 'NULL') {
        $base_where_clauses[] = "t.StatusAfterID IS NULL";
    } elseif (is_numeric($status_after_filter_id)) {
        $base_where_clauses[] = "t.StatusAfterID = ?";
        $base_params[] = (int)$status_after_filter_id;
    } // If $status_after_filter_id is empty string "", no clause is added (fetches all)


    if ($employee_id) {
        $base_where_clauses[] = "t.OperatorEmployeeID = ?";
        $base_params[] = $employee_id;
    }

    // --- Calculate Initial Balance (Before Start Date) - ONLY KG ---
    $initial_where_clauses = $base_where_clauses;
    $initial_where_clauses[] = "t.TransactionDate < ?";
    $initial_params = array_merge($base_params, [$start_datetime_gregorian_exclusive]);
    $initial_where_sql = empty($initial_where_clauses) ? '1' : implode(' AND ', $initial_where_clauses); // Handle empty where

    $sql_initial_balance = "
        SELECT COALESCE(SUM(
            CASE
                WHEN t.ToStationID IN ($warehouse_station_placeholders) THEN t.NetWeightKG
                WHEN t.FromStationID IN ($warehouse_station_placeholders) THEN -t.NetWeightKG
                ELSE 0
            END
        ), 0) as BalanceKG
        $base_select_from
        WHERE $initial_where_sql
          AND t.CartonQuantity IS NULL -- Exclude carton transactions from KG balance
    ";
    $stmt_initial = $pdo->prepare($sql_initial_balance);
    $stmt_initial->execute($initial_params);
    $initial_balance_kg = (float)$stmt_initial->fetchColumn();


    // --- Fetch Transactions within Range ---
    $transactions_list = null; // Set to null initially
    $total_inflow_kg = 0;
    $total_outflow_kg = 0;
    $total_inflow_cartons = 0;  // *** NEW: Initialize carton totals ***
    $total_outflow_cartons = 0; // *** NEW: Initialize carton totals ***

    $range_where_clauses = $base_where_clauses;
    $range_where_clauses[] = "t.TransactionDate >= ?";
    $range_where_clauses[] = "t.TransactionDate <= ?";
    $range_params = array_merge($base_params, [$start_datetime_gregorian_exclusive, $end_datetime_gregorian]);
    $range_where_sql = empty($range_where_clauses) ? '1' : implode(' AND ', $range_where_clauses); // Handle empty where

    $sql_transactions = "
        SELECT
            t.TransactionDate,
            t.FromStationID,
            fs.StationName as from_station_name,
            t.ToStationID,
            ts.StationName as to_station_name,
            t.NetWeightKG,
            t.CartonQuantity,
            t.TransactionTypeID,
            tt.StockEffect,
            emp.name as operator_name,
            rec.ReceiverName as receiver_name
            " . ($part_id ? "" : ", p.PartName") . "
        FROM tbl_stock_transactions t
        LEFT JOIN tbl_stations fs ON t.FromStationID = fs.StationID
        LEFT JOIN tbl_stations ts ON t.ToStationID = ts.StationID
        LEFT JOIN tbl_employees emp ON t.OperatorEmployeeID = emp.EmployeeID
        LEFT JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
        JOIN tbl_parts p ON t.PartID = p.PartID
        LEFT JOIN tbl_receivers rec ON t.ReceiverID = rec.ReceiverID
        WHERE $range_where_sql
        ORDER BY t.TransactionDate ASC, t.TransactionID ASC
    ";
    $stmt_transactions = $pdo->prepare($sql_transactions);
    $stmt_transactions->execute($range_params);
    $transactions_raw = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);

    // --- Calculate Totals and Prepare Transaction List ---
    $transactions_list = [];
    foreach ($transactions_raw as $tx) {
        $net_weight = (float)($tx['NetWeightKG'] ?? 0);
        $inflow_kg_calc = 0;
        $outflow_kg_calc = 0;
        $inflow_display = '-';
        $outflow_display = '-';
        $unit_display = '-';

        $isCartonHistory = (
            ($tx['FromStationID'] == PACKAGING_STATION_ID && $tx['ToStationID'] == CARTON_WAREHOUSE_STATION_ID) ||
            ($tx['FromStationID'] == CARTON_WAREHOUSE_STATION_ID && $tx['ToStationID'] == CUSTOMER_STATION_ID) ||
            ($tx['FromStationID'] == CARTON_WAREHOUSE_STATION_ID && $tx['ToStationID'] == CARTON_WAREHOUSE_STATION_ID && $tx['TransactionTypeID'] != null) // Stocktake
        );

        if ($isCartonHistory) {
            $unit_display = 'کارتن';
            $qty = (int)($tx['CartonQuantity'] ?? 0);
            
            // Stocktake
            if ($tx['TransactionTypeID'] != null && $tx['FromStationID'] == $tx['ToStationID']) {
                $stock_effect = (int)($tx['StockEffect'] ?? 0);
                if ($stock_effect > 0) {
                    $inflow_display = '+' . abs($qty);
                    $total_inflow_cartons += abs($qty); // *** NEW: Add to carton total ***
                } elseif ($stock_effect < 0) {
                    $outflow_display = abs($qty); // Show as positive outflow
                    $total_outflow_cartons += abs($qty); // *** NEW: Add to carton total ***
                } else {
                     $inflow_display = '0'; // No effect
                }
            }
            // Standard Carton Transfer
            elseif (in_array((int)$tx['ToStationID'], $warehouse_station_ids)) {
                 $inflow_display = $qty; // Inflow
                 $total_inflow_cartons += abs($qty); // *** NEW: Add to carton total ***
            } 
            elseif (in_array((int)$tx['FromStationID'], $warehouse_station_ids)) {
                 $outflow_display = $qty; // Outflow
                 $total_outflow_cartons += abs($qty); // *** NEW: Add to carton total ***
            }
            // Note: $inflow_kg_calc and $outflow_kg_calc remain 0 for carton tx
            
        } else {
            // KG Transaction
            $unit_display = 'KG';
            if (in_array((int)$tx['ToStationID'], $warehouse_station_ids)) {
                $inflow_kg_calc = $net_weight;
                $inflow_display = number_format($inflow_kg_calc, 3);
            } 
            elseif (in_array((int)$tx['FromStationID'], $warehouse_station_ids)) {
                $outflow_kg_calc = abs($net_weight);
                $outflow_display = number_format($outflow_kg_calc, 3);
            }
            // else: transfer between non-warehouse stations, no inflow/outflow for this report
        }

        $total_inflow_kg += $inflow_kg_calc;
        $total_outflow_kg += $outflow_kg_calc;

        $transactions_list[] = [
            'transaction_date_jalali' => to_jalali($tx['TransactionDate']),
            'part_name' => $part_info ? $part_info['PartName'] : ($tx['PartName'] ?? 'نامشخص'),
            'from_station_name' => $tx['from_station_name'] ?? 'نامشخص',
            'to_station_name' => $tx['to_station_name'] ?? 'نامشخص',
            'operator_name' => $tx['operator_name'] ?? '-',
            'receiver_name' => $tx['receiver_name'] ?? null,
            'inflow_display' => $inflow_display,
            'outflow_display' => $outflow_display,
            'unit_display' => $unit_display,
            'inflow_kg_calc' => $inflow_kg_calc,   // For running balance calculation
            'outflow_kg_calc' => $outflow_kg_calc, // For running balance calculation
        ];
    }

    // --- Calculate Final Balance (KG Only) ---
    $final_balance_kg = $initial_balance_kg + $total_inflow_kg - $total_outflow_kg;

    // Get Status Name for display
     $status_name_display = '-- همه وضعیت‌ها --'; // Default
     if ($status_after_filter_id === 'NULL') {
         $status_name_display = '-- بدون وضعیت --';
     } elseif (is_numeric($status_after_filter_id)) {
        $status_info = find_by_id($pdo, 'tbl_part_statuses', (int)$status_after_filter_id, 'StatusID');
        if($status_info) $status_name_display = $status_info['StatusName'];
     }

    // --- Prepare Response Data ---
    $response_data = [
        'part_name' => $part_info['PartName'] ?? null,
        'family_name' => $family_info['FamilyName'] ?? null,
        'status_after' => $status_name_display,
        'initial_balance_kg' => $initial_balance_kg,
        'transactions' => $transactions_list, // List of transactions
        'total_inflow_kg' => $total_inflow_kg,
        'total_outflow_kg' => $total_outflow_kg,
        'final_balance_kg' => $final_balance_kg,
        'total_inflow_cartons' => $total_inflow_cartons,   // *** NEW: Send carton total ***
        'total_outflow_cartons' => $total_outflow_cartons // *** NEW: Send carton total ***
    ];

    $response['success'] = true;
    $response['data'] = $response_data;
    $response['message'] = 'گزارش گردش موجودی با موفقیت ایجاد شد.';

} catch (Exception $e) {
    error_log("API Get Inventory Report Error: " . $e->getMessage() . " | Input: " . print_r($_GET, true));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>


