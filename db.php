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
        Could not connect to the database. Please try again later.<br><br>
        <small>Make sure XAMPP is running and the database has been imported.</small>
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

// ── CSRF ─────────────────────────────────────────────────────────
function csrf_token(){
    if(empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(){
    return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function csrf_check(){
    $t = $_POST['csrf_token'] ?? '';
    if(!$t || !hash_equals(csrf_token(), $t)){
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}

// ── Image upload validation (extension + MIME) ───────────────────
function valid_image_upload($file, &$error){
    $allowed_ext  = ['jpg','jpeg','png','gif','webp'];
    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, $allowed_ext)){ $error = "Image must be JPG, PNG, GIF or WEBP."; return false; }
    if(function_exists('finfo_open')){
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if(!in_array($mime, $allowed_mime)){ $error = "Invalid image file type."; return false; }
    }
    return true;
}
?>
