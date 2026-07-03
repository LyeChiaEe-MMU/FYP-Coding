<?php
session_start();
require 'db.php';
require_once 'admin/Mailer.php';

if(is_logged()){ header("Location: index.php"); exit; }

$max_attempts = 5;
$error   = '';
$resent  = false;
$success = false;

// Helper: send the reset OTP email
function fp_send_otp($email, $name, $resend = false){
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['fp_otp']          = $otp;
    $_SESSION['fp_otp_expires']  = time() + 600;
    $_SESSION['fp_otp_attempts'] = 0;
    $_SESSION['fp_otp_sent_at']  = time();

    $otp_html = "<div style=\"font-family:monospace;font-size:34px;font-weight:700;"
              . "letter-spacing:10px;color:#C8543C;text-align:center;"
              . "background:#fff8f6;border:2px dashed #C8543C;border-radius:8px;"
              . "padding:22px;margin:20px 0;\">$otp</div>";

    $name_safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $body = apex_mail_html_body(
        "<p>Hello {$name_safe},</p>"
        . "<p>We received a request to reset your Apex Store password. Use the code below to continue:</p>"
        . $otp_html
        . "<p>This code is valid for <strong>10 minutes</strong>.</p>"
        . "<p>If you did not request a password reset, you can safely ignore this email — your password will not be changed.</p>"
    );
    $subject = $resend ? 'Your Apex Password Reset Code (Resent)' : 'Your Apex Password Reset Code';
    return apex_send_mail($email, $name, $subject, $body);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ── Step 1: submit email ─────────────────────────────────────
    if($action === 'send'){
        $email = trim($_POST['email'] ?? '');
        $wait = 60 - (time() - ($_SESSION['fp_otp_sent_at'] ?? 0));
        if(!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = "Please enter a valid email address.";
        } elseif(!empty($_SESSION['fp_otp']) && $wait > 0){
            $error = "A reset code was already sent recently. Please wait $wait second" . ($wait === 1 ? '' : 's') . " before requesting another.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, name, is_banned FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if(!$user){
                $error = "No account found with that email address.";
            } elseif(!empty($user['is_banned'])){
                $error = "This account has been suspended. Please contact support.";
            } else {
                $_SESSION['fp_email']    = $email;
                $_SESSION['fp_name']     = $user['name'];
                $_SESSION['fp_uid']      = (int)$user['user_id'];
                $_SESSION['fp_verified'] = false;

                $result = fp_send_otp($email, $user['name']);
                if(!$result['ok']){
                    unset($_SESSION['fp_email'], $_SESSION['fp_name'], $_SESSION['fp_uid'],
                          $_SESSION['fp_otp'], $_SESSION['fp_otp_expires'],
                          $_SESSION['fp_otp_attempts'], $_SESSION['fp_otp_sent_at'], $_SESSION['fp_verified']);
                    $error = "Failed to send the reset code. Please try again later.";
                }
            }
        }

    // ── Step 2: resend code ──────────────────────────────────────
    } elseif($action === 'resend' && !empty($_SESSION['fp_email'])){
        $wait = 60 - (time() - ($_SESSION['fp_otp_sent_at'] ?? 0));
        if($wait > 0){
            $error = "Please wait $wait second" . ($wait === 1 ? '' : 's') . " before resending.";
        } else {
            $result = fp_send_otp($_SESSION['fp_email'], $_SESSION['fp_name'], true);
            if($result['ok']) $resent = true;
            else $error = "Failed to resend. Please try again later.";
        }

    // ── Step 2: verify OTP ───────────────────────────────────────
    } elseif($action === 'verify' && !empty($_SESSION['fp_email'])){
        $entered = '';
        if(isset($_POST['otp']) && strlen(trim($_POST['otp'])) === 6){
            $entered = trim($_POST['otp']);
        } else {
            for($i=1;$i<=6;$i++) $entered .= preg_replace('/\D/','',$_POST["d$i"] ?? '');
        }

        if(time() > ($_SESSION['fp_otp_expires'] ?? 0)){
            $error = 'Your code has expired. Please <a href="forgot_password.php?restart=1" style="color:var(--accent);">request a new one</a>.';
        } elseif(($_SESSION['fp_otp_attempts'] ?? 0) >= $max_attempts){
            unset($_SESSION['fp_email'], $_SESSION['fp_name'], $_SESSION['fp_uid'],
                  $_SESSION['fp_otp'], $_SESSION['fp_otp_expires'],
                  $_SESSION['fp_otp_attempts'], $_SESSION['fp_otp_sent_at'], $_SESSION['fp_verified']);
            header("Location: forgot_password.php?err=toomany"); exit;
        } elseif(strlen($entered) !== 6 || !ctype_digit($entered)){
            $error = "Please enter the full 6-digit code.";
        } elseif($entered !== ($_SESSION['fp_otp'] ?? '')){
            $_SESSION['fp_otp_attempts'] = ($_SESSION['fp_otp_attempts'] ?? 0) + 1;
            $left  = $max_attempts - $_SESSION['fp_otp_attempts'];
            $error = "Incorrect code. " . ($left > 0 ? "$left attempt" . ($left === 1 ? '' : 's') . " remaining." : "No attempts remaining.");
            if($left <= 0){
                unset($_SESSION['fp_email'], $_SESSION['fp_name'], $_SESSION['fp_uid'],
                      $_SESSION['fp_otp'], $_SESSION['fp_otp_expires'],
                      $_SESSION['fp_otp_attempts'], $_SESSION['fp_otp_sent_at'], $_SESSION['fp_verified']);
                header("Location: forgot_password.php?err=toomany"); exit;
            }
        } else {
            $_SESSION['fp_verified'] = true;
            unset($_SESSION['fp_otp']);
        }

    // ── Step 3: set new password ─────────────────────────────────
    } elseif($action === 'reset' && !empty($_SESSION['fp_verified'])){
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if(strlen($pass) < 8 || strlen($pass) > 16){
            $error = "Password must be 8-16 characters.";
        } elseif(!preg_match('/[A-Z]/', $pass)){
            $error = "Password must contain an uppercase letter.";
        } elseif(!preg_match('/[a-z]/', $pass)){
            $error = "Password must contain a lowercase letter.";
        } elseif(!preg_match('/[0-9]/', $pass)){
            $error = "Password must contain a number.";
        } elseif(!preg_match('/[^A-Za-z0-9]/', $pass)){
            $error = "Password must contain a special character.";
        } elseif($pass !== $pass2){
            $error = "Passwords do not match.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $uid    = (int)$_SESSION['fp_uid'];
            $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
            $upd->bind_param("si", $hashed, $uid);
            if($upd->execute()){
                // Confirmation email
                $name_safe = htmlspecialchars($_SESSION['fp_name'], ENT_QUOTES, 'UTF-8');
                $body = apex_mail_html_body(
                    "<p>Hello {$name_safe},</p>"
                    . "<p>Your Apex Store password was changed successfully on <strong>" . date('d M Y, h:i A') . "</strong>.</p>"
                    . "<p>If this wasn't you, please contact our support team immediately.</p>"
                );
                apex_send_mail($_SESSION['fp_email'], $_SESSION['fp_name'], 'Your Apex Password Was Changed', $body);

                unset($_SESSION['fp_email'], $_SESSION['fp_name'], $_SESSION['fp_uid'],
                      $_SESSION['fp_otp'], $_SESSION['fp_otp_expires'],
                      $_SESSION['fp_otp_attempts'], $_SESSION['fp_otp_sent_at'], $_SESSION['fp_verified']);
                $success = true;
            } else {
                $error = "Failed to update your password. Please try again.";
            }
        }
    }
}

