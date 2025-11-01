<?php
/**
 * API Endpoint to fetch details of a specific spare part order.
 *
 * This script connects to the database, validates the incoming order ID,
 * queries for the order details (including part, mold, and contractor info),
 * and returns the data as a JSON response.
 */

// --- 1. Set Headers ---
header('Content-Type: application/json; charset=utf-8');

// --- 2. Include Dependencies ---
require_once __DIR__ . '/../config/db.php';

// --- 3. Initialize Response Array ---
$response = [
    'success' => false,
    'message' => 'An unknown error occurred.',
    'data' => null
];

// --- 4. Validate Input ---
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $response['message'] = 'Invalid or missing Order ID provided.';
    echo json_encode($response);
    exit;
}
$orderId = (int)$_GET['order_id'];

// --- 5. Database Query ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.PartID, 
            p.PartName, 
            p.PartCode, 
            p.MoldID, 
            c.ContractorName 
        FROM tbl_spare_part_orders o
        INNER JOIN tbl_eng_spare_parts p ON o.PartID = p.PartID
        LEFT JOIN tbl_contractors c ON o.ContractorID = c.ContractorID
        WHERE o.OrderID = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- 6. Process Result ---
    if ($details) {
        $response['success'] = true;
        $response['message'] = 'Order details fetched successfully.';
        $response['data'] = [
            'PartID'         => (int)$details['PartID'],
            'PartName'       => $details['PartName'],
            'PartCode'       => $details['PartCode'],
            'MoldID'         => (int)$details['MoldID'],
            'ContractorName' => $details['ContractorName'] ?? 'پیمانکار تعیین نشده'
        ];
    } else {
        $response['message'] = 'Order with the provided ID was not found.';
    }

} catch (PDOException $e) {
    error_log("API Error in api_get_order_details.php: " . $e->getMessage());
    $response['message'] = 'A database error occurred. Please contact support.';
}

// --- 7. Send Final Response ---
echo json_encode($response, JSON_UNESCAPED_UNICODE);
