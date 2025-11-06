<?php
require_once __DIR__ . '/../../config/init.php';


$pageTitle = "لیست دستور کارها و چاپ";
include_once __DIR__ . '/../../templates/header.php';

// --- تعریف ایستگاه‌های قابل فیلتر ---
$station_filters = [
    ['id' => 2, 'name' => 'پرسکاری'],
    ['id' => 6, 'name' => 'پیچ سازی'],
    ['id' => 4, 'name' => 'آبکاری'],
    ['id' => 1, 'name' => 'شستشو'],
    ['id' => 7, 'name' => 'دوباره کاری'],
    ['id' => 5, 'name' => 'رول'],
    ['id' => 3, 'name' => 'دنده زنی'],
    ['id' => 12, 'name' => 'مونتاژ'],
    ['id' => 10, 'name' => 'بسته بندی'],
];

// --- دریافت فیلترها از GET ---
$planned_date_gregorian = date('Y-m-d');
if (isset($_GET['planned_date']) && !empty($_GET['planned_date'])) {
    $planned_date_gregorian = to_gregorian($_GET['planned_date']);
}
$planned_date_jalali = to_jalali($planned_date_gregorian);

$selected_stations = $_GET['stations'] ?? [];
$selected_stations = array_filter($selected_stations, 'is_numeric'); // Clean array

// --- ساخت کوئری داینامیک ---
$sql_params = [$planned_date_gregorian]; // Start with the first parameter
$sql = "
    SELECT 
        wo.WorkOrderID, wo.PlannedDate, wo.Quantity, wo.Unit, wo.Status, wo.StationID,
        wo.AuxQuantity, wo.AuxUnit, wo.BatchGUID, /* [!!!] ستون GUID اضافه شد */
        p.PartName, p.PartCode,
        s.StationName,
        m.MachineName,
        pw.WeightGR 
    FROM tbl_planning_work_orders wo
    JOIN tbl_parts p ON wo.PartID = p.PartID
    JOIN tbl_stations s ON wo.StationID = s.StationID
    LEFT JOIN tbl_machines m ON wo.MachineID = m.MachineID
    LEFT JOIN tbl_part_weights pw ON p.PartID = pw.PartID AND pw.EffectiveTo IS NULL
    WHERE wo.PlannedDate = ? 
";

if (!empty($selected_stations)) {
    $placeholders = implode(',', array_fill(0, count($selected_stations), '?'));
    $sql .= " AND wo.StationID IN ($placeholders)";
    // ادغام پارامترهای ایستگاه‌ها با پارامترهای اصلی
    $sql_params = array_merge($sql_params, $selected_stations);
}

$sql .= " ORDER BY s.StationName, wo.BatchGUID, p.PartName"; // [!!!] مرتب‌سازی بر اساس بچ

$stmt = $pdo->prepare($sql);
$stmt->execute($sql_params);
$work_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered_station_names = 'همه ایستگاه‌ها';
if (!empty($selected_stations)) {
    $names = [];
    foreach ($station_filters as $filter) {
        if (in_array($filter['id'], $selected_stations)) {
            $names[] = $filter['name'];
        }
    }
    $filtered_station_names = implode('، ', $names);
}
?>

