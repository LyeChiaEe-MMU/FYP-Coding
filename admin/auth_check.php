<?php
// admin/auth_check.php - Include this at the top of ALL admin pages
session_start();
require_once '../db.php';

// Function to check remember me cookie and restore session
function restore_from_remember_cookie(){
    global $conn;
    
    // If already logged in via session, do nothing
    if(!empty($_SESSION['admin_id'])){
        return true;
    }
    
    // Check if remember me cookie exists
    if(isset($_COOKIE['admin_remember'])){
        $token = $_COOKIE['admin_remember'];
        $current_time = time();
        
        // Find valid token in database
        $stmt = $conn->prepare("SELECT admin_id, username FROM admins WHERE remember_token = ? AND token_expiry > ? LIMIT 1");
        $stmt->bind_param("si", $token, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($admin = $result->fetch_assoc()){
            // Restore session
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['login_method'] = 'remember_me';
            return true;
        }
        $stmt->close();
    }
    
    return false;
}

// Try to restore from remember me cookie
restore_from_remember_cookie();

// Check if admin is logged in
if(empty($_SESSION['admin_id'])){
    // Not logged in, redirect to login page
    header("Location: admin_login.php");
    exit;
}

// CRITICAL: For session-only logins (no remember me), 
// we need to ensure they are not kept after browser close
// This checks if the user is supposed to be logged out on browser close
if(isset($_SESSION['login_method']) && $_SESSION['login_method'] == 'session_only'){
    // Check if this is a new browser session (no ongoing session)
    // We use a simple check - if the page was accessed directly after browser close
    // This is a safety measure
    if(!isset($_SERVER['HTTP_REFERER']) && empty($_SERVER['HTTP_REFERER'])){
        // Direct access without referer might be a restored session
        // Force logout for session_only users
        session_unset();
        session_destroy();
        header("Location: admin_login.php");
        exit;
    }
}