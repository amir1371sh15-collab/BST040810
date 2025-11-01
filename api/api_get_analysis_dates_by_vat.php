<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Use init for helpers like to_jalali

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_GET['vat_id']) || !is_numeric($_GET['vat_id'])) {
    http_response_code(400);
    $response['message'] = 'شناسه وان نامعتبر است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$vatId = (int)$_GET['vat_id'];

try {
    $sql = "SELECT AnalysisID, AnalysisDate 
            FROM tbl_plating_vat_analysis 
            WHERE VatID = ? 
            ORDER BY AnalysisDate DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vatId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates to Jalali for display
    $formatted_results = [];
    foreach ($results as $row) {
        $formatted_results[] = [
            'AnalysisID' => $row['AnalysisID'],
            'AnalysisDateJalali' => to_jalali($row['AnalysisDate'])
        ];
    }

    $response['success'] = true;
    $response['data'] = $formatted_results;
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("API Error in api_get_analysis_dates_by_vat.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده رخ داده است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
