<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('users.permissions.manage')) { die('شما مجوز مدیریت دسترسی‌ها را ندارید.'); }

// ساختار دسترسی‌ها با اضافه شدن ماژول برنامه‌ریزی و انبار مواد اولیه
$permissions_structure = [
    'base_info' => [
        'label' => 'اطلاعات پایه',
        'permissions' => ['view' => 'مشاهده ماژول', 'manage' => 'مدیریت کامل']
    ],
    'engineering' => [
        'label' => 'مهندسی',
        'permissions' => [
            'view' => 'مشاهده ماژول',
            'base_info' => 'مدیریت اطلاعات پایه مهندسی',
            'projects.view' => 'مشاهده پروژه‌ها',
            'projects.manage' => 'مدیریت پروژه‌ها',
            'spare_parts.view' => 'مشاهده انبار قطعات یدکی',
            'spare_parts.manage' => 'مدیریت انبار قطعات یدکی',
            'changes.view' => 'مشاهده تغییرات مهندسی',
            'changes.manage' => 'مدیریت تغییرات مهندسی',
            'tools.view' => 'مشاهده انبار ابزار',
            'tools.manage' => 'مدیریت انبار ابزار',
            'maintenance.view' => 'مشاهده نت',
            'maintenance.manage' => 'مدیریت نت'
        ]
    ],
    'production' => [
        'label' => 'تولید',
        'permissions' => [
            'view' => 'مشاهده ماژول',
            'production_hall.view' => 'مشاهده سالن تولید',
            'production_hall.manage' => 'مدیریت سالن تولید',
            'plating_hall.view' => 'مشاهده سالن آبکاری',
            'plating_hall.manage' => 'مدیریت سالن آبکاری',
            'assembly_hall.view' => 'مشاهده سالن مونتاژ',
            'assembly_hall.manage' => 'مدیریت سالن مونتاژ',
        ]
    ],
     'quality' => [
        'label' => 'کیفیت',
        'permissions' => [
            'view' => 'مشاهده ماژول',
            'deviations.view' => 'مشاهده مجوزهای ارفاقی',
            'deviations.manage' => 'مدیریت مجوزهای ارفاقی',
            'overrides.manage' => 'مدیریت مسیرهای غیراستاندارد',
            'pending_transactions.view' => 'مشاهده تراکنش‌های در انتظار',
            'pending_transactions.manage' => 'تعیین تکلیف تراکنش‌ها',
        ]
    ],
    // --- ماژول جدید برنامه‌ریزی ---
    'planning' => [
        'label' => 'برنامه‌ریزی تولید (MRP)',
        'permissions' => [
            'view' => 'مشاهده ماژول برنامه‌ریزی',
            'sales_orders.view' => 'مشاهده سفارشات فروش (تقاضا)',
            'sales_orders.manage' => 'مدیریت سفارشات فروش (تقاضا)',
            'bom.view' => 'مشاهده ساختار محصول (BOM)',
            'bom.manage' => 'مدیریت ساختار محصول (BOM)',
            'safety_stock.view' => 'مشاهده نقطه سفارش قطعات',
            'safety_stock.manage' => 'مدیریت نقطه سفارش قطعات',
            'view_alerts' => 'مشاهده هشدارهای موجودی قطعات (WIP)',
            'mrp.run' => 'اجرای MRP و مشاهده نتایج'
        ]
    ],
    // --- به‌روزرسانی ماژول انبار ---
    'warehouse' => [
        'label' => 'انبار',
        'permissions' => [
            'view' => 'مشاهده ماژول و گزارشات',
            'transactions.manage' => 'مدیریت تراکنش‌ها (قطعات)',
            'inventory.view' => 'مشاهده داشبورد موجودی (قطعات)',
            'inventory.snapshot' => 'ثبت عکس لحظه‌ای موجودی (قطعات)',
            'inventory.history' => 'مشاهده تاریخچه عکس‌های لحظه‌ای (قطعات)',
            'inventory.alerts' => 'مشاهده هشدارهای انبار مواد (متفرقه/اولیه)', // مربوط به inventory_alerts.php
            'misc.view' => 'مشاهده انبار متفرقه',
            'misc.manage' => 'مدیریت انبار متفرقه (تعاریف، تراکنش)',
            'raw.view' => 'مشاهده انبار مواد اولیه', // دسترسی برای raw_...
            'raw.manage' => 'مدیریت انبار مواد اولیه (تعاریف، تراکنش)' // دسترسی برای raw_...
        ]
    ],
    'users' => [
        'label' => 'کاربران',
        'permissions' => [
            'view' => 'مشاهده ماژول',
            'roles.manage' => 'مدیریت نقش‌ها',
            'users.manage' => 'مدیریت کاربران',
            'permissions.manage' => 'مدیریت دسترسی‌ها',
        ]
    ]
];

