<?php
// ط§غŒظ† ظپط§غŒظ„ ط´ط§ظ…ظ„ طھظˆط§ط¨ط¹ ط³ط±ط§ط³ط±غŒ ط¨ط±ط§غŒ طھط¨ط¯غŒظ„ طھط§ط±غŒط® ط§ط³طھ

// ط§غŒظ† ط´ط±ط· ط§ط² ط®ط·ط§غŒ ظپط±ط§ط®ظˆط§ظ†غŒ ظ…ط¬ط¯ط¯ ط¯ط± طµظˆط±طھ ظˆط¬ظˆط¯ ط¬ظ„ظˆع¯غŒط±غŒ ظ…غŒâ€Œع©ظ†ط¯
if (!function_exists('jdf_gregorian_to_jalali')) {
    require_once __DIR__ . '/../lib/jdf.php';
}


/**
 * طھط§ط¨ط¹غŒ ط¨ط±ط§غŒ طھط¨ط¯غŒظ„ ط§ط¹ط¯ط§ط¯ ظپط§ط±ط³غŒ ظˆ ط¹ط±ط¨غŒ ط¨ظ‡ ط§ظ†ع¯ظ„غŒط³غŒ
 * ط§غŒظ† طھط§ط¨ط¹ ط¨ط±ط§غŒ ظ¾ط±ط¯ط§ط²ط´ طµط­غŒط­ طھط§ط±غŒط®â€Œظ‡ط§غŒ ظˆط±ظˆط¯غŒ ط§ط² ظپط±ظ… ط¶ط±ظˆط±غŒ ط§ط³طھ
 */
function convert_persian_to_english_numbers($string) {
    if (empty($string)) {
        return $string;
    }
    $persian = ['غ°', 'غ±', 'غ²', 'غ³', 'غ´', 'غµ', 'غ¶', 'غ·', 'غ¸', 'غ¹'];
    $arabic = ['ظ ', 'ظ،', 'ظ¢', 'ظ£', 'ظ¤', 'ظ¥', 'ظ¦', 'ظ§', 'ظ¨', 'ظ©'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $string = str_replace($persian, $english, $string);
    $string = str_replace($arabic, $english, $string);
    return $string;
}


/**
 * طھط§ط±غŒط® ط´ظ…ط³غŒ (ط¬ظ„ط§ظ„غŒ) ط±ط§ ط¨ظ‡ ظ…غŒظ„ط§ط¯غŒ طھط¨ط¯غŒظ„ ظ…غŒâ€Œع©ظ†ط¯
 * @param string|null $jalali_date طھط§ط±غŒط® ط´ظ…ط³غŒ ظ…ط§ظ†ظ†ط¯ '1403/07/22'
 * @return string|null طھط§ط±غŒط® ظ…غŒظ„ط§ط¯غŒ ظ…ط§ظ†ظ†ط¯ '2024-10-14' غŒط§ null ط¯ط± طµظˆط±طھ ظˆط±ظˆط¯غŒ ظ†ط§ظ…ط¹طھط¨ط±
 */
function to_gregorian($jalali_date) {
    if (empty(trim($jalali_date))) {
        return null;
    }
    
    // ظ…ط±ط­ظ„ظ‡ ع©ظ„غŒط¯غŒ: ط§ط¨طھط¯ط§ ط§ط¹ط¯ط§ط¯ ط±ط§ ط¨ظ‡ ط§ظ†ع¯ظ„غŒط³غŒ طھط¨ط¯غŒظ„ ظ…غŒâ€Œع©ظ†غŒظ…
    $jalali_date = convert_persian_to_english_numbers($jalali_date);
    
    $parts = explode('/', $jalali_date);
    if (count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2])) {
        list($j_y, $j_m, $j_d) = $parts;
        // ط¨ظ‡ ط¬ط§غŒ طھط§ط¨ط¹ ظ…ط´ع©ظ„â€Œط³ط§ط²طŒ ظ…ط³طھظ‚غŒظ…ط§ظ‹ طھط¨ط¯غŒظ„ ط±ط§ ط§ظ†ط¬ط§ظ… ظ…غŒâ€Œط¯ظ‡غŒظ… ع†ظˆظ† ع©طھط§ط¨ط®ط§ظ†ظ‡ jdf ط¨ظ‡ ط§ظ†ط¯ط§ط²ظ‡ ع©ط§ظپغŒ ظ‡ظˆط´ظ…ظ†ط¯ ط§ط³طھ
        return jdf_jalali_to_gregorian($j_y, $j_m, $j_d, '-');
    }
    return null; // ط¯ط± طµظˆط±طھ ظپط±ظ…طھ ظ†ط§ظ…ط¹طھط¨ط±طŒ null ط¨ط±ظ…غŒâ€Œع¯ط±ط¯ط§ظ†غŒظ…
}


/**
 * طھط§ط±غŒط® ظ…غŒظ„ط§ط¯غŒ ط±ط§ ط¨ظ‡ ط´ظ…ط³غŒ (ط¬ظ„ط§ظ„غŒ) طھط¨ط¯غŒظ„ ظ…غŒâ€Œع©ظ†ط¯
 * @param string|null $gregorian_date طھط§ط±غŒط® ظ…غŒظ„ط§ط¯غŒ ظ…ط§ظ†ظ†ط¯ '2024-10-14'
 * @return string طھط§ط±غŒط® ط´ظ…ط³غŒ ظ…ط§ظ†ظ†ط¯ '1403/07/22' غŒط§ ط±ط´طھظ‡ ط®ط§ظ„غŒ
 */
