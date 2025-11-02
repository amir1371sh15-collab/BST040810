<?php
include_once '../../config/init.php';
include_once '../../templates/header.php';

// check_permission('planning_constraints.view');

// 1. واکشی ایستگاه‌های تولیدی (فیلتر شده)
$stmt_stations = $pdo->query("SELECT StationID, StationName FROM tbl_stations WHERE StationType = 'Production' ORDER BY StationName");
$stations = $stmt_stations->fetchAll(PDO::FETCH_ASSOC);

// واکشی همه خانواده‌های محصول (برای مودال)
$stmt_families = $pdo->query("SELECT FamilyID, FamilyName FROM tbl_part_families ORDER BY FamilyName");
$all_families = $stmt_families->fetchAll(PDO::FETCH_ASSOC);

// 2. بررسی ایستگاه انتخاب شده
$selected_station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : 0;
$selected_station_name = '';

if ($selected_station_id > 0) {
    foreach ($stations as $station) {
        if ($station['StationID'] == $selected_station_id) {
            $selected_station_name = $station['StationName'];
            break;
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            
            <!-- Card: انتخاب ایستگاه -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">مدیریت ظرفیت و محدودیت‌های ایستگاه</h5>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> بازگشت به برنامه‌ریزی
                    </a>
                </div>
                <div class="card-body">
                    <p>لطفاً ایستگاه تولیدی را که می‌خواهید ظرفیت آن را تنظیم کنید، انتخاب نمایید.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="station-select" class="form-label">انتخاب ایستگاه:</label>
                            <select id="station-select" class="form-select">
                                <option value="">-- یک ایستگاه را انتخاب کنید --</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo $station['StationID']; ?>" <?php echo ($station['StationID'] == $selected_station_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station['StationName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: فرم داینامیک تنظیمات -->
            <?php if ($selected_station_id > 0): ?>
            <div id="dynamic-form-container">
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0">تنظیمات ظرفیت برای: <?php echo htmlspecialchars($selected_station_name); ?></h5></div>
                    <div class="card-body">
                        
                        <form id="capacity-form">
                            <input type="hidden" name="station_id" value="<?php echo $selected_station_id; ?>">
                            
                            <?php
                            try {
                                switch ($selected_station_id) {
                                    
                                    // --- ایستگاه پرسکاری (2) و پیچ‌سازی (6) ---
                                    case 2: // پرسکاری
                                    case 6: // پیچ سازی
                                        $machine_type = ($selected_station_id == 2) ? 'پرس' : 'پیچ سازی';
                                        ?>
                                        <input type="hidden" name="form_type" value="OEE_Machine">
                                        <h3>تنظیمات ظرفیت مبتنی بر OEE</h3>
                                        <p>زمان در دسترس و راندمان (OEE) را وارد کنید تا ظرفیت به صورت خودکار محاسبه شود.</p>
                                        
                                        <?php
                                        $stmt_machines = $pdo->prepare("SELECT MachineID, MachineName, strokes_per_minute FROM tbl_machines WHERE MachineType = ? AND Status = 'Active' ORDER BY MachineName");
                                        $stmt_machines->execute([$machine_type]);
                                        $machines = $stmt_machines->fetchAll(PDO::FETCH_ASSOC);

                                        $stmt_rules = $pdo->prepare("SELECT MachineID, StandardValue, FinalCapacity FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'OEE'");
                                        $stmt_rules->execute([$selected_station_id]);
                                        $rules_raw = $stmt_rules->fetchAll(PDO::FETCH_GROUP);
                                        
                                        $rules = [];
                                        foreach ($rules_raw as $key => $value) {
                                            $rules[$key] = $value[0]; // [MachineID => ['StandardValue' => OEE, 'FinalCapacity' => AvailableTime]]
                                        }
                                        ?>
                                        
                                        <table class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>نام دستگاه</th>
                                                    <th>ضرب در دقیقه (SPM)</th>
                                                    <th>زمان در دسترس (دقیقه)</th>
                                                    <th>راندمان (OEE) %</th>
                                                    <th>ظرفیت محاسبه شده (قطعه/روز)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($machines as $machine):
                                                $machine_id = $machine['MachineID'];
                                                $spm = floatval($machine['strokes_per_minute'] ?? 0);
                                                $existing_oee = isset($rules[$machine_id]['StandardValue']) ? floatval($rules[$machine_id]['StandardValue']) : 80;
                                                $existing_time = isset($rules[$machine_id]['FinalCapacity']) ? floatval($rules[$machine_id]['FinalCapacity']) : 480;
                                                
                                                $calculated_capacity = ($spm * $existing_time) * ($existing_oee / 100);
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($machine['MachineName']); ?></td>
                                                    <td><?php echo $spm; ?></td>
                                                    <td><input type="number" class="form-control capacity-input" name="rules[<?php echo $machine_id; ?>][available_time]" value="<?php echo $existing_time; ?>" data-spm="<?php echo $spm; ?>"></td>
                                                    <td><input type="number" class="form-control capacity-input" name="rules[<?php echo $machine_id; ?>][oee]" value="<?php echo $existing_oee; ?>" data-spm="<?php echo $spm; ?>"></td>
                                                    <td class="calculated-capacity"><?php echo number_format($calculated_capacity, 0); ?></td>
                                                    <input type="hidden" name="rules[<?php echo $machine_id; ?>][spm]" value="<?php echo $spm; ?>">
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php
                                        break;

                                    // --- ایستگاه مونتاژ (12) ---
                                    case 12: // مونتاژ
                                        ?>
                                        <input type="hidden" name="form_type" value="Assembly">
                                        <h3>تنظیمات ظرفیت مونتاژ</h3>
                                        <p>ظرفیت را برای دو نوع دستگاه مونتاژ (کوچک و بزرگ) به صورت مجزا وارد کنید.</p>
                                        <?php
                                        $stmt_rules = $pdo->prepare("SELECT CalculationMethod, FinalCapacity FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND (CalculationMethod = 'AssemblySmall' OR CalculationMethod = 'AssemblyLarge')");
                                        $stmt_rules->execute([$selected_station_id]);
                                        $rules = $stmt_rules->fetchAll(PDO::FETCH_KEY_PAIR); 

                                        $capacity_small = isset($rules['AssemblySmall']) ? htmlspecialchars($rules['AssemblySmall']) : '70000';
                                        $capacity_large = isset($rules['AssemblyLarge']) ? htmlspecialchars($rules['AssemblyLarge']) : '';
                                        ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">ظرفیت دستگاه‌های مونتاژ کوچک (قطعه/روز)</label>
                                                <input type="number" class="form-control" name="rules[AssemblySmall][capacity]" value="<?php echo $capacity_small; ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">ظرفیت دستگاه‌های مونتاژ بزرگ (قطعه/روز)</label>
                                                <input type="number" class="form-control" name="rules[AssemblyLarge][capacity]" value="<?php echo $capacity_large; ?>">
                                            </div>
                                        </div>
                                        <?php
                                        break;

                                    // --- ایستگاه دنده زنی (3) و رول (5) ---
                                    case 3: // دنده زنی
                                    case 5: // رول
                                        $method = ($selected_station_id == 3) ? 'Gearing' : 'Rolling';
                                        ?>
                                        <input type="hidden" name="form_type" value="<?php echo $method; ?>_Part">
                                        <h3>تنظیمات ظرفیت <?php echo $selected_station_name; ?> (بر اساس محصول)</h3>
                                        <p>ظرفیت تولید (کیلوگرم در روز) را برای هر محصول مشخص کنید.</p>
                                        
                                        <table class="table table-bordered" id="product-capacity-table">
                                            <thead><tr><th>محصول</th><th>ظرفیت (KG/Day)</th><th>عملیات</th></tr></thead>
                                            <tbody>
                                            <?php
                                            // حالا که دستگاه مهم نیست، دیگر به ماشین‌ها join نمی‌زنیم
                                            $stmt_rules = $pdo->prepare("
                                                SELECT r.RuleID, r.PartID, r.FinalCapacity, p.PartName, p.FamilyID
                                                FROM tbl_planning_station_capacity_rules r
                                                LEFT JOIN tbl_parts p ON p.PartID = r.PartID
                                                WHERE r.StationID = ? AND r.CalculationMethod = ?
                                            ");
                                            $stmt_rules->execute([$selected_station_id, $method]);
                                            $rules = $stmt_rules->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($rules as $rule):
                                            ?>
                                                <tr id="rule-row-<?php echo $rule['RuleID']; ?>">
                                                     <td><?php echo htmlspecialchars($rule['PartName'] ?? 'N/A'); ?></td>
                                                     <td><input type="number" class="form-control" name="rules[<?php echo $rule['RuleID']; ?>][capacity]" value="<?php echo htmlspecialchars($rule['FinalCapacity']); ?>"></td>
                                                     <td>
                                                        <button type="button" class="btn btn-sm btn-info edit-row-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#addRuleModal"
                                                            data-rule-id="<?php echo $rule['RuleID']; ?>"
                                                            data-family-id="<?php echo $rule['FamilyID']; ?>"
                                                            data-part-id="<?php echo $rule['PartID']; ?>"
                                                            data-capacity="<?php echo htmlspecialchars($rule['FinalCapacity']); ?>">
                                                            ویرایش
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-row-btn" data-rule-id="<?php echo $rule['RuleID']; ?>">حذف</button>
                                                     </td>
                                                </tr>
                                            <?php
                                            endforeach;
                                            ?>
                                            </tbody>
                                        </table>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addRuleModal" id="add-new-rule-btn">
                                            افزودن ردیف جدید
                                        </button>
                                        <?php
                                        break;

                                    // --- ایستگاه آبکاری (4) ---
                                    case 4: // آبکاری
                                        ?>
                                        <input type="hidden" name="form_type" value="Plating">
                                        <h3>تنظیمات ظرفیت آبکاری</h3>
                                        <p>ظرفیت کل ایستگاه آبکاری را بر اساس واحد مورد نظر وارد کنید.</p>
                                        <?php
                                        $stmt_rule = $pdo->prepare("SELECT FinalCapacity, CapacityUnit FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'PlatingManHours' LIMIT 1");
                                        $stmt_rule->execute([$selected_station_id]);
                                        $rule = $stmt_rule->fetch(PDO::FETCH_ASSOC);

                                        $capacity = $rule ? htmlspecialchars($rule['FinalCapacity']) : '';
                                        $unit = $rule ? htmlspecialchars($rule['CapacityUnit']) : 'KG/ManHour';
                                        ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">ظرفیت</label>
                                                <input type="number" class="form-control" name="rules[PlatingManHours][capacity]" value="<?php echo $capacity; ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">واحد ظرفیت</label>
                                                <select class="form-select" name="rules[PlatingManHours][unit]">
                                                    <option value="KG/ManHour" <?php echo ($unit == 'KG/ManHour' ? 'selected' : ''); ?>>کیلوگرم بر نفر ساعت</option>
                                                    <option value="Barrels/ManHour" <?php echo ($unit == 'Barrels/ManHour' ? 'selected' : ''); ?>>بارل بر نفر ساعت</option>
                                                </select>
                                            </div>
                                        </div>
                                        <?php
                                        break;

                                    // --- ایستگاه بسته‌بندی (10) ---
                                    case 10: // بسته بندی
                                        ?>
                                        <input type="hidden" name="form_type" value="Packaging">
                                        <h3>تنظیمات ظرفیت بسته‌بندی</h3>
                                        <p>ظرفیت کل ایستگاه بسته‌بندی را بر اساس "کارتن در روز" وارد کنید.</p>
                                        <?php
                                        $stmt_rule = $pdo->prepare("SELECT FinalCapacity FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'Packaging' LIMIT 1");
                                        $stmt_rule->execute([$selected_station_id]);
                                        $capacity = $stmt_rule->fetchColumn();
                                        ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">ظرفیت (کارتن در روز)</label>
                                                <input type="number" class="form-control" name="rules[Packaging][capacity]" value="<?php echo htmlspecialchars($capacity ?: ''); ?>">
                                                <input type="hidden" name="rules[Packaging][unit]" value="Cartons/Day">
                                            </div>
                                        </div>
                                        <?php
                                        break;

                                    // --- سایر ایستگاه‌ها (ظرفیت ثابت) ---
                                    default:
                                        ?>
                                        <input type="hidden" name="form_type" value="FixedAmount">
                                        <h3>تنظیمات ظرفیت ثابت</h3>
                                        <p>یک ظرفیت ثابت (پیش‌فرض) برای این ایستگاه وارد کنید.</p>
                                        <?php
                                        $stmt_rule = $pdo->prepare("SELECT FinalCapacity, CapacityUnit FROM tbl_planning_station_capacity_rules WHERE StationID = ? AND CalculationMethod = 'FixedAmount' LIMIT 1");
                                        $stmt_rule->execute([$selected_station_id]);
                                        $rule = $stmt_rule->fetch(PDO::FETCH_ASSOC);

                                        $capacity = $rule ? htmlspecialchars($rule['FinalCapacity']) : '';
                                        $unit = $rule ? htmlspecialchars($rule['CapacityUnit']) : 'KG/Day';
                                        
                                        $common_units = ['KG/Day', 'Pieces/Day', 'Cartons/Day', 'KG/ManHour', 'Barrels/ManHour'];
                                        ?>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">ظرفیت</label>
                                                <input type="number" class="form-control" name="rules[FixedAmount][capacity]" value="<?php echo $capacity; ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">واحد ظرفیت</label>
                                                <select class="form-select" name="rules[FixedAmount][unit]">
                                                    <?php
                                                    foreach ($common_units as $common_unit) {
                                                        echo "<option value='{$common_unit}' " . ($unit == $common_unit ? 'selected' : '') . ">{$common_unit}</option>";
                                                    }
                                                    // اگر واحد ذخیره شده در لیست رایج نبود، آن را به عنوان یک گزینه اضافه کن
                                                    if (!in_array($unit, $common_units) && !empty($unit)) {
                                                        echo "<option value='{$unit}' selected>{$unit} (سفارشی)</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <?php
                                        break;
                                }
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger">خطای سیستمی: ' . $e->getMessage() . '</div>';
                            }
                            ?>
                            
                            <hr>
                            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div id="form-message" class="mt-3"></div> 
        </div>
    </div>
</div>

<!-- Modal: افزودن / ویرایش ردیف برای رول / دنده زنی -->
<div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRuleModalLabel">افزودن قانون ظرفیت جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-rule-form">
                    <input type="hidden" name="station_id" value="<?php echo $selected_station_id; ?>">
                    <input type="hidden" name="form_type" value="<?php echo ($selected_station_id == 3) ? 'Gearing_Part_Add' : 'Rolling_Part_Add'; ?>">
                    <!-- فیلد مخفی برای ویرایش -->
                    <input type="hidden" id="modal-rule-id" name="rule_id" value=""> 
                    
                    <div class="mb-3">
                        <label for="modal-family" class="form-label">خانواده محصول</label>
                        <select id="modal-family" name="family_id" class="form-select" required>
                            <option value="">-- انتخاب خانواده --</option>
                            <?php
                            foreach ($all_families as $family) {
                                echo "<option value='{$family['FamilyID']}'>" . htmlspecialchars($family['FamilyName']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal-part" class="form-label">نام محصول</label>
                        <select id="modal-part" name="part_id" class="form-select" required disabled>
                            <option value="">-- ابتدا خانواده را انتخاب کنید --</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal-capacity" class="form-label">ظرفیت (KG/Day)</label>
                        <input type="number" id="modal-capacity" name="capacity" class="form-control" required>
                    </div>
                </form>
                <div id="modal-message"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" id="save-new-rule-btn">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>

<script>
// متغیر سراسری برای نگهداری پارت آی‌دی در حالت ویرایش
var partIdToSelectOnLoad = null;

$(document).ready(function() {
    
    // --- بارگیری صفحه بر اساس انتخاب ایستگاه ---
    $('#station-select').on('change', function() {
        var stationId = $(this).val();
        if (stationId) {
            window.location.href = 'manage_station_capacity.php?station_id=' + stationId;
        } else {
            window.location.href = 'manage_station_capacity.php';
        }
    });

    // --- محاسبه خودکار ظرفیت OEE ---
    $('#dynamic-form-container').on('input', '.capacity-input', function() {
        var $row = $(this).closest('tr');
        var spm = parseFloat($row.find('input[name*="[spm]"]').val());
        var time = parseFloat($row.find('input[name*="[available_time]"]').val());
        var oee = parseFloat($row.find('input[name*="[oee]"]').val());

        if (!isNaN(spm) && !isNaN(time) && !isNaN(oee)) {
            var capacity = (spm * time) * (oee / 100);
            $row.find('.calculated-capacity').text(capacity.toLocaleString('fa-IR', { maximumFractionDigits: 0 }));
        } else {
            $row.find('.calculated-capacity').text('---');
        }
    });

    // --- منطق مودال افزودن/ویرایش ردیف (رول / دنده زنی) ---

    // 1. بارگیری محصولات بر اساس خانواده (رفع باگ)
    $('#modal-family').on('change', function() {
        var familyId = $(this).val();
        var $partSelect = $('#modal-part');
        $partSelect.prop('disabled', true).html('<option value="">در حال بارگیری...</option>');

        if (!familyId) {
            $partSelect.html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
            return;
        }

        $.ajax({
            url: '../../api/get_parts_for_modal.php', // API جدید و تمیز
            type: 'GET',
            data: { family_id: familyId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.parts.length > 0) {
                    var options = '<option value="">-- محصول را انتخاب کنید --</option>';
                    response.parts.forEach(function(part) {
                        options += '<option value="' + part.PartID + '">' + part.PartName + '</option>';
                    });
                    $partSelect.html(options).prop('disabled', false);
                    
                    // اگر در حالت ویرایش بودیم، پارت مورد نظر را انتخاب کن
                    if (partIdToSelectOnLoad) {
                        $partSelect.val(partIdToSelectOnLoad);
                        partIdToSelectOnLoad = null; // متغیر را پاک کن
                    }
                } else {
                    $partSelect.html('<option value="">هیچ محصولی یافت نشد</option>');
                }
            },
            error: function(xhr) {
                $partSelect.html('<option value="">خطا در بارگیری: ' + xhr.responseText + '</option>');
            }
        });
    });

    // 2. آماده‌سازی مودال برای "افزودن جدید"
    $('#add-new-rule-btn').on('click', function() {
        $('#addRuleModalLabel').text('افزودن قانون ظرفیت جدید');
        $('#save-new-rule-btn').text('ذخیره');
        $('#modal-rule-id').val(''); // اطمینان از خالی بودن آی‌دی
        $('#add-rule-form')[0].reset();
        $('#modal-part').prop('disabled', true).html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
    });

    // 3. آماده‌سازی مودال برای "ویرایش"
    $('#product-capacity-table').on('click', '.edit-row-btn', function() {
        var ruleId = $(this).data('rule-id');
        var familyId = $(this).data('family-id');
        var partId = $(this).data('part-id');
        var capacity = $(this).data('capacity');

        // تنظیم متغیر سراسری تا پس از لود شدن پارت‌ها، انتخاب شود
        partIdToSelectOnLoad = partId;

        $('#addRuleModalLabel').text('ویرایش قانون ظرفیت');
        $('#save-new-rule-btn').text(' به‌روزرسانی');
        
        $('#modal-rule-id').val(ruleId);
        $('#modal-capacity').val(capacity);
        
        // خانواده را انتخاب کن و رویداد change را فعال کن تا پارت‌ها لود شوند
        $('#modal-family').val(familyId).trigger('change');
    });

    // 4. ذخیره (افزودن یا ویرایش)
    $('#save-new-rule-btn').on('click', function() {
        var $form = $('#add-rule-form');
        var $message = $('#modal-message');
        var $thisBtn = $(this);

        $thisBtn.prop('disabled', true);
        $message.html('<div class="alert alert-info">در حال ذخیره...</div>');

        $.ajax({
            type: 'POST',
            url: '../../api/save_capacity_rules.php', 
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $message.html('<div class="alert alert-success">عملیات با موفقیت انجام شد. صفحه دوباره بارگیری می‌شود...</div>');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $message.html('<div class="alert alert-danger">خطا: ' + response.error + '</div>');
                    $thisBtn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                $message.html('<div class="alert alert-danger">خطای سرور: ' + xhr.responseText + '</div>');
                $thisBtn.prop('disabled', false);
            }
        });
    });

    // 5. پاک کردن مودال پس از بسته شدن
    $('#addRuleModal').on('hidden.bs.modal', function () {
        $('#add-rule-form')[0].reset();
        $('#modal-rule-id').val('');
        $('#modal-part').prop('disabled', true).html('<option value="">-- ابتدا خانواده را انتخاب کنید --</option>');
        $('#modal-message').html('');
        $('#save-new-rule-btn').prop('disabled', false).text('ذخیره');
        $('#addRuleModalLabel').text('افزودن قانون ظرفیت جدید');
        partIdToSelectOnLoad = null; // پاک کردن متغیر سراسری
    });

    // --- حذف ردیف (رول / دنده زنی) ---
    $('#product-capacity-table').on('click', '.delete-row-btn', function() {
        var ruleId = $(this).data('rule-id');
        var $row = $('#rule-row-' + ruleId);
        
        if (confirm('آیا از حذف این قانون مطمئن هستید؟')) {
            $.ajax({
                type: 'POST',
                url: '../../api/delete_capacity_rule.php', 
                data: { rule_id: ruleId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert('خطا در حذف: ' + response.error);
                    }
                },
                error: function(xhr) {
                    alert('خطای سرور: ' + xhr.responseText);
                }
            });
        }
    });

    // --- ذخیره فرم اصلی ---
    $('#dynamic-form-container').on('submit', '#capacity-form', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var $formMessage = $('#form-message');
        
        $formMessage.html('<div class="alert alert-info">در حال ذخیره...</div>').removeClass('alert-danger alert-success');

        $.ajax({
            type: 'POST',
            url: '../../api/save_capacity_rules.php', 
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $formMessage.html('<div class="alert alert-success">تغییرات با موفقیت ذخیره شد.</div>');
                } else {
                    $formMessage.html('<div class="alert alert-danger">خطا در ذخیره‌سازی: ' + response.error + '</div>');
                }
            },
            error: function(xhr) {
                $formMessage.html('<div class="alert alert-danger">خطای ارتباط با سرور: ' + xhr.responseText + '</div>');
            }
        });
    });
});
</script>

