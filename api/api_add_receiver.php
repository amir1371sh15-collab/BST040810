<?php
header('Content-Type: application/json; charset=utf-8');
ob_start(); // Start output buffering to catch any stray output

require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => 'درخواست نامعتبر.', 'new_receiver_id' => null, 'receiver_name' => null, 'existing_receiver_id' => null];

// Permission Check
if (!has_permission('warehouse.transactions.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز افزودن تحویل گیرنده جدید را ندارید.';
    ob_end_clean(); // Clear buffer
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'فقط متد POST مجاز است.';
    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$receiver_name = trim($_POST['receiver_name'] ?? '');

if (empty($receiver_name)) {
    http_response_code(400);
    $response['message'] = 'نام تحویل گیرنده نمی‌تواند خالی باشد.';
    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate length
if (strlen($receiver_name) > 255) {
    http_response_code(400);
    $response['message'] = 'نام تحویل گیرنده بیش از حد طولانی است (حداکثر 255 کاراکتر).';
    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // First, check if receiver already exists
    $existing = find_one_by_field($pdo, 'tbl_receivers', 'ReceiverName', $receiver_name);
    
    if ($existing) {
        // Receiver already exists
        http_response_code(200); // Use 200 for successful "found existing"
        $response['success'] = false;
        $response['message'] = 'تحویل گیرنده با این نام از قبل وجود دارد و انتخاب شد.';
        $response['existing_receiver_id'] = (int)$existing['ReceiverID'];
        $response['receiver_name'] = $existing['ReceiverName'];
        ob_end_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Insert new receiver
    $result = insert_record($pdo, 'tbl_receivers', ['ReceiverName' => $receiver_name]);

    if ($result['success']) {
        http_response_code(201); // Created
        $response['success'] = true;
        $response['message'] = 'تحویل گیرنده جدید با موفقیت اضافه شد.';
        $response['new_receiver_id'] = (int)$result['id'];
        $response['receiver_name'] = $receiver_name;
        error_log("New receiver added successfully: ID {$result['id']}, Name: $receiver_name");
    } else {
        // Insert failed
        error_log("Insert receiver failed: " . ($result['message'] ?? 'Unknown error'));
        
        // Check if it's a duplicate entry error (in case of race condition)
        if (strpos($result['message'], 'Duplicate entry') !== false || 
            strpos($result['message'], 'ReceiverName_unique') !== false) {
            
            // Try to find the existing receiver again
            $existing = find_one_by_field($pdo, 'tbl_receivers', 'ReceiverName', $receiver_name);
            
            if ($existing) {
                http_response_code(200);
                $response['success'] = false;
                $response['message'] = 'تحویل گیرنده با این نام از قبل وجود دارد (در همان لحظه توسط کاربر دیگری اضافه شد).';
                $response['existing_receiver_id'] = (int)$existing['ReceiverID'];
                $response['receiver_name'] = $existing['ReceiverName'];
            } else {
                http_response_code(409);
                $response['message'] = 'خطای تکراری در افزودن تحویل گیرنده. لطفاً دوباره تلاش کنید.';
            }
        } else {
            http_response_code(500);
            $response['message'] = 'خطا در ثبت تحویل گیرنده: ' . ($result['message'] ?? 'خطای نامشخص');
        }
    }

} catch (PDOException $e) {
    error_log("API Add Receiver PDO Error: " . $e->getMessage() . " | Input: receiver_name='$receiver_name'");
    
    // Check for duplicate entry in exception
    if ($e->getCode() == 23000) { // Integrity constraint violation
        $existing = find_one_by_field($pdo, 'tbl_receivers', 'ReceiverName', $receiver_name);
        if ($existing) {
            http_response_code(200);
            $response['success'] = false;
            $response['message'] = 'تحویل گیرنده با این نام از قبل وجود دارد.';
            $response['existing_receiver_id'] = (int)$existing['ReceiverID'];
            $response['receiver_name'] = $existing['ReceiverName'];
        } else {
            http_response_code(409);
            $response['message'] = 'خطای تکراری در پایگاه داده.';
        }
    } else {
        http_response_code(500);
        $response['message'] = 'خطای پایگاه داده هنگام افزودن تحویل گیرنده.';
    }
    
} catch (Exception $e) {
    error_log("API Add Receiver General Error: " . $e->getMessage() . " | Input: receiver_name='$receiver_name'");
    http_response_code(500);
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
}

// Clean buffer and output JSON
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>