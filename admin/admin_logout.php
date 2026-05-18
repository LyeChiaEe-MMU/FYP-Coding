<?php
session_start();
require '../db.php';

// Clear remember me token from database
if(isset($_SESSION['admin_id'])){
    $stmt = $conn->prepare("UPDATE admins SET remember_token = NULL, token_expiry = NULL WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $stmt->close();
}

// Clear session
session_unset();
session_destroy();

// Clear cookie
if(isset($_COOKIE['admin_remember'])){
    setcookie('admin_remember', '', time() - 3600, '/');
}

header("Location: admin_login.php");
exit;
?>
