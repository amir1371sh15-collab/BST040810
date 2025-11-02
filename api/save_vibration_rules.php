<?php
// api/save_vibration_rules.php
require_once __DIR__ . '/../config/init.php';

// Set header to JSON
header('Content-Type: application/json; charset=utf-8');

// Initialize response array
$response = ['success' => false, 'message' => 'خطای ناشناخته.'];

try {
    // 1. Check permissions
    if (!has_permission('planning_constraints.manage')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    // 2. Check HTTP Method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('متد درخواست نامعتبر است.', 405);
    }

    // 3. Get and validate Primary Part ID
    $primary_part_id = filter_input(INPUT_POST, 'primary_part_id', FILTER_VALIDATE_INT);
    if (empty($primary_part_id)) {
        throw new Exception('شناسه قطعه اصلی نامعتبر است.');
    }

    // 4. Get the list of incompatible parts from the form
    // If 'incompatible' is not set, it means all checkboxes were unchecked, which is valid.
    $incompatible_parts = $_POST['incompatible'] ?? [];
    
    // Ensure all values are integers
    $incompatible_part_ids = array_map('intval', array_keys($incompatible_parts));

    // 5. Database Transaction
    $pdo->beginTransaction();

    // 5a. Delete all existing rules for this primary part
    $stmt_delete = $pdo->prepare("DELETE FROM tbl_planning_vibration_incompatibility WHERE PrimaryPartID = ? OR IncompatiblePartID = ?");
    $stmt_delete->execute([$primary_part_id, $primary_part_id]);

    // 5b. Insert new rules
    if (!empty($incompatible_part_ids)) {
        $sql_insert = "INSERT INTO tbl_planning_vibration_incompatibility (PrimaryPartID, IncompatiblePartID) VALUES (?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);
        
        $sql_insert_reverse = "INSERT INTO tbl_planning_vibration_incompatibility (PrimaryPartID, IncompatiblePartID) VALUES (?, ?)";
        $stmt_insert_reverse = $pdo->prepare($sql_insert_reverse);

        foreach ($incompatible_part_ids as $incomp_id) {
            if ($incomp_id > 0) {
                // Insert (A -> B)
                $stmt_insert->execute([$primary_part_id, $incomp_id]);
                // Insert (B -> A)
                $stmt_insert_reverse->execute([$incomp_id, $primary_part_id]);
            }
        }
    }

    // 5c. Commit transaction
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'قوانین ناسازگاری ویبره با موفقیت ذخیره شد.';

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Set appropriate HTTP status code
    $code = ($e->getCode() >= 400) ? $e->getCode() : 500;
    http_response_code($code); 
    
    $response['message'] = $e->getMessage();
}

// 6. Echo final JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);

