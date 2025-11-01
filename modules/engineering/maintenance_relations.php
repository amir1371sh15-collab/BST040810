<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('engineering.maintenance.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    try {
        if ($type === 'breakdown_causes') {
            $breakdown_id = (int)$_POST['breakdown_id'];
            $causes = $_POST['causes'] ?? [];
            
            // Delete old links
            $stmt = $pdo->prepare("DELETE FROM tbl_maintenance_breakdown_cause_links WHERE BreakdownTypeID = ?");
            $stmt->execute([$breakdown_id]);

            // Insert new links
            $stmt = $pdo->prepare("INSERT INTO tbl_maintenance_breakdown_cause_links (BreakdownTypeID, CauseID) VALUES (?, ?)");
            foreach ($causes as $cause_id) {
                $stmt->execute([$breakdown_id, (int)$cause_id]);
            }
            $_SESSION['message'] = 'روابط خرابی و علل با موفقیت ذخیره شد.';
        } elseif ($type === 'cause_actions') {
            $cause_id = (int)$_POST['cause_id'];
            $actions = $_POST['actions'] ?? [];

            // Delete old links
            $stmt = $pdo->prepare("DELETE FROM tbl_maintenance_cause_action_links WHERE CauseID = ?");
            $stmt->execute([$cause_id]);

            // Insert new links
            $stmt = $pdo->prepare("INSERT INTO tbl_maintenance_cause_action_links (CauseID, ActionID) VALUES (?, ?)");
            foreach ($actions as $action_id) {
                $stmt->execute([$cause_id, (int)$action_id]);
            }
            $_SESSION['message'] = 'روابط علت و اقدامات با موفقیت ذخیره شد.';
        }
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'خطا در ذخیره‌سازی: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch data for forms
$breakdowns = find_all($pdo, "SELECT * FROM tbl_maintenance_breakdown_types ORDER BY Description");
$causes = find_all($pdo, "SELECT * FROM tbl_maintenance_causes ORDER BY CauseDescription");
$actions = find_all($pdo, "SELECT * FROM tbl_maintenance_actions ORDER BY ActionDescription");

$pageTitle = "مدیریت روابط نت";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">مدیریت روابط نگهداری و تعمیرات</h1>
    <a href="maintenance_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <!-- Breakdown -> Causes -->
    <div class="col-md-6">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">۱. تخصیص علل به انواع خرابی</h5></div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="breakdown_select" class="form-label">یک نوع خرابی را انتخاب کنید:</label>
                        <select name="breakdown_id" id="breakdown_select" class="form-select" onchange="this.form.submit()">
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($breakdowns as $b): ?>
                                <option value="<?php echo $b['BreakdownTypeID']; ?>" <?php echo (isset($_GET['breakdown_id']) && $_GET['breakdown_id'] == $b['BreakdownTypeID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['Description']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <?php if(isset($_GET['breakdown_id']) && !empty($_GET['breakdown_id'])): 
                    $selected_breakdown_id = (int)$_GET['breakdown_id'];
                    $linked_causes_raw = find_all($pdo, "SELECT CauseID FROM tbl_maintenance_breakdown_cause_links WHERE BreakdownTypeID = ?", [$selected_breakdown_id]);
                    $linked_causes = array_column($linked_causes_raw, 'CauseID');
                ?>
                <hr>
                <form method="POST" action="">
                    <input type="hidden" name="type" value="breakdown_causes">
                    <input type="hidden" name="breakdown_id" value="<?php echo $selected_breakdown_id; ?>">
                    <p>علل احتمالی را برای این خرابی انتخاب کنید:</p>
                    <div class="list-group">
                        <?php foreach($causes as $c): ?>
                        <label class="list-group-item">
                            <input class="form-check-input me-1" type="checkbox" name="causes[]" value="<?php echo $c['CauseID']; ?>" <?php echo in_array($c['CauseID'], $linked_causes) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($c['CauseDescription']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">ذخیره علل</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cause -> Actions -->
    <div class="col-md-6">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">۲. تخصیص اقدامات به علل خرابی</h5></div>
            <div class="card-body">
                <form method="GET" action="">
                     <div class="mb-3">
                        <label for="cause_select" class="form-label">یک علت را انتخاب کنید:</label>
                        <select name="cause_id" id="cause_select" class="form-select" onchange="this.form.submit()">
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($causes as $c): ?>
                                <option value="<?php echo $c['CauseID']; ?>" <?php echo (isset($_GET['cause_id']) && $_GET['cause_id'] == $c['CauseID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['CauseDescription']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <?php if(isset($_GET['cause_id']) && !empty($_GET['cause_id'])):
                    $selected_cause_id = (int)$_GET['cause_id'];
                    $linked_actions_raw = find_all($pdo, "SELECT ActionID FROM tbl_maintenance_cause_action_links WHERE CauseID = ?", [$selected_cause_id]);
                    $linked_actions = array_column($linked_actions_raw, 'ActionID');
                ?>
                 <hr>
                <form method="POST" action="">
                    <input type="hidden" name="type" value="cause_actions">
                    <input type="hidden" name="cause_id" value="<?php echo $selected_cause_id; ?>">
                    <p>اقدامات لازم برای این علت را انتخاب کنید:</p>
                    <div class="list-group">
                        <?php foreach($actions as $a): ?>
                        <label class="list-group-item">
                            <input class="form-check-input me-1" type="checkbox" name="actions[]" value="<?php echo $a['ActionID']; ?>" <?php echo in_array($a['ActionID'], $linked_actions) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($a['ActionDescription']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">ذخیره اقدامات</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
