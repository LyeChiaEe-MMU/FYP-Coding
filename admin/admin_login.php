<?php
session_start();
require '../db.php';

// Function to check remember me cookie and restore session
function check_remember_me_cookie(){
    global $conn;
    
    if(isset($_COOKIE['admin_remember'])){
        $token = $_COOKIE['admin_remember'];
        $current_time = time();
        
        $stmt = $conn->prepare("SELECT admin_id, username FROM admins WHERE remember_token = ? AND token_expiry > ? LIMIT 1");
        $stmt->bind_param("si", $token, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($admin = $result->fetch_assoc()){
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['login_method'] = 'remember_me';
            return true;
        }
        $stmt->close();
    }
    return false;
}

// Check if already logged in
if(!empty($_SESSION['admin_id'])){
    header("Location: admin_dashboard.php");
    exit;
}

// Check cookie for auto-login
if(check_remember_me_cookie()){
    header("Location: admin_dashboard.php"); 
    exit;
}

// Check if any admin account exists
$check = $conn->query("SELECT * FROM admins LIMIT 1");

// If no admin exists, create a default admin account
if($check->num_rows === 0){
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password) VALUES ('admin', '$hashed')");
}

$error = '';

// Process login form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? true : false;

    if(!$username || !$password){
        $error = "Please enter your username and password.";
    } else {
        $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if($admin && password_verify($password, $admin['password'])){
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];

            if($remember_me){
                // REMEMBER ME CHECKED - Create 30 day cookie
                $_SESSION['login_method'] = 'remember_me';
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (86400 * 30);

                $update_stmt = $conn->prepare("UPDATE admins SET remember_token = ?, token_expiry = ? WHERE admin_id = ?");
                $update_stmt->bind_param("sii", $token, $expiry, $admin['admin_id']);
                $update_stmt->execute();
                $update_stmt->close();

                setcookie('admin_remember', $token, $expiry, '/', '', false, true);

            } else {
                // REMEMBER ME NOT CHECKED - Session only, no cookie
                $_SESSION['login_method'] = 'session_only';

                // Clear any existing remember me data
                $clear_stmt = $conn->prepare("UPDATE admins SET remember_token = NULL, token_expiry = NULL WHERE admin_id = ?");
                $clear_stmt->bind_param("i", $admin['admin_id']);
                $clear_stmt->execute();
                $clear_stmt->close();

                if(isset($_COOKIE['admin_remember'])){
                    setcookie('admin_remember', '', time() - 3600, '/', '', false, true);
                }
            }

            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Login | Apex</title>
<link rel="stylesheet" href="../css/style.css?v=4">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-login-wrap {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 35% 65%;
    background: var(--navy);
    position: relative;
    overflow: hidden;
}

/* Decorative background shapes */
.admin-login-wrap::before {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(200,84,60,.06) 0%, transparent 70%);
    top: -200px; left: -200px;
    pointer-events: none;
}
.admin-login-wrap::after {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(200,84,60,.04) 0%, transparent 70%);
    bottom: -150px; right: -100px;
    pointer-events: none;
}

/* Left brand strip */
.admin-brand-strip {
    background:
        radial-gradient(ellipse at 15% 85%, rgba(255,255,255,0.18) 0%, transparent 50%),
        radial-gradient(ellipse at 85% 15%, rgba(255,220,180,0.22) 0%, transparent 50%),
        linear-gradient(160deg, #C8543C 0%, #D96A46 45%, #C05030 100%);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px 44px;
    position: relative;
    overflow: hidden;
}
.admin-brand-strip::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: repeating-linear-gradient(
        -45deg,
        transparent,
        transparent 30px,
        rgba(255,255,255,.05) 30px,
        rgba(255,255,255,.05) 31px
    );
}
.admin-strip-logo {
    font-family: 'Oswald', sans-serif;
    font-size: 2.4rem;
    letter-spacing: 6px;
    color: #FFFFFF;
    position: relative;
    margin-bottom: 6px;
}
.admin-strip-logo span { color: #2A0E04; }
.admin-strip-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.45);
    border-radius: 100px;
    padding: 4px 14px;
    font-size: .65rem;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #FFFFFF;
    margin-bottom: 44px;
    position: relative;
    width: fit-content;
}
.admin-strip-divider {
    width: 40px; height: 2px;
    background: rgba(255,255,255,0.5);
    margin-bottom: 28px;
    position: relative;
}
.admin-strip-feat {
    display: flex;
    flex-direction: column;
    gap: 18px;
    position: relative;
}
.admin-strip-feat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: .82rem;
    color: #FFFFFF;
}
.admin-strip-feat-item i {
    width: 32px; height: 32px;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
    font-size: .8rem;
    flex-shrink: 0;
}

