<?php
session_start();
require 'db.php';
if(is_logged()){ header("Location: index.php"); exit; }

$error = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if($user && password_verify($password,$user['password'])){
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: index.php"); exit;
    } else {
        $error = "Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login | Apex</title>
<link rel="stylesheet" href="css/style.css?v=9">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
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
  0%,100% { transform:rotate(-15deg) translateY(0); }
  50%     { transform:rotate(-15deg) translateY(-18px); }
}
@keyframes apxRingPulse {
  0%   { transform:scale(.8); opacity:.45; }
  100% { transform:scale(1.75); opacity:0; }
}

/* Animated gradient background */
.register-brand {
  background: linear-gradient(140deg,#B03828,#C8543C,#D06840,#C04032) !important;
  background-size:300% 300% !important;
  animation: apxBgPulse 10s ease infinite !important;
}

/* Decorative pulsing rings */
.brand-deco {
  position:absolute; border-radius:50%;
  border:1.5px solid rgba(255,255,255,.18);
  pointer-events:none;
}
.brand-deco-1 { width:300px;height:300px; bottom:-80px;right:-80px; animation:apxRingPulse 4.5s ease-out infinite; }
.brand-deco-2 { width:190px;height:190px; bottom:-30px;right:-30px; animation:apxRingPulse 4.5s 1.4s ease-out infinite; }
.brand-deco-3 { width:200px;height:200px; top:-60px;left:-60px;    animation:apxRingPulse 5.5s 0.7s ease-out infinite; }

/* Floating shoe icon */
.brand-float-icon {
  position:absolute; right:32px; bottom:80px;
  font-size:5.5rem; opacity:.1;
  animation:apxFloat 7s ease-in-out infinite;
  pointer-events:none;
}

/* Content staggered animations */
.register-brand-logo    { animation:apxSlideIn .65s cubic-bezier(.22,1,.36,1) both; }
.register-brand-tagline { animation:apxFadeUp  .6s .18s cubic-bezier(.22,1,.36,1) both; }
.register-brand-desc    { animation:apxFadeUp  .6s .34s cubic-bezier(.22,1,.36,1) both; }
.register-feature:nth-child(1) { animation:apxFadeUp .5s .50s cubic-bezier(.22,1,.36,1) both; }
.register-feature:nth-child(2) { animation:apxFadeUp .5s .65s cubic-bezier(.22,1,.36,1) both; }
.register-feature:nth-child(3) { animation:apxFadeUp .5s .80s cubic-bezier(.22,1,.36,1) both; }

/* Form panel entry */
.register-form-inner { animation:apxFadeUp .55s .05s cubic-bezier(.22,1,.36,1) both; }

/* Back button — pill style, clearly visible */
.back-btn {
  display:inline-flex; align-items:center; gap:8px;
  padding:9px 18px 9px 14px;
  background:rgba(150,100,75,.09);
  border:1.5px solid rgba(150,100,75,.25);
  border-radius:100px;
  color:var(--muted); font-size:.8rem; font-weight:600;
  letter-spacing:.4px; text-decoration:none;
  transition:all .22s; margin-bottom:30px;
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

<div class="register-split">
  <!-- ─── Left: Brand Panel ─── -->
  <div class="register-brand">
    <div class="brand-deco brand-deco-1"></div>
    <div class="brand-deco brand-deco-2"></div>
    <div class="brand-deco brand-deco-3"></div>
    <div class="brand-float-icon"><i class="fa-solid fa-shoe-prints"></i></div>
    <div class="register-brand-overlay"></div>
    <div class="register-brand-content">
      <div class="register-brand-logo">APE<span>X</span></div>
      <h2 class="register-brand-tagline">Welcome<br><span>Back.</span></h2>
      <p class="register-brand-desc">Sign in to continue your Apex journey. Your orders, wishlist, and exclusive member deals are waiting for you.</p>
      <div class="register-brand-features">
        <div class="register-feature">
          <i class="fa-solid fa-box"></i>
          <span>Track your orders in real-time</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-heart"></i>
          <span>Access your wishlist &amp; saved items</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-bell"></i>
          <span>Get notified when prices drop</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Right: Form Panel ─── -->
  <div class="register-form-panel">
    <div class="register-form-inner">
      <a href="index.php" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
      </a>
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Sign In</h1>
        <p>Welcome back! Enter your details to continue.</p>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?></div>
      <?php endif; ?>

      <form method="POST">
        <?=csrf_field()?>
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="email" placeholder="you@email.com"
                   value="<?=e($_POST['email']??'')?>" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" id="loginPw" placeholder="Your password" required>
            <button type="button" class="pw-toggle" onclick="toggleLoginPw()" tabindex="-1">
              <i class="fa-solid fa-eye" id="loginPwIcon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg register-btn" style="margin-top:6px;">
          <span>SIGN IN</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <div class="auth-link">Don't have an account? <a href="register.php">Register here</a></div>
      <div class="auth-link" style="margin-top:8px;font-size:.8rem;">
        Admin? <a href="admin/admin_login.php">Admin Panel →</a>
      </div>
    </div>
  </div>
</div>

<script>
function toggleLoginPw(){
  const f=document.getElementById('loginPw');
  const i=document.getElementById('loginPwIcon');
  if(f.type==='password'){ f.type='text'; i.className='fa-solid fa-eye-slash'; }
  else { f.type='password'; i.className='fa-solid fa-eye'; }
}
</script>
</body>
</html>
