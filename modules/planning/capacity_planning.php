<?php
require_once __DIR__ . '/../../config/init.php';
if (!has_permission('planning_constraints.planning_capacity.run')) { die('شما مجوز دسترسی به این صفحه را ندارید.'); }

$pageTitle = "برنامه‌ریزی و بازبینی ظرفیت";
include __DIR__ . '/../../templates/header.php';

// واکشی ایستگاه‌هایی که برای آن‌ها قانون تعریف شده است
$stations = $pdo->query("
    SELECT s.StationID, s.StationName 
    FROM tbl_stations s
    JOIN tbl_planning_station_capacity_rules r ON s.StationID = r.StationID
    ORDER BY s.StationName
")->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo BASE_URL; ?>modules/planning/constraints_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<div class="alert alert-info mt-3">
    <i class="bi bi-info-circle-fill me-2"></i>
    لطفاً تاریخ و ایستگاه مورد نظر را انتخاب کنید. سیستم ظرفیت پیشنهادی را بر اساس قوانین و داده‌های واقعی محاسبه می‌کند. شما می‌توانید این مقدار را قبل از تایید نهایی برای اجرای MRP ویرایش (Override) کنید.
</div>

<!-- Card: انتخاب ایستگاه و تاریخ -->
<div class="card content-card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">۱. انتخاب محدوده برنامه‌ریزی</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label for="planning_date" class="form-label">تاریخ برنامه‌ریزی</label>
                <input type="text" class="form-control" id="planning_date" placeholder="تاریخ را انتخاب کنید..." value="<?php echo jdate('Y/m/d'); ?>">
            </div>
            <div class="col-md-5">
                <label for="station_id" class="form-label">ایستگاه</label>
                <select class="form-select" id="station_id">
                    <option value="">-- انتخاب ایستگاه --</option>
                    <?php foreach ($stations as $station): ?>
                        <option value="<?php echo $station['StationID']; ?>"><?php echo htmlspecialchars($station['StationName']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" id="loadCapacityBtn">
                    <i class="bi bi-arrow-down-circle"></i> بارگذاری ظرفیت
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Card: نمایش و ویرایش ظرفیت (در ابتدا مخفی) -->
<div class="card content-card shadow-sm" id="capacityResultCard" style="display: none;">
    <div class="card-header">
        <h5 class="mb-0">۲. بازبینی و تایید ظرفیت</h5>
    </div>
    <div class="card-body">
        <div id="loadingSpinner" class="text-center" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">در حال محاسبه...</span>
            </div>
            <p class="mt-2">در حال محاسبه ظرفیت پیشنهادی...</p>
        </div>
        
        <form id="capacitySaveForm" style="display: none;">
            <input type="hidden" id="hidden_date" name="planning_date">
            <input type="hidden" id="hidden_station_id" name="station_id">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ظرفیت پیشنهادی سیستم:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="suggested_capacity" name="suggested_capacity" readonly style="background-color: #e9ecef;">
                        <span class="input-group-text" id="capacity_unit_display">--</span>
                    </div>
                    <small class="form-text text-muted" id="calculation_details">--</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="final_capacity" class="form-label">ظرفیت نهایی (تایید برنامه‌ریز): <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" id="final_capacity" name="final_capacity" required>
                        <span class="input-group-text" id="capacity_unit_display_2">--</span>
                    </div>
                    <small class="form-text text-muted">این مقدار در محاسبات MRP استفاده خواهد شد.</small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success" id="saveCapacityBtn">
                <i class="bi bi-check2-circle"></i> ذخیره ظرفیت نهایی
            </button>
            <div id="saveResult" class="mt-3"></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // 1. Setup Jalali Date Picker
    $("#planning_date").pDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true
    });

    // 2. Load Capacity Button Click
    $('#loadCapacityBtn').on('click', function() {
        const date = $('#planning_date').val();
        const stationId = $('#station_id').val();
        
        if (!date || !stationId) {
            Swal.fire('خطا', 'لطفاً تاریخ و ایستگاه را انتخاب کنید.', 'warning');
            return;
        }

        $('#capacityResultCard').show();
        $('#loadingSpinner').show();
        $('#capacitySaveForm').hide();
        $('#saveResult').html('');

        $.ajax({
            url: '<?php echo BASE_URL; ../../api/api_get_suggested_capacity.php',
            type: 'POST',
            data: {
                planning_date: date,
                station_id: stationId
            },
            dataType: 'json',
            success: function(response) {
                $('#loadingSpinner').hide();
                if (response.success) {
                    const data = response.data;
                    $('#hidden_date').val(date);
                    $('#hidden_station_id').val(stationId);
                    
                    $('#suggested_capacity').val(data.suggested_capacity.toLocaleString());
                    // اگر قبلا ذخیره شده، مقدار نهایی را نمایش بده، در غیر این صورت مقدار پیشنهادی را
                    $('#final_capacity').val(data.final_capacity > 0 ? data.final_capacity : data.suggested_capacity); 
                    
                    $('#capacity_unit_display').text(data.capacity_unit);
                    $('#capacity_unit_display_2').text(data.capacity_unit);
                    $('#calculation_details').text(data.details);
                    
                    $('#capacitySaveForm').show();
                } else {
                    Swal.fire('خطا', response.message, 'error');
                }
            },
            error: function(jqXHR) {
                $('#loadingSpinner').hide();
                let errorMsg = 'خطای سرور در محاسبه ظرفیت. ' + (jqXHR.responseJSON ? jqXHR.responseJSON.message : '');
                Swal.fire('خطا', errorMsg, 'error');
            }
        });
    });
    
    // 3. Save Capacity Form Submit
    $('#capacitySaveForm').on('submit', function(e) {
        e.preventDefault();
        
        const finalCapacity = $('#final_capacity').val();
        if (finalCapacity === '' || finalCapacity < 0) {
            Swal.fire('خطا', 'مقدار ظرفیت نهایی نمی‌تواند خالی یا منفی باشد.', 'warning');
            return;
        }
        
        $('#saveCapacityBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> در حال ذخیره...');
        $('#saveResult').html('');

        $.ajax({
            url: ../../api/api_save_capacity_override.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#saveResult').html('<div class="alert alert-success">' + response.message + '</div>');
                } else {
                    $('#saveResult').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(jqXHR) {
                let errorMsg = 'خطای سرور در ذخیره‌سازی. ' + (jqXHR.responseJSON ? jqXHR.responseJSON.message : '');
                $('#saveResult').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            },
            complete: function() {
                $('#saveCapacityBtn').prop('disabled', false).html('<i class="bi bi-check2-circle"></i> ذخیره ظرفیت نهایی');
            }
        });
    });
});
</script>

