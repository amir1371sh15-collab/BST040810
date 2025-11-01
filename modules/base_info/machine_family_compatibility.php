<?php
require_once __DIR__ . '/../../config/init.php';

if (!has_permission('base_info.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

const TABLE_NAME_COMPAT = 'tbl_machine_producible_families';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_machine_id']) && isset($_POST['delete_family_id'])) {
        $delete_machine_id = (int)$_POST['delete_machine_id'];
        $delete_family_id = (int)$_POST['delete_family_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME_COMPAT . " WHERE MachineID = ? AND FamilyID = ?");
            $stmt->execute([$delete_machine_id, $delete_family_id]);
            $_SESSION['message'] = 'ارتباط با موفقیت حذف شد.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'خطا در حذف ارتباط: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
        $redirect_url = BASE_URL . "modules/base_info/machine_family_compatibility.php";
        if(isset($_POST['redirect_machine_id'])) {
             $redirect_url .= "?machine_id=" . (int)$_POST['redirect_machine_id'];
        }
        header("Location: " . $redirect_url);
        exit;

    } elseif (isset($_POST['machine_id'])) {
        $machine_id = (int)$_POST['machine_id'];
        $producible_families = $_POST['families'] ?? [];

        $pdo->beginTransaction();
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME_COMPAT . " WHERE MachineID = ?");
            $delete_stmt->execute([$machine_id]);

            if (!empty($producible_families)) {
                $insert_stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME_COMPAT . " (MachineID, FamilyID) VALUES (?, ?)");
                foreach ($producible_families as $family_id) {
                    if (is_numeric($family_id)) {
                        $insert_stmt->execute([$machine_id, (int)$family_id]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['message'] = 'ارتباط دستگاه با خانواده‌های محصول با موفقیت ذخیره شد.';
            $_SESSION['message_type'] = 'success';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'خطا در ذخیره‌سازی ارتباط: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
        header("Location: " . BASE_URL . "modules/base_info/machine_family_compatibility.php?machine_id=" . $machine_id);
        exit;
    }
}

$machines = find_all($pdo, "SELECT MachineID, MachineName FROM tbl_machines ORDER BY MachineName");
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$selected_machine_id = null;
$linked_families = [];

if (isset($_GET['machine_id']) && is_numeric($_GET['machine_id'])) {
    $selected_machine_id = (int)$_GET['machine_id'];
    $linked_families_raw = find_all($pdo, "SELECT FamilyID FROM " . TABLE_NAME_COMPAT . " WHERE MachineID = ?", [$selected_machine_id]);
    $linked_families = array_column($linked_families_raw, 'FamilyID');
}

$all_compatibilities = find_all($pdo, "
    SELECT mpc.MachineID, mpc.FamilyID, m.MachineName, pf.FamilyName
    FROM " . TABLE_NAME_COMPAT . " mpc
    JOIN tbl_machines m ON mpc.MachineID = m.MachineID
    JOIN tbl_part_families pf ON mpc.FamilyID = pf.FamilyID
    ORDER BY m.MachineName, pf.FamilyName
");

$pageTitle = "ارتباط دستگاه و خانواده محصول";
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ارتباط دستگاه و خانواده محصول</h1>
    <a href="<?php echo BASE_URL; ?>modules/base_info/machinery_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card content-card mb-4">
            <div class="card-header"><h5 class="mb-0">۱. انتخاب دستگاه</h5></div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="machine_select" class="form-label">یک دستگاه را برای ویرایش انتخاب کنید:</label>
                        <select name="machine_id" id="machine_select" class="form-select" onchange="this.form.submit()">
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($machines as $machine): ?>
                                <option value="<?php echo $machine['MachineID']; ?>" <?php echo ($selected_machine_id == $machine['MachineID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($machine['MachineName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if($selected_machine_id):
            $selected_machine_name = find_by_id($pdo, 'tbl_machines', $selected_machine_id, 'MachineID')['MachineName'] ?? 'انتخاب نشده';
        ?>
            <div class="card content-card">
                 <div class="card-header"><h5 class="mb-0">۲. تخصیص خانواده محصول به: <?php echo htmlspecialchars($selected_machine_name); ?></h5></div>
                 <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="machine_id" value="<?php echo $selected_machine_id; ?>">
                        <p>خانواده‌های محصولی که این دستگاه می‌تواند تولید کند را انتخاب کنید:</p>
                        <div class="list-group mb-3" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach($families as $family): ?>
                            <label class="list-group-item">
                                <input class="form-check-input me-1" type="checkbox" name="families[]" value="<?php echo $family['FamilyID']; ?>" <?php echo in_array($family['FamilyID'], $linked_families) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($family['FamilyName']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">ذخیره ارتباطات</button>
                    </form>
                 </div>
            </div>
        <?php endif; ?>
    </div>


    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">لیست ارتباطات ثبت شده</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="p-3">دستگاه</th>
                                <th class="p-3">خانواده محصول</th>
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_compatibilities)): ?>
                                <tr><td colspan="3" class="text-center p-3 text-muted">هیچ ارتباطی ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($all_compatibilities as $item): ?>
                                <tr>
                                    <td class="p-3"><?php echo htmlspecialchars($item['MachineName']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                                    <td class="p-3">
                                        <a href="?machine_id=<?php echo $item['MachineID']; ?>" class="btn btn-warning btn-sm" title="ویرایش ارتباطات این دستگاه">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['MachineID'] . '-' . $item['FamilyID']; ?>" title="حذف این ارتباط خاص">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>

                                        <div class="modal fade" id="deleteModal<?php echo $item['MachineID'] . '-' . $item['FamilyID']; ?>" tabindex="-1">
                                          <div class="modal-dialog">
                                            <div class="modal-content">
                                              <div class="modal-header">
                                                <h5 class="modal-title">تایید حذف</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                              </div>
                                              <div class="modal-body">
                                                آیا از حذف ارتباط بین "<?php echo htmlspecialchars($item['MachineName']); ?>" و "<?php echo htmlspecialchars($item['FamilyName']); ?>" مطمئن هستید؟
                                              </div>
                                              <div class="modal-footer">
                                                <form method="POST" action="" class="d-inline">
                                                  <input type="hidden" name="delete_machine_id" value="<?php echo $item['MachineID']; ?>">
                                                  <input type="hidden" name="delete_family_id" value="<?php echo $item['FamilyID']; ?>">
                                                  <?php if($selected_machine_id == $item['MachineID']): ?>
                                                   <input type="hidden" name="redirect_machine_id" value="<?php echo $selected_machine_id; ?>">
                                                  <?php endif; ?>
                                                  <button type="submit" class="btn btn-danger">بله، حذف کن</button>
                                                </form>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                              </div>
                                            </div>
                                          </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

