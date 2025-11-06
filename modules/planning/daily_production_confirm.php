<?php
// daily_production_confirm.php
// گام ۳ و ۴: نمایش ظرفیت، مقدار پیشنهادی، هشداره
// و دریافت مقدار نهایی (قابل ویرایش) از برنامه‌ریز

include_once __DIR__ . '/../../config/init.php';
include_once __DIR__ . '/../../templates/header.php';

// بررسی اینکه آیا داده‌ای از صفحه قبل ارسال شده است
if (!isset($_POST['selected_parts']) || empty($_POST['selected_parts'])) {
    echo "<div class='alert alert-danger text-center' style='direction: rtl; font-family: Tahoma;'>هیچ قطعه‌ای برای برنامه‌ریزی انتخاب نشده است. لطفاً به <a href='daily_production_select.php'>صفحه انتخاب</a> بازگردید.</div>";
    include_once __DIR__ . '/../../templates/footer.php';
    exit;
}

// داده‌های ارسال شده از فرم قبلی
// $selected_parts آرایه‌ای شبیه به [part_id => "required=800&wip_id=0"]
$selected_parts_raw = $_POST['selected_parts'];
$selected_parts_data = []; // آرایه‌ای برای نگهداری داده‌های قطعات
$part_ids_for_js = []; // آرایه‌ای از IDها برای ارسال به API محدودیت‌ها

// TODO: تابع واقعی برای دریافت نام قطعه
function get_part_name_by_id($db, $part_id) {
    $parts_db = [
        101 => 'قطعه X - مدل A',
        102 => 'قطعه Y - مدل B',
        103 => 'قطعه Z - مدل C',
        104 => 'قطعه C - ناسازگار',
    ];
    return isset($parts_db[$part_id]) ? $parts_db[$part_id] : 'قطعه ناشناخته';
}

// پردازش داده‌های ورودی
foreach ($selected_parts_raw as $part_id => $query_string) {
    parse_str($query_string, $data); // رشته "required=800&wip_id=0" را به آرایه تبدیل می‌کند
    $part_id_int = (int)$part_id;
    $selected_parts_data[$part_id_int] = [
        'part_name' => get_part_name_by_id($db, $part_id_int),
        'required_qty' => (int)$data['required'],
        'wip_id' => (int)$data['wip_id']
    ];
    $part_ids_for_js[] = $part_id_int; // برای API محدودیت‌ها
}

?>

