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

// Check if already logged in via session
if(!empty($_SESSION['admin_id']) && isset($_SESSION['login_method'])){
    // If logged in via remember me, allow
    if($_SESSION['login_method'] == 'remember_me'){
        header("Location: admin_dashboard.php");
        exit;
    }
    // If logged in via normal login, but session might still exist
    // We need to check if it's a restored session
    if(!isset($_COOKIE['admin_remember'])){
        // No remember me cookie, but session exists - this is a browser restore
        // Force logout to be safe
        session_unset();
        session_destroy();
    } else {
        header("Location: admin_dashboard.php");
        exit;
    }
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

    $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if($admin && password_verify($password, $admin['password'])){
        
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
            
            // Set cookie for 30 days
            setcookie('admin_remember', $token, $expiry, '/');
            
        } else {
            // REMEMBER ME NOT CHECKED - Session only, no cookie
            $_SESSION['login_method'] = 'session_only';
            
            // Clear any existing remember me data
            $clear_stmt = $conn->prepare("UPDATE admins SET remember_token = NULL, token_expiry = NULL WHERE admin_id = ?");
            $clear_stmt->bind_param("i", $admin['admin_id']);
            $clear_stmt->execute();
            $clear_stmt->close();
            
            // Delete cookie if exists
            if(isset($_COOKIE['admin_remember'])){
                setcookie('admin_remember', '', time() - 3600, '/');
            }
        }
        
        header("Location: admin_dashboard.php"); 
        exit;
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
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
        
        <div class="form-group" style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
          <input type="checkbox" name="remember_me" id="remember_me" value="1" style="width:18px;height:18px;margin:0;cursor:pointer;">
          <label for="remember_me" style="margin:0;cursor:pointer;color:var(--muted);font-size:.82rem;">
            Remember me (Stay logged in for 30 days)
          </label>
        </div>
        
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:6px;">ACCESS DASHBOARD</button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:.8rem;color:var(--muted);">
        <a href="../index.php" style="color:var(--muted);">← Back to Store</a>
      </div>
    </div>

    <div style="text-align:center;margin-top:16px;font-size:.75rem;color:var(--muted);background:rgba(100,255,218,.05);border:1px solid var(--border);border-radius:6px;padding:10px;">
      Default login: <strong style="color:var(--text);">admin / admin123</strong>
      <br>
      <span style="font-size:.7rem;">✓ Check "Remember me" → Stay logged in for 30 days</span>
      <br>
      <span style="font-size:.7rem;">✗ Uncheck → Must login every time you reopen browser</span>
    </div>
  </div>
</body>
</html>