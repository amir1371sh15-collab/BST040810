<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.manage')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

const TABLE_NAME = 'tbl_planning_part_to_group';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id_redirect = $_POST['group_id'] ?? null;
    
    if (isset($_POST['delete_part_id']) && isset($_POST['delete_group_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . " WHERE PartID = ? AND GroupID = ?");
            $stmt->execute([(int)$_POST['delete_part_id'], (int)$_POST['delete_group_id']]);
            $_SESSION['message'] = 'اتصال با موفقیت حذف شد.';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = 'خطا در حذف اتصال: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    } elseif (isset($_POST['group_id']) && isset($_POST['part_id'])) {
        if (empty($_POST['group_id']) || empty($_POST['part_id'])) {
            $_SESSION['message'] = 'گروه و قطعه باید انتخاب شوند.';
            $_SESSION['message_type'] = 'warning';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME . " (PartID, GroupID) VALUES (?, ?)");
                $stmt->execute([(int)$_POST['part_id'], (int)$_POST['group_id']]);
                $_SESSION['message'] = 'اتصال جدید با موفقیت ثبت شد.';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $_SESSION['message'] = 'خطا: این قطعه از قبل به این گروه متصل شده است.';
                    $_SESSION['message_type'] = 'warning';
                } else {
                    $_SESSION['message'] = 'خطا در ثبت اتصال: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'danger';
                }
            }
        }
    }
    header("Location: " . BASE_URL . "modules/Planning/manage_part_to_group.php?group_id=" . $group_id_redirect);
    exit;
}

$groups = find_all($pdo, "SELECT GroupID, GroupName FROM tbl_planning_plating_groups ORDER BY GroupName");
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");

$selected_group_id = $_GET['group_id'] ?? null;
$selected_group_name = '';
$linked_parts = [];
if ($selected_group_id && is_numeric($selected_group_id)) {
    $selected_group_id = (int)$selected_group_id;
    $group_info = find_by_id($pdo, 'tbl_planning_plating_groups', $selected_group_id, 'GroupID');
    if ($group_info) {
        $selected_group_name = $group_info['GroupName'];
        $linked_parts = find_all($pdo, "
            SELECT pg.PartID, pg.GroupID, p.PartName, pf.FamilyName
            FROM " . TABLE_NAME . " pg
            JOIN tbl_parts p ON pg.PartID = p.PartID
            JOIN tbl_part_families pf ON p.FamilyID = pf.FamilyID
            WHERE pg.GroupID = ?
            ORDER BY pf.FamilyName, p.PartName
        ", [$selected_group_id]);
    }
}

$pageTitle = "اتصال قطعه به گروه آبکاری";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="constraints_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">اتصال جدید</h5></div>
            <div class="card-body">
                <form method="POST" action="manage_part_to_group.php">
                    <div class="mb-3">
                        <label for="group_id_selector" class="form-label">۱. گروه آبکاری *</label>
                        <select class="form-select" id="group_id_selector" name="group_id" onchange="if(this.value) { window.location.href='?group_id='+this.value; }" required>
                            <option value="">-- انتخاب گروه --</option>
                            <?php foreach($groups as $group): ?>
                                <option value="<?php echo $group['GroupID']; ?>" <?php echo ($selected_group_id == $group['GroupID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group['GroupName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($selected_group_id): ?>
                        <div class="mb-3">
                            <label for="family_id_selector" class="form-label">۲. خانواده قطعه *</label>
                            <select class="form-select" id="family_id_selector" name="family_id" required>
                                <option value="">-- انتخاب خانواده --</option>
                                <?php foreach($families as $family): ?>
                                    <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="part_id" class="form-label">۳. قطعه *</label>
                            <select class="form-select" id="part_id" name="part_id" required disabled>
                                <option value="">-- ابتدا خانواده --</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">افزودن اتصال</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">قطعات متصل به: <?php echo htmlspecialchars($selected_group_name ?: 'هیچ گروهی انتخاب نشده'); ?></h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th class="p-3">خانواده</th><th class="p-3">قطعه</th><th class="p-3">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($linked_parts)): ?>
                                <tr><td colspan="3" class="text-center p-3 text-muted">هیچ قطعه‌ای به این گروه متصل نیست.</td></tr>
                            <?php else: ?>
                                <?php foreach ($linked_parts as $item): ?>
                                <tr>
                                    <td class="p-3"><?php echo htmlspecialchars($item['FamilyName']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($item['PartName']); ?></td>
                                    <td class="p-3">
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['PartID']; ?>"><i class="bi bi-trash-fill"></i></button>
                                        <div class="modal fade" id="deleteModal<?php echo $item['PartID']; ?>" tabindex="-1">
                                            <div class="modal-dialog"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">آیا از حذف اتصال این قطعه مطمئن هستید؟</div>
                                            <div class="modal-footer">
                                                <form method="POST"><input type="hidden" name="delete_part_id" value="<?php echo $item['PartID']; ?>"><input type="hidden" name="delete_group_id" value="<?php echo $item['GroupID']; ?>"><input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                                            </div>
                                            </div></div>
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
<script>
$(document).ready(function() {
    const familySelect = $('#family_id_selector');
    const partSelect = $('#part_id');
    // We re-use the API from BOM management
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_all_parts_for_bom.php'; 

    familySelect.on('change', function() {
        const familyId = $(this).val();
        partSelect.prop('disabled', true).html('<option value="">...</option>');
        if (familyId) {
            $.getJSON(apiPartsUrl, { family_id: familyId }, function(response) {
                partSelect.html('<option value="">-- انتخاب قطعه --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(part) {
                        partSelect.append($('<option>', { value: part.PartID, text: part.PartName }));
                    });
                    partSelect.prop('disabled', false);
                } else {
                    partSelect.html('<option value="">قطعه‌ای یافت نشد</option>');
                }
            });
        } else {
            partSelect.html('<option value="">-- ابتدا خانواده --</option>');
        }
    });
});
</script>

