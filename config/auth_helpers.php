<?php
/**
 * Redirects the user to the login page (index.php) if they are not currently logged in.
 */
function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        // Redirect to the main index.php, which is now the login page.
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Loads the permissions for the currently logged-in user into the session.
 */
function load_user_permissions(PDO $pdo, int $user_id): void
{
    if (isset($_SESSION['user_permissions']) && $_SESSION['user_id'] === $user_id) {
        return; // Permissions already loaded
    }

    $user = find_by_id($pdo, 'tbl_users', $user_id, 'UserID');
    if (!$user) {
        $_SESSION['user_permissions'] = [];
        return;
    }

    $role_id = $user['RoleID'];
    $permissions_raw = find_all($pdo, "SELECT PermissionKey FROM tbl_role_permissions WHERE RoleID = ?", [$role_id]);
    
    $_SESSION['user_permissions'] = array_column($permissions_raw, 'PermissionKey');
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $user['Username']; 
}

/**
 * Checks if the current user has a specific permission.
 */
function has_permission(string $permission_key): bool
{
    if (!isset($_SESSION['user_permissions'])) {
        return false;
    }
    return in_array($permission_key, $_SESSION['user_permissions']);
}

