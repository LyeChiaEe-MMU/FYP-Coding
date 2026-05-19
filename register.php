<?php
session_start();
require 'db.php';
if(is_logged()){ header("Location: index.php"); exit; }

$error = ''; $success = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $pass    = $_POST['password']     ?? '';
    $phone   = trim($_POST['phone']   ?? '');
    $pref    = $_POST['shopping_preference'] ?? '';
    $dob_day   = $_POST['dob_day']   ?? '';
    $dob_month = $_POST['dob_month'] ?? '';
    $dob_year  = $_POST['dob_year']  ?? '';

    // Build DOB
    $dob = null;
    if($dob_day && $dob_month && $dob_year){
        $dob = sprintf('%04d-%02d-%02d', intval($dob_year), intval($dob_month), intval($dob_day));
        if(!checkdate(intval($dob_month), intval($dob_day), intval($dob_year))){
            $error = "Please enter a valid date of birth.";
        }
    }

    if(!$error && (!$name||!$email||!$pass||!$phone)){
        $error = "All fields are required.";
    } elseif(!$error && !filter_var($email,FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } elseif(!$error && (strlen($pass)<8 || strlen($pass)>16)){
        $error = "Password must be 8-16 characters.";
    } elseif(!$error && !preg_match('/[A-Z]/',$pass)){
        $error = "Password must contain an uppercase letter.";
    } elseif(!$error && !preg_match('/[a-z]/',$pass)){
        $error = "Password must contain a lowercase letter.";
    } elseif(!$error && !preg_match('/[0-9]/',$pass)){
        $error = "Password must contain a number.";
    } elseif(!$error && !preg_match('/[^A-Za-z0-9]/',$pass)){
        $error = "Password must contain a special character.";
    } elseif(!$error && !in_array($pref, ['men','women','kids'])){
        $error = "Please select a shopping preference.";
    } else if(!$error) {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email=?");
        $chk->bind_param("s",$email); $chk->execute();
        if($chk->get_result()->num_rows > 0){
            $error = "An account with this email already exists.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name,email,password,phone,shopping_preference,date_of_birth) VALUES (?,?,?,?,?,?)");
            $ins->bind_param("ssssss",$name,$email,$hashed,$phone,$pref,$dob);
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

<div class="register-split">
  <!-- ─── Left: Brand Panel ─── -->
  <div class="register-brand">
    <div class="register-brand-overlay"></div>
    <div class="register-brand-content">
      <div class="register-brand-logo">APE<span>X</span></div>
      <h2 class="register-brand-tagline">Step Into<br><span>Greatness</span></h2>
      <p class="register-brand-desc">Join the Apex community and unlock exclusive access to the latest drops, member-only deals, and personalised recommendations.</p>
      <div class="register-brand-features">
        <div class="register-feature">
          <i class="fa-solid fa-truck-fast"></i>
          <span>Free shipping on first order</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-tag"></i>
          <span>Member-exclusive pricing</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-gift"></i>
          <span>Birthday rewards</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Right: Form Panel ─── -->
  <div class="register-form-panel">
    <div class="register-form-inner">
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Create Account</h1>
        <p>Fill in your details to get started</p>
      </div>

      <?php if($error):   ?><div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?></div><?php endif; ?>
      <?php if($success): ?><div class="flash flash-ok"><i class="fa-solid fa-circle-check"></i> <?=e($success)?> <a href="login.php" style="color:var(--accent);font-weight:600;margin-left:4px;">Login here &rarr;</a></div><?php endif; ?>

      <?php if(!$success): ?>
      <form method="POST" id="registerForm" novalidate>

        <!-- Full Name -->
        <div class="form-group">
          <label>Full Name</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="name" placeholder="John Doe" value="<?=e($_POST['name']??'')?>" required>
          </div>
        </div>

        <!-- Email + Phone -->
        <div class="form-row">
          <div class="form-group">
            <label>Email Address</label>
            <div class="input-icon-wrap">
              <i class="fa-solid fa-envelope"></i>
              <input type="email" name="email" placeholder="you@email.com" value="<?=e($_POST['email']??'')?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <div class="input-icon-wrap">
              <i class="fa-solid fa-phone"></i>
              <input type="tel" name="phone" placeholder="01xxxxxxxx" value="<?=e($_POST['phone']??'')?>" required>
            </div>
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label>Password</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" id="regPassword" placeholder="Create a strong password" required>
            <button type="button" class="pw-toggle" onclick="togglePw()" tabindex="-1">
              <i class="fa-solid fa-eye" id="pwIcon"></i>
            </button>
          </div>
          <div class="pw-rules" id="pwRules">
            <div class="pw-rule" id="rule-length">
              <i class="fa-solid fa-xmark rule-icon"></i>
              <span>8 – 16 characters</span>
            </div>
            <div class="pw-rule" id="rule-upper">
              <i class="fa-solid fa-xmark rule-icon"></i>
              <span>One uppercase letter</span>
            </div>
            <div class="pw-rule" id="rule-lower">
              <i class="fa-solid fa-xmark rule-icon"></i>
              <span>One lowercase letter</span>
            </div>
            <div class="pw-rule" id="rule-number">
              <i class="fa-solid fa-xmark rule-icon"></i>
              <span>One number</span>
            </div>
            <div class="pw-rule" id="rule-special">
              <i class="fa-solid fa-xmark rule-icon"></i>
              <span>One special character (!@#$...)</span>
            </div>
          </div>
        </div>

        <!-- Shopping Preference -->
        <div class="form-group">
          <label>Shopping Preference</label>
          <div class="pref-options">
            <?php $prevPref = $_POST['shopping_preference'] ?? ''; ?>
            <label class="pref-card">
              <input type="radio" name="shopping_preference" value="men" <?= $prevPref==='men'?'checked':'' ?>>
              <div class="pref-card-inner">
                <i class="fa-solid fa-person"></i>
                <span>Men</span>
              </div>
            </label>
            <label class="pref-card">
              <input type="radio" name="shopping_preference" value="women" <?= $prevPref==='women'?'checked':'' ?>>
              <div class="pref-card-inner">
                <i class="fa-solid fa-person-dress"></i>
                <span>Women</span>
              </div>
            </label>
            <label class="pref-card">
              <input type="radio" name="shopping_preference" value="kids" <?= $prevPref==='kids'?'checked':'' ?>>
              <div class="pref-card-inner">
                <i class="fa-solid fa-child"></i>
                <span>Kids</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Date of Birth -->
        <div class="form-group">
          <label>Date of Birth</label>
          <div class="dob-row">
            <select name="dob_day" required>
              <option value="">Day</option>
              <?php for($d=1;$d<=31;$d++): ?>
              <option value="<?=$d?>" <?=($d==($_POST['dob_day']??''))?'selected':''?>><?=$d?></option>
              <?php endfor; ?>
            </select>
            <select name="dob_month" required>
              <option value="">Month</option>
              <?php
              $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
              for($m=1;$m<=12;$m++): ?>
              <option value="<?=$m?>" <?=($m==($_POST['dob_month']??''))?'selected':''?>><?=$months[$m-1]?></option>
              <?php endfor; ?>
            </select>
            <select name="dob_year" required>
              <option value="">Year</option>
              <?php for($y=date('Y');$y>=1940;$y--): ?>
              <option value="<?=$y?>" <?=($y==($_POST['dob_year']??''))?'selected':''?>><?=$y?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="dob-hint"><i class="fa-solid fa-gift"></i> Get an Apex birthday reward!</div>
        </div>

        <!-- Terms -->
        <div class="terms-check">
          <label class="custom-check">
            <input type="checkbox" id="agreeTerms" required>
            <span class="checkmark"></span>
            <span>I agree to Apex's <a href="#" class="link-accent">Terms of Use</a> and <a href="#" class="link-accent">Privacy Policy</a></span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg register-btn">
          <span>CREATE ACCOUNT</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>
      <?php endif; ?>

      <div class="auth-link">Already have an account? <a href="login.php">Login here</a></div>
    </div>
  </div>
</div>

<script>
// ─── Password live validation ───
const pw = document.getElementById('regPassword');
const rules = {
  length:  { el: document.getElementById('rule-length'),  test: v => v.length >= 8 && v.length <= 16 },
  upper:   { el: document.getElementById('rule-upper'),   test: v => /[A-Z]/.test(v) },
  lower:   { el: document.getElementById('rule-lower'),   test: v => /[a-z]/.test(v) },
  number:  { el: document.getElementById('rule-number'),  test: v => /[0-9]/.test(v) },
  special: { el: document.getElementById('rule-special'), test: v => /[^A-Za-z0-9]/.test(v) }
};

if(pw) {
  pw.addEventListener('input', function(){
    const v = this.value;
    for(const key in rules){
      const r    = rules[key];
      const pass = r.test(v);
      const icon = r.el.querySelector('.rule-icon');
      if(pass){
        r.el.classList.add('pass');
        r.el.classList.remove('fail');
        icon.className = 'fa-solid fa-circle-check rule-icon';
      } else {
        r.el.classList.remove('pass');
        if(v.length > 0) r.el.classList.add('fail');
        else r.el.classList.remove('fail');
        icon.className = 'fa-solid fa-xmark rule-icon';
      }
    }
  });
}

// ─── Toggle password visibility ───
function togglePw(){
  const f = document.getElementById('regPassword');
  const i = document.getElementById('pwIcon');
  if(f.type === 'password'){
    f.type = 'text';
    i.className = 'fa-solid fa-eye-slash';
  } else {
    f.type = 'password';
    i.className = 'fa-solid fa-eye';
  }
}
</script>

</body>
</html>