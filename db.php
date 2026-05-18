<?php
// ── Database ────────────────────────────────────────────────────
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "apex_store";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('<div style="font-family:monospace;background:#0d0d1a;color:#ff4d4f;padding:24px 28px;border-left:4px solid #ff4d4f;margin:20px;border-radius:6px;">
        <strong>Database Connection Failed</strong><br><br>
        '.$conn->connect_error.'<br><br>
        <small>Database: <b>'.$dbname.'</b> | Make sure XAMPP is running and <code>setup.sql</code> has been imported.</small>
    </div>');
}
$conn->set_charset('utf8mb4');

// ── Helpers ─────────────────────────────────────────────────────
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function cart_count($conn){
    if(empty($_SESSION['user_id'])) return 0;
    $uid=(int)$_SESSION['user_id'];
    $r=$conn->query("SELECT COALESCE(SUM(quantity),0) AS c FROM cart_items WHERE user_id=$uid");
    return (int)$r->fetch_assoc()['c'];
}

function is_logged(){ return !empty($_SESSION['user_id']); }

// SIMPLE is_admin - only checks session
function is_admin(){ 
    return !empty($_SESSION['admin_id']);
}

function status_badge($status){
    $map=['Processing'=>'st-processing','Shipped'=>'st-shipped','Completed'=>'st-completed','Cancelled'=>'st-cancelled'];
    $cls=$map[$status]??'st-processing';
    return '<span class="status-badge '.$cls.'">'.e($status).'</span>';
}
?>
