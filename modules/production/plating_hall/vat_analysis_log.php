<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.plating_hall.manage')) { die('Access Denied.'); }

const TABLE_NAME = 'tbl_plating_vat_analysis';
const PRIMARY_KEY = 'AnalysisID';

$editMode = false;
$itemToEdit = null;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $result = delete_record($pdo, TABLE_NAME, (int)$_POST['delete_id'], PRIMARY_KEY);
    } else {
        $data = [
            'AnalysisDate' => to_gregorian($_POST['analysis_date']),
            'VatID' => (int)$_POST['vat_id'],
            'Cyanide_gL' => !empty($_POST['cyanide_gl']) ? (float)$_POST['cyanide_gl'] : null,
            'CausticSoda_gL' => !empty($_POST['causticsoda_gl']) ? (float)$_POST['causticsoda_gl'] : null,
            'Zinc_gL' => !empty($_POST['zinc_gl']) ? (float)$_POST['zinc_gl'] : null,
            'AnalysisBy' => trim($_POST['analysis_by']),
            'Notes' => trim($_POST['notes'])
        ];

        if (empty($data['AnalysisDate']) || empty($data['VatID'])) {
            $result = ['success' => false, 'message' => 'تاریخ و انتخاب وان الزامی است.'];
            $_SESSION['message_type'] = 'warning';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $result = update_record($pdo, TABLE_NAME, $data, (int)$_POST['id'], PRIMARY_KEY);
            } else {
                $result = insert_record($pdo, TABLE_NAME, $data);
            }
             $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger'; 
        }
        $_SESSION['message'] = $result['message']; 
    }
    header("Location: " . BASE_URL . "modules/production/plating_hall/vat_analysis_log.php");
    exit;
}

if (isset($_GET['edit_id'])) {
    $editMode = true;
    $itemToEdit = find_by_id($pdo, TABLE_NAME, (int)$_GET['edit_id'], PRIMARY_KEY);
}

$vats = find_all($pdo, "SELECT VatID, VatName FROM tbl_plating_vats WHERE IsActive = 1 ORDER BY VatName");
$items = find_all($pdo, "SELECT va.*, pv.VatName FROM " . TABLE_NAME . " va JOIN tbl_plating_vats pv ON va.VatID = pv.VatID ORDER BY va.AnalysisDate DESC, va.AnalysisID DESC LIMIT 50");

define('CYANIDE_ID_JS', 1);
define('CAUSTIC_SODA_ID_JS', 2);
define('ZINC_ID_JS', 3);

