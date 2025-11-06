<?php
// api/calculate_next_step.php
// هدف: جمع‌آوری داده‌های کمکی برای انتخاب دستی ایستگاه و دستگاه توسط برنامه‌ریز.
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// --- (Constants) ---
const PRESS_STATION_ID = 2; // شناسه پرسکاری
const SCREW_STATION_ID = 6; // شناسه پیچ سازی

// --- (Helper Functions) ---

/**
 * Gets all available machines for a specific station type (e.g., Press or Screw).
 * @param PDO $pdo
 * @param int $station_id
 * @return array{MachineID: int, MachineName: string}[]
 */
if (!function_exists('get_available_machines')) {
    function get_available_machines($pdo, $station_id) {
        $machines = find_all($pdo, 
            "SELECT MachineID, MachineName FROM tbl_machines WHERE StationID = ? AND IsActive = 1", 
            [$station_id]
        );
        return $machines;
    }
}

/**
 * Gets a list of all active production stations for manual selection.
 * @param PDO $pdo
 * @return array{StationID: int, StationName: string}[]
 */
if (!function_exists('get_all_production_stations')) {
    function get_all_production_stations($pdo) {
        // فرض می‌کنیم ایستگاه‌های تولیدی دارای StationType معین هستند یا ID > 0 دارند.
        $stations = find_all($pdo, 
            "SELECT StationID, StationName FROM tbl_stations WHERE StationType = 'Production' AND StationID NOT IN (8, 9) AND IsActive = 1 ORDER BY StationID ASC"
        );
        // اگر StationType وجود نداشت، می‌توانید فقط ایستگاه‌هایی را که نباید انبار باشند، انتخاب کنید.
        if (empty($stations)) {
             $stations = find_all($pdo, 
                "SELECT StationID, StationName FROM tbl_stations WHERE StationID NOT IN (8, 9) AND StationID > 0 ORDER BY StationID ASC"
            );
        }
        return $stations;
    }
}

/**
 * Placeholder/Simple implementation of constraint checking.
 */
if (!function_exists('check_production_constraints')) {
    function check_production_constraints($pdo, $part_ids) {
        $warnings = [];
        if (count(array_unique($part_ids)) > 1) { 
            $warnings[] = "توصیه: چند قطعه مختلف برای برنامه‌ریزی انتخاب شده است. از سازگاری بچ (آبکاری/ویبره) مطمئن شوید.";
        }
        
        // Add a specific warning for contamination
        // ... (Vibration rules logic as defined previously)
        
        return $warnings;
    }
}
// --- END Helper Functions ---

// --- (Main API Logic) ---
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if (!has_permission('planning.production_schedule.save')) {
        throw new Exception('شما مجوز دسترسی به این عملیات را ندارید.', 403);
    }

    $input = json_decode(file_get_contents("php://input"), true);
    $selected_keys = $input['selected_keys'] ?? [];
    
    if (empty($selected_keys)) {
        throw new Exception('هیچ آیتمی برای برنامه‌ریزی انتخاب نشده است.');
    }

    // --- ۱. پردازش آیتم‌های انتخابی برای استخراج اطلاعات اولیه و تقاضا ---
    $planning_items = []; 
    $part_ids_in_plan = [];

    // Fetch basic part info (required for loop)
    $all_parts_raw = find_all($pdo, "SELECT PartID, PartName, FamilyID FROM tbl_parts");
    $all_parts = array_column($all_parts_raw, null, 'PartID');
    
    // Fetch Status Names
    $all_statuses_raw = find_all($pdo, "SELECT StatusID, StatusName FROM tbl_part_statuses");
    $all_statuses = array_column($all_statuses_raw, 'StatusName', 'StatusID');
    $all_statuses[0] = 'کسری MRP / شروع تولید'; 


    foreach ($selected_keys as $item_key) {
        // Key format: PartID_StatusID_SourceType_RunID(optional)
        $parts = explode('_', $item_key);
        $part_id = (int)$parts[0];
        $current_status_id = ($parts[1] === 'NULL' || $parts[1] === '0') ? 0 : (int)$parts[1]; 
        $source_type = $parts[2];
        $run_id = isset($parts[3]) ? (int)$parts[3] : null;

        $part_ids_in_plan[] = $part_id;
        $part_info = $all_parts[$part_id] ?? null;
        if (!$part_info) continue;
        
        // --- تعیین مقدار Gross Demand و واحد (از منطق قبلی) ---
        $gross_demand = 0;
        $unit_name = 'KG'; 

        if ($source_type === 'wip') {
            $gross_demand = 100; // Simplified
            $unit_name = 'KG'; 
        } elseif ($source_type === 'mrp' && $run_id) {
             $mrp_result_sql = "SELECT NetRequirement, Unit FROM tbl_planning_mrp_results WHERE RunID = ? AND ItemID = ? AND (ItemStatusID = ? OR ItemStatusID IS NULL)";
             $mrp_params = [$run_id, $part_id, $current_status_id];
             $mrp_result = find_all($pdo, $mrp_result_sql, $mrp_params);
             $gross_demand = $mrp_result[0]['NetRequirement'] ?? 0;
             $unit_name = $mrp_result[0]['Unit'] ?? 'عدد';
        }

        if ($gross_demand < 0.01) continue;
        
        // Add item details for the UI table
        $planning_items[] = [
            'PartID' => $part_id,
            'PartName' => $part_info['PartName'],
            'CurrentStatusID' => $current_status_id,
            'CurrentStatusName' => $all_statuses[$current_status_id] ?? 'شروع تولید',
            'GrossDemand' => $gross_demand,
            'Unit' => $unit_name,
            'ItemKey' => $item_key, // Keep original key for reference/future use
        ];
    }
    
    // --- ۲. جمع‌آوری داده‌های فرعی (ایستگاه‌ها و دستگاه‌ها) ---
    $all_stations = get_all_production_stations($pdo);
    $press_machines = get_available_machines($pdo, PRESS_STATION_ID);
    $screw_machines = get_available_machines($pdo, SCREW_STATION_ID);

    // --- ۳. بررسی محدودیت‌ها (Constraints) ---
    $warnings = check_production_constraints($pdo, $part_ids_in_plan); 

    $response['success'] = true;
    $response['data'] = [
        'planning_items' => $planning_items,
        'warnings' => $warnings,
        'stations' => $all_stations,
        'machines' => [
            PRESS_STATION_ID => $press_machines,
            SCREW_STATION_ID => $screw_machines,
        ]
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>