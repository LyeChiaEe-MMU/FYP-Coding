<?php
session_start();
require 'db.php';
if(is_logged()){ header("Location: index.php"); exit; }

$error = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if($user && password_verify($password,$user['password'])){
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
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="card" style="padding:44px 40px;">
      <div class="auth-logo">APE<span>X</span></div>
      <div class="auth-sub">Sign in to your account</div>

      <?php if($error): ?>
      <div class="flash flash-err"><?=e($error)?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@email.com"
                 value="<?=e($_POST['email']??'')?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:6px;">SIGN IN</button>
      </form>

      <div class="auth-link">Don't have an account? <a href="register.php">Register here</a></div>
      <div class="auth-link" style="margin-top:8px;font-size:.8rem;">
        Admin? <a href="admin/admin_login.php">Admin Panel →</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
