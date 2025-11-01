<?php
require_once __DIR__ . '/../config/init.php';

// Station IDs Constants
const PACKAGING_STATION_ID = 10;
const CARTON_WAREHOUSE_STATION_ID = 11;
const CUSTOMER_STATION_ID = 13;

function get_base_weight_gr(PDO $pdo, int $part_id, string $transaction_date_gregorian): ?float
{
    $sql = "SELECT WeightGR FROM tbl_part_weights
            WHERE PartID = ? AND EffectiveFrom <= ? AND (EffectiveTo IS NULL OR EffectiveTo >= ?)
            ORDER BY EffectiveFrom DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$part_id, $transaction_date_gregorian, $transaction_date_gregorian]);
    $result = $stmt->fetchColumn();
    error_log("BaseWeightGR for PartID $part_id on $transaction_date_gregorian: " . ($result !== false ? $result : 'NULL'));
    return ($result !== false) ? (float)$result : null;
}

function get_weight_change_percent(PDO $pdo, int $part_id, int $from_station_id, int $to_station_id, string $transaction_date_gregorian): ?float
{
     $sql = "SELECT WeightChangePercent FROM tbl_process_weight_changes
            WHERE PartID = ? AND FromStationID = ? AND ToStationID = ? AND EffectiveFrom <= ? AND (EffectiveTo IS NULL OR EffectiveTo >= ?)
            ORDER BY EffectiveFrom DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$part_id, $from_station_id, $to_station_id, $transaction_date_gregorian, $transaction_date_gregorian]);
    $result = $stmt->fetchColumn();
    error_log("WeightChangePercent for PartID $part_id ($from_station_id -> $to_station_id) on $transaction_date_gregorian: " . ($result !== false ? $result : 'NULL'));
     return ($result !== false) ? (float)$result : null;
}

function get_standard_route(PDO $pdo, ?int $family_id, int $from_station_id, int $to_station_id): ?array
{
     if ($family_id === null) return null; // Cannot find route without family
     // Select NewStatusID instead of NewStatus
     $sql = "SELECT *, IsFinalStage FROM tbl_routes WHERE FamilyID = ? AND FromStationID = ? AND ToStationID = ? LIMIT 1";
     $stmt = $pdo->prepare($sql);
     $stmt->execute([$family_id, $from_station_id, $to_station_id]);
     $route = $stmt->fetch(PDO::FETCH_ASSOC);
     error_log("Standard route check for FamilyID $family_id ($from_station_id -> $to_station_id): " . ($route ? "Found (StatusID: {$route['NewStatusID']})" : 'Not Found'));
     return $route ?: null;
}

function find_active_route_override(PDO $pdo, ?int $family_id, int $from_station_id, int $to_station_id): ?array
{
    if ($family_id === null) return null; // Cannot find override without family
     // Select OutputStatusID instead of OutputStatus
     $sql = "SELECT * FROM tbl_route_overrides WHERE FamilyID = ? AND FromStationID = ? AND ToStationID = ? AND IsActive = 1 LIMIT 1";
     $stmt = $pdo->prepare($sql);
     $stmt->execute([$family_id, $from_station_id, $to_station_id]);
     $override = $stmt->fetch(PDO::FETCH_ASSOC);
     error_log("Active override check for FamilyID $family_id ($from_station_id -> $to_station_id): " . ($override ? "Found (StatusID: {$override['OutputStatusID']}, DevID: {$override['DeviationID']})" : 'Not Found'));
     return $override ?: null;
}


function get_relevant_stations_for_family(PDO $pdo, int $family_id): array {
    $station_ids = [];
    error_log("Fetching relevant stations for FamilyID: $family_id");

    // Fetch stations used in standard routes for this family
    $sql_routes = "SELECT DISTINCT FromStationID, ToStationID FROM tbl_routes WHERE FamilyID = ?";
    $stmt_routes = $pdo->prepare($sql_routes);
    $stmt_routes->execute([$family_id]);
    while ($row = $stmt_routes->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['FromStationID'])) $station_ids[] = (int)$row['FromStationID'];
        if (!empty($row['ToStationID'])) $station_ids[] = (int)$row['ToStationID'];
    }
    error_log("Stations from standard routes: " . implode(', ', array_unique($station_ids)));

    // Fetch stations used in active overrides for this family
    $sql_overrides = "SELECT DISTINCT FromStationID, ToStationID FROM tbl_route_overrides WHERE FamilyID = ? AND IsActive = 1";
    $stmt_overrides = $pdo->prepare($sql_overrides);
    $stmt_overrides->execute([$family_id]);
    while ($row = $stmt_overrides->fetch(PDO::FETCH_ASSOC)) {
         if (!empty($row['FromStationID'])) $station_ids[] = (int)$row['FromStationID'];
         if (!empty($row['ToStationID'])) $station_ids[] = (int)$row['ToStationID'];
    }
     error_log("Stations after adding overrides: " . implode(', ', array_unique($station_ids)));

    // Add Carton Warehouse, Packaging, and Customer stations explicitly
    if (!in_array(PACKAGING_STATION_ID, $station_ids)) $station_ids[] = PACKAGING_STATION_ID;
    if (!in_array(CARTON_WAREHOUSE_STATION_ID, $station_ids)) $station_ids[] = CARTON_WAREHOUSE_STATION_ID;
    if (!in_array(CUSTOMER_STATION_ID, $station_ids)) $station_ids[] = CUSTOMER_STATION_ID;

    $unique_station_ids = array_values(array_unique($station_ids));
    error_log("Final unique relevant stations for FamilyID $family_id (standard + overrides + packaging logic): " . implode(', ', $unique_station_ids));
    return $unique_station_ids;
}

