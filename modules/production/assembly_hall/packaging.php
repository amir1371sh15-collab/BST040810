<?php
require_once __DIR__ . '/../../../config/init.php';

if (!has_permission('production.assembly_hall.manage')) {
    die('شما مجوز دسترسی به این صفحه را ندارید.');
}

// Fetch employees (adjust DepartmentID if needed)
$packaging_dept_id = 3; // Assuming assembly/packaging is Dept 3
$employees = find_all($pdo, "SELECT EmployeeID, name FROM tbl_employees WHERE DepartmentID = ? OR DepartmentID IS NULL ORDER BY name", [$packaging_dept_id]); // Or adjust query as needed

// Session Messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Persistent values from session
$sess_avail_time = $_SESSION['packaging_available_time'] ?? 480;
$sess_description = $_SESSION['packaging_description'] ?? '';
$sess_log_date = $_SESSION['packaging_log_date'] ?? to_jalali(date('Y-m-d'));

$display_available_time = $sess_avail_time;
$display_description = $sess_description;
$initial_log_date_jalali = $sess_log_date;

$pageTitle = "ثبت آمار بسته‌بندی";
include __DIR__ . '/../../../templates/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center no-print">
    <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
</div>

<?php if ($message): ?>
<div id="flash-message" class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form id="packaging-form" method="POST"> <!-- Add ID for easier selection -->
    <!-- Section 1: Date, Time, Description -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۱. اطلاعات روزانه</h5></div>
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label class="form-label">تاریخ</label>
                    <input type="text" id="log_date_field" name="log_date_field" class="form-control persian-date persistent-input" data-session-key="packaging_log_date"
                           value="<?php echo $initial_log_date_jalali; ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">زمان در دسترس (دقیقه)</label>
                    <input type="number" id="available_time_field" name="available_time_field" class="form-control persistent-input" data-session-key="packaging_available_time"
                           value="<?php echo htmlspecialchars($display_available_time); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">توضیحات روز</label>
                    <textarea id="description_field" name="description_field" class="form-control persistent-input" data-session-key="packaging_description" rows="1"><?php echo htmlspecialchars($display_description); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Personnel Shifts -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۲. ثبت پرسنل و زمان کاری</h5></div>
        <div class="card-body">
            <div id="personnel-shifts-container">
                <!-- Shift rows added dynamically -->
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="add-personnel-shift-btn">+ افزودن پرسنل</button>
        </div>
    </div>


    <!-- Section 3: Packaging Data Table -->
    <div class="card content-card mb-4">
        <div class="card-header"><h5 class="mb-0">۳. ثبت تعداد کارتن</h5></div>
        <div class="card-body">
            <div id="packaging-parts-feedback" class="mb-2 small text-muted">در حال بارگذاری لیست محصولات...</div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light text-center small">
                        <tr>
                            <th>خانواده</th>
                            <th>محصول (بست)</th>
                            <th>تعداد در کارتن</th>
                            <th>وزن کارتن (KG)</th>
                            <th style="width: 120px;">تعداد کارتن</th>
                        </tr>
                    </thead>
                    <tbody id="packaging-table-body">
                        <tr id="loading-parts-row"><td colspan="5" class="text-center p-4"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>
                    </tbody>
                    <tfoot class="table-light fw-bold text-center">
                        <tr>
                            <td colspan="4" class="text-end pe-3">مجموع کارتن‌ها:</td>
                            <td id="total-cartons">0</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="text-end mt-4 mb-5">
         <div id="form-submit-feedback" class="mt-2 small d-inline-block me-3"></div>
        <button type="submit" class="btn btn-primary" id="submit-packaging-btn"><i class="bi bi-check-lg"></i> ثبت نهایی آمار بسته‌بندی</button>
    </div>
</form>

