<?php
require_once __DIR__ . '/../../config/init.php';
// مجوز انبار را چک می‌کند
if (!has_permission('warehouse.view')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$pageTitle = "داشبورد هشدارهای موجودی انبار";
include __DIR__ . '/../../templates/header.php';

// --- 1. Fetch Raw Materials Alerts ---
$raw_items_sql = "
    SELECT 
        i.ItemName, 
        c.CategoryName, 
        u.Symbol, 
        i.SafetyStock,
        COALESCE(SUM(t.Quantity), 0) as CurrentBalance
    FROM tbl_raw_items i
    JOIN tbl_raw_categories c ON i.CategoryID = c.CategoryID
    JOIN tbl_units u ON i.UnitID = u.UnitID
    LEFT JOIN tbl_raw_transactions t ON i.ItemID = t.ItemID
    GROUP BY i.ItemID, i.ItemName, c.CategoryName, u.Symbol, i.SafetyStock
    HAVING i.SafetyStock IS NOT NULL AND CurrentBalance <= i.SafetyStock
    ORDER BY c.CategoryName, i.ItemName
";
$raw_alerts = find_all($pdo, $raw_items_sql);

// --- 2. Fetch Misc Inventory Alerts ---
$misc_items_sql = "
    SELECT 
        i.ItemName, 
        c.CategoryName, 
        u.Symbol, 
        i.SafetyStock,
        COALESCE(SUM(t.Quantity), 0) as CurrentBalance
    FROM tbl_misc_items i
    JOIN tbl_misc_categories c ON i.CategoryID = c.CategoryID
    JOIN tbl_units u ON i.UnitID = u.UnitID
    LEFT JOIN tbl_misc_transactions t ON i.ItemID = t.ItemID
    GROUP BY i.ItemID, i.ItemName, c.CategoryName, u.Symbol, i.SafetyStock
    HAVING i.SafetyStock IS NOT NULL AND CurrentBalance <= i.SafetyStock
    ORDER BY c.CategoryName, i.ItemName
";
$misc_alerts = find_all($pdo, $misc_items_sql);
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?> (مواد اولیه و متفرقه)</h1>
    <!-- لینک بازگشت به داشبورد انبار اصلاح شد -->
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت به ماژول انبار</a>
</div>
<p class="lead">این داشبورد، اقلامی از **انبار مواد اولیه** و **انبار متفرقه** را که موجودی آن‌ها به "نقطه سفارش" (Safety Stock) رسیده یا از آن کمتر است، نمایش می‌دهد.</p>

<div class="row">
    <!-- Raw Materials Alerts -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">انبار مواد اولیه (ورق، مفتول و...)</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="p-2">نام ماده</th>
                                <th class="p-2">دسته‌بندی</th>
                                <th class="p-2">موجودی فعلی</th>
                                <th class="p-2">نقطه سفارش</th>
                                <th class="p-2">واحد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($raw_alerts)): ?>
                                <tr><td colspan="5" class="text-center p-3 text-success">هیچ ماده اولیه‌ای به نقطه سفارش نرسیده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($raw_alerts as $item): ?>
                                <tr class="table-danger">
                                    <td class="p-2"><?php echo htmlspecialchars($item['ItemName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['CategoryName']); ?></td>
                                    <td class="p-2 fw-bold"><?php echo number_format($item['CurrentBalance'], 2); ?></td>
                                    <td class="p-2"><?php echo number_format($item['SafetyStock'], 2); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['Symbol']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Misc Inventory Alerts -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">انبار متفرقه (کارتن، شیمیایی و...)</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead>
                             <tr>
                                <th class="p-2">نام کالا</th>
                                <th class="p-2">دسته‌بندی</th>
                                <th class="p-2">موجودی فعلی</th>
                                <th class="p-2">نقطه سفارش</th>
                                <th class="p-2">واحد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($misc_alerts)): ?>
                                <tr><td colspan="5" class="text-center p-3 text-success">هیچ کالای متفرقه‌ای به نقطه سفارش نرسیده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($misc_alerts as $item): ?>
                                <tr class="table-danger">
                                    <td class="p-2"><?php echo htmlspecialchars($item['ItemName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['CategoryName']); ?></td>
                                    <td class="p-2 fw-bold"><?php echo number_format($item['CurrentBalance'], 2); ?></td>
                                    <td class="p-2"><?php echo number_format($item['SafetyStock'], 2); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['Symbol']); ?></td>
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
