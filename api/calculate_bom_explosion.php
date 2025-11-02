<?php
// api/calculate_bom_explosion.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// --- Permission Check ---
if (!has_permission('planning.view')) {
    echo json_encode(['success' => false, 'message' => 'شما مجوز دسترسی به این قابلیت را ندارید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = [
    'success' => false,
    'message' => '',
    'data' => [
        'summary' => null,
        'semiFinished' => [],
        'rawMaterials' => []
    ]
];

try {
    $partId = (int)($_GET['part_id'] ?? 0);
    $unit = $_GET['unit'] ?? 'Pieces';
    $quantity = (float)($_GET['quantity'] ?? 0);

    if (empty($partId) || empty($quantity)) {
        throw new Exception("شناسه قطعه و مقدار الزامی است.");
    }

    // 1. واکشی اطلاعات قطعه اصلی
    $mainPartStmt = $pdo->prepare("
        SELECT 
            p.PartID, p.PartName, p.FamilyID,
            pf.FamilyName,
            pw.WeightGR
        FROM tbl_parts p
        JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
        LEFT JOIN tbl_part_weights pw ON p.PartID = pw.PartID AND (pw.EffectiveTo IS NULL OR pw.EffectiveTo >= CURDATE())
        WHERE p.PartID = ?
    ");
    $mainPartStmt->execute([$partId]);
    $mainPart = $mainPartStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainPart) {
        throw new Exception("محصول نهایی یافت نشد.");
    }

    // 2. تبدیل واحد (Unit Conversion) -> محاسبه $baseQuantityInPieces
    $baseQuantityInPieces = 0;
    $userMessage = "";

    switch ($unit) {
        case 'Pieces':
            $baseQuantityInPieces = (int)$quantity;
            $userMessage = htmlspecialchars($quantity) . " عدد";
            break;

        case 'KG':
            if (empty($mainPart['WeightGR'])) {
                throw new Exception("وزن (WeightGR) برای این محصول تعریف نشده است. امکان محاسبه بر اساس کیلوگرم وجود ندارد.");
            }
            $weightPerPieceKG = (float)$mainPart['WeightGR'] / 1000;
            $baseQuantityInPieces = (int)ceil($quantity / $weightPerPieceKG);
            $userMessage = htmlspecialchars($quantity) . " کیلوگرم";
            break;

        case 'Carton':
            $sizeName = trim(str_replace($mainPart['FamilyName'], '', $mainPart['PartName']));
            $sizeStmt = $pdo->prepare("
                SELECT SizeID FROM tbl_part_sizes WHERE FamilyID = ? AND SizeName = ?
            ");
            $sizeStmt->execute([$mainPart['FamilyID'], $sizeName]);
            $size = $sizeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$size) {
                 throw new Exception("اطلاعات سایز (SizeID) برای این محصول بر اساس نام آن یافت نشد.");
            }
            $correctSizeId = $size['SizeID'];

            $pkgStmt = $pdo->prepare("SELECT * FROM tbl_packaging_configs WHERE SizeID = ?");
            $pkgStmt->execute([$correctSizeId]);
            $packagingConfig = $pkgStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$packagingConfig || empty($packagingConfig['ContainedQuantity'])) {
                throw new Exception("اطلاعات بسته‌بندی (ContainedQuantity) برای این محصول یافت نشد. (لطفاً از منوی اطلاعات پایه -> پیکربندی بسته‌بندی آن را تعریف کنید)");
            }
            
            $piecesPerCarton = (int)$packagingConfig['ContainedQuantity'];
            $baseQuantityInPieces = (int)$quantity * $piecesPerCarton;
            $userMessage = htmlspecialchars($quantity) . " کارتن (هر کارتن " . $piecesPerCarton . " عدد)";
            break;
    }
    
    $response['message'] = $userMessage;
    $response['data']['summary'] = [
        'partName' => $mainPart['PartName'],
        'finalQuantity' => $baseQuantityInPieces
    ];

    // --- START REFACTOR (BUG FIX) ---

    // 3. واکشی تمام ساختار BOM (قطعه به قطعه)
    $bomMap = [];
    $bomStmt = $pdo->prepare("
        SELECT 
            bom.ParentPartID, 
            bom.ChildPartID, 
            bom.QuantityPerParent,
            p_child.PartName AS ChildPartName,
            w_child.WeightGR AS ChildWeightGR
        FROM tbl_bom_structure bom
        JOIN tbl_parts p_child ON bom.ChildPartID = p_child.PartID
        LEFT JOIN tbl_part_weights w_child ON bom.ChildPartID = w_child.PartID 
             AND (w_child.EffectiveTo IS NULL OR w_child.EffectiveTo >= CURDATE())
    ");
    $bomStmt->execute();
    while ($row = $bomStmt->fetch(PDO::FETCH_ASSOC)) {
        $bomMap[$row['ParentPartID']][] = [
            'childId' => $row['ChildPartID'],
            'childName' => $row['ChildPartName'],
            'childWeightGR' => (float)$row['ChildWeightGR'],
            'qty' => (float)$row['QuantityPerParent']
        ];
    }

    // 4. واکشی تمام مواد اولیه (قطعه به ماده خام)
    $rawMaterialMap = [];
    $rawStmt = $pdo->prepare("
        SELECT 
            raw_bom.PartID,
            raw_bom.RawMaterialItemID,
            raw.ItemName AS RawMaterialName,
            raw_bom.QuantityGram AS RawQuantityGram
        FROM tbl_part_raw_materials raw_bom
        JOIN tbl_raw_items raw ON raw_bom.RawMaterialItemID = raw.ItemID
    ");
    $rawStmt->execute();
    while ($row = $rawStmt->fetch(PDO::FETCH_ASSOC)) {
        $rawMaterialMap[$row['PartID']][] = [
            'rawId' => $row['RawMaterialItemID'],
            'rawName' => $row['RawMaterialName'],
            'qtyGram' => (float)$row['RawQuantityGram']
        ];
    }

    // --- END REFACTOR ---


    // 5. تابع بازگشتی (Recursive Function) برای انفجار BOM (بدون تغییر)
    $semiFinishedNeeds = [];
    $rawMaterialNeeds = [];

    function explodeBom($partId, $requiredQuantity, $bomMap, $rawMaterialMap, &$semiFinishedNeeds, &$rawMaterialNeeds) {
        
        // الف) افزودن نیازهای ماده خام این قطعه
        if (isset($rawMaterialMap[$partId])) {
            foreach ($rawMaterialMap[$partId] as $rawMaterial) {
                $rawId = $rawMaterial['rawId'];
                if (!isset($rawMaterialNeeds[$rawId])) {
                    $rawMaterialNeeds[$rawId] = [
                        'name' => $rawMaterial['rawName'],
                        'totalGram' => 0
                    ];
                }
                $rawMaterialNeeds[$rawId]['totalGram'] += $rawMaterial['qtyGram'] * $requiredQuantity;
            }
        }

        // ب) بررسی اینکه آیا این قطعه، خود دارای زیرمجموعه است؟
        if (isset($bomMap[$partId])) {
            // اگر بله، به ازای هر فرزند، تابع را دوباره فراخوانی کن
            foreach ($bomMap[$partId] as $child) {
                $childId = $child['childId'];
                $childRequiredQty = $requiredQuantity * $child['qty'];

                // ۱. افزودن خود فرزند به لیست نیازمندی‌های نیمه‌ساخته
                if (!isset($semiFinishedNeeds[$childId])) {
                    $semiFinishedNeeds[$childId] = [
                        'name' => $child['childName'],
                        'weightGR' => $child['childWeightGR'],
                        'totalQty' => 0
                    ];
                }
                $semiFinishedNeeds[$childId]['totalQty'] += $childRequiredQty;

                // ۲. انفجار BOM برای این فرزند
                explodeBom($childId, $childRequiredQty, $bomMap, $rawMaterialMap, $semiFinishedNeeds, $rawMaterialNeeds);
            }
        }
    }

    // 6. شروع انفجار از قطعه اصلی
    explodeBom($partId, $baseQuantityInPieces, $bomMap, $rawMaterialMap, $semiFinishedNeeds, $rawMaterialNeeds);

    // 7. مرتب‌سازی و فرمت‌دهی نتایج
    
    // قطعات نیمه‌ساخته
    ksort($semiFinishedNeeds);
    foreach ($semiFinishedNeeds as $partData) {
        $totalWeightKG = 0;
        if (!empty($partData['weightGR'])) {
            $totalWeightKG = ($partData['weightGR'] / 1000) * $partData['totalQty'];
        }
        $response['data']['semiFinished'][] = [
            'partName' => $partData['name'],
            'totalQuantity' => $partData['totalQty'],
            'totalWeightKG' => $totalWeightKG
        ];
    }

    // مواد خام
    ksort($rawMaterialNeeds);
    foreach ($rawMaterialNeeds as $rawData) {
        $response['data']['rawMaterials'][] = [
            'rawMaterialName' => $rawData['name'],
            'totalWeightKG' => $rawData['totalGram'] / 1000 // تبدیل گرم به کیلوگرم
        ];
    }

    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

