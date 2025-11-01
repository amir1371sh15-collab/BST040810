<?php // Ensure no characters before this line
header('Content-Type: application/json; charset=utf-8'); // Set header first
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/warehouse_helpers.php';

const TABLE_NAME = 'tbl_stock_transactions';
const PRIMARY_KEY = 'TransactionID';

// Station IDs Constants
const PACKAGING_STATION_ID = 10;
const CARTON_WAREHOUSE_STATION_ID = 11;
const CUSTOMER_STATION_ID = 13;

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

if (!has_permission('warehouse.transactions.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز ثبت یا ویرایش تراکنش را ندارید.';
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
    $part_id = filter_input(INPUT_POST, 'part_id', FILTER_VALIDATE_INT);
    $from_station_id = filter_input(INPUT_POST, 'from_station_id', FILTER_VALIDATE_INT);
    $to_station_id = filter_input(INPUT_POST, 'to_station_id', FILTER_VALIDATE_INT);
    $pallet_type_id = filter_input(INPUT_POST, 'pallet_type_id', FILTER_VALIDATE_INT) ?: null;
    $pallet_weight_kg = filter_input(INPUT_POST, 'pallet_weight_kg', FILTER_VALIDATE_FLOAT) ?: 0.0;
    $gross_weight_kg_input = filter_input(INPUT_POST, 'gross_weight_kg', FILTER_VALIDATE_FLOAT); // Input might be weight or cartons
    $carton_quantity_input = filter_input(INPUT_POST, 'carton_quantity', FILTER_VALIDATE_INT); // Specific input for carton qty
    $operator_employee_id = filter_input(INPUT_POST, 'operator_employee_id', FILTER_VALIDATE_INT) ?: null;
    $sender_employee_id = null; // *** Field removed from form ***
    $receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT) ?: null;
    $transaction_type_id = filter_input(INPUT_POST, 'transaction_type_id', FILTER_VALIDATE_INT) ?: null; // For stocktake

    // --- Input Validation ---
    if (!$transaction_date_jalali || !$part_id || !$from_station_id || !$to_station_id) {
        throw new Exception('داده‌های ورودی ناقص است (تاریخ، قطعه، ایستگاه‌ها).');
    }

    $transaction_date_gregorian = to_gregorian($transaction_date_jalali);
    if (!$transaction_date_gregorian) {
        throw new Exception('فرمت تاریخ نامعتبر است.');
    }
    $transaction_datetime = $transaction_date_gregorian . ' ' . date('H:i:s');
    if ($transaction_id) {
        $existing_tx = find_by_id($pdo, TABLE_NAME, $transaction_id, PRIMARY_KEY);
        if ($existing_tx) {
            $transaction_datetime = $existing_tx['TransactionDate']; // Keep original timestamp on edit
        }
    }

    // --- Determine Unit and Quantity ---
    $net_weight_kg = null;
    $carton_quantity = null;
    $gross_weight_kg = null; // Final gross weight to store
    $is_carton_transaction = false;

    // Case 1: Packaging -> Carton Warehouse
    if ($from_station_id == PACKAGING_STATION_ID && $to_station_id == CARTON_WAREHOUSE_STATION_ID) {
        if ($carton_quantity_input === null || $carton_quantity_input <= 0) {
            throw new Exception('تعداد کارتن برای انتقال به انبار کارتن الزامی و باید مثبت باشد.');
        }
        $carton_quantity = $carton_quantity_input;
        $is_carton_transaction = true;
        $gross_weight_kg = 0; $net_weight_kg = 0; $pallet_weight_kg = 0; $pallet_type_id = null;
    }
    // Case 2: Carton Warehouse -> Customer
    elseif ($from_station_id == CARTON_WAREHOUSE_STATION_ID && $to_station_id == CUSTOMER_STATION_ID) {
        if ($carton_quantity_input === null || $carton_quantity_input <= 0) {
            throw new Exception('تعداد کارتن برای ارسال به مشتری الزامی و باید مثبت باشد.');
        }
        if ($receiver_id === null) {
            throw new Exception('انتخاب تحویل گیرنده (مشتری) برای ارسال به مشتری الزامی است.');
        }
        $carton_quantity = $carton_quantity_input;
        $is_carton_transaction = true;
        $gross_weight_kg = 0; $net_weight_kg = 0; $pallet_weight_kg = 0; $pallet_type_id = null;
    }
    // Case 3: Stocktake at Carton Warehouse
    elseif ($transaction_type_id !== null && $from_station_id == CARTON_WAREHOUSE_STATION_ID && $to_station_id == CARTON_WAREHOUSE_STATION_ID) {
         $stocktake_carton_input = filter_input(INPUT_POST, 'quantity_kg', FILTER_VALIDATE_FLOAT);
         if ($stocktake_carton_input === null || $stocktake_carton_input < 0) {
             throw new Exception('مقدار کارتن برای انبارگردانی انبار کارتن الزامی و باید غیرمنفی باشد.');
         }
         $carton_quantity_abs = (int)round($stocktake_carton_input);
         $carton_quantity = $carton_quantity_abs;
         $is_carton_transaction = true;

        $stocktake_type_info = find_by_id($pdo, 'tbl_transaction_types', $transaction_type_id, 'TypeID');
        $stock_effect = $stocktake_type_info ? (int)$stocktake_type_info['StockEffect'] : 0;

        if ($stock_effect < 0) { $carton_quantity = -$carton_quantity_abs; }
         elseif ($stock_effect == 0) { $carton_quantity = 0; }
         // Positive effect uses positive $carton_quantity

         $net_weight_kg = null; // Not used for carton stocktake balance calc
         $gross_weight_kg = 0; $pallet_weight_kg = 0; $pallet_type_id = null;
    }
    // Case 4: Standard Weight Transaction (includes KG stocktake)
    else {
        // For stocktake, the input field is named 'quantity_kg'
        // For standard transfer, it's also 'gross_weight_kg'
        // We check 'quantity_kg' first (from stocktake form)
        $weight_input = $gross_weight_kg_input;
        if ($transaction_type_id !== null && $gross_weight_kg_input === null) {
             $weight_input = filter_input(INPUT_POST, 'quantity_kg', FILTER_VALIDATE_FLOAT);
        }

        if ($weight_input === null || $weight_input < 0) {
            throw new Exception('وزن ناخالص (KG) الزامی و باید غیرمنفی باشد.');
        }
        
        $gross_weight_kg = $weight_input; // This is the positive value from the form
        $net_weight_kg = $gross_weight_kg - $pallet_weight_kg; // This is the positive net value
        
        if ($net_weight_kg < 0) {
            error_log("Warning: Negative NetWeightKG calculated for Transfer PartID: $part_id (Gross: $gross_weight_kg, Pallet: $pallet_weight_kg). Setting NetWeight to 0.");
            $net_weight_kg = 0; // Net weight cannot be negative
        }
        
        $carton_quantity = null;
        $sender_employee_id = null; $receiver_id = null;
    }

    // --- Calculate Route Details ---
    $calculated_details = calculate_details($pdo, $part_id, $from_station_id, $to_station_id, $transaction_date_jalali, $is_carton_transaction);
    if ($calculated_details['error'] && !$is_carton_transaction && $calculated_details['base_weight_gr'] === null) {
        error_log('Critical calculation error: ' . $calculated_details['error']);
        // throw new Exception('خطا در محاسبه جزئیات وزن: ' . $calculated_details['error']);
    } elseif ($calculated_details['error'] && $calculated_details['route_status'] != 'NonStandardPending') { // Log non-blocking errors unless it's pending reason
         error_log('Non-critical calculation error: ' . $calculated_details['error']);
    }

    $status_after_id = null;
    $final_route_status = $calculated_details['route_status'];
    $final_deviation_id = $calculated_details['deviation_id'];
    $pending_reason = null;

    if ($calculated_details['needs_selection']) {
        $status_after_selected_id_str = $_POST['status_after_select'] ?? null;
        if (empty($status_after_selected_id_str)) { // Check if it's ""
            throw new Exception('وضعیت خروجی انتخاب شده نامعتبر است.');
        }
        $status_after_id = ($status_after_selected_id_str === 'NULL') ? null : (int)$status_after_selected_id_str;

        $part = find_by_id($pdo, 'tbl_parts', $part_id, 'PartID');
        $family_id = $part['FamilyID'] ?? null;
        $standardRoute = get_standard_route($pdo, $family_id, $from_station_id, $to_station_id);
        $activeOverride = find_active_route_override($pdo, $family_id, $from_station_id, $to_station_id);

        if ($standardRoute && $standardRoute['NewStatusID'] == $status_after_id) {
            $final_route_status = 'Standard'; $final_deviation_id = null;
        } elseif ($activeOverride && $activeOverride['OutputStatusID'] == $status_after_id) {
            $final_route_status = 'NonStandardApproved'; $final_deviation_id = $activeOverride['DeviationID'];
        } else {
             $final_route_status = 'NonStandardPending'; $final_deviation_id = null;
             $pending_reason = 'وضعیت انتخاب شده با مسیر استاندارد یا مجاز تطابق ندارد';
             error_log("Could not match selected status ID '$status_after_id' to standard or override. Setting RouteStatus to NonStandardPending.");
        }
    } else {
        $status_after_id = $calculated_details['StatusAfterID']; // Can be null
        if ($final_route_status === 'NonStandardPending') {
             $pending_reason = $calculated_details['error'] ?: 'مسیر استاندارد یا مجاز یافت نشد';
        }
    }

    // --- Override for Stocktake ---
    $stocktake_type_ids_from_db = array_map('intval', array_column(
        find_all($pdo, "SELECT TypeID FROM tbl_transaction_types WHERE TypeName IN ('موجودی اولیه', 'کسر انبارگردانی', 'اضافه انبارگردانی')"),
        'TypeID'
    ));
    
    if ($transaction_type_id !== null && in_array($transaction_type_id, $stocktake_type_ids_from_db)) {
        $final_route_status = 'Standard'; $pending_reason = null;
        $status_after_id_from_post = $_POST['status_after_id'] ?? null;
        $status_after_id = ($status_after_id_from_post === 'NULL' || $status_after_id_from_post === '') ? null : (int)$status_after_id_from_post;
        
        if ($from_station_id !== $to_station_id) {
            throw new Exception('برای عملیات انبارگردانی، ایستگاه مبدا و مقصد باید یکی باشند.');
        }
        $sender_employee_id = null; $receiver_id = null;

        // *** START FIX FOR KG STOCKTAKE ***
        // This check ensures we only apply effect to KG stocktake (carton is handled in Case 3)
        if ($is_carton_transaction == false) {
            $stocktake_type_info = find_by_id($pdo, 'tbl_transaction_types', $transaction_type_id, 'TypeID');
            $stock_effect = $stocktake_type_info ? (int)$stocktake_type_info['StockEffect'] : 0;
            
            // $net_weight_kg holds the positive absolute value (e.g., 50.0)
            // Now we apply the effect (e.g., 50.0 * -1 = -50.0 for "کسر")
            $net_weight_kg = $net_weight_kg * $stock_effect;
            
            // Gross weight should also reflect this adjustment for consistency
            $gross_weight_kg = $net_weight_kg + $pallet_weight_kg;
        }
        // *** END FIX ***
    }

    // --- Prepare Data for DB ---
    $data = [
        'TransactionDate' => $transaction_datetime,
        'PartID' => $part_id,
        'FromStationID' => $from_station_id,
        'ToStationID' => $to_station_id,
        'GrossWeightKG' => $gross_weight_kg,
        'PalletTypeID' => $pallet_type_id,
        'PalletWeightKG' => $pallet_weight_kg,
        'NetWeightKG' => $net_weight_kg, // Null for carton transactions (except stocktake difference)
        'CartonQuantity' => $carton_quantity, // Holds the carton count
        'BaseWeightGR' => $is_carton_transaction ? null : ($calculated_details['base_weight_gr'] ?? null),
        'AppliedWeightChangePercent' => $is_carton_transaction ? null : ($calculated_details['change_percent'] ?? 0.0),
        'FinalWeightGR' => $is_carton_transaction ? null : ($calculated_details['final_weight_gr'] ?? null),
        'StatusAfterID' => $status_after_id, // Can be null
        'RouteStatus' => $final_route_status,
        'DeviationID' => $final_deviation_id,
        'PendingReason' => $pending_reason,
        'CreatedBy' => $_SESSION['user_id'] ?? null,
        'OperatorEmployeeID' => $operator_employee_id,
        'SenderEmployeeID' => $sender_employee_id, // *** This is now always NULL ***
        'ReceiverID' => $receiver_id,
        'TransactionTypeID' => $transaction_type_id
    ];

    if ($transaction_id) { // Update
        unset($data['TransactionDate'], $data['CreatedBy'], $data['TransactionTypeID']);
        // Fields that should NOT change on edit:
        unset($data['FromStationID'], $data['ToStationID'], $data['PartID']);

        $result = update_record($pdo, TABLE_NAME, $data, $transaction_id, PRIMARY_KEY);
        $message = $result['success'] ? 'تراکنش با موفقیت ویرایش شد.' : 'خطا در ویرایش تراکنش.';
    } else { // Insert
        $result = insert_record($pdo, TABLE_NAME, $data);
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
    error_log("API Record Transaction Error: " . $e->getMessage() . " | POST Data: " . print_r($_POST, true));
    $statusCode = ($e instanceof PDOException) ? 500 : 400;
    http_response_code($statusCode);
    $response['message'] = $e->getMessage();
}

// Ensure no BOM character before output
if (ob_get_level() > 0) { ob_end_clean(); } // Clean output buffer if needed
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

