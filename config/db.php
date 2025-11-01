<?php
// Start the session at the very beginning.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Define the Base URL for the project ---
define('BASE_URL', 'http://localhost/bst/');


// --- Database Connection Settings ---
$host = 'localhost';
$db   = 'bst_manufacturing_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Include All Helper Functions ---
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/crud_helpers.php';

