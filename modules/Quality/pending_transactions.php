<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('quality.pending_transactions.view')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle linking deviation (Example - needs refinement based on desired workflow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_deviation'])) {
    if (!has_permission('quality.pending_transactions.manage')) {
         $_SESSION['message'] = 'شما مجوز تعیین تکلیف تراکنش‌ها را ندارید.';
         $_SESSION['message_type'] = 'danger';
    } else {
        $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
        $deviationId = filter_input(INPUT_POST, 'deviation_id_link', FILTER_VALIDATE_INT);

        if ($transactionId && $deviationId) {
            // Find the deviation to ensure it's approved
            $deviation = find_by_id($pdo, 'tbl_quality_deviations', $deviationId, 'DeviationID');

            if ($deviation && $deviation['Status'] === 'Approved') {
                $updateData = [
                    'DeviationID' => $deviationId,
                    'RouteStatus' => 'NonStandardApproved',
                    'PendingReason' => null // Clear pending reason
                ];
                $result = update_record($pdo, 'tbl_stock_transactions', $updateData, $transactionId, 'TransactionID');
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            } else {
                $_SESSION['message'] = 'خطا: مجوز ارفاقی انتخاب شده معتبر یا تایید شده نیست.';
                $_SESSION['message_type'] = 'warning';
            }
        } else {
            $_SESSION['message'] = 'اطلاعات ارسالی برای لینک کردن نامعتبر است.';
            $_SESSION['message_type'] = 'warning';
        }
    }
     header("Location: " . BASE_URL . "modules/quality/pending_transactions.php");
     exit;
}


$pending_transactions = find_all($pdo, "
    SELECT st.*, p.PartName, fs.StationName as FromStationName, ts.StationName as ToStationName, u.Username as CreatorName
    FROM tbl_stock_transactions st
    JOIN tbl_parts p ON st.PartID = p.PartID
    JOIN tbl_stations fs ON st.FromStationID = fs.StationID
    JOIN tbl_stations ts ON st.ToStationID = ts.StationID
    LEFT JOIN tbl_users u ON st.CreatedBy = u.UserID
    WHERE st.RouteStatus = 'NonStandardPending'
    ORDER BY st.TransactionDate DESC, st.TransactionID DESC
");

// Fetch active, approved deviations for linking
$approved_deviations = find_all($pdo, "SELECT DeviationID, DeviationCode, Reason FROM tbl_quality_deviations WHERE Status = 'Approved' AND (ValidTo IS NULL OR ValidTo >= CURDATE()) ORDER BY DeviationCode");


$pageTitle = "تراکنش‌های انبار در انتظار تایید";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card content-card mt-4">
    <div class="card-header"><h5 class="mb-0">لیست تراکنش‌های با مسیر غیراستاندارد (در انتظار)</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">قطعه</th>
                        <th class="p-2">از</th>
                        <th class="p-2">به</th>
                        <th class="p-2">وزن خالص (gr)</th>
                        <th class="p-2">کاربر</th>
                        <th class="p-2">دلیل انتظار</th>
                        <th class="p-2">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($pending_transactions)): ?>
                        <tr><td colspan="8" class="text-center p-3 text-muted">هیچ تراکنش در انتظاری یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach($pending_transactions as $tx): ?>
                        <tr>
                            <td class="p-2"><?php echo to_jalali($tx['TransactionDate']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['PartName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['FromStationName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['ToStationName']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars(number_format($tx['NetWeightGR'] ?? 0, 1)); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($tx['CreatorName'] ?? '-'); ?></td>
                            <td class="p-2 small"><?php echo htmlspecialchars($tx['PendingReason'] ?? '-'); ?></td>
                            <td class="p-2">
                                <?php if (has_permission('quality.pending_transactions.manage')): ?>
                                    <button type="button" class="btn btn-primary btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#linkDeviationModal<?php echo $tx['TransactionID']; ?>" title="لینک به مجوز ارفاقی">
                                        <i class="bi bi-link-45deg"></i> لینک
                                    </button>
                                    <!-- Add more buttons for other actions like 'Rework', 'Scrap' etc. later -->

                                     <!-- Link Deviation Modal -->
                                    <div class="modal fade" id="linkDeviationModal<?php echo $tx['TransactionID']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST">
                                                <input type="hidden" name="link_deviation" value="1">
                                                <input type="hidden" name="transaction_id" value="<?php echo $tx['TransactionID']; ?>">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">لینک تراکنش به مجوز ارفاقی</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>تراکنش برای: <?php echo htmlspecialchars($tx['PartName']); ?> (<?php echo htmlspecialchars($tx['FromStationName']); ?> ← <?php echo htmlspecialchars($tx['ToStationName']); ?>)</p>
                                                        <label for="deviation_id_link_<?php echo $tx['TransactionID']; ?>" class="form-label">انتخاب مجوز ارفاقی تایید شده:</label>
                                                        <select class="form-select" name="deviation_id_link" id="deviation_id_link_<?php echo $tx['TransactionID']; ?>" required>
                                                            <option value="">-- انتخاب کنید --</option>
                                                            <?php foreach($approved_deviations as $dev): ?>
                                                                <option value="<?php echo $dev['DeviationID']; ?>"><?php echo htmlspecialchars($dev['DeviationCode'] . ' - ' . $dev['Reason']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-primary">لینک و تایید مسیر</button>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <span class="text-muted small">عدم دسترسی</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                     <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