// Allow restarting the flow
if(isset($_GET['restart'])){
    unset($_SESSION['fp_email'], $_SESSION['fp_name'], $_SESSION['fp_uid'],
          $_SESSION['fp_otp'], $_SESSION['fp_otp_expires'],
          $_SESSION['fp_otp_attempts'], $_SESSION['fp_otp_sent_at'], $_SESSION['fp_verified']);
    header("Location: forgot_password.php"); exit;
}

if(isset($_GET['err']) && $_GET['err'] === 'toomany' && !$error){
    $error = "Too many incorrect attempts. Please start over.";
}

// Determine which step to show
if($success)                              $step = 'done';
elseif(!empty($_SESSION['fp_verified']))  $step = 'reset';
elseif(!empty($_SESSION['fp_email']) && !empty($_SESSION['fp_otp'])) $step = 'otp';
else                                      $step = 'email';

// Masked email for OTP step
$masked = '';
if($step === 'otp'){
    $email    = $_SESSION['fp_email'];
    $at       = strpos($email, '@');
    $local    = substr($email, 0, $at);
    $domain   = substr($email, $at + 1);
    $mask_len = max(0, strlen($local) - 2);
    $masked   = (strlen($local) > 2 ? substr($local, 0, 2) : $local)
              . str_repeat('*', $mask_len) . '@' . $domain;
}

