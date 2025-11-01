<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

$response = ['success' => false, 'data' => null, 'message' => '', 'pagination' => null];
const RECORDS_PER_PAGE = 15; // Define how many records per page

if (!has_permission('warehouse.view')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز مشاهده تاریخچه را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}

try {
    $snapshot_id = filter_input(INPUT_GET, 'snapshot_id', FILTER_VALIDATE_INT) ?: null;

    if ($snapshot_id) {
        // Fetch details for a single snapshot
        $sql = "SELECT s.*, u.Username as RecordedByUsername, 
                       pf.FamilyName as FilterFamilyName, 
                       p.PartName as FilterPartName,
                       ps.StatusName as FilterStatusName
                FROM tbl_inventory_snapshots s
                LEFT JOIN tbl_users u ON s.RecordedByUserID = u.UserID
                LEFT JOIN tbl_part_families pf ON s.FilterFamilyID = pf.FamilyID
                LEFT JOIN tbl_parts p ON s.FilterPartID = p.PartID
                LEFT JOIN tbl_part_statuses ps ON s.FilterStatusID = ps.StatusID
                WHERE s.SnapshotID = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$snapshot_id]);
        $snapshot_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($snapshot_details) {
             $snapshot_details['SnapshotTimestampJalali'] = to_jalali($snapshot_details['SnapshotTimestamp']);
             // Handle null status name explicitly
             if ($snapshot_details['FilterStatusID'] === null && $snapshot_details['FilterStatusName'] === null) {
                 $snapshot_details['FilterStatusName'] = '-- بدون وضعیت --';
             }
            $response['success'] = true;
            $response['data'] = $snapshot_details;
        } else {
             $response['message'] = 'عکس لحظه‌ای مورد نظر یافت نشد.';
        }

    } else {
        // Fetch history list with pagination
        $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        if ($current_page < 1) $current_page = 1;
        $offset = ($current_page - 1) * RECORDS_PER_PAGE;

        // Get total count first
        $total_records = $pdo->query("SELECT COUNT(*) FROM tbl_inventory_snapshots")->fetchColumn();
        $total_pages = $total_records ? ceil($total_records / RECORDS_PER_PAGE) : 1;

        $sql = "SELECT s.SnapshotID, s.SnapshotTimestamp, s.FilterStatusID,
                       u.Username as RecordedByUsername, 
                       pf.FamilyName as FilterFamilyName, 
                       p.PartName as FilterPartName,
                       ps.StatusName as FilterStatusName
                FROM tbl_inventory_snapshots s
                LEFT JOIN tbl_users u ON s.RecordedByUserID = u.UserID
                LEFT JOIN tbl_part_families pf ON s.FilterFamilyID = pf.FamilyID
                LEFT JOIN tbl_parts p ON s.FilterPartID = p.PartID
                LEFT JOIN tbl_part_statuses ps ON s.FilterStatusID = ps.StatusID
                ORDER BY s.SnapshotTimestamp DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', RECORDS_PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($history as &$item) {
             $item['SnapshotTimestampJalali'] = to_jalali($item['SnapshotTimestamp']);
             // Handle null status name
             if ($item['FilterStatusID'] === null && $item['FilterStatusName'] === null) {
                 $item['FilterStatusName'] = '-- بدون وضعیت --';
             } elseif ($item['FilterStatusName'] === null) {
                 // Handle case where status might be deleted but ID exists
                 $item['FilterStatusName'] = 'نامشخص (ID: ' . $item['FilterStatusID'] . ')';
             }
        }
        unset($item);

        $response['success'] = true;
        $response['data'] = $history;
        $response['pagination'] = [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total_records' => (int)$total_records
        ];
    }

} catch (Exception $e) {
    error_log("API Get Snapshot History Error: " . $e->getMessage() . " | Input: " . print_r($_GET, true));
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

