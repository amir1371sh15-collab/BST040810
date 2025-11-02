<?php
require_once __DIR__ . '/../../config/init.php';
// Add permission checks
if (!has_permission('planning.sales_orders.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

$pageTitle = "مدیریت سفارشات فروش";
include_once __DIR__ . '/../../templates/header.php';

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- Handle POST Requests (Add/Delete) ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_id'])) {
            // --- DELETE ---
            $delete_id = (int)$_POST['delete_id'];
            $result = delete_record($pdo, 'tbl_sales_orders', $delete_id, 'SalesOrderID');
            if ($result['success']) {
                $_SESSION['message'] = 'سفارش با موفقیت حذف شد.';
                $_SESSION['message_type'] = 'success';
            } else {
                throw new Exception($result['message']);
            }

        } else {
            // --- ADD ---
            $part_id = (int)($_POST['part_id'] ?? 0);
            $quantity = (int)($_POST['quantity_required'] ?? 0);
            $due_date = $_POST['due_date'] ?? '';

            if (empty($part_id) || empty($quantity) || empty($due_date)) {
                throw new Exception('تمام فیلدها (قطعه، تعداد و تاریخ) الزامی هستند.');
            }

            $data = [
                'PartID' => $part_id,
                'QuantityRequired' => $quantity,
                'DueDate' => $due_date,
                'Status' => 'Open' // default status
            ];
            
            $result = insert_record($pdo, 'tbl_sales_orders', $data);
            
            if ($result['success']) {
                $_SESSION['message'] = 'سفارش جدید با موفقیت ثبت شد.';
                $_SESSION['message_type'] = 'success';
            } else {
                throw new Exception($result['message']);
            }
        }
        
        // Redirect to avoid form resubmission
        header("Location: sales_orders.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'خطا: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header("Location: sales_orders.php");
    exit;
}

// --- Fetch Data for Display ---
$families = find_all($pdo, "SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$open_orders = find_all($pdo, "
    SELECT so.*, p.PartName, p.PartCode 
    FROM tbl_sales_orders so
    JOIN tbl_parts p ON so.PartID = p.PartID
    WHERE so.Status = 'Open'
    ORDER BY so.DueDate ASC
");

?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Add New Order Form -->
            <div class="card content-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">ثبت سفارش فروش جدید</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="sales_orders.php" id="add-order-form">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="family_id_selector" class="form-label">خانواده محصول</label>
                                <select class="form-select" id="family_id_selector" name="family_id" required>
                                    <option value="" selected disabled>انتخاب کنید...</option>
                                    <?php foreach ($families as $family): ?>
                                        <option value="<?php echo $family['FamilyID']; ?>"><?php echo htmlspecialchars($family['FamilyName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="part_id" class="form-label">نام محصول نهایی</label>
                                <select class="form-select" id="part_id" name="part_id" required disabled>
                                    <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="quantity_required" class="form-label">تعداد مورد نیاز</label>
                                <input type="number" class="form-control" id="quantity_required" name="quantity_required" required>
                            </div>
                            <div class="col-md-2">
                                <label for="due_date" class="form-label">تاریخ تحویل</label>
                                <input type="text" class="form-control" id="due_date" name="due_date" placeholder="YYYY-MM-DD" required>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">ثبت</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Open Orders List -->
            <div class="card content-card">
                <div class="card-header">
                    <h5 class="mb-0">سفارشات فروش باز (تقاضای کل)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="p-3">کد قطعه</th>
                                    <th class="p-3">نام قطعه</th>
                                    <th class="p-3">تعداد مورد نیاز</th>
                                    <th class="p-3">تاریخ تحویل</th>
                                    <th class="p-3">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($open_orders)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted p-4">در حال حاضر هیچ سفارش بازی وجود ندارد.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($open_orders as $order): ?>
                                    <tr>
                                        <td class="p-3"><?php echo htmlspecialchars($order['PartCode']); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($order['PartName']); ?></td>
                                        <td class="p-3"><?php echo number_format($order['QuantityRequired']); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($order['DueDate']); ?></td>
                                        <td class="p-3">
                                            <form method="POST" action="sales_orders.php" onsubmit="return confirm('آیا از حذف این سفارش مطمئن هستید؟');" class="d-inline">
                                                <input type="hidden" name="delete_id" value="<?php echo $order['SalesOrderID']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="حذف سفارش">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include_once __DIR__ . '/../../templates/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('#family_id_selector, #part_id').select2({
        theme: 'bootstrap-5',
        dir: 'rtl'
    });

    // Load parts based on family selection
    $('#family_id_selector').on('change', function() {
        const familyId = $(this).val();
        const partSelector = $('#part_id');
        
        partSelector.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');

        if (!familyId) {
            partSelector.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }

        $.ajax({
            url: '../../api/api_get_all_parts_grouped.php', // API با مجوز اصلاح شده
            type: 'GET',
            data: { family_id: familyId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    partSelector.html('<option value="">-- انتخاب محصول --</option>');
                    $.each(response.data, function(family, parts) {
                        $.each(parts, function(i, part) {
                            partSelector.append($('<option>', {
                                value: part.PartID,
                                text: part.PartName + ' (' + part.PartCode + ')'
                            }));
                        });
                    });
                    partSelector.prop('disabled', false);
                } else {
                    partSelector.html('<option value="">قطعه‌ای یافت نشد</option>');
                }
            },
            error: function() {
                partSelector.html('<option value="">خطا در بارگذاری قطعات</option>');
            }
        });
    });
});
</script>
