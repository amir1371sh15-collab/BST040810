<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and PDO

$response = ['success' => false, 'data' => [], 'message' => ''];

// --- Input Validation ---
$family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);

// Family ID is optional. If not provided, return all statuses.
// if (!$family_id) {
//     http_response_code(400);
//     $response['message'] = 'شناسه خانواده نامعتبر یا ارسال نشده است.';
//     echo json_encode($response, JSON_UNESCAPED_UNICODE);
//     exit;
// }

try {
    $params = [];
    $sql = "SELECT DISTINCT ps.StatusID, ps.StatusName
            FROM tbl_part_statuses ps ";

    if ($family_id) {
        // If a family ID is provided, join with the compatibility table
        $sql .= " JOIN tbl_family_status_compatibility fsc ON ps.StatusID = fsc.StatusID
                  WHERE fsc.FamilyID = ? ";
        $params[] = $family_id;
    }
    // If no family ID, the query fetches all statuses

    $sql .= " ORDER BY ps.StatusName";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $statuses;
    if (empty($statuses) && $family_id) {
        $response['message'] = 'هیچ وضعیت مرتبطی برای این خانواده یافت نشد.';
    } elseif (empty($statuses)) {
         $response['message'] = 'هیچ وضعیتی در سیستم تعریف نشده است.';
    }


} catch (PDOException $e) {
    error_log("API Error in api_get_statuses_by_family.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
} catch (Exception $e) {
    error_log("API General Error in api_get_statuses_by_family.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
?>