function to_jalali($gregorian_date) {
    if (empty($gregorian_date) || $gregorian_date == '0000-00-00 00:00:00' || $gregorian_date == '0000-00-00') {
        return '';
    }
    
    $datetime_parts = explode(' ', $gregorian_date);
    $date_part = $datetime_parts[0];

    $parts = explode('-', $date_part);
    if (count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2])) {
        list($g_y, $g_m, $g_d) = $parts;
         if ($g_y > 0) { // ط¨ط±ط±ط³غŒ ظ…غŒâ€Œع©ظ†غŒظ… ع©ظ‡ طھط§ط±غŒط® طµظپط± ظ†ط¨ط§ط´ط¯
            return jdf_gregorian_to_jalali($g_y, $g_m, $g_d, '/');
        }
    }
    return ''; // ط¯ط± طµظˆط±طھ ظپط±ظ…طھ ظ†ط§ظ…ط¹طھط¨ط±طŒ ط±ط´طھظ‡ ط®ط§ظ„غŒ ط¨ط±ظ…غŒâ€Œع¯ط±ط¯ط§ظ†غŒظ…
}


/**
 * Load packaging station configuration and optionally auto-detect IDs.
 *
 * @param PDO $pdo
 * @return array{packaging_production_station_ids:int[],packaging_warehouse_station_ids:int[],packaging_customer_station_ids:int[]}
 */
function get_packaging_station_config(PDO $pdo): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $config = [
        'packaging_production_station_ids' => [],
        'packaging_warehouse_station_ids' => [],
        'packaging_customer_station_ids' => [],
    ];
    $autoDetect = true;

    $configPath = __DIR__ . '/packaging_config.php';
    if (file_exists($configPath)) {
        $userConfig = require $configPath;
        if (is_array($userConfig)) {
            foreach ($config as $key => $value) {
                if (isset($userConfig[$key]) && is_array($userConfig[$key])) {
                    $config[$key] = array_values(array_unique(array_map('intval', $userConfig[$key])));
                }
            }
            if (array_key_exists('auto_detect', $userConfig)) {
                $autoDetect = (bool)$userConfig['auto_detect'];
            }
        }
    }

    if ($autoDetect) {
        try {
            if (empty($config['packaging_warehouse_station_ids'])) {
                $stmt = $pdo->prepare("SELECT StationID FROM tbl_stations WHERE StationType = 'Warehouse' AND (StationName LIKE :fa OR StationName LIKE :en)");
                $stmt->execute([':fa' => '%بسته%', ':en' => '%pack%']);
                $config['packaging_warehouse_station_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
            if (empty($config['packaging_production_station_ids'])) {
                $stmt = $pdo->prepare("SELECT StationID FROM tbl_stations WHERE StationType = 'Production' AND (StationName LIKE :fa OR StationName LIKE :en)");
                $stmt->execute([':fa' => '%بسته%', ':en' => '%pack%']);
                $config['packaging_production_station_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
            if (empty($config['packaging_customer_station_ids'])) {
                $stmt = $pdo->prepare("SELECT StationID FROM tbl_stations WHERE StationType = 'External' AND (StationName LIKE :fa_customer OR StationName LIKE :en_customer)");
                $stmt->execute([':fa_customer' => '%مشتری%', ':en_customer' => '%customer%']);
                $config['packaging_customer_station_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
        } catch (PDOException $e) {
            error_log('Packaging station auto-detection failed: ' . $e->getMessage());
        }
    }

    foreach ($config as $key => $ids) {
        $config[$key] = array_values(array_unique(array_map('intval', $ids)));
    }

    $cached = $config;
    return $cached;
}

/**
 * Determine if a station should be tracked in cartons.
 */
function station_uses_cartons(PDO $pdo, int $stationId): bool
{
    if ($stationId <= 0) {
        return false;
    }
    $config = get_packaging_station_config($pdo);
    return in_array($stationId, $config['packaging_warehouse_station_ids'], true);
}

/**
 * Determine if a transaction between two stations should use carton counts.
 */
function is_carton_transaction(PDO $pdo, int $fromStationId, int $toStationId): bool
{
    if ($fromStationId <= 0 || $toStationId <= 0) {
        return false;
    }
    $config = get_packaging_station_config($pdo);
    $fromProduction = in_array($fromStationId, $config['packaging_production_station_ids'], true);
    $toWarehouse = in_array($toStationId, $config['packaging_warehouse_station_ids'], true);
    $fromWarehouse = in_array($fromStationId, $config['packaging_warehouse_station_ids'], true);
    $toCustomer = in_array($toStationId, $config['packaging_customer_station_ids'], true);

    return ($fromProduction && $toWarehouse) || ($fromWarehouse && ($toWarehouse || $toCustomer));
}

/**
 * Determine if a carton-based movement is heading towards a customer.
 */
function is_packaging_to_customer(PDO $pdo, int $fromStationId, int $toStationId): bool
{
    if ($fromStationId <= 0 || $toStationId <= 0) {
        return false;
    }
    $config = get_packaging_station_config($pdo);
    return in_array($fromStationId, $config['packaging_warehouse_station_ids'], true)
        && in_array($toStationId, $config['packaging_customer_station_ids'], true);
}

?>
