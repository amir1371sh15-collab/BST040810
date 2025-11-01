<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers and DB

$response = ['success' => false, 'data' => [], 'message' => 'تاریخ مشخص نشده است.'];

// Basic permission check
if (!has_permission('production.assembly_hall.manage')) { // Or view permission if appropriate
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده تاریخچه را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$log_date_str = $_GET['log_date'] ?? null;

if (!$log_date_str) {
    http_response_code(400);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$log_date_gregorian = to_gregorian($log_date_str);
if (!$log_date_gregorian) {
    http_response_code(400);
    $response['message'] = 'فرمت تاریخ نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Find header for the date
    $header_stmt = $pdo->prepare("SELECT AssemblyHeaderID FROM tbl_assembly_log_header WHERE LogDate = ?");
    $header_stmt->execute([$log_date_gregorian]);
    $header_id = $header_stmt->fetchColumn();

    $entries = [];
    if ($header_id) {
        // Fetch entries if header exists
        $sql = "
            SELECT
                e.AssemblyEntryID,
                e.MachineID,
                e.Operator1ID,
                e.Operator2ID,
                e.StartTime,
                e.EndTime,
                e.PartID,
                e.ProductionKG,
                m.MachineName,
                p.PartName,
                op1.name as Operator1Name,
                op2.name as Operator2Name
            FROM tbl_assembly_log_entries e
            JOIN tbl_machines m ON e.MachineID = m.MachineID
            JOIN tbl_parts p ON e.PartID = p.PartID
            LEFT JOIN tbl_employees op1 ON e.Operator1ID = op1.EmployeeID
            LEFT JOIN tbl_employees op2 ON e.Operator2ID = op2.EmployeeID
            WHERE e.AssemblyHeaderID = ?
            ORDER BY e.AssemblyEntryID DESC"; // Order by ID or time if needed
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$header_id]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $response['success'] = true;
    $response['data'] = $entries;
    $response['message'] = count($entries) > 0 ? '' : 'رکوردی برای این تاریخ یافت نشد.';


} catch (Exception $e) {
    error_log("API Get Assembly History Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['message'] = 'خطای داخلی سرور در واکشی تاریخچه.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