$selected_role_id = null;
$role_permissions = [];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_id'])) {
    $role_id_to_update = (int)$_POST['role_id'];
    $submitted_permissions = $_POST['permissions'] ?? [];
    try {
        $pdo->beginTransaction();
        $delete_stmt = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE RoleID = ?");
        $delete_stmt->execute([$role_id_to_update]);
        $insert_stmt = $pdo->prepare("INSERT INTO tbl_role_permissions (RoleID, PermissionKey) VALUES (?, ?)");
        foreach ($submitted_permissions as $permission_key) {
            $insert_stmt->execute([$role_id_to_update, $permission_key]);
        }
        $pdo->commit();
        $_SESSION['message'] = 'دسترسی‌ها با موفقیت بروزرسانی شد.';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'خطا در بروزرسانی دسترسی‌ها: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: " . BASE_URL . "modules/users/manage_permissions.php?role_id=" . $role_id_to_update);
    exit;
}

$roles = find_all($pdo, "SELECT * FROM tbl_roles ORDER BY RoleName");
if (isset($_GET['role_id']) && is_numeric($_GET['role_id'])) {
    $selected_role_id = (int)$_GET['role_id'];
    $current_permissions_raw = find_all($pdo, "SELECT PermissionKey FROM tbl_role_permissions WHERE RoleID = ?", [$selected_role_id]);
    $role_permissions = array_column($current_permissions_raw, 'PermissionKey');
}

$pageTitle = "مدیریت دسترسی‌ها";
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>modules/users/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="card content-card">
    <div class="card-header"><h5 class="mb-0">۱. انتخاب نقش</h5></div>
    <div class="card-body">
        <form method="GET" action="manage_permissions.php" class="d-flex align-items-center">
            <div class="flex-grow-1 me-2">
                <label for="role_id_selector" class="form-label">برای ویرایش دسترسی‌ها، ابتدا یک نقش را انتخاب کنید:</label>
                <select class="form-select" id="role_id_selector" name="role_id" onchange="this.form.submit()">
                    <option value="">-- انتخاب نقش --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['RoleID']; ?>" <?php echo ($selected_role_id == $role['RoleID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['RoleName']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>
<?php if ($selected_role_id): ?>
<div class="card content-card mt-4">
    <div class="card-header"><h5 class="mb-0">۲. تخصیص دسترسی‌ها برای نقش "<?php echo htmlspecialchars(find_one_by_field($pdo, 'tbl_roles', 'RoleID', $selected_role_id)['RoleName'] ?? 'نامشخص'); ?>"</h5></div>
    <div class="card-body">
        <form method="POST" action="manage_permissions.php">
            <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
            <?php foreach ($permissions_structure as $module_key => $module_data): ?>
                <fieldset class="mb-4">
                    <legend class="h6 mb-3 p-2 bg-light rounded"><?php echo htmlspecialchars($module_data['label']); ?></legend>
                    <div class="list-group">
                        <?php foreach ($module_data['permissions'] as $permission_key_suffix => $permission_label): ?>
                             <?php $full_key = $module_key . '.' . $permission_key_suffix; ?>
                            <label class="list-group-item">
                                <input class="form-check-input me-2" type="checkbox" name="permissions[]" value="<?php echo $full_key; ?>" <?php echo in_array($full_key, $role_permissions) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($permission_label); ?>
                                <small class="text-muted ms-2">(<?php echo $full_key; ?>)</small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-success mt-3"><i class="bi bi-check2-circle"></i> ذخیره تغییرات</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

