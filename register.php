<?php
session_start();
require 'db.php';
if(is_logged()){ header("Location: index.php"); exit; }

$error = ''; $success = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $pass    = $_POST['password']     ?? '';
    $pass2   = $_POST['password2']    ?? '';
    $phone   = trim($_POST['phone']   ?? '');
    if(!$name||!$email||!$pass||!$phone){
        $error = "All fields are required.";
    } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } elseif(strlen($pass)<6){
        $error = "Password must be at least 6 characters.";
    } elseif($pass !== $pass2){
        $error = "Passwords do not match.";
    } else {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email=?");
        $chk->bind_param("s",$email); $chk->execute();
        if($chk->get_result()->num_rows > 0){
            $error = "An account with this email already exists.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name,email,password,phone) VALUES (?,?,?,?)");
            $ins->bind_param("ssss",$name,$email,$hashed,$phone);
            if($ins->execute()){
                $success = "Account created! You can now login.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Register | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap" style="padding:60px 20px;">
  <div class="auth-box" style="max-width:520px;">
    <div class="card" style="padding:44px 40px;">
      <div class="auth-logo">APE<span>X</span></div>
      <div class="auth-sub">Create your account</div>

      <?php if($error):   ?><div class="flash flash-err"><?=e($error)?></div><?php endif; ?>
      <?php if($success): ?><div class="flash flash-ok"><?=e($success)?> <a href="login.php" style="color:var(--accent);font-weight:600;">Login here →</a></div><?php endif; ?>

      <?php if(!$success): ?>
      <form method="POST">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" placeholder="John Doe" value="<?=e($_POST['name']??'')?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="you@email.com" value="<?=e($_POST['email']??'')?>" required>
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="01xxxxxxxx" value="<?=e($_POST['phone']??'')?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Min. 6 characters" required>
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="password2" placeholder="Repeat password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:6px;">CREATE ACCOUNT</button>
      </form>
      <?php endif; ?>

      <div class="auth-link">Already have an account? <a href="login.php">Login here</a></div>
    </div>
  </div>
</div>
</body>
</html>
