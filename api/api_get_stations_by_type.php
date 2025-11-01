<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php'; // Includes PDO and helpers

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!has_permission('planning.view') && !has_permission('base_info.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این اطلاعات را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    // Fetch relevant stations (Warehouse and Production)
    $sql = "SELECT StationID, StationName, StationType
            FROM tbl_stations 
            WHERE StationType IN ('Warehouse', 'Production')
            ORDER BY StationType, StationName";
    
    $stations_raw = find_all($pdo, $sql);
    
    // Group by type for <optgroup>
    $grouped_stations = [];
    foreach ($stations_raw as $station) {
        $type = $station['StationType'] == 'Warehouse' ? 'انبارها' : 'ایستگاه‌های تولیدی';
        if (!isset($grouped_stations[$type])) {
            $grouped_stations[$type] = [];
        }
        $grouped_stations[$type][] = [
            'StationID' => $station['StationID'],
            'StationName' => $station['StationName']
        ];
    }

    $response['success'] = true;
    $response['data'] = $grouped_stations;

} catch (Exception $e) {
    error_log("API Get Stations By Type Error: " . $e->getMessage());
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
