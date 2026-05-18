<?php
// admin/auth_check.php - Include at top of ALL admin pages
session_start();
require_once '../db.php';

// Try restore from remember-me cookie
function restore_from_remember_cookie() {
    global $conn;
    if (!empty($_SESSION['admin_id'])) return true;
    if (!isset($_COOKIE['admin_remember'])) return false;

    $token = $_COOKIE['admin_remember'];
    $now   = time();
    $stmt  = $conn->prepare("SELECT admin_id, username FROM admins WHERE remember_token = ? AND token_expiry > ? LIMIT 1");
    $stmt->bind_param("si", $token, $now);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if ($admin) {
        $_SESSION['admin_id']       = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['login_method']   = 'remember_me';
        return true;
    }
    return false;
}

restore_from_remember_cookie();

if (empty($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
