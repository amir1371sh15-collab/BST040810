<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Includes PDO and helpers

$response = ['success' => false, 'data' => [], 'message' => ''];

// --- Input Validation: Expect Machine ID ---
if (!isset($_GET['machine_id']) || !is_numeric($_GET['machine_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه دستگاه نامعتبر یا ارسال نشده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
$machineId = (int)$_GET['machine_id'];

try {
    // --- Step 1: Find Family IDs associated with the Machine ---
    $family_sql = "SELECT FamilyID FROM tbl_machine_producible_families WHERE MachineID = ?";
    $stmt_family = $pdo->prepare($family_sql);
    $stmt_family->execute([$machineId]);
    $producible_family_ids = $stmt_family->fetchAll(PDO::FETCH_COLUMN);

    if (empty($producible_family_ids)) {
        $response['success'] = true; // Still success, but no data
        $response['message'] = 'هیچ خانواده محصولی برای این دستگاه تعریف نشده است.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Step 2: Fetch parts belonging to the found families ---
    // Create placeholders for the IN clause (e.g., ?,?,?)
    $placeholders = implode(',', array_fill(0, count($producible_family_ids), '?'));

    $parts_sql = "SELECT p.PartID, p.PartName, pf.FamilyName
                  FROM tbl_parts p
                  JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
                  WHERE p.FamilyID IN ($placeholders)
                  ORDER BY pf.FamilyName, p.PartName"; // Order by family then part name

    $stmt_parts = $pdo->prepare($parts_sql);
    $stmt_parts->execute($producible_family_ids);
    $parts = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);

    if ($parts) {
        $response['success'] = true;
        $response['data'] = $parts;
    } else {
        $response['success'] = true; // Still success, but no data
        $response['message'] = 'هیچ قطعه‌ای در خانواده‌های مرتبط با این دستگاه یافت نشد.';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_producible_parts_for_rolling.php (Machine Based): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>

