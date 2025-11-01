<?php
header('Content-Type: application/json; charset=utf-8');
// Ensure the path to init.php is correct relative to this file's location
require_once __DIR__ . '/../config/init.php'; // Includes PDO and helpers

$response = ['success' => false, 'data' => [], 'message' => ''];

// Define the Family IDs for packaging products
// 3 = بست بزرگ, 9 = بست کوچک
$packaging_family_ids = [3, 9]; 

try {
    // Ensure $pdo is available after including init.php
    if (!isset($pdo) || !$pdo instanceof PDO) {
        error_log("API Error in api_get_parts_for_packaging.php: Database connection (\$pdo) is not available.");
        throw new Exception("اتصال به پایگاه داده برقرار نیست.");
    }

    // Prevent SQL error if array is empty
    if (empty($packaging_family_ids)) {
         $response['success'] = true;
         $response['message'] = 'هیچ خانواده محصولی برای فیلتر مشخص نشده است.';
         echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
         exit;
    }
    $placeholders = implode(',', array_fill(0, count($packaging_family_ids), '?'));

    // --- UPDATED QUERY (VERSION 2) ---
    // 1. Replaced the JOIN `ON p.SizeID = ps.SizeID` with a logical JOIN:
    //    `ON p.FamilyID = ps.FamilyID AND p.PartName = CONCAT(pf.FamilyName, ' ', ps.SizeName)`
    //    This links a part to its size based on the naming convention found in the data.
    // 2. Changed `ORDER BY ps.SizeName` to `ORDER BY p.PartName` as ps.SizeName might be null.
    
    $sql = "SELECT
                p.PartID, p.PartName, p.PartCode, pf.FamilyName,
                pc.ContainedQuantity,
                pw.TotalWeightKG,
                pw_current.WeightGR as UnitWeight -- Get current weight in GR and alias it
            FROM tbl_parts p
            JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
            LEFT JOIN tbl_part_sizes ps ON p.FamilyID = ps.FamilyID AND p.PartName = CONCAT(pf.FamilyName, ' ', ps.SizeName) -- *** INFERRED LOGICAL JOIN ***
            LEFT JOIN tbl_packaging_configs pc ON ps.SizeID = pc.SizeID -- This JOIN now uses the found ps.SizeID
            LEFT JOIN tbl_packaging_weights pw ON ps.SizeID = pw.SizeID -- This JOIN also uses ps.SizeID
            LEFT JOIN tbl_part_weights pw_current ON p.PartID = pw_current.PartID 
                                                 AND (pw_current.EffectiveTo IS NULL OR pw_current.EffectiveTo >= CURDATE()) -- Get current active weight
            WHERE p.FamilyID IN ($placeholders) -- Filter by FamilyID
            ORDER BY pf.FamilyName, p.PartName"; // Order by PartName as SizeName might be null

    $stmt = $pdo->prepare($sql);
    $stmt->execute($packaging_family_ids);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($parts !== false) {
        $response['success'] = true;
        // Ensure numeric values are cast correctly, handle NULLs
        foreach ($parts as &$part) {
            // Note: UnitWeight is now WeightGR (grams)
            $part['UnitWeight'] = isset($part['UnitWeight']) ? (float)$part['UnitWeight'] : null; 
            $part['ContainedQuantity'] = isset($part['ContainedQuantity']) ? (int)$part['ContainedQuantity'] : null;
            $part['TotalWeightKG'] = isset($part['TotalWeightKG']) ? (float)$part['TotalWeightKG'] : null;
        }
        unset($part); // Unset the reference
        $response['data'] = $parts;
        if (empty($parts)) {
            $response['message'] = 'هیچ قطعه فعالی برای بسته‌بندی یافت نشد (بر اساس خانواده‌های بست).';
        }
    } else {
         throw new Exception("واکشی اطلاعات قطعات از پایگاه داده ناموفق بود.");
    }

} catch (PDOException $e) {
    error_log("API PDO Error in api_get_parts_for_packaging.php: " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A'));
    http_response_code(500);
    $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("API General Error in api_get_parts_for_packaging.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'خطای سرور: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
?>