$expires_in    = max(0, ($_SESSION['fp_otp_expires'] ?? time()) - time());
$cooldown_left = max(0, 60 - (time() - ($_SESSION['fp_otp_sent_at'] ?? 0)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password | Apex</title>
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
@keyframes apxRingPulse {
  0%   { transform:scale(.8); opacity:.45; }
  100% { transform:scale(1.75); opacity:0; }
}
@keyframes apxFloat {
  0%,100% { transform:rotate(-10deg) translateY(0); }
  50%     { transform:rotate(-10deg) translateY(-18px); }
}
@keyframes apxShake {
  0%,100%{ transform:translateX(0); }
  20%    { transform:translateX(-7px); }
  40%    { transform:translateX(7px); }
  60%    { transform:translateX(-5px); }
  80%    { transform:translateX(5px); }
}

.register-brand {
  background: linear-gradient(140deg,#B03828,#C8543C,#D06840,#C04032) !important;
  background-size:300% 300% !important;
  animation: apxBgPulse 10s ease infinite !important;
}
.brand-deco { position:absolute; border-radius:50%; border:1.5px solid rgba(255,255,255,.18); pointer-events:none; }
.brand-deco-1 { width:300px;height:300px; bottom:-80px;right:-80px; animation:apxRingPulse 4.5s ease-out infinite; }
.brand-deco-2 { width:190px;height:190px; bottom:-30px;right:-30px; animation:apxRingPulse 4.5s 1.4s ease-out infinite; }
.brand-deco-3 { width:200px;height:200px; top:-60px;left:-60px;    animation:apxRingPulse 5.5s 0.7s ease-out infinite; }
.brand-float-icon {
  position:absolute; right:32px; bottom:80px;
  font-size:5.5rem; opacity:.1;
  animation:apxFloat 7s ease-in-out infinite; pointer-events:none;
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
  background:rgba(150,100,75,.09); border:1.5px solid rgba(150,100,75,.25);
  border-radius:100px; color:var(--muted); font-size:.8rem; font-weight:600;
  letter-spacing:.4px; text-decoration:none; transition:all .22s; margin-bottom:30px;
}
.back-btn:hover { color:var(--accent); border-color:rgba(200,84,60,.55); background:rgba(200,84,60,.08); transform:translateX(-3px); }
.back-btn i { font-size:.7rem; transition:transform .22s; }
.back-btn:hover i { transform:translateX(-4px); }

/* Step indicator */
.fp-steps { display:flex; align-items:center; gap:8px; margin-bottom:24px; }
.fp-step {
  width:30px; height:30px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:.75rem; font-weight:700; font-family:'Oswald',sans-serif;
  background:var(--navy2); border:2px solid var(--border); color:var(--muted);
  flex-shrink:0;
}
.fp-step.active { background:var(--accent); border-color:var(--accent); color:#fff; }
.fp-step.done   { background:rgba(16,185,129,.15); border-color:#10b981; color:#10b981; }
.fp-step-line { flex:1; height:2px; background:var(--border); border-radius:2px; }
.fp-step-line.done { background:#10b981; }

/* OTP boxes */
.otp-row { display:flex; gap:10px; justify-content:center; margin:28px 0 20px; }
.otp-box {
  width:52px; height:60px;
  text-align:center; font-size:1.5rem; font-weight:700; font-family:'Oswald',sans-serif;
  background:var(--navy2); border:2px solid var(--border); border-radius:10px;
  color:var(--white); outline:none; transition:border-color .18s;
  caret-color: var(--accent);
}
.otp-box:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(200,84,60,.18); }
.otp-box.filled { border-color:rgba(200,84,60,.55); }
.otp-box.error  { border-color:#ef4444; animation:apxShake .4s ease; }

.otp-timer { text-align:center; font-size:.78rem; color:var(--muted); margin-bottom:8px; }
.otp-timer span { font-weight:700; color:var(--accent); }
.otp-timer.urgent span { color:#ef4444; }

.resend-row { text-align:center; margin-top:12px; font-size:.82rem; color:var(--muted); }
.resend-btn { background:none; border:none; color:var(--accent); cursor:pointer; font-size:.82rem; font-weight:600; padding:0; text-decoration:underline; }
.resend-btn:disabled { color:var(--muted); text-decoration:none; cursor:default; }

.email-chip {
  display:inline-flex; align-items:center; gap:7px;
  background:rgba(100,255,218,.07); border:1px solid rgba(100,255,218,.2);
  border-radius:100px; padding:5px 14px;
  font-size:.82rem; color:var(--white); margin:8px 0 4px;
}
.email-chip i { color:var(--accent); }

/* Password rules checklist */
.pw-rules { list-style:none; padding:0; margin:10px 0 18px; display:grid; grid-template-columns:1fr 1fr; gap:5px 14px; }
.pw-rules li { font-size:.74rem; color:var(--muted); display:flex; align-items:center; gap:6px; transition:color .2s; }
.pw-rules li i { font-size:.65rem; width:13px; }
.pw-rules li.ok { color:#10b981; }

.success-card { text-align:center; padding:20px 0; }
.success-icon { font-size:3.5rem; color:#10b981; margin-bottom:16px; }
</style>
</head>
<body>
<div class="register-split">

  <!-- ─── Left: Brand Panel ─── -->
  <div class="register-brand">
    <div class="brand-deco brand-deco-1"></div>
    <div class="brand-deco brand-deco-2"></div>
    <div class="brand-deco brand-deco-3"></div>
    <div class="brand-float-icon"><i class="fa-solid fa-key"></i></div>
    <div class="register-brand-overlay"></div>
    <div class="register-brand-content">
      <div class="register-brand-logo">APE<span>X</span></div>
      <h2 class="register-brand-tagline">Reset Your<br><span>Password.</span></h2>
      <p class="register-brand-desc">Forgot your password? No worries — we'll send a verification code to your email so you can set a new one securely.</p>
      <div class="register-brand-features">
        <div class="register-feature">
          <i class="fa-solid fa-envelope"></i>
          <span>Enter your registered email</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-shield-halved"></i>
          <span>Verify with a 6-digit code</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-lock"></i>
          <span>Set a brand new password</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Right: Form Panel ─── -->
  <div class="register-form-panel">
    <div class="register-form-inner">
      <a href="login.php" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Login
      </a>

      <?php if($step !== 'done'): ?>
      <!-- Step indicator -->
      <div class="fp-steps">
        <div class="fp-step <?=$step==='email'?'active':'done'?>"><?=$step==='email'?'1':'<i class="fa-solid fa-check"></i>'?></div>
        <div class="fp-step-line <?=in_array($step,['otp','reset'])?'done':''?>"></div>
        <div class="fp-step <?=$step==='otp'?'active':($step==='reset'?'done':'')?>"><?=$step==='reset'?'<i class="fa-solid fa-check"></i>':'2'?></div>
        <div class="fp-step-line <?=$step==='reset'?'done':''?>"></div>
        <div class="fp-step <?=$step==='reset'?'active':''?>">3</div>
      </div>
      <?php endif; ?>

      <?php if($step === 'done'): ?>
      <!-- ═══ Success ═══ -->
      <div class="success-card">
        <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:2px;color:var(--white);margin-bottom:8px;">PASSWORD RESET</h1>
        <p style="color:var(--muted);margin-bottom:24px;">Your password has been changed successfully.<br>You can now sign in with your new password.</p>
        <a href="login.php" class="btn btn-primary btn-lg" style="letter-spacing:2px;">
          <span>LOGIN NOW</span>
          <i class="fa-solid fa-arrow-right" style="margin-left:8px;"></i>
        </a>
      </div>

      <?php elseif($step === 'email'): ?>
      <!-- ═══ Step 1: Email ═══ -->
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Forgot Password?</h1>
        <p>Enter your registered email and we'll send you a verification code.</p>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
      <?php endif; ?>

      <form method="POST">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="send">
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="email" placeholder="you@email.com"
                   value="<?=e($_POST['email']??'')?>" required autofocus>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg register-btn" style="margin-top:6px;">
          <span>SEND RESET CODE</span>
          <i class="fa-solid fa-paper-plane"></i>
        </button>
      </form>

      <div class="auth-link">Remembered your password? <a href="login.php">Sign in here</a></div>

      <?php elseif($step === 'otp'): ?>
      <!-- ═══ Step 2: OTP ═══ -->
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Check Your Email</h1>
        <p>We sent a 6-digit reset code to</p>
        <div class="email-chip"><i class="fa-solid fa-envelope"></i><?=e($masked)?></div>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
      <?php endif; ?>
      <?php if($resent): ?>
      <div class="flash flash-ok"><i class="fa-solid fa-circle-check"></i> A new code has been sent.</div>
      <?php endif; ?>

      <div class="otp-timer" id="otpTimer">
        Code expires in <span id="countdown"><?=gmdate('i:s', $expires_in)?></span>
      </div>

      <form method="POST" id="otpForm">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="verify">
        <input type="hidden" name="otp" id="otpHidden">

        <div class="otp-row" id="otpRow">
          <input type="text" class="otp-box" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="one-time-code" data-index="1">
          <input type="text" class="otp-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="2">
          <input type="text" class="otp-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="3">
          <input type="text" class="otp-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="4">
          <input type="text" class="otp-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="5">
          <input type="text" class="otp-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="6">
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="verifyBtn" disabled>
          <span>VERIFY CODE</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <form method="POST" id="resendForm" style="display:none;">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="resend">
      </form>

      <div class="resend-row">
        Didn't receive it?
        <button class="resend-btn" id="resendBtn" onclick="doResend()" disabled>
          Resend code
        </button>
        <span id="resendTimer"></span>
      </div>
      <div class="auth-link" style="margin-top:14px;font-size:.8rem;">
        Wrong email? <a href="forgot_password.php?restart=1">Start over</a>
      </div>

      <?php else: ?>
      <!-- ═══ Step 3: New Password ═══ -->
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Set New Password</h1>
        <p>Your identity is verified. Choose a new password for your account.</p>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?></div>
      <?php endif; ?>

      <form method="POST">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="reset">
        <div class="form-group">
          <label>New Password</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" id="fpPw" placeholder="Create a strong password" required>
            <button type="button" class="pw-toggle" onclick="togglePw('fpPw','fpPwIcon')" tabindex="-1">
              <i class="fa-solid fa-eye" id="fpPwIcon"></i>
            </button>
          </div>
        </div>

        <ul class="pw-rules" id="pwRules">
          <li data-rule="len"><i class="fa-solid fa-circle"></i> 8-16 characters</li>
          <li data-rule="upper"><i class="fa-solid fa-circle"></i> Uppercase letter</li>
          <li data-rule="lower"><i class="fa-solid fa-circle"></i> Lowercase letter</li>
          <li data-rule="num"><i class="fa-solid fa-circle"></i> A number</li>
          <li data-rule="spec"><i class="fa-solid fa-circle"></i> Special character</li>
        </ul>

        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="input-icon-wrap">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password2" id="fpPw2" placeholder="Re-type your new password" required>
            <button type="button" class="pw-toggle" onclick="togglePw('fpPw2','fpPw2Icon')" tabindex="-1">
              <i class="fa-solid fa-eye" id="fpPw2Icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg register-btn" style="margin-top:6px;">
          <span>RESET PASSWORD</span>
          <i class="fa-solid fa-check"></i>
        </button>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function togglePw(fieldId, iconId){
  const f = document.getElementById(fieldId);
  const i = document.getElementById(iconId);
  if(f.type === 'password'){ f.type='text'; i.className='fa-solid fa-eye-slash'; }
  else { f.type='password'; i.className='fa-solid fa-eye'; }
}

<?php if($step === 'otp'): ?>
// ── OTP box behaviour ──────────────────────────────────────────────────
const boxes  = Array.from(document.querySelectorAll('.otp-box'));
const hidden = document.getElementById('otpHidden');
const btn    = document.getElementById('verifyBtn');

function collectOtp(){
    return boxes.map(b => b.value.replace(/\D/,'')).join('');
}
function syncHidden(){
    const val = collectOtp();
    hidden.value = val;
    btn.disabled = val.length < 6;
    boxes.forEach(b => {
        b.classList.toggle('filled', b.value !== '');
        b.classList.remove('error');
    });
}

boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(/\D/g,'').slice(-1);
        syncHidden();
        if(box.value && idx < boxes.length - 1) boxes[idx+1].focus();
    });
    box.addEventListener('keydown', e => {
        if(e.key === 'Backspace' && !box.value && idx > 0){
            boxes[idx-1].focus();
            boxes[idx-1].value = '';
            syncHidden();
        }
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        paste.split('').forEach((ch,i) => { if(boxes[i]) boxes[i].value = ch; });
        syncHidden();
        if(paste.length > 0) boxes[Math.min(paste.length, boxes.length)-1].focus();
    });
});

if(boxes[0]) boxes[0].focus();

<?php if($error && strpos($error,'Incorrect') !== false): ?>
boxes.forEach(b => { b.value=''; b.classList.add('error'); });
syncHidden();
boxes[0].focus();
setTimeout(() => boxes.forEach(b => b.classList.remove('error')), 600);
<?php endif; ?>

// ── Countdown timer ────────────────────────────────────────────────────
let expiresIn = <?=(int)$expires_in?>;
const timerEl  = document.getElementById('countdown');
const timerRow = document.getElementById('otpTimer');

function pad(n){ return String(n).padStart(2,'0'); }
function tickCountdown(){
    if(expiresIn <= 0){
        if(timerRow) timerRow.innerHTML = '<span style="color:#ef4444;">Code expired. <a href="forgot_password.php?restart=1" style="color:var(--accent);">Request a new one</a>.</span>';
        if(btn) btn.disabled = true;
        return;
    }
    expiresIn--;
    const m = Math.floor(expiresIn/60), s = expiresIn%60;
    if(timerEl) timerEl.textContent = pad(m)+':'+pad(s);
    if(expiresIn <= 60 && timerRow) timerRow.classList.add('urgent');
    setTimeout(tickCountdown, 1000);
}
if(expiresIn > 0) setTimeout(tickCountdown, 1000);

// ── Resend cooldown ────────────────────────────────────────────────────
let cooldownLeft = <?=(int)$cooldown_left?>;
const resendBtn   = document.getElementById('resendBtn');
const resendTimer = document.getElementById('resendTimer');

function tickResend(){
    if(cooldownLeft <= 0){
        if(resendBtn){ resendBtn.disabled = false; }
        if(resendTimer) resendTimer.textContent = '';
        return;
    }
    if(resendBtn) resendBtn.disabled = true;
    if(resendTimer) resendTimer.textContent = ' ('+cooldownLeft+'s)';
    cooldownLeft--;
    setTimeout(tickResend, 1000);
}
tickResend();

function doResend(){
    document.getElementById('resendForm').submit();
}
<?php endif; ?>

<?php if($step === 'reset'): ?>
// ── Live password rule checklist ───────────────────────────────────────
const pwField = document.getElementById('fpPw');
const rules = {
    len:   v => v.length >= 8 && v.length <= 16,
    upper: v => /[A-Z]/.test(v),
    lower: v => /[a-z]/.test(v),
    num:   v => /[0-9]/.test(v),
    spec:  v => /[^A-Za-z0-9]/.test(v),
};
pwField.addEventListener('input', () => {
    const v = pwField.value;
    document.querySelectorAll('#pwRules li').forEach(li => {
        const ok = rules[li.dataset.rule](v);
        li.classList.toggle('ok', ok);
        li.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle';
    });
});
<?php endif; ?>
</script>
</body>
</html>
