<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and PDO

$response = ['success' => false, 'data' => [], 'message' => ''];

// --- Input Validation ---
if (!isset($_GET['family_id']) || !is_numeric($_GET['family_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه خانواده نامعتبر یا ارسال نشده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$familyId = (int)$_GET['family_id'];

try {
    // --- Database Query ---
    $sql = "SELECT SizeID, SizeName 
            FROM tbl_part_sizes 
            WHERE FamilyID = ? 
            ORDER BY SizeName"; // Order sizes alphabetically or by value if needed

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$familyId]);
    $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Process Result ---
    if ($sizes) {
        $response['success'] = true;
        $response['data'] = $sizes;
        $response['message'] = 'سایزها با موفقیت یافت شدند.';
    } else {
        $response['success'] = true; // Still success, but no data
        $response['data'] = [];
        $response['message'] = 'هیچ سایزی برای این خانواده یافت نشد.';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // --- Error Handling ---
    error_log("API Error in api_get_part_sizes.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