<!-- History Section -->
<div class="card content-card mt-5">
    <div class="card-header"><h5 class="mb-0">تاریخچه ثبت بسته‌بندی</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="small">
                    <tr>
                        <th class="p-2">تاریخ</th>
                        <th class="p-2">تعداد پرسنل</th>
                        <th class="p-2">مجموع کارتن</th>
                        <th class="p-2">عملیات</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <!-- History rows will be loaded here -->
                    <tr><td colspan="4" class="text-center p-3 text-muted">در حال بارگذاری تاریخچه...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination (optional, add if needed) -->
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">جزئیات ثبت بسته‌بندی</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailsModalBody"><div class="text-center p-5"><div class="spinner-border" role="status"></div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button></div>
</div></div></div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1"> <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف کامل گزارش این روز مطمئن هستید؟</div>
    <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="confirm-delete-btn">بله، حذف کن</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
        <input type="hidden" id="delete-header-id-input">
    </div>
</div></div></div>


<?php
// Pass employee data to JavaScript
$employeeOptions = '';
foreach ($employees as $emp) {
    $employeeOptions .= '<option value="' . $emp['EmployeeID'] . '">' . htmlspecialchars($emp['name']) . '</option>';
}
?>
<script>
    const employeeOptionsJS = <?php echo json_encode($employeeOptions); ?>;
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    let shiftRowIndex = 0;
    let partsData = {};
    const apiPartsUrl = '<?php echo BASE_URL; ?>api/api_get_parts_for_packaging.php';
    const apiSaveUrl = '<?php echo BASE_URL; ?>api/api_save_packaging_entry.php';
    const apiUpdateSessionUrl = '<?php echo BASE_URL; ?>api/api_update_session.php';
    const apiDailySummaryUrl = '<?php echo BASE_URL; ?>api/api_get_packaging_daily_summary.php'; // For modal
    const apiDeleteUrl = '<?php echo BASE_URL; ?>api/api_delete_packaging_log.php'; // For delete
    const apiHistoryUrl = '<?php echo BASE_URL; ?>api/api_get_packaging_history.php'; // <<< CORRECT API URL for history

    // --- Personnel Shift Logic ---
    function addPersonnelShiftRow(employeeId = '', startTime = '', endTime = '') {
        shiftRowIndex++;
        const newRow = `
            <div class="row align-items-center mb-2 personnel-shift-row">
                <div class="col-md-5">
                    <select name="personnel_shifts[${shiftRowIndex}][employee_id]" class="form-select form-select-sm employee-select" required>
                        <option value="">-- انتخاب پرسنل --</option>
                        ${employeeOptionsJS}
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="time" name="personnel_shifts[${shiftRowIndex}][start_time]" class="form-control form-control-sm start-time-input" value="${startTime}">
                </div>
                <div class="col-md-3">
                    <input type="time" name="personnel_shifts[${shiftRowIndex}][end_time]" class="form-control form-control-sm end-time-input" value="${endTime}">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger remove-shift-btn" style="display: none;">&times;</button>
                </div>
            </div>`;
        const $newRow = $(newRow);
        if (employeeId) {
            $newRow.find('.employee-select').val(employeeId);
        }
        $('#personnel-shifts-container').append($newRow);
        updateRemoveShiftButtons();
    }

    $('#add-personnel-shift-btn').on('click', function() {
        addPersonnelShiftRow();
    });

    $(document).on('click', '.remove-shift-btn', function() {
        $(this).closest('.personnel-shift-row').remove();
        updateRemoveShiftButtons();
    });

    function updateRemoveShiftButtons() {
        const rows = $('.personnel-shift-row');
        rows.find('.remove-shift-btn').toggle(rows.length > 1);
    }

    // Add initial shift row
    addPersonnelShiftRow();


    // --- Packaging Parts Logic ---
    async function loadPackagingParts() {
        const feedbackDiv = $('#packaging-parts-feedback');
        const tableBody = $('#packaging-table-body');
        const loadingRow = $('#loading-parts-row');

        feedbackDiv.text('در حال بارگذاری لیست محصولات...').removeClass('text-danger text-warning'); // Reset classes
        loadingRow.show();
        tableBody.find('tr:not(#loading-parts-row)').remove(); // Clear previous rows

        try {
            const response = await $.getJSON(apiPartsUrl);
            loadingRow.hide();

            if (response.success && response.data && response.data.length > 0) {
                feedbackDiv.text('محصولات بارگذاری شدند. لطفاً تعداد کارتن‌ها را وارد کنید.');
                partsData = {}; // Clear old data
                const groupedParts = {};

                // Group by family
                response.data.forEach(part => {
                    partsData[part.PartID] = part; // Store full part data
                    const family = part.FamilyName || 'سایر';
                    if (!groupedParts[family]) { groupedParts[family] = []; }
                    groupedParts[family].push(part);
                });

                // Render table rows grouped by family
                Object.keys(groupedParts).sort().forEach(familyName => {
                    let firstRow = true;
                    groupedParts[familyName].forEach(part => {
                        const containedQty = part.ContainedQuantity ? parseInt(part.ContainedQuantity) : '-';
                        let weightDisplay = '-';
                        if (part.TotalWeightKG) { weightDisplay = parseFloat(part.TotalWeightKG).toFixed(3); }
                         else if (part.UnitWeight && containedQty > 0) { weightDisplay = (parseFloat(part.UnitWeight) * containedQty).toFixed(3) + '*'; }
                        const rowHtml = `
                            <tr data-part-id="${part.PartID}">
                                ${firstRow ? `<td rowspan="${groupedParts[familyName].length}" class="align-middle small text-muted">${familyName}</td>` : ''}
                                <td class="small left-align">${part.PartName} (${part.PartCode})</td>
                                <td class="text-center small">${containedQty}</td>
                                <td class="text-center small">${weightDisplay}</td>
                                <td><input type="number" min="0" name="packaged_cartons[${part.PartID}]" class="form-control form-control-sm carton-input" data-part-id="${part.PartID}"></td>
                            </tr>`;
                        tableBody.append(rowHtml);
                        firstRow = false;
                    });
                });
                calculateTotalCartons(); // Initial calculation
            } else {
                feedbackDiv.text(response.message || 'هیچ محصولی برای بسته‌بندی یافت نشد.').addClass('text-warning');
                tableBody.append('<tr><td colspan="5" class="text-center text-warning p-3">هیچ محصولی یافت نشد.</td></tr>');
            }
        } catch (error) {
            loadingRow.hide();
            console.error("Error loading packaging parts:", error);
            const errorMsg = error.responseJSON?.message || error.statusText || 'خطای ناشناخته';
            feedbackDiv.text(`خطا در بارگذاری لیست محصولات: ${errorMsg}`).addClass('text-danger');
            tableBody.append(`<tr><td colspan="5" class="text-center text-danger p-3">خطا در بارگذاری: ${errorMsg}</td></tr>`);
        }
    }

    $(document).on('input', '.carton-input', calculateTotalCartons);

    function calculateTotalCartons() {
        let total = 0;
        $('.carton-input').each(function() { total += parseInt($(this).val()) || 0; });
        $('#total-cartons').text(total);
    }

    // --- Form Submission (AJAX) ---
    $('#packaging-form').on('submit', async function(e) {
        e.preventDefault();
        const feedbackDiv = $('#form-submit-feedback');
        const submitButton = $('#submit-packaging-btn');

        feedbackDiv.text('در حال ثبت...').removeClass('text-danger text-success text-warning').addClass('text-info'); // Reset classes
        submitButton.prop('disabled', true);

        // Client-side Validation
        const logDate = $('#log_date_field').val();
        const availableTime = $('#available_time_field').val();
        let hasPersonnel = false;
        $('.employee-select').each(function() { if ($(this).val()) { hasPersonnel = true; return false; } });

        let errors = [];
        if (!logDate) errors.push("تاریخ");
        if (!availableTime || !$.isNumeric(availableTime) || parseInt(availableTime) <= 0) errors.push("زمان در دسترس (باید عدد مثبت باشد)");
        if (!hasPersonnel) errors.push("حداقل یک نفر پرسنل");

        if (errors.length > 0) {
            feedbackDiv.text('خطا: ' + errors.join('، ') + ' الزامی/نامعتبر است.').removeClass('text-info').addClass('text-danger');
            submitButton.prop('disabled', false);
            return;
        }

        const formData = $(this).serialize();
        console.log("Form Data Sent:", formData); // Log data being sent

        try {
            const response = await $.post(apiSaveUrl, formData);
             console.log("Server Response:", response); // Log server response
            if (response.success) {
                feedbackDiv.text(response.message).removeClass('text-info text-danger').addClass('text-success');
                $('.carton-input').val(''); calculateTotalCartons();
                loadHistory(); // Reload history
                setTimeout(() => { feedbackDiv.text(''); }, 3000);
            } else {
                feedbackDiv.text('خطا در ثبت: ' + response.message).removeClass('text-info').addClass('text-danger');
            }
        } catch (jqXHR) {
            console.error("Error submitting form:", jqXHR);
            let errorMsg = 'خطا در ارتباط با سرور.';
            if(jqXHR.responseJSON && jqXHR.responseJSON.message){ errorMsg = jqXHR.responseJSON.message; }
            else if (jqXHR.responseText){ try { errorMsg = JSON.parse(jqXHR.responseText).message || errorMsg } catch(e){} }
            feedbackDiv.text(errorMsg).removeClass('text-info').addClass('text-danger');
        } finally {
            submitButton.prop('disabled', false);
        }
    });


    // --- Persistent Input Logic ---
    $('.persistent-input').on('blur', function() {
        const input = $(this);
        const sessionKey = input.data('session-key');
        const value = input.val();
        $.post(apiUpdateSessionUrl, { key: sessionKey, value: value })
            .fail(function(jqXHR) {
                console.error(`AJAX error updating session key '${sessionKey}' on blur:`, jqXHR.responseText);
            });
    });

    // --- Date Change Logic ---
     $('#log_date_field').on('change', function() {
         const newDate = $(this).val();
         // Save other persistent fields first
         $('#available_time_field, #description_field').trigger('blur');
          // Update session date via AJAX
          $.post(apiUpdateSessionUrl, { key: 'packaging_log_date', value: newDate })
             .fail(function(jqXHR) {
                 console.error(`AJAX error updating session key 'packaging_log_date' on date change:`, jqXHR.responseText);
             });
         loadHistory(); // Reload history when date changes
     });


    // --- History & Details Modal ---
    function loadHistory(page = 1) {
        const historyTbody = $('#history-tbody');
        historyTbody.html('<tr><td colspan="4" class="text-center p-3 text-muted">در حال بارگذاری تاریخچه...</td></tr>');
        const selectedDateJalali = $('#log_date_field').val(); // Get current date

        console.log("Loading history for date:", selectedDateJalali); // Log the date being requested

        // <<< CORRECTED API CALL >>>
        $.getJSON(apiHistoryUrl, { log_date_jalali: selectedDateJalali })
        .done(function(response) {
            console.log("History API Response:", response); // Log the response
            historyTbody.empty();
            if (response.success && response.data && response.data.length > 0) {
                 response.data.forEach(log => {
                    const row = `
                        <tr>
                            <td class="p-2">${log.LogDateJalali}</td>
                            <td class="p-2">${log.PersonnelCount}</td>
                            <td class="p-2">${log.TotalCartons}</td>
                            <td class="p-2">
                                <button class="btn btn-info btn-sm view-details-btn py-0 px-1" data-header-id="${log.PackagingHeaderID}" data-bs-toggle="modal" data-bs-target="#detailsModal" title="جزئیات"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-danger btn-sm delete-log-btn py-0 px-1" data-header-id="${log.PackagingHeaderID}" data-log-date="${log.LogDateJalali}" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" title="حذف"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>`;
                    historyTbody.append(row);
                });
            } else {
                 historyTbody.html('<tr><td colspan="4" class="text-center p-3 text-muted">تاریخچه‌ای برای این روز یافت نشد.</td></tr>');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error("History API Call Failed:", textStatus, errorThrown, jqXHR.responseText); // Log failure details
            historyTbody.html('<tr><td colspan="4" class="text-center p-3 text-danger">خطا در بارگذاری تاریخچه.</td></tr>');
        });
    }

    $(document).on('click', '.view-details-btn', async function() {
        const headerId = $(this).data('header-id');
        if (!headerId) {
            $('#detailsModalBody').html('<div class="alert alert-warning">اطلاعات این رکورد ناقص است (شناسه یافت نشد).</div>');
            return;
        }
        const modalBody = $('#detailsModalBody');
        modalBody.html('<div class="text-center p-5"><div class="spinner-border" role="status"></div></div>');
        try {
            // Using apiDailySummaryUrl which now includes header_id
            const response = await $.getJSON(apiDailySummaryUrl, { header_id: headerId });
            if (response.success && response.data) {
                const data = response.data; let content = `<p><strong>تاریخ:</strong> ${data.log_date_jalali}</p>`; content += `<p><strong>زمان در دسترس:</strong> ${data.available_time || '-'} دقیقه</p>`; content += `<p><strong>توضیحات:</strong> ${data.description || '-'}</p>`; content += `<h6>پرسنل:</h6>`; if (data.shifts && data.shifts.length > 0) { content += '<ul class="list-unstyled small">'; data.shifts.forEach(shift => { content += `<li>${shift.EmployeeName} (شروع: ${shift.StartTimeFmt || '-'} پایان: ${shift.EndTimeFmt || '-'})</li>`; }); content += '</ul>'; } else { content += '<p class="small text-muted">ثبت نشده</p>'; } content += `<h6 class="mt-3">جزئیات بسته‌بندی:</h6>`; if (data.details && data.details.length > 0) { content += '<table class="table table-sm table-bordered small"><thead><tr><th>محصول</th><th>تعداد کارتن</th></tr></thead><tbody>'; data.details.forEach(detail => { content += `<tr><td>${detail.PartName}</td><td>${detail.CartonsPackaged}</td></tr>`; }); content += `</tbody></table>`; } else { content += '<p class="small text-muted">ثبت نشده</p>'; } modalBody.html(content);
            } else { modalBody.html(`<div class="alert alert-warning">${response.message || 'خطا در دریافت جزئیات.'}</div>`); }
        } catch (error) { console.error("Error fetching details:", error); modalBody.html('<div class="alert alert-danger">خطا در ارتباط با سرور.</div>'); }
    });


    // --- Delete Logic ---
    let headerIdToDelete = null;
    $(document).on('click', '.delete-log-btn', function() {
        headerIdToDelete = $(this).data('header-id');
        if (!headerIdToDelete) return;
        const logDate = $(this).data('log-date');
        $('#deleteConfirmModal .modal-body').text(`آیا از حذف کامل گزارش روز ${logDate} مطمئن هستید؟`);
        $('#delete-header-id-input').val(headerIdToDelete);
    });

    $('#confirm-delete-btn').on('click', async function() {
        headerIdToDelete = $('#delete-header-id-input').val();
        if (!headerIdToDelete) return; const deleteButton = $(this); deleteButton.prop('disabled', true).text('در حال حذف...'); try { const response = await $.post(apiDeleteUrl, { delete_header_id: headerIdToDelete }); if (response.success) { bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide(); loadHistory(); $('#flash-message').remove(); $('<div id="flash-message" class="alert alert-success alert-dismissible fade show" role="alert">' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>').insertBefore('.page-header'); } else { alert('خطا در حذف: ' + response.message); } } catch (error) { console.error("Error deleting log:", error); alert('خطا در ارتباط با سرور هنگام حذف.'); } finally { deleteButton.prop('disabled', false).text('بله، حذف کن'); headerIdToDelete = null; $('#delete-header-id-input').val(''); }
    });

    // --- Initial Load ---
    loadPackagingParts();
    loadHistory(); // Load history on initial page load

}); // End of document ready
</script>

