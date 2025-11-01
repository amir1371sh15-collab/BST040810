<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'weight_gr' => null, 'message' => ''];

if (!has_permission('planning.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

$part_id = filter_input(INPUT_GET, 'part_id', FILTER_VALIDATE_INT);

if (!$part_id) {
    http_response_code(400);
    $response['message'] = 'شناسه قطعه نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    // Get the *current* active weight
    $sql = "SELECT WeightGR FROM tbl_part_weights
            WHERE PartID = ? AND (EffectiveTo IS NULL OR EffectiveTo >= CURDATE())
            ORDER BY EffectiveFrom DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$part_id]);
    $weight = $stmt->fetchColumn();
    
    if ($weight !== false) {
        $response['success'] = true;
        $response['weight_gr'] = (float)$weight;
    } else {
        $response['message'] = 'وزن استانداردی برای این قطعه یافت نشد.';
    }

} catch (Exception $e) {
    error_log("API Get Part Base Weight Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
