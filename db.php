<?php
// ── Database ────────────────────────────────────────────────────
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "apex_store";

$conn = new mysqli($host, $user, $pass, $dbname);
date_default_timezone_set('Asia/Kuala_Lumpur');
if ($conn->connect_error) {
    die('<div style="font-family:monospace;background:#0d0d1a;color:#ff4d4f;padding:24px 28px;border-left:4px solid #ff4d4f;margin:20px;border-radius:6px;">
        <strong>Database Connection Failed</strong><br><br>
        Could not connect to the database. Please try again later.<br><br>
        <small>Make sure XAMPP is running and the database has been imported.</small>
    </div>');
}
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");

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
// (one-time data migration was already applied — no recurring UPDATE needed)

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

// ── One-time migration: add sale_percent column to products if missing ──
$_spcol = $conn->query("SHOW COLUMNS FROM products LIKE 'sale_percent'");
if($_spcol && $_spcol->num_rows === 0){
    $conn->query("ALTER TABLE products ADD COLUMN sale_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_on_sale");
}

// ── Create site_settings table if missing ────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── One-time migration: add banner_image to categories ───────────
$_catbanner = $conn->query("SHOW COLUMNS FROM categories LIKE 'banner_image'");
if($_catbanner && $_catbanner->num_rows === 0){
    $conn->query("ALTER TABLE categories ADD COLUMN banner_image VARCHAR(255) DEFAULT NULL");
}

// ── Create order_status_history table if missing ──────────────────
$conn->query("CREATE TABLE IF NOT EXISTS order_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT NOT NULL,
    status     VARCHAR(30) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_osh_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

// ── One-time migration: add original_price column to order_items if missing ─
$_oiorig = $conn->query("SHOW COLUMNS FROM order_items LIKE 'original_price'");
if($_oiorig && $_oiorig->num_rows === 0){
    $conn->query("ALTER TABLE order_items ADD COLUMN original_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");
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

// Returns the effective selling price after discount — rounded to whole ringgit
function effective_price($price, $percent){
    $pct = max(0, min(99, (int)$percent));
    return $pct > 0 ? (float)round((float)$price * (1 - $pct / 100)) : (float)$price;
}

// Renders price with strikethrough original + red sale price + % badge
// $mode: 'card' (product cards) | 'detail' (product detail page)
function price_html($price, $percent, $mode = 'card'){
    $pct = max(0, min(99, (int)$percent));
    if($pct <= 0){
        if($mode === 'detail')
            return '<div class="detail-price">RM '.number_format((float)$price,2).'</div>';
        return '<span class="prod-price">RM '.number_format((float)$price,2).'</span>';
    }
    $sp = effective_price($price, $pct);
    $badge = '<span style="background:var(--danger);color:#fff;font-size:.6em;padding:2px 7px;border-radius:4px;font-weight:700;letter-spacing:.5px;vertical-align:middle;margin-left:4px;">'.$pct.'% OFF</span>';
    if($mode === 'detail'){
        return '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">'
             . '<span style="text-decoration:line-through;color:var(--muted);font-size:1.1rem;">RM '.number_format((float)$price,2).'</span>'
             . '<span class="detail-price" style="color:var(--danger);margin-bottom:0;">RM '.number_format($sp,2).'</span>'
             . $badge
             . '</div>';
    }
    return '<span style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">'
         . '<span style="text-decoration:line-through;color:var(--muted);font-size:.78rem;">RM '.number_format((float)$price,2).'</span>'
         . '<span class="prod-price" style="color:var(--danger);">RM '.number_format($sp,2).'</span>'
         . $badge
         . '</span>';
}

function add_notification($conn, $user_id, $title, $message, $type = 'info'){
    $s = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $s->bind_param("isss", $user_id, $title, $message, $type);
    $s->execute();
}

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
