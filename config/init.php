<?php
/**
 * Master Initialization File for Protected Pages.
 *
 * This script is the single entry point for all pages that require a user to be logged in.
 * It performs the following critical tasks in order:
 * 1. Starts the session to manage user state.
 * 2. Includes the core database configuration and all helper functions.
 * 3. Enforces authentication by redirecting non-logged-in users to the login page.
 * 4. Loads the specific permissions for the logged-in user into the session.
 */

// --- 1. Start Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 2. Include Core Configuration and Helpers ---
// This brings in BASE_URL, the $pdo database connection, and all helper functions.
require_once __DIR__ . '/db.php';


// --- 3. Enforce Login ---
// This function (from auth_helpers.php) checks if $_SESSION['user_id'] is set.
// If not, it halts script execution and redirects to the login page.
require_login();


// --- 4. Load User Permissions ---
// Now that we are certain a user is logged in, we fetch their specific permissions.
load_user_permissions($pdo, $_SESSION['user_id']);