$pageTitle = "ثبت نتایج آنالیز وان";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">ثبت نتایج آنالیز وان</h1>
    <a href="<?php echo BASE_URL; ?>modules/production/plating_hall/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card content-card mb-4"> 
            <div class="card-header"><h5 class="mb-0"><?php echo $editMode ? 'ویرایش آنالیز' : 'ثبت آنالیز جدید'; ?></h5></div>
            <div class="card-body">
                <form method="POST" action="vat_analysis_log.php">
                    <?php if ($editMode && $itemToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo $itemToEdit[PRIMARY_KEY]; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="analysis_date" class="form-label">تاریخ آنالیز</label>
                        <input type="text" class="form-control persian-date" id="analysis_date" name="analysis_date" value="<?php echo to_jalali($itemToEdit['AnalysisDate'] ?? date('Y-m-d')); ?>" required>
                    </div>
                     <div class="mb-3">
                        <label for="vat_id" class="form-label">وان</label>
                        <select class="form-select" id="vat_id" name="vat_id" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach($vats as $vat): ?>
                                <option value="<?php echo $vat['VatID']; ?>" <?php echo (isset($itemToEdit['VatID']) && $itemToEdit['VatID'] == $vat['VatID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vat['VatName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                             <label for="cyanide_gl" class="form-label">سیانور (g/L)</label>
                             <input type="number" step="0.01" class="form-control" id="cyanide_gl" name="cyanide_gl" value="<?php echo htmlspecialchars($itemToEdit['Cyanide_gL'] ?? ''); ?>">
                        </div>
                         <div class="col-md-4 mb-3">
                             <label for="causticsoda_gl" class="form-label">سود (g/L)</label>
                             <input type="number" step="0.01" class="form-control" id="causticsoda_gl" name="causticsoda_gl" value="<?php echo htmlspecialchars($itemToEdit['CausticSoda_gL'] ?? ''); ?>">
                        </div>
                         <div class="col-md-4 mb-3">
                             <label for="zinc_gl" class="form-label">روی (g/L)</label>
                             <input type="number" step="0.01" class="form-control" id="zinc_gl" name="zinc_gl" value="<?php echo htmlspecialchars($itemToEdit['Zinc_gL'] ?? ''); ?>">
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="analysis_by" class="form-label">آنالیز توسط</label>
                        <input type="text" class="form-control" id="analysis_by" name="analysis_by" value="<?php echo htmlspecialchars($itemToEdit['AnalysisBy'] ?? ''); ?>">
                    </div>
                     <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($itemToEdit['Notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn <?php echo $editMode ? 'btn-success' : 'btn-primary'; ?>"><?php echo $editMode ? 'بروزرسانی' : 'ثبت'; ?></button>
                    <?php if ($editMode): ?><a href="vat_analysis_log.php" class="btn btn-secondary">لغو</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card mb-4"> 
            <div class="card-header"><h5 class="mb-0">تاریخچه آنالیز وان‌ها</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead><tr><th class="p-2">تاریخ</th><th class="p-2">وان</th><th class="p-2">CN (g/L)</th><th class="p-2">NaOH (g/L)</th><th class="p-2">Zn (g/L)</th><th class="p-2">توسط</th><th class="p-2">عملیات</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="7" class="text-center p-3 text-muted">موردی برای نمایش یافت نشد.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="p-2"><?php echo to_jalali($item['AnalysisDate']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['VatName']); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['Cyanide_gL'] ?? '-'); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['CausticSoda_gL'] ?? '-'); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['Zinc_gL'] ?? '-'); ?></td>
                                    <td class="p-2"><?php echo htmlspecialchars($item['AnalysisBy'] ?? '-'); ?></td>
                                    <td class="p-2">
                                        <a href="?edit_id=<?php echo $item[PRIMARY_KEY]; ?>" class="btn btn-warning btn-sm py-0 px-1"><i class="bi bi-pencil-square"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item[PRIMARY_KEY]; ?>"><i class="bi bi-trash-fill"></i></button>
                                        <div class="modal fade" id="deleteModal<?php echo $item[PRIMARY_KEY]; ?>" tabindex="-1">
                                            <div class="modal-dialog"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">آیا از حذف رکورد آنالیز تاریخ <?php echo to_jalali($item['AnalysisDate']); ?> برای <?php echo htmlspecialchars($item['VatName']); ?> مطمئن هستید؟</div>
                                            <div class="modal-footer">
                                                <form method="POST" action="vat_analysis_log.php"><input type="hidden" name="delete_id" value="<?php echo $item[PRIMARY_KEY]; ?>"><button type="submit" class="btn btn-danger">بله</button></form>
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

<div class="row">
    <div class="col-lg-12">
         <div class="card content-card">
            <div class="card-header"><h5 class="mb-0">محاسبات آنالیز وان</h5></div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label for="calc_vat_id" class="form-label">۱. انتخاب وان</label>
                        <select class="form-select" id="calc_vat_id">
                             <option value="">-- ابتدا وان را انتخاب کنید --</option>
                             <?php foreach($vats as $vat): ?>
                                <option value="<?php echo $vat['VatID']; ?>"><?php echo htmlspecialchars($vat['VatName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                         <label for="start_analysis_id" class="form-label">۲. انتخاب آنالیز مبدأ</label>
                         <select class="form-select" id="start_analysis_id" disabled>
                             <option value="">-- ابتدا وان را انتخاب کنید --</option>
                         </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="calculation_end_date" class="form-label">۳. محاسبه تا تاریخ</label>
                        <input type="text" class="form-control persian-date" id="calculation_end_date" value="<?php echo to_jalali(date('Y-m-d')); ?>">
                    </div>
                     <div class="col-md-12 text-center">
                        <button type="button" class="btn btn-primary" id="calculate_analysis_btn">محاسبه وضعیت فعلی</button>
                    </div>
                </div>
                <hr>
                <div id="analysis_calculation_result">
                    <p class="text-muted text-center">لطفاً یک وان، یک آنالیز مبدأ و تاریخ پایان را انتخاب کرده و دکمه محاسبه را بزنید.</p>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<script>
$(document).ready(function() {

    // --- New logic for dependent dropdowns ---
    const vatSelect = $('#calc_vat_id');
    const analysisSelect = $('#start_analysis_id');

    vatSelect.on('change', function() {
        const vatId = $(this).val();
        analysisSelect.prop('disabled', true).html('<option value="">-- لطفا صبر کنید... --</option>');

        if (!vatId) {
            analysisSelect.html('<option value="">-- ابتدا وان را انتخاب کنید --</option>');
            return;
        }

        $.getJSON('<?php echo BASE_URL; ?>api/api_get_analysis_dates_by_vat.php', { vat_id: vatId })
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    analysisSelect.html('<option value="">-- یک تاریخ آنالیز انتخاب کنید --</option>');
                    $.each(response.data, function(i, item) {
                        analysisSelect.append($('<option>', {
                            value: item.AnalysisID,
                            text: item.AnalysisDateJalali // API returns Jalali date
                        }));
                    });
                    analysisSelect.prop('disabled', false);
                } else {
                    analysisSelect.html('<option value="">-- هیچ آنالیزی برای این وان ثبت نشده --</option>');
                }
            })
            .fail(function() {
                 analysisSelect.html('<option value="">-- خطا در بارگذاری تاریخ‌ها --</option>');
            });
    });


    $('#calculate_analysis_btn').on('click', function() {
        const analysisId = $('#start_analysis_id').val();
        const endDate = $('#calculation_end_date').val();
        const resultDiv = $('#analysis_calculation_result');
        
        if (!analysisId) {
            resultDiv.html('<div class="alert alert-warning">لطفاً یک آنالیز مبدأ را انتخاب کنید.</div>');
            return;
        }
        
        resultDiv.html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">در حال محاسبه...</span></div></div>');
        
        $.getJSON('<?php echo BASE_URL; ?>api/api_calculate_vat_analysis.php', { 
            analysis_id: analysisId, 
            end_date: endDate 
        })
        .done(function(response) {
            console.log("API Response:", response); 
            if (response.success && response.data) {
                const data = response.data;
                if(data.error){
                     resultDiv.html(`<div class="alert alert-warning">${data.error}</div>`);
                     return;
                }
                
                let content = `<h6 class="mb-3">پیش‌بینی وضعیت ${data.vat_name} تا تاریخ ${data.period_end_date_jalali} (بر اساس آنالیز ${data.analysis_date_start_jalali})</h6>`;
                content += `<p class="small text-muted mb-2">حجم وان: ${data.vat_volume} لیتر | بارل دوره: ${data.period_barrels_total} (سهم هر وان: ${data.barrels_per_vat}) | کیلوگرم آبکاری دوره: ${data.period_plated_kg_total} (سهم هر وان: ${data.plated_kg_per_vat})</p>`;
                
                content += `<div class="table-responsive"><table class="table table-sm table-bordered text-center small"><thead><tr class="table-light"><th>ماده</th><th>غلظت اولیه (g/L)</th><th>مصرف (g/barrel)</th><th>مصرف (g/Kg)</th><th>افزوده شده (g)</th><th>تغییر خالص (g/L)<br><small>(بر اساس Barrel)</small></th><th>تغییر خالص (g/L)<br><small>(بر اساس KG)</small></th><th>غلظت پیش‌بینی (g/L)<br><small>(بر اساس Barrel)</small></th><th>غلظت پیش‌بینی (g/L)<br><small>(بر اساس KG)</small></th></tr></thead><tbody>`;

                const chemIds = [<?php echo CYANIDE_ID_JS; ?>, <?php echo CAUSTIC_SODA_ID_JS; ?>, <?php echo ZINC_ID_JS; ?>]; 
                chemIds.forEach(id => {
                     const item = data.calculations[id];
                     if(item) {
                         content += `<tr>
                            <td>${item.chemical_name}</td>
                            <td>${item.initial_conc_gl}</td>
                            <td>${item.consumed_barrel_g.toLocaleString(undefined, { maximumFractionDigits: 1 })}</td>
                            <td>${item.consumed_kg_g.toLocaleString(undefined, { maximumFractionDigits: 1 })}</td>
                            <td>${item.total_added_g.toLocaleString(undefined, { maximumFractionDigits: 1 })}</td>
                            <td class="${item.net_change_barrel_gl >= 0 ? 'text-success' : 'text-danger'}">${item.net_change_barrel_gl}</td>
                            <td class="${item.net_change_kg_gl >= 0 ? 'text-success' : 'text-danger'}">${item.net_change_kg_gl}</td>
                            <td><b>${item.predicted_barrel_gl}</b></td>
                            <td><b>${item.predicted_kg_gl}</b></td>
                         </tr>`;
                     } else {
                         console.warn(`Calculation data missing for chemical ID: ${id}`);
                     }
                });

                content += `</tbody></table></div>`;
                resultDiv.html(content);

            } else {
                 resultDiv.html(`<div class="alert alert-danger">${response.message || 'خطا در دریافت اطلاعات.'}</div>`);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
             console.error("API Calculation Error:", textStatus, errorThrown, jqXHR.responseText); 
             resultDiv.html('<div class="alert alert-danger">خطا در برقراری ارتباط با سرور یا محاسبه. لطفا کنسول مرورگر (F12) را برای جزئیات بررسی کنید.</div>');
        });
    });
});
</script>
