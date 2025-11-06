<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!has_permission('warehouse.misc.view')) {
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
    $category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT) ?: null;

    if (!$start_date_jalali || !$end_date_jalali) {
        throw new Exception('بازه زمانی (از تاریخ و تا تاریخ) الزامی است.');
    }
    if (!$category_id && !$item_id) {
         throw new Exception('حداقل یک فیلتر (دسته‌بندی یا نام ماده) الزامی است.');
    }

    $start_date_gregorian = to_gregorian($start_date_jalali);
    $end_date_gregorian = to_gregorian($end_date_jalali);

    if (!$start_date_gregorian || !$end_date_gregorian) {
        throw new Exception('فرمت تاریخ نامعتبر است.');
    }
    if (strtotime($start_date_gregorian) > strtotime($end_date_gregorian)) {
         throw new Exception('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.');
    }
    
    $end_datetime_gregorian = $end_date_gregorian . ' 23:59:59';
    $start_datetime_gregorian_exclusive = $start_date_gregorian . ' 00:00:00';

    $item_info = null;
    if ($item_id) {
        // (FIX 1) Modified query to also fetch UnitSymbol
        $sql_item_info = "SELECT mi.ItemID, mi.ItemName, u.Symbol as UnitSymbol 
                          FROM tbl_misc_items mi
                          JOIN tbl_units u ON mi.UnitID = u.UnitID
                          WHERE mi.ItemID = ?";
        $item_info_raw = find_all($pdo, $sql_item_info, [$item_id]);
        if (empty($item_info_raw)) throw new Exception('ماده انتخاب شده یافت نشد.');
        $item_info = $item_info_raw[0]; // Get the first (and only) row
    }

    // --- Build Base WHERE Clause and Params ---
    $base_select_from = "FROM tbl_misc_transactions t 
                         JOIN tbl_misc_items mi ON t.ItemID = mi.ItemID
                         JOIN tbl_units u ON mi.UnitID = u.UnitID";
    $base_where_clauses = [];
    $base_params = [];

    if ($item_id) {
        $base_where_clauses[] = "t.ItemID = ?";
        $base_params[] = $item_id;
    } elseif ($category_id) {
        $base_where_clauses[] = "mi.CategoryID = ?";
        $base_params[] = $category_id;
    }
    
    // --- Calculate Initial Balance (Before Start Date) ---
    $initial_where_clauses = $base_where_clauses;
    $initial_where_clauses[] = "t.TransactionDate < ?";
    $initial_params = array_merge($base_params, [$start_datetime_gregorian_exclusive]);
    $initial_where_sql = implode(' AND ', $initial_where_clauses);

    // Group by item if no specific item is selected
    $group_by_sql_initial = $item_id ? "" : "GROUP BY t.ItemID, mi.ItemName, u.Symbol";
    $select_sql_initial = $item_id 
        ? "COALESCE(SUM(t.Quantity), 0) as Balance"
        : "t.ItemID, mi.ItemName, u.Symbol as UnitSymbol, COALESCE(SUM(t.Quantity), 0) as Balance";

    $sql_initial_balance = "
        SELECT $select_sql_initial
        $base_select_from
        WHERE $initial_where_sql
        $group_by_sql_initial
    ";
    
    $stmt_initial = $pdo->prepare($sql_initial_balance);
    $stmt_initial->execute($initial_params);
    $initial_balances_raw = $stmt_initial->fetchAll(PDO::FETCH_ASSOC);

    // --- Fetch Transactions within Range ---
    $range_where_clauses = $base_where_clauses;
    $range_where_clauses[] = "t.TransactionDate >= ?";
    $range_where_clauses[] = "t.TransactionDate <= ?";
    $range_params = array_merge($base_params, [$start_datetime_gregorian_exclusive, $end_datetime_gregorian]);
    $range_where_sql = implode(' AND ', $range_where_clauses);

    $sql_transactions = "
        SELECT
            t.TransactionDate, t.ItemID, mi.ItemName, u.Symbol as UnitSymbol,
            t.Quantity,
            tt.TypeName as TransactionTypeName,
            emp.name as operator_name,
            t.Description
        $base_select_from
        LEFT JOIN tbl_transaction_types tt ON t.TransactionTypeID = tt.TypeID
        LEFT JOIN tbl_employees emp ON t.OperatorEmployeeID = emp.EmployeeID
        WHERE $range_where_sql
        ORDER BY t.TransactionDate ASC, t.TransactionID ASC
    ";
    
    $stmt_transactions = $pdo->prepare($sql_transactions);
    $stmt_transactions->execute($range_params);
    $transactions_raw = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);

    // --- Process Data into Report Format ---
    // This logic is complex because we need to track balance per item
    $report_data_by_item = [];

    // 1. Initialize balances
    // (FIX 2) Handle the two scenarios: by item_id or by category_id
    if ($item_id) {
        // SCENARIO 1: Specific item was requested ($item_info is available)
        $initial_balance_value = 0;
        if (!empty($initial_balances_raw) && isset($initial_balances_raw[0]['Balance'])) {
            $initial_balance_value = (float)$initial_balances_raw[0]['Balance'];
        }

        $report_data_by_item[$item_id] = [
            'item_name' => $item_info['ItemName'], // Use $item_info
            'unit_symbol' => $item_info['UnitSymbol'], // Use $item_info
            'initial_balance' => $initial_balance_value,
            'transactions' => [],
            'total_inflow' => 0,
            'total_outflow' => 0,
            'final_balance' => $initial_balance_value // Start with initial
        ];
        
    } else {
        // SCENARIO 2: Category was requested (the old logic works here)
        foreach ($initial_balances_raw as $row) {
            $report_data_by_item[$row['ItemID']] = [
                'item_name' => $row['ItemName'],
                'unit_symbol' => $row['UnitSymbol'],
                'initial_balance' => (float)$row['Balance'],
                'transactions' => [],
                'total_inflow' => 0,
                'total_outflow' => 0,
                'final_balance' => (float)$row['Balance'] // Start with initial
            ];
        }
    }
    
    // 2. Process transactions
    foreach ($transactions_raw as $tx) {
        $itemID = $tx['ItemID'];
        if (!isset($report_data_by_item[$itemID])) {
            // Item had no initial balance, but has transactions
            $report_data_by_item[$itemID] = [
                'item_name' => $tx['ItemName'],
                'unit_symbol' => $tx['UnitSymbol'],
                'initial_balance' => 0,
                'transactions' => [],
                'total_inflow' => 0,
                'total_outflow' => 0,
                'final_balance' => 0
            ];
        }
        
        $quantity = (float)$tx['Quantity'];
        $inflow = ($quantity > 0) ? $quantity : 0;
        $outflow = ($quantity < 0) ? abs($quantity) : 0;
        
        // Update totals
        $report_data_by_item[$itemID]['total_inflow'] += $inflow;
        $report_data_by_item[$itemID]['total_outflow'] += $outflow;
        
        // Update running balance
        $currentBalance = $report_data_by_item[$itemID]['final_balance'] + $quantity;
        $report_data_by_item[$itemID]['final_balance'] = $currentBalance;

        $transactions_list[] = [
            'transaction_date_jalali' => to_jalali($tx['TransactionDate']),
            'item_name' => $tx['ItemName'],
            'transaction_type_name' => $tx['TransactionTypeName'] ?? 'نامشخص',
            'operator_name' => $tx['operator_name'] ?? '-',
            'description' => $tx['Description'] ?? '-',
            'inflow' => $inflow,
            'outflow' => $outflow,
            'balance' => $currentBalance // Running balance
        ];
         $report_data_by_item[$itemID]['transactions'][] = $transactions_list[count($transactions_list)-1];
    }

    $response['success'] = true;
    $response['data'] = array_values($report_data_by_item); // Return as indexed array
    $response['message'] = 'گزارش گردش موجودی با موفقیت ایجاد شد.';

} catch (Exception $e) {
    error_log("API Get Misc Inventory Report Error: " . $e->getMessage() . " | Input: " . print_r($_GET, true));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>