<div class="container-fluid rtl">
    <div class="row">
        <main class="col-md-12 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> بازگشت به منوی برنامه‌ریزی
                </a>
            </div>

            <!-- Filter Form -->
            <div class="card content-card mb-3 d-print-none">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-filter me-2"></i>فیلتر دستور کارها</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="planned-date" class="form-label">تاریخ برنامه‌ریزی *</label>
                            <input type="text" id="planned-date" name="planned_date" class="form-control text-center persian-date" value="<?php echo $planned_date_jalali; ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">ایستگاه‌ها (خالی = همه)</label>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($station_filters as $station): ?>
                                    <div class="form-check form-check-inline me-3">
                                        <input class="form-check-input" type="checkbox" name="stations[]" id="station_<?php echo $station['id']; ?>" value="<?php echo $station['id']; ?>" <?php echo in_array($station['id'], $selected_stations) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="station_<?php echo $station['id']; ?>"><?php echo $station['name']; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>اعمال فیلتر
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card content-card d-print-none">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">لیست دستور کارها</h5>
                    <button id="print-report-btn" class="btn btn-success">
                        <i class="bi bi-printer-fill me-2"></i>چاپ گزارش
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="planner-notes" class="form-label">توضیحات برنامه‌ریز (برای چاپ)</label>
                        <textarea id="planner-notes" class="form-control" rows="2" placeholder="توضیحات لازم برای اپراتورها یا مدیران را اینجا وارد کنید..."></textarea>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm" id="wo-table">
                            <thead class="table-light">
                                <tr>
                                    <th>ایستگاه</th>
                                    <th>دستگاه</th>
                                    <th>کد قطعه</th>
                                    <th>نام قطعه</th>
                                    <th>مقدار (KG)</th>
                                    <th>تعداد</th>
                                    <th>واحد شمارش</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($work_orders)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">دستور کاری برای این فیلتر یافت نشد.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($work_orders as $wo): ?>
                                        <?php
                                        // محاسبه هر دو مقدار
                                        $quantity_kg = 0;
                                        $quantity_count = 0;
                                        $count_unit = 'عدد'; // پیش‌فرض
                                        
                                        $weight_gr = (float)($wo['WeightGR'] ?? 0);
                                        $quantity = (float)($wo['Quantity'] ?? 0);

                                        if ($wo['StationID'] == 4 && $wo['AuxUnit'] === 'بارل') {
                                            // حالت آبکاری: واحد اصلی KG است، واحد کمکی بارل
                                            $quantity_kg = $quantity;
                                            $quantity_count = (float)$wo['AuxQuantity'];
                                            $count_unit = 'بارل';
                                        
                                        } elseif ($wo['Unit'] === 'عدد') {
                                            // حالت پرسکاری: واحد اصلی عدد است
                                            $quantity_count = $quantity;
                                            if ($weight_gr > 0) {
                                                $quantity_kg = ($quantity * $weight_gr) / 1000;
                                            }
                                        } elseif ($wo['Unit'] === 'KG') {
                                            // حالت شستشو/دوباره‌کاری: واحد اصلی KG است
                                            $quantity_kg = $quantity;
                                            if ($weight_gr > 0) {
                                                $quantity_count = round($quantity / ($weight_gr / 1000));
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($wo['StationName']); ?></td>
                                            <td><?php echo htmlspecialchars($wo['MachineName'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($wo['PartCode']); ?></td>
                                            <td><?php echo htmlspecialchars($wo['PartName']); ?></td>
                                            <td><?php echo $quantity_kg > 0 ? number_format($quantity_kg, 2) : '-'; ?></td>
                                            <td><?php echo $quantity_count > 0 ? number_format($quantity_count) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($count_unit); ?></td>
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

<!-- ============================================= -->
<!-- PRINT AREA (Hidden by default)                -->
<!-- ============================================= -->
<div id="print-area">
    <div class="print-header">
        <div class="print-header-logo">
             <h4 class="mb-0">گروه صنعتی BST</h4>
        </div>
        <div class="print-header-title">
            <h2 class="mb-1">برنامه تولید</h2>
            <h5 class="mb-0" id="print-title-stations">قسمت‌ها: </h5>
        </div>
        <div class="print-header-info">
            <div>تاریخ برنامه: <strong id="print-planned-date"></strong></div>
            <div>تاریخ چاپ: <strong id="print-current-datetime"></strong></div>
        </div>
    </div>

    <table id="print-table" class="table-print">
        <thead>
            <tr>
                <th>ایستگاه</th>
                <th>دستگاه</th>
                <th>کد قطعه</th>
                <th>نام قطعه</th>
                <th>مقدار (KG)</th>
                <th>تعداد</th>
                <th>واحد شمارش</th>
            </tr>
        </thead>
        <tbody>
            <!-- Rows will be injected by JS -->
        </tbody>
    </table>

    <div class="print-summary">
        <h5 class="summary-title">مجموع برنامه:</h5>
        <table class="table-print" id="print-summary-table">
            <thead>
                <tr>
                    <th>ایستگاه</th>
                    <th>مجموع (KG)</th>
                    <th>مجموع تعداد</th>
                    <th>واحد شمارش</th>
                </tr>
            </thead>
            <tbody id="print-summary-tbody">
                <!-- Summary rows will be injected by JS -->
            </tbody>
        </table>
    </div>
    
    <div class="print-notes">
        <strong>توضیحات برنامه‌ریز:</strong>
        <p id="print-notes-content"></p>
    </div>

    <div class="print-footer">
        <div class="print-signature-box">
            <strong>امضای برنامه‌ریز:</strong>
        </div>
        <div class="print-signature-box">
            <strong>امضای مدیر تولید:</strong>
        </div>
        <div class="print-signature-box">
            <strong>امضای سرپرست:</strong>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- CSS for Printing                          -->
<!-- ============================================= -->
<style>
    @media screen {
        #print-area {
            display: none;
        }
    }

    @media print {
        body {
            font-family: 'Tahoma', sans-serif;
            font-size: 10pt;
            direction: rtl;
            background-color: #fff;
            color: #000;
        }

        /* Hide everything except the print area */
        body * {
            visibility: hidden;
        }
        #print-area, #print-area * {
            visibility: visible;
        }
        #print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 1cm;
            box-sizing: border-box;
        }
        
        /* A4 Portrait Layout */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        /* Print Header */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .print-header-title { text-align: center; }
        .print-header-info { text-align: left; font-size: 9pt; }
        .print-header-logo { text-align: right; }

        /* Print Table */
        .table-print {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table-print th, .table-print td {
            border: 1px solid #000;
            padding: 5px;
            text-align: right;
            font-size: 9pt; /* کوچکتر کردن فونت جدول چاپی */
        }
        .table-print th {
            background-color: #eee;
            font-weight: bold;
        }

        /* Print Summary */
        .print-summary {
            padding: 0;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .summary-title { margin-bottom: 5px; }

        /* Print Notes */
        .print-notes {
            margin-bottom: 30px;
            min-height: 50px;
            page-break-inside: avoid;
        }
        #print-notes-content {
            border-bottom: 1px solid #ccc;
            padding-top: 5px;
            min-height: 40px;
            white-space: pre-wrap; /* حفظ خطوط جدید از textarea */
        }

        /* Print Footer (Signatures) */
        .print-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 30px;
            border-top: 1px solid #000;
            page-break-inside: avoid;
        }
        .print-signature-box {
            width: 30%;
            text-align: center;
            padding-top: 40px; /* Space for signature */
        }
    }
</style>

<!-- افزودن فوتر برای بارگذاری jQuery و تقویم -->
<?php include_once __DIR__ . '/../../templates/footer.php'; ?>


<?php
// Pass PHP data to JS
echo '<script>const all_work_orders = ' . json_encode($work_orders) . ';</script>';
echo '<script>const filtered_station_names = ' . json_encode($filtered_station_names) . ';</script>';
echo '<script>const planned_date_jalali = ' . json_encode($planned_date_jalali) . ';</script>';
?>

<script>
// Check if jQuery is loaded
if (typeof $ === 'undefined') {
    // Provide a fallback error message if footer.php fails
    document.body.innerHTML = '<div class="alert alert-danger m-5">خطای بحرانی: jQuery بارگذاری نشده است. فایل footer.php را بررسی کنید.</div>';
} else {
    $(document).ready(function() {
        
        // footer.php automatically initializes '.persian-date'
        
        $('#print-report-btn').on('click', function() {
            // --- 1. Populate Header ---
            $('#print-title-stations').text('قسمت‌ها: ' + filtered_station_names);
            $('#print-planned-date').text(planned_date_jalali);
            $('#print-current-datetime').text(new Date().toLocaleString('fa-IR', { dateStyle: 'short', timeStyle: 'short' }));
            
            // --- 2. Populate Table ---
            const $printTableBody = $('#print-table tbody');
            $printTableBody.empty();
            
            if (all_work_orders.length === 0) {
                $printTableBody.html('<tr><td colspan="7" style="text-align: center;">دستور کاری یافت نشد.</td></tr>');
            } else {
                all_work_orders.forEach(function(wo) {
                    // محاسبه هر دو مقدار
                    let quantity_kg = 0;
                    let quantity_pcs = 0;
                    let count_unit = 'عدد'; // پیش‌فرض
                    
                    const weight_gr = parseFloat(wo.WeightGR || 0);
                    const quantity = parseFloat(wo.Quantity || 0);

                    if (wo.StationID == 4 && wo.AuxUnit === 'بارل') {
                        // حالت آبکاری
                        quantity_kg = quantity;
                        quantity_pcs = parseFloat(wo.AuxQuantity);
                        count_unit = 'بارل';
                    
                    } else if (wo.Unit === 'عدد') {
                        // حالت پرسکاری
                        quantity_pcs = quantity;
                        if (weight_gr > 0) {
                            quantity_kg = (quantity * weight_gr) / 1000;
                        }
                    } else if (wo.Unit === 'KG') {
                        // حالت شستشو/دوباره‌کاری
                        quantity_kg = quantity;
                        if (weight_gr > 0) {
                            quantity_pcs = Math.round(quantity / (weight_gr / 1000));
                        }
                    }
                    
                    const row = `
                        <tr>
                            <td>${wo.StationName || '-'}</td>
                            <td>${wo.MachineName || '-'}</td>
                            <td>${wo.PartCode || '-'}</td>
                            <td>${wo.PartName || '-'}</td>
                            <td>${quantity_kg > 0 ? quantity_kg.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                            <td>${quantity_pcs > 0 ? quantity_pcs.toLocaleString() : '-'}</td>
                            <td>${count_unit}</td>
                        </tr>
                    `;
                    $printTableBody.append(row);
                });
            }
            
            // --- 3. Calculate & Populate Summary (Grouped by Station) ---
            const summary_by_station = {};
            const processedBatchGuids = new Set(); // [!!!] جلوگیری از شمارش مضاعف

            all_work_orders.forEach(function(wo) {
                const station_name = wo.StationName;
                if (!summary_by_station[station_name]) {
                    summary_by_station[station_name] = { 
                        total_kg: 0, 
                        total_pcs: 0, 
                        total_barrels: 0 
                    };
                }

                const weight_gr = parseFloat(wo.WeightGR || 0);
                const quantity = parseFloat(wo.Quantity || 0);

                if (wo.StationID == 4 && wo.AuxUnit === 'بارل') {
                    // آبکاری
                    summary_by_station[station_name].total_kg += quantity; // KG همیشه جمع می‌شود
                    
                    // [!!!] بارل فقط یکبار بر اساس GUID بچ شمـرده می‌شود
                    if (wo.BatchGUID && !processedBatchGuids.has(wo.BatchGUID)) {
                        summary_by_station[station_name].total_barrels += parseFloat(wo.AuxQuantity);
                        processedBatchGuids.add(wo.BatchGUID);
                    } else if (!wo.BatchGUID && wo.AuxQuantity > 0) {
                         // پشتیبانی از داده‌های قدیمی بدون GUID
                         summary_by_station[station_name].total_barrels += parseFloat(wo.AuxQuantity);
                    }
                
                } else if (wo.Unit === 'عدد') {
                    // پرسکاری/پیچ‌سازی
                    summary_by_station[station_name].total_pcs += quantity;
                    if (weight_gr > 0) {
                        summary_by_station[station_name].total_kg += (quantity * weight_gr) / 1000;
                    }
                } else if (wo.Unit === 'KG') {
                    // شستشو/دوباره‌کاری/سایر
                    summary_by_station[station_name].total_kg += quantity;
                    if (weight_gr > 0) {
                        summary_by_station[station_name].total_pcs += Math.round(quantity / (weight_gr / 1000));
                    }
                }
            });

            // [!!!] منطق جدید برای ساخت جدول مجموع
            const $summaryTbody = $('#print-summary-tbody');
            $summaryTbody.empty();
            let hasSummary = false;

            for (const stationName in summary_by_station) {
                hasSummary = true;
                const summary = summary_by_station[stationName];
                let kg_text = summary.total_kg > 0 ? summary.total_kg.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-';
                let count_text = '-';
                let count_unit = '-';

                if (summary.total_barrels > 0) {
                    count_text = summary.total_barrels.toLocaleString();
                    count_unit = 'بارل';
                } else if (summary.total_pcs > 0) {
                    count_text = summary.total_pcs.toLocaleString();
                    count_unit = 'عدد';
                }
                
                const summaryRow = `
                    <tr>
                        <td><strong>${stationName}</strong></td>
                        <td>${kg_text}</td>
                        <td>${count_text}</td>
                        <td>${count_unit}</td>
                    </tr>
                `;
                $summaryTbody.append(summaryRow);
            }

            if (!hasSummary) {
                 $summaryTbody.html('<tr><td colspan="4" style="text-align: center;">مجموعی برای محاسبه یافت نشد.</td></tr>');
            }
            
            // --- 4. Populate Notes ---
            const notes = $('#planner-notes').val();
            $('#print-notes-content').text(notes || 'توضیحات خاصی ثبت نشده است.');

            // --- 5. Trigger Print ---
            window.print();
        });
    });
}
</script>

