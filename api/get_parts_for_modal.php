<?php
include_once '../config/init.php';

header('Content-Type: application/json');

// این فایل نیازی به لاگین ندارد چون فقط دیتا می‌خواند
// اما اگر نیاز به امنیت بود، چک لاگین را اضافه کنید
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'error' => 'Access Denied']);
//     exit;
// }

$family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : 0;

if ($family_id === 0) {
    echo json_encode(['success' => false, 'error' => 'شناسه خانواده نامعتبر است.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT PartID, PartName FROM tbl_parts WHERE FamilyID = ? ORDER BY PartName");
    $stmt->execute([$family_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'parts' => $parts]);

} catch (Exception $e) {
    error_log("Error in get_parts_for_modal.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