<div class="container mt-4" style="direction: rtl; font-family: Tahoma;">
    <h2 class="text-center mb-4">گام ۲: بررسی ظرفیت و تخصیص نهایی</h2>
    
    <!-- بخش نمایش هشدارها (گام ۴) -->
    <div id="warnings-container" class="mb-3">
        <div class="alert alert-info">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            در حال بررسی محدودیت‌ها و توصیه‌ها...
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <!-- فرم نهایی که به API ذخیره‌سازی ارسال می‌شود (گام ۵) -->
            <form id="confirm-plan-form">
                <table class="table table-bordered table-vcenter">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th>نام قطعه</th>
                            <th>نیاز خالص</th>
                            <th>ایستگاه بعدی (گام ۳)</th>
                            <th>ظرفیت ایستگاه (گام ۳)</th>
                            <th>تعداد پیشنهادی</th>
                            <th style="width: 15%;">تعداد نهایی (قابل تغییر)</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php foreach ($selected_parts_data as $part_id => $data): ?>
                            <!-- 
                                هر سطر یک ID منحصر به فرد دارد
                                جاوا اسکریپت سلول‌های مربوط به API را پر خواهد کرد
                            -->
                            <tr id="row-<?php echo $part_id; ?>" data-part-id="<?php echo $part_id; ?>" data-wip-id="<?php echo $data['wip_id']; ?>" data-required="<?php echo $data['required_qty']; ?>">
                                
                                <!-- ستون‌های ثابت -->
                                <td class="align-middle"><strong><?php echo htmlspecialchars($data['part_name']); ?></strong></td>
                                <td class="align-middle"><?php echo $data['required_qty']; ?></td>
                                
                                <!-- ستون‌هایی که با AJAX پر می‌شوند -->
                                <td class="station-name align-middle">
                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                </td>
                                <td class="station-capacity align-middle">...</td>
                                <td class="suggested-qty align-middle">...</td>
                                
                                <!-- ستون ورودی نهایی -->
                                <td class="align-middle">
                                    <input type="number" 
                                           name="final_qty[<?php echo $part_id; ?>]" 
                                           class="form-control form-control-lg text-center final-qty-input" 
                                           value="0" 
                                           min="0">
                                    
                                    <!-- ما به شناسه ایستگاه برای ذخیره نهایی نیاز داریم -->
                                    <input type="hidden" 
                                           name="station_id[<?php echo $part_id; ?>]" 
                                           class="station-id-hidden" 
                                           value="0">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <hr>
                <div class="text-left">
                    <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='daily_production_select.php';">
                        &rarr; بازگشت و انتخاب مجدد
                    </button>
                    <button type="submit" id="save-plan-btn" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> ذخیره نهایی برنامه و ایجاد دستور کار
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    // --- گام ۳: واکشی اطلاعات ایستگاه و ظرفیت ---
    function fetchStationData() {
        // برای هر سطر جدول، یک درخواست API ارسال می‌کنیم
        $('#confirm-plan-form tbody tr').each(function() {
            const $row = $(this);
            const partId = $row.data('part-id');
            const wipId = $row.data('wip-id');
            const requiredQty = $row.data('required');

            // فراخوانی API گام ۳
            $.ajax({
                url: '../api/get_next_station_capacity.php',
                type: 'GET',
                data: {
                    part_id: partId,
                    wip_id: wipId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const station = response.station;
                        const capacity = parseInt(response.capacity) || 0;

                        // محاسبه مقدار پیشنهادی سیستم
                        // مقدار پیشنهادی = حداقلِ (نیاز خالص، ظرفیت ایستگاه)
                        const suggestedQty = (station.id == 99) ? 0 : Math.min(requiredQty, capacity); // اگر ایستگاه انبار محصول بود، 0 پیشنهاد بده

                        // پر کردن مقادیر در جدول
                        $row.find('.station-name').text(station.name).css('color', station.id == 99 ? 'blue' : 'black');
                        $row.find('.station-capacity').text(station.id == 99 ? 'N/A' : capacity);
                        $row.find('.suggested-qty').text(suggestedQty).css('font-weight', 'bold');
                        
                        // تنظیم مقدار پیش‌فرض در فیلد قابل ویرایش
                        $row.find('.final-qty-input').val(suggestedQty);
                        
                        // ذخیره شناسه ایستگاه در فیلد مخفی برای ارسال نهایی
                        $row.find('.station-id-hidden').val(station.id);

                    } else {
                        // در صورت خطا در API
                        $row.find('td').slice(2, 6).text('خطا در واکشی').css('color', 'red');
                    }
                },
                error: function() {
                    $row.find('td').slice(2, 6).text('خطای ارتباط با سرور').css('color', 'red');
                }
            });
        });
    }

    // --- گام ۴: بررسی محدودیت‌ها و توصیه‌ها ---
    function checkConstraints() {
        const partIds = <?php echo json_encode($part_ids_for_js); ?>;
        
        // فراخوانی API گام ۴
        $.ajax({
            url: '../api/check_production_constraints.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ part_ids: partIds }),
            dataType: 'json',
            success: function(response) {
                const $container = $('#warnings-container');
                $container.empty(); // پاک کردن پیام "در حال بررسی"

                if (response.success) {
                    if (response.warnings.length > 0) {
                        // نمایش هشدارها
                        response.warnings.forEach(function(warning) {
                            $container.append('<div class="alert alert-warning"><strong>' + warning + '</strong></div>');
                        });
                    } else {
                        // اگر هشداری نبود
                        $container.append('<div class="alert alert-success"><strong>بررسی محدودیت‌ها انجام شد. هیچ تداخل یا توصیه خاصی یافت نشد.</strong></div>');
                    }
                } else {
                    $container.append('<div class="alert alert-danger"><strong>خطا در بررسی محدودیت‌ها:</strong> ' + response.message + '</div>');
                }
            },
            error: function() {
                $('#warnings-container').empty().append('<div class="alert alert-danger"><strong>خطای ارتباطی در بررسی محدودیت‌ها.</strong></div>');
            }
        });
    }

    // --- گام ۵: ذخیره نهایی برنامه ---
    $('#confirm-plan-form').submit(function(e) {
        e.preventDefault(); // جلوگیری از رفرش صفحه
        
        if (!confirm('آیا از ذخیره این برنامه تولید اطمینان دارید؟ این عملیات دستور کارهای مجزا ایجاد خواهد کرد.')) {
            return;
        }

        const $btn = $('#save-plan-btn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> در حال ذخیره‌سازی...');

        const formData = $(this).serialize(); // جمع‌آوری تمام داده‌های فرم (final_qty و station_id)

        // فراخوانی API گام ۵ (که هنوز نساخته‌ایم، اما آدرس آن را می‌نویسیم)
        $.ajax({
            url: '../api/save_daily_plan.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('برنامه تولید روزانه با موفقیت ذخیره شد. دستور کارها ایجاد شدند.');
                    // انتقال کاربر به صفحه لیست دستور کارها
                    window.location.href = 'work_order_list.php'; 
                } else {
                    alert('خطا در ذخیره‌سازی: \n' + response.message);
                    $btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره نهایی برنامه');
                }
            },
            error: function() {
                alert('خطای بحرانی در ارتباط با سرور.');
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره نهایی برنامه');
            }
        });
    });


    // --- اجرای توابع هنگام بارگذاری صفحه ---
    fetchStationData();
    checkConstraints();
});
</script>


<?php
include_once __DIR__ . '/../../templates/footer.php';
?>
