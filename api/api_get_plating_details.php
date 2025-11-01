<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers like to_jalali

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($_GET['header_id']) || !is_numeric($_GET['header_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه گزارش نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$headerId = (int)$_GET['header_id'];

try {
    // 1. Get Header Info
    $header_stmt = $pdo->prepare("SELECT * FROM tbl_plating_log_header WHERE PlatingHeaderID = ?");
    $header_stmt->execute([$headerId]);
    $header = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        http_response_code(404);
        $response['message'] = 'گزارش یافت نشد.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Get Shift Details
    $shifts_stmt = $pdo->prepare("
        SELECT s.StartTime, s.EndTime, e.name as EmployeeName 
        FROM tbl_plating_log_shifts s
        JOIN tbl_employees e ON s.EmployeeID = e.EmployeeID
        WHERE s.PlatingHeaderID = ?
        ORDER BY e.name
    ");
    $shifts_stmt->execute([$headerId]);
    $shifts = $shifts_stmt->fetchAll(PDO::FETCH_ASSOC);
    // Format times
    foreach ($shifts as &$shift) {
        $shift['StartTimeFmt'] = $shift['StartTime'] ? date('H:i', strtotime($shift['StartTime'])) : null;
        $shift['EndTimeFmt'] = $shift['EndTime'] ? date('H:i', strtotime($shift['EndTime'])) : null;
    }
    unset($shift); // Unset reference

    // 3. Get Production Details
    $prod_stmt = $pdo->prepare("
        SELECT d.WashedKG, d.PlatedKG, d.ReworkedKG, p.PartName 
        FROM tbl_plating_log_details d
        JOIN tbl_parts p ON d.PartID = p.PartID
        WHERE d.PlatingHeaderID = ?
        ORDER BY p.PartName
    ");
    $prod_stmt->execute([$headerId]);
    $production = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get Chemical Addition Details (Corrected JOIN)
    $chem_stmt = $pdo->prepare("
        SELECT v.VatName, a.Quantity, a.Unit, c.ChemicalName
        FROM tbl_plating_log_additions a
        JOIN tbl_chemicals c ON a.ChemicalID = c.ChemicalID
        LEFT JOIN tbl_plating_vats v ON a.VatID = v.VatID -- Join using VatID
        WHERE a.PlatingHeaderID = ?
        ORDER BY v.VatName, c.ChemicalName
    ");
    $chem_stmt->execute([$headerId]);
    $additions = $chem_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Combine data and format
    $response['success'] = true;
    $response['data'] = [
        'PlatingHeaderID' => $header['PlatingHeaderID'],
        'LogDate' => $header['LogDate'],
        'LogDateJalali' => to_jalali($header['LogDate']),
        'NumberOfBarrels' => $header['NumberOfBarrels'],
        'Description' => $header['Description'],
        'shifts' => $shifts,
        'production' => $production,
        'additions' => $additions
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); 

} catch (PDOException $e) {
    error_log("API Error in api_get_plating_details.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
     error_log("General Error in api_get_plating_details.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

