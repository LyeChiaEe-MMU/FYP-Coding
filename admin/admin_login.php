<?php
// Start session to maintain login state
session_start();
require '../db.php';

// Redirect to dashboard if admin is already logged in
if(is_admin()){ 
    header("Location: admin_dashboard.php"); 
    exit; 
}

// Check if any admin account exists in the database
$check = $conn->query("SELECT * FROM admins LIMIT 1");

// If no admin exists, create a default admin account automatically
if($check->num_rows === 0){
    // Hash the default password 'admin123' using bcrypt (secure one-way encryption)
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    // Insert the default admin into the database
    $conn->query("INSERT INTO admins (username, password) VALUES ('admin', '$hashed')");
}

$error = '';

// Process login form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Get and trim the submitted username
    $username = trim($_POST['username'] ?? '');
    // Get the submitted password
    $password = $_POST['password'] ?? '';

    // Use prepared statement to prevent SQL injection attacks
    $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    // Fetch the admin record from database
    $admin = $stmt->get_result()->fetch_assoc();

    // Verify if admin exists and the password matches the stored hash
    if($admin && password_verify($password, $admin['password'])){
        // Store admin info in session variables for authentication
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        // Redirect to admin dashboard on successful login
        header("Location: admin_dashboard.php"); 
        exit;
    } else {
        // Show error message for invalid credentials
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
      <!-- Logo Section -->
      <div style="text-align:center;margin-bottom:28px;">
        <div style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:4px;color:var(--white);">
          APE<span style="color:var(--accent)">X</span>
        </div>
        <div style="font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-top:4px;">Admin Panel</div>
      </div>

      <!-- Display error message if login fails -->
      <?php if($error): ?>
      <div class="flash flash-err"><?=e($error)?></div>
      <?php endif; ?>

      <!-- Login Form -->
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

      <!-- Back to Store Link -->
      <div style="text-align:center;margin-top:20px;font-size:.8rem;color:var(--muted);">
        <a href="../index.php" style="color:var(--muted);">← Back to Store</a>
      </div>
    </div>

    <!-- Default credentials hint -->
    <div style="text-align:center;margin-top:16px;font-size:.75rem;color:var(--muted);background:rgba(100,255,218,.05);border:1px solid var(--border);border-radius:6px;padding:10px;">
      Default login: <strong style="color:var(--text);">admin / admin123</strong>
    </div>
  </div>
</body>
</html>