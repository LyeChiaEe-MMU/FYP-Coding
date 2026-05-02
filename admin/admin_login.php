<?php
session_start();
require '../db.php';
if(is_admin()){ header("Location: admin_dashboard.php"); exit; }

// Auto-create default admin if none exists
$check = $conn->query("SELECT * FROM admins LIMIT 1");
if($check->num_rows===0){
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username,password) VALUES ('admin','$hashed')");
}

$error = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username=?");
    $stmt->bind_param("s",$username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if($admin && password_verify($password,$admin['password'])){
        $_SESSION['admin_id']       = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        header("Location: admin_dashboard.php"); exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Login | Apex</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body style="background:var(--navy);display:flex;align-items:center;justify-content:center;min-height:100vh;">
  <div style="width:100%;max-width:400px;padding:20px;">
    <div class="card" style="padding:44px 40px;">
      <div style="text-align:center;margin-bottom:28px;">
        <div style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:4px;color:var(--white);">
          APE<span style="color:var(--accent)">X</span>
        </div>
        <div style="font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-top:4px;">Admin Panel</div>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><?=e($error)?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="admin" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="admin123" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:6px;">ACCESS DASHBOARD</button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:.8rem;color:var(--muted);">
        <a href="../index.php" style="color:var(--muted);">← Back to Store</a>
      </div>
    </div>

    <div style="text-align:center;margin-top:16px;font-size:.75rem;color:var(--muted);background:rgba(100,255,218,.05);border:1px solid var(--border);border-radius:6px;padding:10px;">
      Default login: <strong style="color:var(--text);">admin / admin123</strong>
    </div>
  </div>
</body>
</html>
