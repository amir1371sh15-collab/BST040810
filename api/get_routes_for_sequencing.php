<?php
// api/get_routes_for_sequencing.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    if (!has_permission('base_info.manage')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.');
    }

    $family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);
    if (empty($family_id)) {
        throw new Exception('خانواده محصول (FamilyID) مشخص نشده است.');
    }

    $sql = "
        (SELECT
            r.RouteID as ID,
            'standard' as RouteType,
            r.StepNumber,
            s_from.StationName as FromStation,
            s_to.StationName as ToStation,
            ps.StatusName as OutputStatus
        FROM tbl_routes r
        JOIN tbl_stations s_from ON r.FromStationID = s_from.StationID
        JOIN tbl_stations s_to ON r.ToStationID = s_to.StationID
        LEFT JOIN tbl_part_statuses ps ON r.NewStatusID = ps.StatusID
        WHERE r.FamilyID = ?)
        UNION ALL
        (SELECT
            ro.OverrideID as ID,
            'override' as RouteType,
            ro.StepNumber,
            s_from.StationName as FromStation,
            s_to.StationName as ToStation,
            ps.StatusName as OutputStatus
        FROM tbl_route_overrides ro
        JOIN tbl_stations s_from ON ro.FromStationID = s_from.StationID
        JOIN tbl_stations s_to ON ro.ToStationID = s_to.StationID
        LEFT JOIN tbl_part_statuses ps ON ro.OutputStatusID = ps.StatusID
        WHERE ro.FamilyID = ? AND ro.IsActive = 1)
        ORDER BY StepNumber, FromStation, ToStation
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$family_id, $family_id]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $routes;

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

