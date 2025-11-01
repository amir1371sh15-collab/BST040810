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
    $item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
    $end_date_jalali = $_GET['end_date'] ?? null;

    if (!$item_id || !$end_date_jalali) {
        throw new Exception('قطعه و تاریخ برای محاسبه موجودی الزامی است.');
    }
    
    $as_of_date_gregorian = to_gregorian($end_date_jalali);
    if (!$as_of_date_gregorian) {
        throw new Exception('فرمت تاریخ نامعتبر است.');
    }
    $as_of_datetime_gregorian = $as_of_date_gregorian . ' 23:59:59';

    $sql_balance = "
        SELECT COALESCE(SUM(t.Quantity), 0) as CurrentBalance
        FROM tbl_misc_transactions t
        WHERE t.ItemID = :item_id
          AND t.TransactionDate <= :as_of_date
    ";
    
    $stmt = $pdo->prepare($sql_balance);
    $stmt->execute([
        ':item_id' => $item_id,
        ':as_of_date' => $as_of_datetime_gregorian
    ]);
    
    $balance = $stmt->fetchColumn();

    $response['success'] = true;
    $response['data'] = [
        'CurrentBalance' => (float)$balance
    ];
    $response['message'] = 'موجودی فعلی محاسبه شد.';

} catch (Exception $e) {
    error_log("API Get Misc Current Inventory Error: " . $e->getMessage() . " | Input: " . print_r($_GET, true));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
