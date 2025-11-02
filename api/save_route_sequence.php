<?php
// api/save_route_sequence.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];
$pdo->beginTransaction();

try {
    if (!has_permission('base_info.manage')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.');
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $sequence = $input['sequence'] ?? [];

    if (empty($sequence)) {
        throw new Exception('هیچ ترتیبی برای ذخیره ارسال نشده است.');
    }

    $stmt_route = $pdo->prepare("UPDATE tbl_routes SET StepNumber = ? WHERE RouteID = ?");
    $stmt_override = $pdo->prepare("UPDATE tbl_route_overrides SET StepNumber = ? WHERE OverrideID = ?");

    foreach ($sequence as $index => $item) {
        $step_number = $index + 1; // Step numbers start from 1
        $id = $item['id'];
        $type = $item['type'];

        if ($type == 'standard') {
            $stmt_route->execute([$step_number, $id]);
        } elseif ($type == 'override') {
            $stmt_override->execute([$step_number, $id]);
        }
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'ترتیب مسیرها با موفقیت ذخیره شد.';

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

