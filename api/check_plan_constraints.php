<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/init.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data) || empty($data['items'])) {
        throw new Exception("داده‌های ورودی نامعتبر است.");
    }

    $items = $data['items'];
    $capacitySummary = [];
    $constraints = [];
    $stationGroups = [];

    // 1. Group items by station
    foreach ($items as $item) {
        $stationID = $item['station_id'];
        if (!isset($stationGroups[$stationID])) {
            $stationGroups[$stationID] = [
                'station_name' => $item['station_name'],
                'total_required' => 0,
                'unit' => $item['unit'], // فرض می‌کنیم همه قطعات یک ایستگاه واحد یکسان دارند (برای سادگی)
                'items' => []
            ];
        }
        $stationGroups[$stationID]['total_required'] += $item['quantity'];
        $stationGroups[$stationID]['items'][] = $item;
    }

    // 2. Check Capacity and Constraints for each group
    foreach ($stationGroups as $stationID => &$group) {
        
        // --- الف) بررسی ظرفیت (مثال ساده) ---
        // شما باید این بخش را با منطق واقعی خود جایگزین کنید
        // این کد به صورت فرضی ظرفیت را از دیتابیس می‌خواند
        
        // [FIX]: آرگومان‌های سوم و چهارم جابجا شده بودند و $stationID به int تبدیل شد
        $capacityRule = find_by_id($pdo, 'tbl_station_capacity_rules', (int)$stationID, 'StationID');
        
        $availableCapacity = 10000; // ظرفیت پیش‌فرض اگر قانونی یافت نشد
        if ($capacityRule && $capacityRule['CapacityPerDay'] > 0) {
            $availableCapacity = $capacityRule['CapacityPerDay'];
            // TODO: باید واحدها (KG vs PCS) را نیز بررسی کنید
        }

        $balance = $availableCapacity - $group['total_required'];
        
        $capacitySummary[] = [
            'station_id' => $stationID,
            'station_name' => $group['station_name'],
            'total_required' => $group['total_required'],
            'available_capacity' => $availableCapacity,
            'unit' => $group['unit'],
            'balance' => $balance
        ];

        // --- ب) بررسی محدودیت‌ها (مثال: عدم سازگاری آبکاری) ---
        // این یک مثال برای ایستگاه آبکاری (فرض کنیم ID آن ۵ است)
        if (strpos($group['station_name'], 'آبکاری') !== false) {
            $partFamilyIDs = array_map(function($item) {
                return $item['part_family_id'];
            }, $group['items']);
            
            $partFamilyIDs = array_unique(array_filter($partFamilyIDs));

            if (count($partFamilyIDs) > 1) {
                // بررسی زوج‌های ناسازگار
                $incompatiblePairs = find_all_incompatible_pairs($pdo, $partFamilyIDs);
                
                foreach ($incompatiblePairs as $pair) {
                    // یافتن نام قطعات برای نمایش در هشدار
                    $partName1 = find_part_name_by_family($items, $pair['FamilyID1']);
                    $partName2 = find_part_name_by_family($items, $pair['FamilyID2']);
                    
                    $constraints[] = [
                        'type' => 'warning', // یا 'error' اگر باید متوقف شود
                        'message' => "هشدار آبکاری: قطعه ($partName1) با قطعه ($partName2) در یک بچ ناسازگار است."
                    ];
                }
            }
        }
        
        // TODO: سایر محدودیت‌ها (مثلاً دستگاه) را در اینجا اضافه کنید

    }

    // Helper functions (باید در فایل واقعی تعریف شوند)
    
    // این تابع باید در یک فایل helpers تعریف شود یا در همینجا
    function find_all_incompatible_pairs($pdo, $familyIDs) {
        if (count($familyIDs) < 2) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($familyIDs), '?'));
        
        $sql = "SELECT FamilyID1, FamilyID2 FROM tbl_planning_vibration_incompatibility
                WHERE (FamilyID1 IN ($placeholders) AND FamilyID2 IN ($placeholders))";
        
        // پارامترها باید دو بار تکرار شوند
        $params = array_merge($familyIDs, $familyIDs);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    function find_part_name_by_family($items, $familyID) {
        foreach ($items as $item) {
            if ($item['part_family_id'] == $familyID) {
                return $item['part_name'];
            }
        }
        return "خانواده $familyID";
    }


    // Send response
    echo json_encode([
        'success' => true,
        'data' => [
            'capacity_summary' => $capacitySummary,
            'constraints' => $constraints
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

