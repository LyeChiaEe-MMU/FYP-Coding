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

// ── One-time migration: add color column to cart_items if missing ─
$_col = $conn->query("SHOW COLUMNS FROM cart_items LIKE 'color'");
if($_col && $_col->num_rows === 0){
    $conn->query("ALTER TABLE cart_items ADD COLUMN color VARCHAR(80) NOT NULL DEFAULT 'Default' AFTER size");
}

// ── One-time migration: add gender column to products if missing ──
$_gcol = $conn->query("SHOW COLUMNS FROM products LIKE 'gender'");
if($_gcol && $_gcol->num_rows === 0){
    $conn->query("ALTER TABLE products ADD COLUMN gender VARCHAR(20) NOT NULL DEFAULT 'Unisex' AFTER category_id");
}
// ── Set existing products to Men gender (they are men's shoes) ───
$conn->query("UPDATE products SET gender='Men' WHERE gender='Unisex'");

// ── One-time migration: add is_on_sale column to products if missing ──
$_scol = $conn->query("SHOW COLUMNS FROM products LIKE 'is_on_sale'");
if($_scol && $_scol->num_rows === 0){
    $conn->query("ALTER TABLE products ADD COLUMN is_on_sale TINYINT(1) NOT NULL DEFAULT 0 AFTER stock");
}

// ── One-time migration: add is_active column to products if missing ──
$_acol = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
if($_acol && $_acol->num_rows === 0){
    $conn->query("ALTER TABLE products ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_on_sale");
}

// ── Create contact_messages table if missing ──────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(180) NOT NULL,
    subject    VARCHAR(120) NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cm_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Create notifications table if missing ──────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notif_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(255) NOT NULL,
    message    TEXT NOT NULL,
    type       VARCHAR(50) NOT NULL DEFAULT 'info',
    is_read    TINYINT(1)  NOT NULL DEFAULT 0,
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_uid_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Create vouchers table if missing ───────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS vouchers (
    voucher_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    code       VARCHAR(20) NOT NULL UNIQUE,
    amount     DECIMAL(10,2) NOT NULL,
    is_used    TINYINT(1)  NOT NULL DEFAULT 0,
    reason     VARCHAR(255) NOT NULL DEFAULT '',
    expires_at DATE DEFAULT NULL,
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_voucher_uid (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── One-time migration: add address column to users if missing ───
$_uaddr = $conn->query("SHOW COLUMNS FROM users LIKE 'address'");
if($_uaddr && $_uaddr->num_rows === 0){
    $conn->query("ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL");
}

// ── One-time migration: add is_banned column to users if missing ─
$_ubancol = $conn->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
if($_ubancol && $_ubancol->num_rows === 0){
    $conn->query("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0");
}

// ── Create reviews table if missing ──────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    order_id   INT NOT NULL DEFAULT 0,
    rating     TINYINT(1) NOT NULL,
    comment    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rev_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Add order_id to reviews if table already existed without it ──
$_rvcol = $conn->query("SHOW COLUMNS FROM reviews LIKE 'order_id'");
if($_rvcol && $_rvcol->num_rows === 0){
    $conn->query("ALTER TABLE reviews ADD COLUMN order_id INT NOT NULL DEFAULT 0 AFTER product_id");
}

// ── Add UNIQUE KEY uq_review if not present (safe to ignore if exists) ──
// Use a try-approach: silently drop+re-add or skip if already there
$_rvkey = $conn->query("SHOW INDEX FROM reviews WHERE Key_name='uq_review'");
if($_rvkey && $_rvkey->num_rows === 0){
    // Only add if order_id column now exists
    $_rvcol2 = $conn->query("SHOW COLUMNS FROM reviews LIKE 'order_id'");
    if($_rvcol2 && $_rvcol2->num_rows > 0){
        $conn->query("ALTER TABLE reviews ADD UNIQUE KEY uq_review (user_id, product_id, order_id)");
    }
}

// ── One-time migration: add color column to order_items if missing ─
$_oicol = $conn->query("SHOW COLUMNS FROM order_items LIKE 'color'");
if($_oicol && $_oicol->num_rows === 0){
    $conn->query("ALTER TABLE order_items ADD COLUMN color VARCHAR(80) NOT NULL DEFAULT 'Default' AFTER size");
}

// ── One-time migration: add payment_method to orders ─────────────
$_pmcol = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
if($_pmcol && $_pmcol->num_rows === 0){
    $conn->query("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Online Banking' AFTER shipping_address");
}
// ── One-time migration: add payment_detail to orders ─────────────
$_pdcol = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_detail'");
if($_pdcol && $_pdcol->num_rows === 0){
    $conn->query("ALTER TABLE orders ADD COLUMN payment_detail VARCHAR(100) DEFAULT NULL AFTER payment_method");
}
// ── One-time migration: add discount_amount to orders ────────────
$_dacol = $conn->query("SHOW COLUMNS FROM orders LIKE 'discount_amount'");
if($_dacol && $_dacol->num_rows === 0){
    $conn->query("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
}
// ── One-time migration: add voucher_code to orders ───────────────
$_vccol = $conn->query("SHOW COLUMNS FROM orders LIKE 'voucher_code'");
if($_vccol && $_vccol->num_rows === 0){
    $conn->query("ALTER TABLE orders ADD COLUMN voucher_code VARCHAR(20) DEFAULT NULL AFTER discount_amount");
}

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
    $map=['Processing'=>'st-processing','Delivered'=>'st-shipped','Completed'=>'st-completed','Cancelled'=>'st-cancelled'];
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
