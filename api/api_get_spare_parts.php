<?php
// Set the content type header to signal a JSON response.
header('Content-Type: application/json; charset=utf-8');

// Include the master database configuration file.
require_once __DIR__ . '/../config/db.php';

/**
 * Creates a standardized JSON response.
 * @param bool $success - Whether the request was successful.
 * @param mixed $data - The data payload to be returned.
 * @param string $message - A descriptive message.
 * @return string - The JSON encoded response.
 */
function create_response(bool $success, $data = [], string $message = ''): string {
    return json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
}

// --- Input Validation ---
// Check if mold_id is provided and is a valid number.
if (!isset($_GET['mold_id']) || !is_numeric($_GET['mold_id'])) {
    // If not, set a 400 Bad Request status code and return an error.
    http_response_code(400);
    echo create_response(false, [], 'شناسه قالب نامعتبر یا ارسال نشده است.');
    exit;
}

$moldId = (int)$_GET['mold_id'];

try {
    // Prepare the SQL query using a placeholder to prevent SQL injection.
    $stmt = $pdo->prepare(
        "SELECT PartID, PartName, PartCode 
         FROM tbl_eng_spare_parts 
         WHERE MoldID = ? 
         ORDER BY PartName"
    );

    // Execute the query with the validated mold ID.
    $stmt->execute([$moldId]);
    
    // Fetch all matching spare parts.
    $parts = $stmt->fetchAll();
    
    // Check if any parts were found.
    if ($parts) {
        // If parts are found, return a successful response with the data.
        echo create_response(true, $parts, 'قطعات با موفقیت یافت شدند.');
    } else {
        // If no parts are found for the given mold, return a successful but empty response.
        echo create_response(true, [], 'هیچ قطعه‌ای برای این قالب یافت نشد.');
    }

} catch (PDOException $e) {
    // In case of a database error, log the detailed error for the developer.
    error_log("API Error in api_get_spare_parts.php: " . $e->getMessage());
    
    // Set a 500 Internal Server Error status code.
    http_response_code(500);
    
    // Return a generic error message to the user for security.
    echo create_response(false, [], 'خطایی در سرور رخ داده است. لطفا بعدا تلاش کنید.');
}

