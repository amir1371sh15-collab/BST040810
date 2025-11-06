<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => null, 'message' => ''];

// چک کردن مجوز
if (!has_permission('warehouse.transactions.manage')) {
    http_response_code(403);
    $response['message'] = 'شما مجوز دسترسی به این گزارش را ندارید.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    $response['message'] = 'فقط متد GET مجاز است.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    exit;
}

try {
    // دریافت پارامترها
    $from_date_jalali = $_GET['from_date'] ?? null;
    $to_date_jalali = $_GET['to_date'] ?? null;
    $selected_station_id = !empty($_GET['station_id']) ? (int)$_GET['station_id'] : null;

    // تبدیل تاریخ جلالی به میلادی
    $from_date_only = to_gregorian($from_date_jalali);
    $to_date_only = to_gregorian($to_date_jalali);
    
    if (!$from_date_only || !$to_date_only) {
        throw new Exception("بازه زمانی نامعتبر است.");
    }
    
    // اضافه کردن ساعت
    $from_date = $from_date_only . ' 00:00:00';
    $to_date = $to_date_only . ' 23:59:59';

    // Get helper data
    $part_weights = get_all_part_weights($pdo);
    $bom_map = get_bom_map($pdo);
    $packaging_configs = get_packaging_configs($pdo);
    $weight_changes = get_weight_changes($pdo);

    // --- ایستگاه‌هایی که باید از گزارش حذف شوند (بسته‌بندی را نگه می‌داریم) ---
    $excluded_stations = ['مونتاژ', 'پرسکاری', 'پیچ سازی', 'دنده زنی'];
    
    // --- دریافت ایستگاه‌های تولیدی (به جز ایستگاه‌های مستثنی) ---
    $station_query_sql = "
        SELECT StationID, StationName 
        FROM tbl_stations 
        WHERE StationType = 'Production' 
        AND StationName NOT IN ('" . implode("','", $excluded_stations) . "')
    ";
    
    if ($selected_station_id) {
        $station_query_sql .= " AND StationID = :station_id";
    }
    $station_query_sql .= " ORDER BY StationName";
    
    $station_stmt = $pdo->prepare($station_query_sql);
    if ($selected_station_id) {
        $station_stmt->execute(['station_id' => $selected_station_id]);
    } else {
        $station_stmt->execute();
    }
    $stations = $station_stmt->fetchAll(PDO::FETCH_ASSOC);

    $report_data = [];

    // --- 3. Main Loop: Process Each Station ---
    foreach ($stations as $station) {
        $station_id = (int)$station['StationID'];
        $station_name = $station['StationName'];
        $station_unit = 'KG'; // Default
        $part_results = [];

        // Determine parts that ever moved through this station
        $relevant_parts_stmt = $pdo->prepare("
            SELECT DISTINCT t.PartID, p.PartName, p.FamilyID, r.RequiredStatusID
            FROM tbl_stock_transactions t
            JOIN tbl_parts p ON t.PartID = p.PartID
            LEFT JOIN tbl_routes r ON p.FamilyID = r.FamilyID AND r.ToStationID = ? 
            WHERE (t.ToStationID = ? OR t.FromStationID = ?) AND t.PartID IS NOT NULL
            GROUP BY t.PartID, p.PartName, p.FamilyID, r.RequiredStatusID
        ");
        $relevant_parts_stmt->execute([$station_id, $station_id, $station_id]);
        $relevant_parts = $relevant_parts_stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- منطق خاص برای ایستگاه بسته‌بندی ---
        if ($station_id == 10) { // 10 = ایستگاه بسته‌بندی
            $station_unit = 'Carton';
            
            foreach ($relevant_parts as $part) {
                $part_id = $part['PartID'];
                
                // اگر وزن یا پیکربندی وجود ندارد، از KG استفاده کن
                $has_conversion = isset($part_weights[$part_id]) && $part_weights[$part_id] > 0 
                                  && isset($packaging_configs[$part_id]) && $packaging_configs[$part_id] > 0;
                
                if ($has_conversion) {
                    $weight_gr = $part_weights[$part_id]; // وزن یک عدد قطعه (گرم)
                    $qty_per_carton = $packaging_configs[$part_id]; // تعداد قطعه در هر کارتن
                    
                    // --- محاسبه موجودی اولیه ---
                    $opening_in_kg = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, null, $from_date);
                    $opening_out_cartons = get_sum($pdo, 'CartonQuantity', 'FromStationID', $part_id, $station_id, null, $from_date);
                    
                    $opening_in_pieces = ($opening_in_kg * 1000) / $weight_gr;
                    $opening_in_cartons = $opening_in_pieces / $qty_per_carton;
                    $opening = $opening_in_cartons - $opening_out_cartons;
                    
                    // --- محاسبه ورودی در بازه ---
                    $total_in_kg = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, $from_date, $to_date);
                    $total_in_pieces = ($total_in_kg * 1000) / $weight_gr;
                    $total_in_cartons = $total_in_pieces / $qty_per_carton;
                    
                    // --- محاسبه خروجی در بازه ---
                    $total_out_cartons = get_sum($pdo, 'CartonQuantity', 'FromStationID', $part_id, $station_id, $from_date, $to_date);
                    
                    // --- محاسبه موجودی سیستمی ---
                    $system = $opening + $total_in_cartons - $total_out_cartons;
                    
                    // نمایش همه موارد (حتی صفر) برای دیباگ
                    $part_results[] = [
                        'PartID' => $part_id,
                        'PartName' => $part['PartName'],
                        'FamilyID' => $part['FamilyID'],
                        'StatusID' => $part['RequiredStatusID'],
                        'Opening' => round($opening, 2),
                        'In' => round($total_in_cartons, 2),
                        'Out' => round($total_out_cartons, 2),
                        'System' => round($system, 2),
                        'TooltipIn' => "برابر با " . number_format($total_in_kg, 2) . " کیلوگرم (" . number_format($total_in_pieces, 0) . " عدد)",
                        'TooltipOut' => null
                    ];
                } else {
                    // Fallback: نمایش به KG اگر تبدیل ممکن نباشد
                    $opening = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, null, $from_date);
                    $opening -= get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, null, $from_date);
                    
                    $total_in = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, $from_date, $to_date);
                    $total_out = get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, $from_date, $to_date);
                    
                    $system = $opening + $total_in - $total_out;
                    
                    $part_results[] = [
                        'PartID' => $part_id,
                        'PartName' => $part['PartName'] . ' (KG)',
                        'FamilyID' => $part['FamilyID'],
                        'StatusID' => $part['RequiredStatusID'],
                        'Opening' => $opening,
                        'In' => $total_in,
                        'Out' => $total_out,
                        'System' => $system,
                        'TooltipIn' => "هشدار: تبدیل به کارتن امکان‌پذیر نیست",
                        'TooltipOut' => null
                    ];
                }
            }
        } 
        // --- پردازش ساده برای سایر ایستگاه‌ها (فقط KG) ---
        else {
            foreach ($relevant_parts as $part) {
                $part_id = $part['PartID'];

                $opening = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, null, $from_date);
                $opening -= get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, null, $from_date);
                
                $total_in = get_sum($pdo, 'NetWeightKG', 'ToStationID', $part_id, $station_id, $from_date, $to_date);
                $total_out = get_sum($pdo, 'NetWeightKG', 'FromStationID', $part_id, $station_id, $from_date, $to_date);
                
                $system = $opening + $total_in - $total_out;

                if (abs($opening) < 0.001 && abs($total_in) < 0.001 && abs($total_out) < 0.001) continue;

                $part_results[] = [
                    'PartID' => $part_id,
                    'PartName' => $part['PartName'],
                    'FamilyID' => $part['FamilyID'],
                    'StatusID' => $part['RequiredStatusID'],
                    'Opening' => $opening,
                    'In' => $total_in,
                    'Out' => $total_out,
                    'System' => $system,
                    'TooltipIn' => null,
                    'TooltipOut' => null
                ];
            }
        }

        $report_data[$station_id] = [
            'station_name' => $station_name,
            'unit' => $station_unit,
            'parts' => $part_results
        ];
    }

    $response['success'] = true;
    $response['data'] = $report_data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

