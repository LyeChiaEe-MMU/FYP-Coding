<?php
// admin/auth_check.php - Include at top of ALL admin pages
session_start();
require_once '../db.php';

// Try restore from remember-me cookie
function restore_from_remember_cookie() {
    global $conn;
    if (!empty($_SESSION['admin_id'])) return true;
    if (!isset($_COOKIE['admin_remember'])) return false;

    $token = hash('sha256', $_COOKIE['admin_remember']);
    $now   = time();
    $stmt  = $conn->prepare("SELECT admin_id, username FROM admins WHERE remember_token = ? AND token_expiry > ? AND is_banned = 0 LIMIT 1");
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

// Verify the account still exists and load its role — a deleted admin's
// session dies immediately instead of lingering until it expires
$_ac = $conn->prepare("SELECT role, is_banned FROM admins WHERE admin_id=? LIMIT 1");
$_ac_id = (int)$_SESSION['admin_id'];
$_ac->bind_param("i", $_ac_id);
$_ac->execute();
$_ac_row = $_ac->get_result()->fetch_assoc();
if (!$_ac_row) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['login_method']);
    header("Location: admin_login.php");
    exit;
}
// Banned admin — kill the session and send them to login, where the ban popup explains why
if (!empty($_ac_row['is_banned'])) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['login_method']);
    header("Location: admin_login.php?banned=1");
    exit;
}
$_SESSION['admin_role'] = $_ac_row['role'] ?: 'admin';

function is_superadmin() {
    return ($_SESSION['admin_role'] ?? '') === 'superadmin';
}
