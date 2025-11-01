<?php
require_once __DIR__ . '/../../config/init.php'; // یا مسیر درست به init.php

echo "PHP Current Gregorian Date/Time: " . date('Y-m-d H:i:s P') . "<br>";
echo "Converted Jalali Date (using to_jalali): " . to_jalali(date('Y-m-d')) . "<br>";
echo "Current Jalali Date (using persian-date library format): " . (new persianDate())->format('YYYY/MM/DD'); // Requires persian-date library loaded if not already

// ... بقیه کد صفحه ...
?>