/* Right form panel */
.admin-form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
    position: relative;
}
.admin-form-inner {
    width: 100%;
    max-width: 400px;
}
.admin-form-head {
    margin-bottom: 36px;
}
.admin-form-head h1 {
    font-family: 'Oswald', sans-serif;
    font-size: 1.8rem;
    letter-spacing: 2px;
    color: var(--white);
    margin-bottom: 6px;
}
.admin-form-head p {
    font-size: .875rem;
    color: var(--muted);
}

@media(max-width: 1024px){
    .admin-login-wrap { grid-template-columns: 1fr; }
    .admin-brand-strip { display: none; }
}

/* ── Keyframes ── */
@keyframes apxFadeUp {
  from { opacity:0; transform:translateY(22px); }
  to   { opacity:1; transform:translateY(0); }
}
@keyframes apxSlideIn {
  from { opacity:0; transform:translateX(-24px); }
  to   { opacity:1; transform:translateX(0); }
}
@keyframes apxBgPulse {
  0%,100% { background-position:0% 60%; }
  50%     { background-position:100% 40%; }
}
@keyframes apxFloat {
  0%,100% { transform:rotate(-8deg) translateY(0); }
  50%     { transform:rotate(-8deg) translateY(-18px); }
}
@keyframes apxRingPulse {
  0%   { transform:scale(.8); opacity:.45; }
  100% { transform:scale(1.75); opacity:0; }
}