// --- Helper Functions ---

function get_sum($pdo, $column, $station_col, $part_id, $station_id, $start_date, $end_date) {
    $sql = "SELECT SUM($column) FROM tbl_stock_transactions 
            WHERE $station_col = ? AND PartID = ?";
    $params = [$station_id, $part_id];

    if ($start_date && $end_date) {
        $sql .= " AND TransactionDate BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif ($end_date) {
        $sql .= " AND TransactionDate < ?";
        $params[] = $end_date;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

function get_all_part_weights($pdo) {
    $stmt = $pdo->query("SELECT PartID, WeightGR FROM tbl_part_weights WHERE EffectiveTo IS NULL");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function get_bom_map($pdo) {
    $stmt = $pdo->query("SELECT ParentPartID, ChildPartID, QuantityPerParent FROM tbl_bom_structure");
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int)$row['ParentPartID']][] = [
            'ChildPartID' => (int)$row['ChildPartID'],
            'QuantityPerParent' => (float)$row['QuantityPerParent']
        ];
    }
    return $map;
}

function get_packaging_configs($pdo) {
     $stmt = $pdo->query("
        SELECT p.PartID, pc.ContainedQuantity
        FROM tbl_parts p
        JOIN tbl_packaging_configs pc ON p.SizeID = pc.SizeID
        WHERE p.SizeID IS NOT NULL
    ");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function get_weight_changes($pdo) {
    $stmt = $pdo->query("
        SELECT PartID, FromStationID, WeightChangePercent
        FROM tbl_process_weight_changes
        WHERE EffectiveTo IS NULL
    ");
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int)$row['PartID']][(int)$row['FromStationID']] = (float)$row['WeightChangePercent'];
    }
    return $map;
}
?>