/**
 * Calculates details like weight change and route status.
 * Added $is_carton_transaction flag to skip weight calculations.
 */
function calculate_details(PDO $pdo, int $part_id, int $from_station_id, int $to_station_id, string $transaction_date_jalali, bool $is_carton_transaction = false): array
{
    $details = [
        'is_carton_transaction' => $is_carton_transaction,
        'base_weight_gr' => null,
        'change_percent' => 0.0,
        'final_weight_gr' => null,
        'StatusAfterID' => null, // Will hold the determined status ID (can be null)
        'route_status' => null,
        'route_status_display' => null,
        'deviation_id' => null,
        'needs_selection' => false,
        'possible_statuses' => [], // Ensure it's always an array
        'error' => null
    ];
    error_log("Calculating details for PartID $part_id ($from_station_id -> $to_station_id) on $transaction_date_jalali. Is Carton: " . ($is_carton_transaction ? 'Yes' : 'No'));

    $part = find_by_id($pdo, 'tbl_parts', $part_id, 'PartID');
    if (!$part) {
        $details['error'] = 'قطعه انتخاب شده یافت نشد.';
        error_log($details['error']);
        return $details;
    }
    $family_id = $part['FamilyID'];

    $transaction_date_gregorian = to_gregorian($transaction_date_jalali);
    if (!$transaction_date_gregorian) {
         $details['error'] = 'فرمت تاریخ نامعتبر است.';
         error_log($details['error']);
         return $details;
    }

    // --- Skip weight calculations if it's a carton transaction ---
    if (!$is_carton_transaction) {
        $details['base_weight_gr'] = get_base_weight_gr($pdo, $part_id, $transaction_date_gregorian);
        if ($details['base_weight_gr'] === null) {
            $details['error'] = 'وزن پایه (gr) برای این قطعه در تاریخ انتخاب شده یافت نشد.';
             error_log($details['error']);
        }

        $details['change_percent'] = get_weight_change_percent($pdo, $part_id, $from_station_id, $to_station_id, $transaction_date_gregorian) ?? 0.0;

        if ($details['base_weight_gr'] !== null) {
            $details['final_weight_gr'] = $details['base_weight_gr'] * (1 + ($details['change_percent'] / 100.0));
            error_log("Final Weight GR calculated: {$details['final_weight_gr']} (Base GR: {$details['base_weight_gr']}, Change: {$details['change_percent']}%)");
        } else {
            $details['final_weight_gr'] = null;
            error_log("Final Weight GR cannot be calculated because BaseWeightGR is null.");
        }
    } else {
        $details['base_weight_gr'] = null;
        $details['change_percent'] = null;
        $details['final_weight_gr'] = null;
        error_log("Skipping weight calculation for carton transaction.");
    }

    // Route and Status logic
    $standardRoute = get_standard_route($pdo, $family_id, $from_station_id, $to_station_id);
    $activeOverride = find_active_route_override($pdo, $family_id, $from_station_id, $to_station_id);

    // Get Status IDs (can be null)
    $standardStatusId = isset($standardRoute['NewStatusID']) ? $standardRoute['NewStatusID'] : null;
    $overrideStatusId = isset($activeOverride['OutputStatusID']) ? $activeOverride['OutputStatusID'] : null;

    // --- Corrected Logic for Status ID determination ---
    if ($standardRoute && $activeOverride) {
        // Both standard and override exist.
        $possibleIds = [];
        // Check if standard status is not null before adding
        if ($standardStatusId !== null) $possibleIds[] = (int)$standardStatusId;
        // Check if override status is not null before adding
        if ($overrideStatusId !== null) $possibleIds[] = (int)$overrideStatusId;
        $possibleIds = array_values(array_unique($possibleIds)); // Unique, non-null integer IDs

        error_log("Ambiguous route detected. Possible StatusIDs: " . implode(', ', $possibleIds));

         if (count($possibleIds) > 1) {
            // Different non-null statuses found, require selection
            $details['route_status'] = 'RequiresSelection';
            $details['route_status_display'] = 'انتخاب وضعیت الزامی است';
            $details['possible_statuses'] = $possibleIds; // Return array of valid integer IDs
            $details['needs_selection'] = true;
            $details['StatusAfterID'] = null; // Needs selection
            $details['deviation_id'] = null;
            error_log("Setting needs_selection = true. Possible IDs: " . implode(', ', $possibleIds));
         } elseif (count($possibleIds) == 1) {
             // Both routes lead to the same status ID (or one is null, the other is valid)
             $details['StatusAfterID'] = $possibleIds[0]; // The single valid ID
             // If override defined this status, consider it approved non-standard
             if ($overrideStatusId == $details['StatusAfterID']) {
                 $details['route_status'] = 'NonStandardApproved';
                 $details['route_status_display'] = 'مجاز (غیراستاندارد)';
                 $details['deviation_id'] = $activeOverride['DeviationID'];
                 error_log("Ambiguous resolved (Override): StatusID '{$details['StatusAfterID']}', DevID {$details['deviation_id']}.");
             } else { // Standard route defined this status
                 $details['route_status'] = 'Standard';
                 $details['route_status_display'] = 'استاندارد';
                 $details['deviation_id'] = null; // Standard has no deviation ID
                 error_log("Ambiguous resolved (Standard): StatusID '{$details['StatusAfterID']}'.");
             }
         } else { // Both routes have NULL status ID
              $details['route_status'] = 'Standard'; // Treat as standard if both are NULL
              $details['route_status_display'] = 'استاندارد (بدون وضعیت)';
              $details['StatusAfterID'] = null; // Status is NULL
              $details['deviation_id'] = null;
              error_log('Both standard and override routes exist but have NULL status IDs. Treating as Standard NULL.');
         }
    } elseif ($standardRoute) {
        $details['StatusAfterID'] = $standardStatusId; // Assign null if it's null in DB
        $details['route_status'] = 'Standard';
        $details['route_status_display'] = 'استاندارد' . ($standardStatusId === null ? ' (بدون وضعیت)' : '');
        $details['deviation_id'] = null;
        error_log("Standard route found. StatusAfterID: " . ($details['StatusAfterID'] ?? 'NULL'));
    } elseif ($activeOverride) {
        $details['StatusAfterID'] = $overrideStatusId; // Assign null if it's null in DB
        $details['route_status'] = 'NonStandardApproved';
        $details['route_status_display'] = 'مجاز (غیراستاندارد)' . ($overrideStatusId === null ? ' (بدون وضعیت)' : '');
        $details['deviation_id'] = $activeOverride['DeviationID'];
        error_log("Override route found. StatusAfterID: " . ($details['StatusAfterID'] ?? 'NULL') . ", DeviationID: {$details['deviation_id']}");
    } else {
        // No standard or active override route found
        $details['StatusAfterID'] = null;
        $details['route_status'] = 'NonStandardPending';
        $details['route_status_display'] = 'غیراستاندارد (در انتظار)';
        $details['deviation_id'] = null;
        $details['error'] = ($details['error'] ? $details['error'] . ' ' : '') . 'مسیر استاندارد یا مجاز برای این انتقال یافت نشد.'; // Add specific error
        error_log("No standard or override route found. Status: NonStandardPending");
    }

    // Fetch Status Names if needed
    if ($details['needs_selection'] && !empty($details['possible_statuses'])) {
        $placeholders = implode(',', array_fill(0, count($details['possible_statuses']), '?'));
        // Corrected query to prevent error if possible_statuses is empty
        if (!empty($placeholders)) {
            $statusNames = find_all($pdo, "SELECT StatusID, StatusName FROM tbl_part_statuses WHERE StatusID IN ($placeholders)", $details['possible_statuses']);
            $details['possible_status_names'] = array_column($statusNames, 'StatusName', 'StatusID'); // Map ID => Name
            error_log("Possible status names: " . json_encode($details['possible_status_names']));
        } else {
             $details['possible_status_names'] = []; // Ensure it's an array
        }
    }
    // Also fetch status name if determined directly (and not null)
    elseif ($details['StatusAfterID'] !== null) {
        $statusInfo = find_by_id($pdo, 'tbl_part_statuses', $details['StatusAfterID'], 'StatusID');
        if ($statusInfo) {
            $details['possible_status_names'] = [$details['StatusAfterID'] => $statusInfo['StatusName']];
            error_log("Determined status name: " . $statusInfo['StatusName']);
        } else {
             error_log("WARNING: Determined StatusAfterID {$details['StatusAfterID']} not found in tbl_part_statuses.");
             $details['error'] = ($details['error'] ? $details['error'] . ' ' : '') . "خطا: وضعیت خروجی با شناسه {$details['StatusAfterID']} یافت نشد.";
             if ($details['route_status'] != 'RequiresSelection') {
                 $details['route_status_display'] = 'خطا در وضعیت خروجی';
             }
        }
    } elseif ($details['StatusAfterID'] === null && $details['route_status'] !== 'RequiresSelection') {
        // If status is explicitly NULL and selection isn't needed
        $details['possible_status_names'] = ['NULL' => '-- بدون وضعیت --']; // Represent NULL status
        error_log("Determined status is NULL.");
    }

    return $details;
}
?>

