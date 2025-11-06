<?php
// daily_production_select.php
// گام ۲: نمایش نیازمندی‌های خالص و انتخاب توسط برنامه‌ریز

include_once __DIR__ . '/../../config/init.php';
include_once __DIR__ . '/../../templates/header.php';

// --- شبیه‌سازی دریافت داده‌های نیازمندی خالص (Net Requirements) ---
// TODO: این تابع باید با منطق واقعی شما برای واکشی نیازمندی‌های خالص جایگزین شود.
// این تابع باید لیست قطعاتی که نیاز به تولید دارند را برگرداند.
function get_net_requirements($db) {
    // مثال: (این داده‌ها باید از ماژول MRP شما بیایند)
    // 'current_wip_location_id' شناسه ایستگاهی است که قطعه در حال حاضر در آن قرار دارد.
    // اگر 0 باشد، یعنی هنوز وارد خط نشده است.
    return [
        ['part_id' => 101, 'part_name' => 'قطعه X - مدل A', 'required_qty' => 800, 'current_wip_location_id' => 0],
        ['part_id' => 102, 'part_name' => 'قطعه Y - مدل B', 'required_qty' => 450, 'current_wip_location_id' => 2], // مثلا 2 یعنی ایستگاه پرس
        ['part_id' => 103, 'part_name' => 'قطعه Z - مدل C', 'required_qty' => 1200, 'current_wip_location_id' => 0],
        ['part_id' => 104, 'part_name' => 'قطعه C - ناسازگار', 'required_qty' => 300, 'current_wip_location_id' => 1], // مثلا 1 یعنی برش
    ];
}

$net_requirements = get_net_requirements($db);

?>

<div class="container mt-4" style="direction: rtl; font-family: Tahoma;">
    <h2 class="text-center mb-4">گام ۱: انتخاب قطعات برای برنامه تولید روزانه</h2>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="daily_production_confirm.php" method="POST">
                <table class="table table-hover table-striped">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 5%;" class="text-center">انتخاب</th>
                            <th>نام قطعه</th>
                            <th>تعداد مورد نیاز (خالص)</th>
                            <th>موقعیت فعلی (WIP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($net_requirements as $part): ?>
                            <?php
                            // TODO: تابع واقعی برای دریافت نام ایستگاه بر اساس ID
                            $wip_location_name = ($part['current_wip_location_id'] == 0) ? 'انبار مواد اولیه' : 'ایستگاه ' . $part['current_wip_location_id'];
                            ?>
                            <tr>
                                <td class="text-center">
                                    <!-- 
                                        ما در اینجا یک آرایه انجمنی (associative) به POST ارسال می‌کنیم
                                        کلید (key) همان part_id است
                                        مقدار (value) شامل 'required_qty' و 'wip_id' است
                                    -->
                                    <input type="checkbox" 
                                           name="selected_parts[<?php echo $part['part_id']; ?>]" 
                                           value="<?php echo http_build_query(['required' => $part['required_qty'], 'wip_id' => $part['current_wip_location_id']]); ?>"
                                           class="form-check-input"
                                           style="transform: scale(1.5);">
                                </td>
                                <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                                <td><?php echo htmlspecialchars($part['required_qty']); ?></td>
                                <td><?php echo htmlspecialchars($wip_location_name); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <hr>
                <div class="text-left">
                    <button type="submit" class="btn btn-primary btn-lg">
                        مرحله بعد (بررسی ظرفیت و محدودیت‌ها) &larr;
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../../templates/footer.php';
?>
