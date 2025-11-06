<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

// [!!!] تابع کمکی برای ایجاد GUID
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

$input = json_decode(file_get_contents("php://input"), true);
$response = ['success' => false, 'message' => '', 'data' => ['work_order_count' => 0]];

$planning_date_jalali = $input['planning_date_jalali'] ?? null;
$station_id = (int)($input['station_id'] ?? 0);
$batches = $input['batches'] ?? []; // For Plating
$parts = $input['parts'] ?? [];   // For Washing/Rework

if (empty($planning_date_jalali)) {
    echo json_encode(['success' => false, 'message' => 'تاریخ برنامه‌ریزی ارسال نشده است.']);
    exit;
}
if (!in_array($station_id, [1, 4, 7])) { // 1:Wash, 4:Plating, 7:Rework
    echo json_encode(['success' => false, 'message' => 'ایستگاه (StationID) نامعتبر است.']);
    exit;
}

$planning_date_gregorian = to_gregorian($planning_date_jalali);
if (!$planning_date_gregorian) {
    echo json_encode(['success' => false, 'message' => 'فرمت تاریخ نامعتبر است.']);
    exit;
}

$pdo->beginTransaction();
try {
    $work_order_count = 0;
    
    // [!!!] کوئری با BatchGUID به‌روز شد
    $stmt_insert = $pdo->prepare("
        INSERT INTO tbl_planning_work_orders 
            (StationID, PartID, RequiredStatusID, TargetStatusID, Quantity, Unit, AuxQuantity, AuxUnit, PlannedDate, CreationDate, Priority, Status, MachineID, BatchGUID)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, 'Generated', NULL, ?)
    ");
    
    // کوئری کمکی برای یافتن وضعیت خروجی (TargetStatusID)
    $stmt_route = $pdo->prepare("
        SELECT r.NewStatusID, p.FamilyID
        FROM tbl_parts p
        LEFT JOIN tbl_routes r ON p.FamilyID = r.FamilyID 
                             AND r.FromStationID = ?
                             AND (r.RequiredStatusID = ? OR (r.RequiredStatusID IS NULL AND ? IS NULL))
        WHERE p.PartID = ?
        ORDER BY r.StepNumber ASC
        LIMIT 1
    ");

    // --- منطق بر اساس نوع ایستگاه ---

    if ($station_id === 4) { // حالت آبکاری (پیچیده)
        if (empty($batches)) {
            throw new Exception('لیست بچ‌ها برای آبکاری خالی است.');
        }
        
        foreach ($batches as $batch_info) {
            $batch = $batch_info['batch_details'];
            $planned_barrels = (float)$batch_info['planned_barrels'];
            
            if ($planned_barrels <= 0) continue;
            
            // [!!!] ایجاد یک شناسه واحد برای تمام قطعات این بچ
            $batch_guid = generate_uuid(); 

            foreach ($batch['parts'] as $part) {
                $part_id = (int)$part['part_id'];
                $weight_per_barrel = (float)$part['weight'];
                $total_quantity_kg = $weight_per_barrel * $planned_barrels;
                
                $required_status_id = $part['current_status_id'] ?? null; 
                
                // یافتن وضعیت خروجی (TargetStatusID)
                $stmt_route->execute([$station_id, $required_status_id, $required_status_id, $part_id]);
                $route_info = $stmt_route->fetch(PDO::FETCH_ASSOC);
                $target_status_id = $route_info ? (int)$route_info['NewStatusID'] : null;

                $stmt_insert->execute([
                    $station_id,
                    $part_id,
                    $required_status_id,
                    $target_status_id,
                    $total_quantity_kg,  // Quantity = مجموع KG
                    'KG',                 // Unit = KG
                    $planned_barrels,    // AuxQuantity = تعداد بارل
                    'بارل',              // AuxUnit = بارل
                    $planning_date_gregorian,
                    $batch_guid          // [!!!] شناسه بچ
                ]);
                $work_order_count++;
            } // end inner foreach
        } // end outer foreach

    } else { // حالت شستشو (۱) یا دوباره کاری (۷) (ساده)
        if (empty($parts)) {
            throw new Exception('لیست قطعات برای این عملیات خالی است.');
        }

        foreach ($parts as $part) {
            $part_id = (int)$part['part_id'];
            $quantity = (float)$part['quantity'];
            
            if ($quantity <= 0) continue;
            
            $required_status_id = $part['current_status_id'] ?? null; 
            
            // یافتن وضعیت خروجی (TargetStatusID)
            $stmt_route->execute([$station_id, $required_status_id, $required_status_id, $part_id]);
            $route_info = $stmt_route->fetch(PDO::FETCH_ASSOC);
            $target_status_id = $route_info ? (int)$route_info['NewStatusID'] : null;

            $stmt_insert->execute([
                $station_id,
                $part_id,
                $required_status_id,
                $target_status_id,
                $quantity,           // Quantity = مجموع KG
                'KG',                // Unit = KG
                NULL,                // AuxQuantity = NULL
                NULL,                // AuxUnit = NULL
                $planning_date_gregorian,
                NULL                 // BatchGUID = NULL
            ]);
            $work_order_count++;
        } // end foreach
    } // end else

    if ($work_order_count === 0) {
        throw new Exception('هیچ دستور کاری برای ایجاد وجود نداشت (مقادیر صفر بودند).');
    }
    
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = "تعداد $work_order_count دستور کار با موفقیت ایجاد شد.";
    $response['data']['work_order_count'] = $work_order_count;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Save Plating Schedule Error: " . $e->getMessage() . " | Input: " . json_encode($input));
    $response['message'] = 'خطا در نهایی‌سازی برنامه: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

