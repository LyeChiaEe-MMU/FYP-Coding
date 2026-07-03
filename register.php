<?php
session_start();
require 'db.php';
require_once 'admin/Mailer.php';

if(is_logged()){ header("Location: index.php"); exit; }

// On GET: if already mid-verification, send back to the verify page instead of wiping it
if($_SERVER['REQUEST_METHOD'] === 'GET'){
    if(!empty($_SESSION['reg_pending'])){
        header("Location: verify_email.php"); exit;
    }
    // No active verification — clear any leftover OTP keys from an expired/failed session
    unset($_SESSION['reg_otp'], $_SESSION['reg_otp_expires'],
          $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_sent_at']);
}

$error = ''; $success = '';

// Show error passed back from verify_email.php (too many wrong attempts)
if(isset($_GET['err']) && $_GET['err'] === 'toomany'){
    $error = "Too many incorrect attempts. Please register again.";
}
if(isset($_GET['err']) && $_GET['err'] === 'exists'){
    $error = "This email or phone number was just registered by another account. Please use a different one.";
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $pass    = $_POST['password']     ?? '';
    $phone   = preg_replace('/[\s\-]+/', '', trim($_POST['phone'] ?? '')); // normalise: strip spaces/dashes
    $street   = trim($_POST['street']   ?? '');
    $city     = trim($_POST['city']     ?? '');
    $state    = trim($_POST['state']    ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $address  = implode(', ', array_filter([$street, $city, $state, $postcode]));
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
    } elseif(!$error && !preg_match('/^01[0-9]{8,9}$/', $phone)){
        $error = "Phone number must be a valid Malaysian mobile number starting with 01 (10-11 digits, e.g. 0123456789).";
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
        // Check duplicate email
        $chk_e = $conn->prepare("SELECT user_id FROM users WHERE email=?");
        $chk_e->bind_param("s",$email); $chk_e->execute();
        if($chk_e->get_result()->num_rows > 0){
            $error = "An account with this email address already exists.";
        }
        // Check duplicate phone (normalised comparison so 012-345 6789 matches 0123456789)
        if(!$error){
            $chk_p = $conn->prepare("SELECT user_id FROM users WHERE REPLACE(REPLACE(phone,'-',''),' ','')=?");
            $chk_p->bind_param("s",$phone); $chk_p->execute();
            if($chk_p->get_result()->num_rows > 0){
                $error = "This phone number is already registered to another account.";
            }
        }
        if(!$error){
            // Generate OTP and store pending registration — account only created after verification
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $_SESSION['reg_pending'] = compact('name','email','hashed','phone','address','pref','dob');
            $_SESSION['reg_otp']          = $otp;
            $_SESSION['reg_otp_expires']  = time() + 600; // 10 minutes
            $_SESSION['reg_otp_attempts'] = 0;
            $_SESSION['reg_otp_sent_at']  = time();

            $otp_html = "<div style=\"font-family:monospace;font-size:34px;font-weight:700;"
                      . "letter-spacing:10px;color:#C8543C;text-align:center;"
                      . "background:#fff8f6;border:2px dashed #C8543C;border-radius:8px;"
                      . "padding:22px;margin:20px 0;\">$otp</div>";

            $name_safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $body = apex_mail_html_body(
                "<p>Hello {$name_safe},</p>"
                . "<p>Thank you for joining Apex Store! To complete your registration, enter this verification code:</p>"
                . $otp_html
                . "<p>This code is valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>"
                . "<p>If you did not request this, you can safely ignore this email.</p>"
            );

            $result = apex_send_mail($email, $name, 'Your Apex Verification Code', $body);

            if($result['ok']){
                header("Location: verify_email.php");
                exit;
            } else {
                unset($_SESSION['reg_pending'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expires'],
                      $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_sent_at']);
                $error = "Could not send verification email. Please ensure your Gmail is configured in admin/mail_config.php, or contact the administrator.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Register | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
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

.register-brand {
  background: linear-gradient(140deg,#B03828,#C8543C,#D06840,#C04032) !important;
  background-size:300% 300% !important;
  animation: apxBgPulse 10s ease infinite !important;
}

.brand-deco {
  position:absolute; border-radius:50%;
  border:1.5px solid rgba(255,255,255,.18);
  pointer-events:none;
}
.brand-deco-1 { width:300px;height:300px; bottom:-80px;right:-80px; animation:apxRingPulse 4.5s ease-out infinite; }
.brand-deco-2 { width:190px;height:190px; bottom:-30px;right:-30px; animation:apxRingPulse 4.5s 1.4s ease-out infinite; }
.brand-deco-3 { width:200px;height:200px; top:-60px;left:-60px;    animation:apxRingPulse 5.5s 0.7s ease-out infinite; }

.brand-float-icon {
  position:absolute; right:32px; bottom:80px;
  font-size:5.5rem; opacity:.1;
  animation:apxFloat 7s ease-in-out infinite;
  pointer-events:none;
}

.register-brand-logo    { animation:apxSlideIn .65s cubic-bezier(.22,1,.36,1) both; }
.register-brand-tagline { animation:apxFadeUp  .6s .18s cubic-bezier(.22,1,.36,1) both; }
.register-brand-desc    { animation:apxFadeUp  .6s .34s cubic-bezier(.22,1,.36,1) both; }
.register-feature:nth-child(1) { animation:apxFadeUp .5s .50s cubic-bezier(.22,1,.36,1) both; }
.register-feature:nth-child(2) { animation:apxFadeUp .5s .65s cubic-bezier(.22,1,.36,1) both; }
.register-feature:nth-child(3) { animation:apxFadeUp .5s .80s cubic-bezier(.22,1,.36,1) both; }

.register-form-inner { animation:apxFadeUp .55s .05s cubic-bezier(.22,1,.36,1) both; }

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
      <a href="index.php" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
      </a>
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Create Account</h1>
        <p>Fill in your details to get started</p>
      </div>

      <?php if($error):   ?><div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?></div><?php endif; ?>
      <?php if($success): ?><div class="flash flash-ok"><i class="fa-solid fa-circle-check"></i> <?=e($success)?> <a href="login.php" style="color:var(--accent);font-weight:600;margin-left:4px;">Login here &rarr;</a></div><?php endif; ?>

      <?php if(!$success): ?>
      <form method="POST" id="registerForm" novalidate>
        <?=csrf_field()?>

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

        <!-- Shipping Address -->
        <div class="form-group">
          <label>Shipping Address</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-location-dot"></i>
            <input type="text" name="street" placeholder="Street / Unit No." value="<?=e($_POST['street']??'')?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>City</label>
            <input type="text" name="city" placeholder="" value="<?=e($_POST['city']??'')?>">
          </div>
          <div class="form-group">
            <label>Postcode</label>
            <input type="text" name="postcode" placeholder="" maxlength="5"
                   oninput="this.value=this.value.replace(/\D/g,'')"
                   value="<?=e($_POST['postcode']??'')?>">
          </div>
        </div>
        <div class="form-group">
          <label>State</label>
          <select name="state">
            <option value="" disabled <?=empty($_POST['state'])?'selected':''?>>— Select State —</option>
            <?php foreach(['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'] as $s): ?>
            <option value="<?=e($s)?>" <?=($_POST['state']??'')===$s?'selected':''?>><?=e($s)?></option>
            <?php endforeach; ?>
          </select>
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
            <span>I agree to Apex's <a href="terms.php" target="_blank" class="link-accent">Terms of Use</a> and <a href="privacy.php" target="_blank" class="link-accent">Privacy Policy</a></span>
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