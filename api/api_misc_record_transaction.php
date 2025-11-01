<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

if (!has_permission('warehouse.misc.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز ثبت تراکنش را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'فقط متد POST مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

$pdo->beginTransaction();
try {
    $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
    $transaction_date_jalali = $_POST['transaction_date'] ?? null;
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $transaction_type_id = filter_input(INPUT_POST, 'transaction_type_id', FILTER_VALIDATE_INT);
    $quantity_input = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $operator_employee_id = filter_input(INPUT_POST, 'operator_employee_id', FILTER_VALIDATE_INT) ?: null;
    $description = trim($_POST['description'] ?? '');

    // --- Validation ---
    if (!$transaction_date_jalali || !$item_id || !$transaction_type_id || $quantity_input === null) {
        throw new Exception('داده‌های ورودی ناقص است (تاریخ، نوع، نام و میزان الزامی است).');
    }
    
    $transaction_date_gregorian = to_gregorian($transaction_date_jalali);
    if (!$transaction_date_gregorian) throw new Exception('فرمت تاریخ نامعتبر است.');
    
    $transaction_datetime = $transaction_date_gregorian . ' ' . date('H:i:s');
    if ($transaction_id) {
        $existing_tx = find_by_id($pdo, 'tbl_misc_transactions', $transaction_id, 'TransactionID');
        if ($existing_tx) $transaction_datetime = $existing_tx['TransactionDate']; // Keep original date on edit
    }

    $type_info = find_by_id($pdo, 'tbl_transaction_types', $transaction_type_id, 'TypeID');
    if (!$type_info) throw new Exception('نوع تراکنش نامعتبر است.');
    
    $stock_effect = (int)$type_info['StockEffect'];
    $quantity_to_store = (float)$quantity_input * $stock_effect; // Apply stock effect

    // --- Prepare Data for DB ---
    $data = [
        'TransactionDate' => $transaction_datetime,
        'ItemID' => $item_id,
        'TransactionTypeID' => $transaction_type_id,
        'Quantity' => $quantity_to_store,
        'OperatorEmployeeID' => $operator_employee_id,
        'Description' => $description
    ];

    if ($transaction_id) { // Update
        unset($data['TransactionDate']); // Do not change original date on edit
        $result = update_record($pdo, 'tbl_misc_transactions', $data, $transaction_id, 'TransactionID');
        $message = $result['success'] ? 'تراکنش با موفقیت ویرایش شد.' : 'خطا در ویرایش تراکنش.';
    } else { // Insert
        $result = insert_record($pdo, 'tbl_misc_transactions', $data);
        $message = $result['success'] ? 'تراکنش با موفقیت ثبت شد.' : 'خطا در ثبت تراکنش.';
    }

    if (!$result['success']) {
        throw new Exception($message . (isset($result['message']) && $message != $result['message'] ? ' جزئیات: ' . $result['message'] : ''));
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = $message;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("API Record Misc Transaction Error: " . $e->getMessage() . " | POST Data: " . print_r($_POST, true));
    $statusCode = ($e instanceof PDOException) ? 500 : 400;
    http_response_code($statusCode);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
