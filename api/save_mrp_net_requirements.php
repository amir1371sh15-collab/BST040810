<?php
// api/save_mrp_net_requirements.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'data' => ['run_id' => null]];

try {
    if (!has_permission('planning.mrp.run')) {
        throw new Exception('شما مجوز ذخیره نتایج MRP را ندارید.', 403);
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $net_requirements = $input['net_requirements'] ?? [];
    $run_id = $input['run_id'] ?? null;
    $run_date = $input['run_date'] ?? date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? null;

    if (empty($net_requirements) || empty($run_id)) {
        throw new Exception('هیچ نیازمندی خالصی برای ذخیره ارسال نشده است یا شناسه اجرا نامعتبر است.');
    }
    
    $pdo->beginTransaction();
    $inserted_count = 0;

    // 1. به‌روزرسانی وضعیت اجرای MRP (از پیش فرض به Completed)
    $update_run_sql = "
        UPDATE tbl_planning_mrp_run 
        SET Status = 'Completed', RunDate = ?, RunByUserID = ?
        WHERE RunID = ?
    ";
    $stmt_update = $pdo->prepare($update_run_sql);
    $stmt_update->execute([$run_date, $user_id, $run_id]);

    // 2. درج نتایج MRP
    $insert_result_sql = "
        INSERT INTO tbl_planning_mrp_results 
            (RunID, ItemID, ItemStatusID, NetRequirement, ItemType, Unit) 
        VALUES 
            (?, ?, ?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($insert_result_sql);

    foreach ($net_requirements as $item) {
        $part_id = (int)$item['ItemID'];
        // اگر ItemStatusID رشته 'NULL' بود، آن را به NULL واقعی تبدیل می‌کنیم.
        $item_status_id = ($item['ItemStatusID'] === 'NULL' || $item['ItemStatusID'] === null) ? null : (int)$item['ItemStatusID'];
        $net_requirement = (float)$item['NetRequirement'];
        
        // اطمینان از اینکه فقط کسری واقعی (NetRequirement > 0) ذخیره شود
        if ($part_id > 0 && $net_requirement > 0) {
            $stmt->execute([
                $run_id, 
                $part_id, 
                $item_status_id, 
                $net_requirement, 
                $item['ItemType'], 
                $item['Unit']
            ]);
            $inserted_count++;
        }
    }

    if ($inserted_count === 0) {
        // این یک هشدار است، نه یک شکست تراکنش.
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = "اجرای MRP ذخیره شد، اما هیچ کسری (Net Requirement) بالای صفر برای درج یافت نشد.";
        $response['data']['run_id'] = $run_id;
        error_log("MRP Save Results WARNING: No net requirements > 0 found for insert.");
        return;
    }
    
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = "نتایج MRP با موفقیت ذخیره شدند ({$inserted_count} ردیف) و RunID به Completed تغییر یافت.";
    $response['data']['run_id'] = $run_id;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $error_msg = 'خطای دیتابیس در درج نتایج: ' . $e->getMessage();
    http_response_code(500);
    $response['message'] = $error_msg;
    error_log("MRP Save Results PDO Error: " . $error_msg);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code($e->getCode() > 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
    error_log("MRP Save Results General Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
