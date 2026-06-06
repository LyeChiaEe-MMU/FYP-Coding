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
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login | Apex</title>
<link rel="stylesheet" href="css/style.css?v=9">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="register-split">
  <!-- ─── Left: Brand Panel ─── -->
  <div class="register-brand">
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
      <a href="index.php" style="display:inline-flex;align-items:center;gap:7px;color:var(--muted);font-size:.82rem;margin-bottom:22px;transition:color .2s;text-decoration:none;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">
        <i class="fa-solid fa-arrow-left" style="font-size:.75rem;"></i> Back to Home
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
