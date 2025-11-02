<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر است.', 405);
    }
    if (!has_permission('planning_constraints.manage')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    $primary_part_id = filter_input(INPUT_POST, 'primary_part_id', FILTER_VALIDATE_INT);
    if (empty($primary_part_id)) {
        throw new Exception('قطعه اصلی مشخص نشده است.');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // 1. Update Solo Weight
    $solo_weight = filter_input(INPUT_POST, 'barrel_weight_solo', FILTER_VALIDATE_FLOAT);
    // Use null if input is empty or invalid
    $solo_weight_db = ($solo_weight !== false && $solo_weight > 0) ? $solo_weight : null;
    
    $stmt = $pdo->prepare("UPDATE tbl_parts SET BarrelWeight_Solo_KG = ? WHERE PartID = ?");
    $stmt->execute([$solo_weight_db, $primary_part_id]);

    // 2. Clear old compatibility rules for this part
    $stmt = $pdo->prepare("DELETE FROM tbl_planning_batch_compatibility WHERE PrimaryPartID = ? OR CompatiblePartID = ?");
    $stmt->execute([$primary_part_id, $primary_part_id]);

    // 3. Insert new compatibility rules
    if (isset($_POST['compatible']) && is_array($_POST['compatible'])) {
        $insert_sql = "INSERT INTO tbl_planning_batch_compatibility 
                       (PrimaryPartID, CompatiblePartID, PrimaryPartWeight_KG, CompatiblePartWeight_KG) 
                       VALUES (?, ?, ?, ?)";
        
        $stmt_insert = $pdo->prepare($insert_sql);

        foreach ($_POST['compatible'] as $comp_part_id => $data) {
            // Check if the 'enabled' checkbox was ticked for this part
            if (!isset($data['enabled'])) {
                continue; // This part was not checked, skip it
            }

            $comp_part_id = (int)$comp_part_id;
            $p_weight = filter_var($data['primary_weight'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $c_weight = filter_var($data['compatible_weight'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

            if (empty($p_weight) || empty($c_weight)) {
                // If weights are missing for a checked item, skip it (or throw error)
                continue; 
            }

            // Insert the A -> B relationship
            $stmt_insert->execute([$primary_part_id, $comp_part_id, $p_weight, $c_weight]);
            
            // Insert the B -> A (reverse) relationship
            $stmt_insert->execute([$comp_part_id, $primary_part_id, $c_weight, $p_weight]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    $response['success'] = true;
    $response['message'] = 'قوانین آبکاری با موفقیت ذخیره شد.';
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = 'success';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code($e->getCode() > 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>

