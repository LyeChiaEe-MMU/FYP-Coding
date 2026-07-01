<?php
session_start();
require 'db.php';
require_once 'admin/Mailer.php';

if(is_logged()){ header("Location: index.php"); exit; }

// Must have a pending registration session
if(empty($_SESSION['reg_pending'])){
    header("Location: register.php"); exit;
}

$pending      = $_SESSION['reg_pending'];
$email        = $pending['email'];
$max_attempts = 5;
$error        = '';
$success      = false;
$resent       = false;

// Mask email for display: jo***@gmail.com
$at          = strpos($email, '@');
$local       = substr($email, 0, $at);
$domain      = substr($email, $at + 1);
$mask_len    = max(0, strlen($local) - 2);
$masked      = (strlen($local) > 2 ? substr($local, 0, 2) : $local)
             . str_repeat('*', $mask_len) . '@' . $domain;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_check();
    $action = $_POST['action'] ?? 'verify';

    if($action === 'resend'){
        $cooldown = 60;
        $wait = $cooldown - (time() - ($_SESSION['reg_otp_sent_at'] ?? 0));
        if($wait > 0){
            $error = "Please wait $wait second" . ($wait === 1 ? '' : 's') . " before resending.";
        } else {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['reg_otp']          = $otp;
            $_SESSION['reg_otp_expires']  = time() + 600;
            $_SESSION['reg_otp_attempts'] = 0;
            $_SESSION['reg_otp_sent_at']  = time();

            $otp_html = "<div style=\"font-family:monospace;font-size:34px;font-weight:700;"
                      . "letter-spacing:10px;color:#C8543C;text-align:center;"
                      . "background:#fff8f6;border:2px dashed #C8543C;border-radius:8px;"
                      . "padding:22px;margin:20px 0;\">$otp</div>";

            $name_safe = htmlspecialchars($pending['name'], ENT_QUOTES, 'UTF-8');
            $body = apex_mail_html_body(
                "<p>Hello {$name_safe},</p>"
                . "<p>Here is your new Apex Store verification code:</p>"
                . $otp_html
                . "<p>This code is valid for <strong>10 minutes</strong>.</p>"
                . "<p>If you did not request this, please ignore this email.</p>"
            );

            $result = apex_send_mail($email, $pending['name'], 'Your Apex Verification Code (Resent)', $body);
            if($result['ok']) $resent = true;
            else $error = "Failed to resend. Please try again later.";
        }

    } else {
        // Build OTP from either 6-box inputs or single hidden field
        $entered = '';
        if(isset($_POST['otp']) && strlen(trim($_POST['otp'])) === 6){
            $entered = trim($_POST['otp']);
        } else {
            for($i=1;$i<=6;$i++) $entered .= preg_replace('/\D/','',$_POST["d$i"] ?? '');
        }

        if(time() > ($_SESSION['reg_otp_expires'] ?? 0)){
            $error = 'Your code has expired. Please <a href="register.php" style="color:var(--accent);">register again</a>.';
        } elseif($_SESSION['reg_otp_attempts'] >= $max_attempts){
            unset($_SESSION['reg_pending'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expires'],
                  $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_sent_at']);
            header("Location: register.php?err=toomany"); exit;
        } elseif(strlen($entered) !== 6 || !ctype_digit($entered)){
            $error = "Please enter the full 6-digit code.";
        } elseif($entered !== $_SESSION['reg_otp']){
            $_SESSION['reg_otp_attempts']++;
            $left  = $max_attempts - $_SESSION['reg_otp_attempts'];
            $error = "Incorrect code. " . ($left > 0 ? "$left attempt" . ($left === 1 ? '' : 's') . " remaining." : "No attempts remaining.");
            if($left <= 0){
                unset($_SESSION['reg_pending'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expires'],
                      $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_sent_at']);
                header("Location: register.php?err=toomany"); exit;
            }
        } else {
            // OTP correct — create account
            $p   = $pending;
            $ins = $conn->prepare("INSERT INTO users (name,email,password,phone,address,shopping_preference,date_of_birth) VALUES (?,?,?,?,?,?,?)");
            $ins->bind_param("sssssss", $p['name'], $p['email'], $p['hashed'], $p['phone'], $p['address'], $p['pref'], $p['dob']);
            if($ins->execute()){
                unset($_SESSION['reg_pending'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expires'],
                      $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_sent_at']);
                $success = true;
            } else {
                $error = "Account creation failed. Please try again.";
            }
        }
    }
}

$expires_in = max(0, ($_SESSION['reg_otp_expires'] ?? time()) - time());
$cooldown_left = max(0, 60 - (time() - ($_SESSION['reg_otp_sent_at'] ?? 0)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Verify Email | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
@keyframes apxFadeUp {
  from { opacity:0; transform:translateY(22px); }
  to   { opacity:1; transform:translateY(0); }
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

/* Countdown */
.otp-timer { text-align:center; font-size:.78rem; color:var(--muted); margin-bottom:8px; }
.otp-timer span { font-weight:700; color:var(--accent); }
.otp-timer.urgent span { color:#ef4444; }

/* Resend */
.resend-row { text-align:center; margin-top:12px; font-size:.82rem; color:var(--muted); }
.resend-btn { background:none; border:none; color:var(--accent); cursor:pointer; font-size:.82rem; font-weight:600; padding:0; text-decoration:underline; }
.resend-btn:disabled { color:var(--muted); text-decoration:none; cursor:default; }

/* Email chip */
.email-chip {
  display:inline-flex; align-items:center; gap:7px;
  background:rgba(100,255,218,.07); border:1px solid rgba(100,255,218,.2);
  border-radius:100px; padding:5px 14px;
  font-size:.82rem; color:var(--white); margin:8px 0 4px;
}
.email-chip i { color:var(--accent); }

/* Success card */
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
    <div class="brand-float-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
    <div class="register-brand-overlay"></div>
    <div class="register-brand-content">
      <div class="register-brand-logo">APE<span>X</span></div>
      <h2 class="register-brand-tagline">One Last<br><span>Step.</span></h2>
      <p class="register-brand-desc">We sent a 6-digit code to your email. Enter it to confirm your identity and activate your Apex account.</p>
      <div class="register-brand-features">
        <div class="register-feature">
          <i class="fa-solid fa-shield-halved"></i>
          <span>Keeps your account secure</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-clock"></i>
          <span>Code expires in 10 minutes</span>
        </div>
        <div class="register-feature">
          <i class="fa-solid fa-rotate"></i>
          <span>Didn't get it? Resend after 60 s</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Right: OTP Panel ─── -->
  <div class="register-form-panel">
    <div class="register-form-inner">
      <a href="register.php" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Register
      </a>

      <?php if($success): ?>
      <!-- Success state -->
      <div class="success-card">
        <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:2px;color:var(--white);margin-bottom:8px;">EMAIL VERIFIED</h1>
        <p style="color:var(--muted);margin-bottom:24px;">Your Apex account has been created successfully.</p>
        <a href="login.php" class="btn btn-primary btn-lg" style="letter-spacing:2px;">
          <span>LOGIN NOW</span>
          <i class="fa-solid fa-arrow-right" style="margin-left:8px;"></i>
        </a>
      </div>

      <?php else: ?>
      <!-- OTP form -->
      <div class="register-form-head">
        <div class="auth-logo register-mobile-logo">APE<span>X</span></div>
        <h1>Verify Your Email</h1>
        <p>We sent a 6-digit code to</p>
        <div class="email-chip"><i class="fa-solid fa-envelope"></i><?=e($masked)?></div>
      </div>

      <?php if($error): ?>
      <div class="flash flash-err"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
      <?php endif; ?>
      <?php if($resent): ?>
      <div class="flash flash-ok"><i class="fa-solid fa-circle-check"></i> A new code has been sent.</div>
      <?php endif; ?>

      <!-- Countdown timer -->
      <div class="otp-timer" id="otpTimer">
        Code expires in <span id="countdown"><?=gmdate('i:s', $expires_in)?></span>
      </div>

      <!-- OTP entry form -->
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
          <span>VERIFY &amp; CREATE ACCOUNT</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <!-- Resend form -->
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
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
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
    boxes.forEach((b,i) => {
        b.classList.toggle('filled', b.value !== '');
        b.classList.remove('error');
    });
}

boxes.forEach((box, idx) => {
    box.addEventListener('input', e => {
        // Allow only digits
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
        // Allow paste via ctrl/cmd+v
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        paste.split('').forEach((ch,i) => { if(boxes[i]) boxes[i].value = ch; });
        syncHidden();
        if(paste.length > 0) boxes[Math.min(paste.length, boxes.length)-1].focus();
    });
});

// Focus first box on load
if(boxes[0]) boxes[0].focus();

// Shake error boxes if there was a server-side error
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
        if(timerRow) timerRow.innerHTML = '<span style="color:#ef4444;">Code expired. <a href="register.php" style="color:var(--accent);">Register again</a>.</span>';
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
</script>
</body>
</html>