/* Animated gradient on left strip */
.admin-brand-strip {
  background: linear-gradient(160deg,#B03828,#C8543C,#D06840,#C04032) !important;
  background-size:300% 300% !important;
  animation: apxBgPulse 10s ease infinite !important;
}

/* Decorative rings */
.brand-deco {
  position:absolute; border-radius:50%;
  border:1.5px solid rgba(255,255,255,.16);
  pointer-events:none;
}
.brand-deco-1 { width:280px;height:280px; bottom:-70px;right:-70px; animation:apxRingPulse 4.5s ease-out infinite; }
.brand-deco-2 { width:175px;height:175px; bottom:-25px;right:-25px; animation:apxRingPulse 4.5s 1.4s ease-out infinite; }
.brand-deco-3 { width:200px;height:200px; top:-60px;left:-60px;    animation:apxRingPulse 5.5s 0.7s ease-out infinite; }

/* Floating icon */
.brand-float-icon {
  position:absolute; right:30px; bottom:75px;
  font-size:5rem; opacity:.1;
  animation:apxFloat 7s ease-in-out infinite;
  pointer-events:none;
}

/* Content animations */
.admin-strip-logo    { animation:apxSlideIn .65s cubic-bezier(.22,1,.36,1) both; }
.admin-strip-badge   { animation:apxFadeUp  .55s .18s cubic-bezier(.22,1,.36,1) both; }
.admin-strip-divider { animation:apxFadeUp  .4s  .32s cubic-bezier(.22,1,.36,1) both; }
.admin-strip-feat-item:nth-child(1) { animation:apxFadeUp .5s .46s cubic-bezier(.22,1,.36,1) both; }
.admin-strip-feat-item:nth-child(2) { animation:apxFadeUp .5s .61s cubic-bezier(.22,1,.36,1) both; }
.admin-strip-feat-item:nth-child(3) { animation:apxFadeUp .5s .76s cubic-bezier(.22,1,.36,1) both; }
.admin-strip-feat-item:nth-child(4) { animation:apxFadeUp .5s .91s cubic-bezier(.22,1,.36,1) both; }

/* Form panel entry */
.admin-form-inner { animation:apxFadeUp .55s .05s cubic-bezier(.22,1,.36,1) both; }

/* Back button */
.back-btn {
  display:inline-flex; align-items:center; gap:8px;
  padding:9px 18px 9px 14px;
  background:rgba(150,100,75,.09);
  border:1.5px solid rgba(150,100,75,.25);
  border-radius:100px;
  color:var(--muted); font-size:.8rem; font-weight:600;
  letter-spacing:.4px; text-decoration:none;
  transition:all .22s;
}
.back-btn:hover {
  color:var(--accent);
  border-color:rgba(200,84,60,.55);
  background:rgba(200,84,60,.08);
  transform:translateX(-3px);
}
.back-btn i { font-size:.7rem; transition:transform .22s; }
.back-btn:hover i { transform:translateX(-4px); }
</style>
</head>
<body>
<div class="admin-login-wrap">

  <!-- Left brand strip -->
  <div class="admin-brand-strip">
    <div class="brand-deco brand-deco-1"></div>
    <div class="brand-deco brand-deco-2"></div>
    <div class="brand-deco brand-deco-3"></div>
    <div class="brand-float-icon"><i class="fa-solid fa-shield-halved"></i></div>
    <div class="admin-strip-logo">APE<span>X</span></div>
    <div class="admin-strip-badge"><i class="fa-solid fa-shield-halved"></i> Admin Panel</div>
    <div class="admin-strip-divider"></div>
    <div class="admin-strip-feat">
      <div class="admin-strip-feat-item">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Dashboard &amp; analytics overview</span>
      </div>
      <div class="admin-strip-feat-item">
        <i class="fa-solid fa-box-open"></i>
        <span>Manage products &amp; inventory</span>
      </div>
      <div class="admin-strip-feat-item">
        <i class="fa-solid fa-truck"></i>
        <span>Track &amp; update orders</span>
      </div>
      <div class="admin-strip-feat-item">
        <i class="fa-solid fa-users"></i>
        <span>View customers &amp; requests</span>
      </div>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="admin-form-panel">
    <div class="admin-form-inner">

      <div class="admin-form-head">
        <h1>ADMIN LOGIN</h1>
        <p>Sign in to access the Apex dashboard.</p>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Username</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="username" placeholder="Username"
                   value="<?=e($_POST['username']??'')?>" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" id="adminPw" placeholder="Password" required>
            <button type="button" class="pw-toggle" onclick="toggleAdminPw()" tabindex="-1">
              <i class="fa-solid fa-eye" id="adminPwIcon"></i>
            </button>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
          <input type="checkbox" name="remember_me" id="remember_me" value="1"
                 style="width:16px;height:16px;margin:0;cursor:pointer;accent-color:var(--accent);">
          <label for="remember_me" style="margin:0;cursor:pointer;color:var(--muted);font-size:.82rem;">
            Stay logged in for 30 days
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" style="letter-spacing:2px;">
          ACCESS DASHBOARD
        </button>
      </form>

      <div style="text-align:center;margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="../index.php" class="back-btn">
          <i class="fa-solid fa-arrow-left"></i> Back to Store
        </a>
      </div>

    </div>
  </div>

</div>

<script>
function toggleAdminPw(){
  const f=document.getElementById('adminPw');
  const i=document.getElementById('adminPwIcon');
  if(f.type==='password'){ f.type='text'; i.className='fa-solid fa-eye-slash'; }
  else { f.type='password'; i.className='fa-solid fa-eye'; }
}
</script>
</body>
</html>