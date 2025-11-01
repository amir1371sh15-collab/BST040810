</div> <!-- Closing the main container-fluid -->

<!-- jQuery is required for Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap Bundle JS (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- *** Jalali Datepicker JS *** -->
<script type="text/javascript" src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>

<!-- Chart.js Datalabels Plugin (Must be included AFTER Chart.js) -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<!-- Global Activation Script for Datepicker -->
<script type="text/javascript">
    $(document).ready(function() {
        // --- REVISED INITIALIZATION using jalaliDatepicker ---
        const todayJalali = '<?php echo to_jalali(date('Y-m-d')); ?>'; // Get today's date from PHP

        // First, ensure all empty date inputs have today's date using jQuery
        $(".persian-date").each(function() {
            if (!$(this).val()) {
                $(this).val(todayJalali);
            }
        });

        // Now, initialize the datepicker on ALL elements matching the selector
        jalaliDatepicker.startWatch({
            selector: ".persian-date", // Use the class selector directly
            time: false,
            format: 'YYYY/MM/DD',
            todayButton: true,
            showCloseButton: true,
        });
        // --- END OF REVISED INITIALIZATION ---
    });
</script>

</body>
</html>
