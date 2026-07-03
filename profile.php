<?php
session_start();
require 'db.php';
if(!is_logged()){ header("Location: login.php"); exit; }

$uid     = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── Fetch current user data ────────────────────────────────────
$stmt = $conn->prepare("SELECT name, email, phone, shopping_preference, date_of_birth, address FROM users WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ── Handle Profile Update ──────────────────────────────────────
$unchanged_fields = []; // for the "same as previous" popup
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])){
    csrf_check();
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = preg_replace('/[\s\-]+/', '', trim($_POST['phone'] ?? '')); // normalise: strip spaces/dashes
    $pref    = $_POST['shopping_preference'] ?? '';
    $address = trim($_POST['address'] ?? '');

    // Compare against current values (phone normalised on both sides)
    $cur_phone  = preg_replace('/[\s\-]+/', '', (string)($user['phone'] ?? ''));
    $name_same  = ($name  === $user['name']);
    $email_same = (strcasecmp($email, $user['email']) === 0);
    $phone_same = ($phone === $cur_phone);
    $pref_same  = ($pref  === ($user['shopping_preference'] ?? ''));
    $addr_same  = ($address === trim((string)($user['address'] ?? '')));

    if($name_same)  $unchanged_fields[] = 'Name';
    if($email_same) $unchanged_fields[] = 'Email';
    if($phone_same) $unchanged_fields[] = 'Phone Number';

    // ── Field validation ──
    if(!$name || !$email || !$phone){
        $error = "Name, email and phone number are required.";
    } elseif(mb_strlen($name) < 2 || mb_strlen($name) > 100){
        $error = "Name must be between 2 and 100 characters.";
    } elseif(!preg_match('/^[\p{L}][\p{L}\s.\'\-]*$/u', $name)){
        $error = "Name can only contain letters, spaces, dots, hyphens and apostrophes.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } elseif(mb_strlen($email) > 180){
        $error = "Email address is too long.";
    } elseif(!preg_match('/^01[0-9]{8,9}$/', $phone)){
        $error = "Phone number must be a valid Malaysian mobile number starting with 01 (10–11 digits, e.g. 0123456789).";
    } elseif(!in_array($pref, ['men','women','kids'])){
        $error = "Please select a valid shopping preference.";
    } elseif($address !== '' && mb_strlen($address) < 10){
        $error = "Delivery address is too short — please enter a complete address.";
    } elseif(mb_strlen($address) > 500){
        $error = "Delivery address is too long (max 500 characters).";

    // ── Nothing changed at all → block with popup ──
    } elseif($name_same && $email_same && $phone_same && $pref_same && $addr_same){
        $error = "no_changes";

    } else {
        // ── Uniqueness: new email/phone must not clash with ANOTHER account ──
        if(!$email_same){
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email=? AND user_id<>?");
            $chk->bind_param("si", $email, $uid);
            $chk->execute();
            if($chk->get_result()->num_rows > 0){
                $error = "This email address is already registered to another account.";
            }
        }
        if(!$error && !$phone_same){
            $chk2 = $conn->prepare("SELECT user_id FROM users WHERE REPLACE(REPLACE(phone,'-',''),' ','')=? AND user_id<>?");
            $chk2->bind_param("si", $phone, $uid);
            $chk2->execute();
            if($chk2->get_result()->num_rows > 0){
                $error = "This phone number is already registered to another account.";
            }
        }

        if(!$error){
            $upd = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, shopping_preference=?, address=? WHERE user_id=?");
            $upd->bind_param("sssssi", $name, $email, $phone, $pref, $address, $uid);
            if($upd->execute()){
                $_SESSION['user_name'] = $name;
                $success = "profile";
                // Re-fetch updated data
                $stmt2 = $conn->prepare("SELECT name, email, phone, shopping_preference, date_of_birth, address FROM users WHERE user_id=?");
                $stmt2->bind_param("i", $uid);
                $stmt2->execute();
                $user = $stmt2->get_result()->fetch_assoc();
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

// ── Handle Password Change ─────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])){
    csrf_check();
    $current  = $_POST['current_password']  ?? '';
    $new_pw   = $_POST['new_password']       ?? '';
    $confirm  = $_POST['confirm_password']   ?? '';

    // Fetch stored hash
    $ph = $conn->prepare("SELECT password FROM users WHERE user_id=?");
    $ph->bind_param("i", $uid);
    $ph->execute();
    $stored = $ph->get_result()->fetch_assoc()['password'];

    if(!$current || !$new_pw || !$confirm){
        $error = "Please fill in all password fields.";
    } elseif(!password_verify($current, $stored)){
        $error = "Current password is incorrect.";
    } elseif(strlen($new_pw) < 8 || strlen($new_pw) > 16){
        $error = "New password must be 8–16 characters.";
    } elseif(!preg_match('/[A-Z]/', $new_pw)){
        $error = "New password must contain an uppercase letter.";
    } elseif(!preg_match('/[a-z]/', $new_pw)){
        $error = "New password must contain a lowercase letter.";
    } elseif(!preg_match('/[0-9]/', $new_pw)){
        $error = "New password must contain a number.";
    } elseif(!preg_match('/[^A-Za-z0-9]/', $new_pw)){
        $error = "New password must contain a special character.";
    } elseif($new_pw !== $confirm){
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd2 = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $upd2->bind_param("si", $hashed, $uid);
        if($upd2->execute()){
            $success = "password";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

// ── Fetch vouchers ─────────────────────────────────────────────
$vouchers_res = null;
$tbl_v = $conn->query("SHOW TABLES LIKE 'vouchers'");
if($tbl_v && $tbl_v->num_rows > 0){
    $vouchers_res = $conn->query("
        SELECT * FROM vouchers
        WHERE user_id = $uid
        ORDER BY is_used ASC, expires_at ASC, created_at DESC
    ");
}

// ── Derived display values ─────────────────────────────────────
$initials = strtoupper(substr($user['name'], 0, 1));
if(strpos($user['name'], ' ') !== false){
    $parts = explode(' ', $user['name']);
    $initials = strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));
}
$dob_display = '';
if(!empty($user['date_of_birth']) && $user['date_of_birth'] !== '0000-00-00'){
    $dob_display = date('d F Y', strtotime($user['date_of_birth']));
}
$pref_labels = ['men'=>'Men','women'=>'Women','kids'=>'Kids'];
$pref_display = $pref_labels[$user['shopping_preference']] ?? ucfirst($user['shopping_preference'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── Profile avatar ── */
.profile-avatar {
    width:76px; height:76px; border-radius:50%;
    background:var(--accent); color:#fff;
    font-family:'Oswald',sans-serif; font-size:1.8rem; letter-spacing:2px;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; box-shadow:0 4px 16px rgba(200,84,60,.3);
}

/* ── Tab buttons ── */
.profile-tabs { display:flex; gap:8px; margin-bottom:28px; border-bottom:1px solid var(--border); padding-bottom:0; }
.prof-tab {
    padding:10px 22px; background:none; border:none; border-bottom:2px solid transparent;
    font-family:'Oswald',sans-serif; font-size:.82rem; letter-spacing:2px; text-transform:uppercase;
    color:var(--muted); cursor:pointer; transition:.2s; margin-bottom:-1px;
}
.prof-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.prof-tab:hover:not(.active) { color:var(--white); }

/* ── Tab panes ── */
.tab-pane { display:none; }
.tab-pane.active { display:block; }

/* ── Info row (view mode) ── */
.info-row {
    display:flex; align-items:flex-start; gap:14px;
    padding:14px 0; border-bottom:1px solid var(--border);
}
.info-row:last-child { border-bottom:none; }
.info-icon { width:36px; height:36px; border-radius:8px; background:rgba(200,84,60,.08);
    border:1px solid rgba(200,84,60,.15); display:flex; align-items:center; justify-content:center;
    color:var(--accent); font-size:.82rem; flex-shrink:0; }
.info-label { font-size:.68rem; letter-spacing:2px; text-transform:uppercase; color:var(--muted); margin-bottom:3px; }
.info-value { font-size:.92rem; color:var(--white); font-weight:500; }
.info-value.empty { color:var(--muted); font-style:italic; font-weight:400; }

/* ── Section header ── */
.sec-head {
    font-family:'Oswald',sans-serif; font-size:.85rem; letter-spacing:2.5px;
    text-transform:uppercase; color:var(--white); margin-bottom:18px;
    padding-bottom:10px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
}
.sec-head i { color:var(--accent); }

/* ── Form inputs consistent with site ── */
.prof-input {
    width:100%; padding:12px 16px;
    background:#FFFFFF; border:1.5px solid rgba(150,100,75,0.2);
    border-radius:var(--radius); color:var(--white); font-size:.9rem;
    transition:border-color .2s; outline:none; font-family:'Inter',sans-serif;
}
.prof-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(200,84,60,.1); }
.prof-input::placeholder { color:#C4AFA9; }
.prof-input-icon { position:relative; }
.prof-input-icon > i { position:absolute; left:14px; top:50%; transform:translateY(-50%);
    color:var(--muted); font-size:.82rem; pointer-events:none; }
.prof-input-icon .prof-input { padding-left:40px; }
.prof-input-icon .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:var(--muted); cursor:pointer; font-size:.9rem; padding:4px; }
.prof-input-icon .pw-toggle:hover { color:var(--accent); }

/* ── Voucher card ── */
.voucher-card {
    background:#FFFFFF; border:1.5px solid rgba(150,100,75,.18);
    border-radius:12px; padding:0; overflow:hidden;
    display:flex; flex-direction:column; position:relative; transition:.2s;
}
.voucher-card.active-voucher { border-color:rgba(42,155,90,.35); }
.voucher-card.active-voucher:hover { border-color:var(--accent); box-shadow:0 8px 28px rgba(200,84,60,.12); }
.voucher-stripe {
    height:5px; background:linear-gradient(90deg, var(--accent), #D96A46);
}
.voucher-stripe.used { background:linear-gradient(90deg,#888,#aaa); }
.voucher-stripe.expired { background:linear-gradient(90deg,#c0392b,#e74c3c); }
.voucher-body { padding:18px 20px; flex:1; }
.voucher-code {
    font-family:'Oswald',sans-serif; font-size:1.25rem; letter-spacing:3px;
    color:var(--accent); margin-bottom:6px; display:flex; align-items:center; gap:8px;
}
.voucher-code.dimmed { color:var(--muted); }
.voucher-amount {
    font-family:'Oswald',sans-serif; font-size:2rem; color:var(--white); line-height:1;
    margin-bottom:8px;
}
.voucher-amount.dimmed { color:var(--muted); }
.voucher-reason { font-size:.78rem; color:var(--muted); line-height:1.5; margin-bottom:10px; }
.voucher-footer {
    padding:10px 20px; background:rgba(0,0,0,.04);
    border-top:1px solid rgba(150,100,75,.1);
    display:flex; align-items:center; justify-content:space-between;
}
.voucher-status {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:100px; font-size:.65rem;
    font-weight:700; letter-spacing:1px; text-transform:uppercase;
}
.vs-active   { background:rgba(42,155,90,.12); color:#2a9b5a; border:1px solid rgba(42,155,90,.3); }
.vs-used     { background:rgba(150,150,150,.1); color:#888; border:1px solid rgba(150,150,150,.2); }
.vs-expired  { background:rgba(214,64,64,.08); color:var(--danger); border:1px solid rgba(214,64,64,.2); }

/* ── Inline field errors ── */
.field-err {
    display:none; font-size:.75rem; color:#ef4444;
    margin-top:6px; align-items:center; gap:6px;
}
.field-err.show { display:flex; }
.prof-input.invalid { border-color:#ef4444 !important; }

/* ── Popup message box ── */
.apx-modal-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center;
    z-index:9999; padding:20px;
}
.apx-modal-overlay.show { display:flex; }
.apx-modal {
    background:var(--card, #fff); border:1px solid var(--border);
    border-radius:14px; max-width:420px; width:100%;
    padding:28px; text-align:center;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
    animation:apxModalIn .25s cubic-bezier(.22,1,.36,1);
}
@keyframes apxModalIn {
    from { opacity:0; transform:translateY(16px) scale(.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
.apx-modal-icon {
    width:60px; height:60px; border-radius:50%; margin:0 auto 16px;
    background:rgba(202,138,4,.12); border:1.5px solid rgba(202,138,4,.3);
    display:flex; align-items:center; justify-content:center;
    color:#ca8a04; font-size:1.5rem;
}
.apx-modal-title {
    font-family:'Oswald',sans-serif; font-size:1.05rem; letter-spacing:2px;
    text-transform:uppercase; color:var(--white); margin-bottom:10px;
}
.apx-modal-text { font-size:.875rem; color:var(--muted); line-height:1.7; margin-bottom:20px; }
.apx-modal-text strong { color:var(--accent); }

/* ── Pref pills (compact) ── */
.pref-pills { display:flex; gap:10px; flex-wrap:wrap; }
.pref-pill { cursor:pointer; }
.pref-pill input[type="radio"] { display:none; }
.pref-pill-inner {
    display:flex; align-items:center; gap:8px;
    padding:10px 18px; background:#FFFFFF; border:2px solid rgba(150,100,75,0.18);
    border-radius:100px; transition:all .2s; font-size:.85rem; font-weight:600; color:var(--muted);
}
.pref-pill-inner i { font-size:.82rem; }
.pref-pill input:checked + .pref-pill-inner { border-color:var(--accent); background:rgba(200,84,60,.07); color:var(--white); }
.pref-pill input:checked + .pref-pill-inner i { color:var(--accent); }
.pref-pill:hover .pref-pill-inner { border-color:rgba(200,84,60,.35); }
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- ── Page Header ── -->
<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb">
      <a href="index.php">Home</a><span class="sep">/</span><span>My Profile</span>
    </div>
    <h1>MY <span style="color:var(--accent)">PROFILE</span></h1>
  </div>
</div>

<section class="section" style="padding-top:36px;">
<div class="wrap" style="max-width:860px;">

  <!-- ── Flash messages ── -->
  <?php if($success==='profile'): ?>
  <div class="flash flash-ok" style="margin-bottom:20px;">
    <i class="fa-solid fa-circle-check"></i> Profile updated successfully.
  </div>
  <?php elseif($success==='password'): ?>
  <div class="flash flash-ok" style="margin-bottom:20px;">
    <i class="fa-solid fa-circle-check"></i> Password changed successfully.
  </div>
  <?php elseif($error && $error !== 'no_changes'): ?>
  <div class="flash flash-err" style="margin-bottom:20px;">
    <i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?>
  </div>
  <?php endif; ?>

  <!-- ── Profile Header Card ── -->
  <div class="card" style="padding:24px 28px;margin-bottom:28px;">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
      <div class="profile-avatar"><?=e($initials)?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-family:'Oswald',sans-serif;font-size:1.4rem;letter-spacing:2px;color:var(--white);margin-bottom:4px;">
          <?=e($user['name'])?>
        </div>
        <div style="font-size:.875rem;color:var(--muted);margin-bottom:8px;">
          <i class="fa-solid fa-envelope" style="width:16px;color:var(--accent);margin-right:4px;"></i><?=e($user['email'])?>
        </div>
        <?php if($pref_display): ?>
        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;background:rgba(200,84,60,.08);border:1px solid rgba(200,84,60,.2);border-radius:100px;font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--accent);font-weight:700;">
          <i class="fa-solid fa-tag" style="font-size:.65rem;"></i><?=e($pref_display)?>
        </span>
        <?php endif; ?>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <a href="order_history.php" class="btn btn-secondary btn-sm">
          <i class="fa-solid fa-box"></i> My Orders
        </a>
      </div>
    </div>
  </div>

  <!-- ── Tabs ── -->
  <div class="profile-tabs">
    <button class="prof-tab active" id="tab-btn-info" onclick="switchTab('info',this)">
      <i class="fa-solid fa-user" style="margin-right:6px;"></i>Personal Info
    </button>
    <button class="prof-tab" id="tab-btn-security" onclick="switchTab('security',this)">
      <i class="fa-solid fa-lock" style="margin-right:6px;"></i>Change Password
    </button>
    <button class="prof-tab" id="tab-btn-vouchers" onclick="switchTab('vouchers',this)">
      <i class="fa-solid fa-ticket" style="margin-right:6px;"></i>My Vouchers
      <?php
      // Count active vouchers
      $active_v = 0;
      if($vouchers_res){
          $vouchers_res->data_seek(0);
          while($tmp=$vouchers_res->fetch_assoc()){
              if(!$tmp['is_used'] && ($tmp['expires_at'] === null || $tmp['expires_at'] >= date('Y-m-d'))) $active_v++;
          }
          $vouchers_res->data_seek(0);
      }
      if($active_v > 0): ?>
      <span style="background:var(--accent);color:#fff;border-radius:100px;padding:1px 7px;font-size:.58rem;margin-left:4px;font-weight:700;"><?=$active_v?></span>
      <?php endif; ?>
    </button>
  </div>

  <!-- ════════════════════════════════════════
       TAB 1 — Personal Info
  ═══════════════════════════════════════════ -->
  <div id="tab-info" class="tab-pane active">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

      <!-- Left: View Card -->
      <div class="card" style="padding:22px 24px;">
        <div class="sec-head"><i class="fa-solid fa-id-card"></i> Current Details</div>

        <div class="info-row">
          <div class="info-icon"><i class="fa-solid fa-user"></i></div>
          <div>
            <div class="info-label">Full Name</div>
            <div class="info-value"><?=e($user['name'])?></div>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="fa-solid fa-envelope"></i></div>
          <div>
            <div class="info-label">Email Address</div>
            <div class="info-value"><?=e($user['email'])?></div>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="fa-solid fa-phone"></i></div>
          <div>
            <div class="info-label">Phone Number</div>
            <div class="info-value <?=empty($user['phone'])?'empty':''?>">
              <?=!empty($user['phone']) ? e($user['phone']) : 'Not set'?>
            </div>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="fa-solid fa-cake-candles"></i></div>
          <div>
            <div class="info-label">Date of Birth</div>
            <div class="info-value <?=empty($dob_display)?'empty':''?>">
              <?=!empty($dob_display) ? $dob_display : 'Not set'?>
            </div>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="fa-solid fa-tag"></i></div>
          <div>
            <div class="info-label">Shopping Preference</div>
            <div class="info-value <?=empty($pref_display)?'empty':''?>">
              <?=!empty($pref_display) ? $pref_display : 'Not set'?>
            </div>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="fa-solid fa-location-dot"></i></div>
          <div>
            <div class="info-label">Delivery Address</div>
            <div class="info-value <?=empty($user['address'])?'empty':''?>">
              <?=!empty($user['address']) ? e($user['address']) : 'Not set'?>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Edit Card -->
      <div class="card" style="padding:22px 24px;">
        <div class="sec-head"><i class="fa-solid fa-pen-to-square"></i> Edit Details</div>
        <form method="POST" novalidate id="profileForm">
          <?=csrf_field()?>

          <div class="form-group">
            <label>Full Name</label>
            <div class="prof-input-icon">
              <i class="fa-solid fa-user"></i>
              <input type="text" name="name" id="editName" class="prof-input"
                     value="<?=e(isset($_POST['update_profile']) ? ($_POST['name'] ?? '') : $user['name'])?>" placeholder="Your full name" required>
            </div>
            <div class="field-err" id="err-name"></div>
          </div>

          <div class="form-group">
            <label>Email Address</label>
            <div class="prof-input-icon">
              <i class="fa-solid fa-envelope"></i>
              <input type="email" name="email" id="editEmail" class="prof-input"
                     value="<?=e(isset($_POST['update_profile']) ? ($_POST['email'] ?? '') : $user['email'])?>" placeholder="you@email.com" required>
            </div>
            <div class="field-err" id="err-email"></div>
          </div>

          <div class="form-group">
            <label>Phone Number</label>
            <div class="prof-input-icon">
              <i class="fa-solid fa-phone"></i>
              <input type="tel" name="phone" id="editPhone" class="prof-input"
                     value="<?=e(isset($_POST['update_profile']) ? ($_POST['phone'] ?? '') : ($user['phone']??''))?>" placeholder="01xxxxxxxx" required>
            </div>
            <div class="field-err" id="err-phone"></div>
          </div>

          <div class="form-group">
            <label>Shopping Preference</label>
            <div class="pref-pills">
              <?php $curPref = isset($_POST['update_profile']) ? ($_POST['shopping_preference'] ?? '') : ($user['shopping_preference'] ?? ''); ?>
              <label class="pref-pill">
                <input type="radio" name="shopping_preference" value="men" <?=$curPref==='men'?'checked':''?>>
                <div class="pref-pill-inner"><i class="fa-solid fa-person"></i> Men</div>
              </label>
              <label class="pref-pill">
                <input type="radio" name="shopping_preference" value="women" <?=$curPref==='women'?'checked':''?>>
                <div class="pref-pill-inner"><i class="fa-solid fa-person-dress"></i> Women</div>
              </label>
              <label class="pref-pill">
                <input type="radio" name="shopping_preference" value="kids" <?=$curPref==='kids'?'checked':''?>>
                <div class="pref-pill-inner"><i class="fa-solid fa-child"></i> Kids</div>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label>Delivery Address</label>
            <textarea name="address" id="editAddress" class="prof-input" rows="3"
                      placeholder="Your delivery address..."
                      style="resize:vertical;"><?=e(isset($_POST['update_profile']) ? ($_POST['address'] ?? '') : ($user['address']??''))?></textarea>
            <div class="field-err" id="err-address"></div>
          </div>

          <button type="submit" name="update_profile" class="btn btn-primary btn-full" style="margin-top:6px;">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
          </button>
        </form>
      </div>

    </div><!-- /grid -->
  </div><!-- /tab-info -->

  <!-- ════════════════════════════════════════
       TAB 2 — Change Password
  ═══════════════════════════════════════════ -->
  <div id="tab-security" class="tab-pane">
    <div class="card" style="padding:28px;max-width:480px;">
      <div class="sec-head"><i class="fa-solid fa-shield-halved"></i> Change Password</div>

      <form method="POST" novalidate>
        <?=csrf_field()?>

        <div class="form-group">
          <label>Current Password</label>
          <div class="prof-input-icon">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="current_password" id="curPw" class="prof-input"
                   placeholder="Your current password" required>
            <button type="button" class="pw-toggle" onclick="togglePw('curPw','curPwIcon')" tabindex="-1">
              <i class="fa-solid fa-eye" id="curPwIcon"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label>New Password</label>
          <div class="prof-input-icon">
            <i class="fa-solid fa-key"></i>
            <input type="password" name="new_password" id="newPw" class="prof-input"
                   placeholder="Create a new password" oninput="checkPwRules(this.value)" required>
            <button type="button" class="pw-toggle" onclick="togglePw('newPw','newPwIcon')" tabindex="-1">
              <i class="fa-solid fa-eye" id="newPwIcon"></i>
            </button>
          </div>
          <!-- Live rules -->
          <div class="pw-rules" id="pwRules" style="margin-top:8px;">
            <div class="pw-rule" id="r-len"><i class="fa-solid fa-xmark rule-icon"></i><span>8–16 characters</span></div>
            <div class="pw-rule" id="r-up"><i class="fa-solid fa-xmark rule-icon"></i><span>Uppercase letter</span></div>
            <div class="pw-rule" id="r-lo"><i class="fa-solid fa-xmark rule-icon"></i><span>Lowercase letter</span></div>
            <div class="pw-rule" id="r-num"><i class="fa-solid fa-xmark rule-icon"></i><span>Number</span></div>
            <div class="pw-rule" id="r-sp"><i class="fa-solid fa-xmark rule-icon"></i><span>Special character</span></div>
          </div>
        </div>

        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="prof-input-icon">
            <i class="fa-solid fa-key"></i>
            <input type="password" name="confirm_password" id="confPw" class="prof-input"
                   placeholder="Repeat new password" required>
            <button type="button" class="pw-toggle" onclick="togglePw('confPw','confPwIcon')" tabindex="-1">
              <i class="fa-solid fa-eye" id="confPwIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="change_password" class="btn btn-primary btn-full" style="margin-top:6px;">
          <i class="fa-solid fa-shield-halved"></i> Update Password
        </button>
      </form>
    </div>
  </div><!-- /tab-security -->

  <!-- ════════════════════════════════════════
       TAB 3 — My Vouchers
  ═══════════════════════════════════════════ -->
  <div id="tab-vouchers" class="tab-pane">

    <?php if($vouchers_res && $vouchers_res->num_rows > 0):
      $vouchers_res->data_seek(0);
      $has_active = false;
      $all_vouchers = [];
      while($v = $vouchers_res->fetch_assoc()) $all_vouchers[] = $v;
      foreach($all_vouchers as $v){
          if(!$v['is_used'] && ($v['expires_at'] === null || $v['expires_at'] >= date('Y-m-d'))) { $has_active = true; break; }
      }
    ?>

    <?php if($has_active): ?>
    <div class="card" style="padding:16px 20px;margin-bottom:20px;background:rgba(42,155,90,.06);border:1px solid rgba(42,155,90,.25);">
      <div style="display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-circle-check" style="color:#2a9b5a;font-size:1.1rem;"></i>
        <div>
          <div style="font-family:'Oswald',sans-serif;font-size:.85rem;letter-spacing:1.5px;color:var(--white);">VOUCHERS AVAILABLE</div>
          <div style="font-size:.8rem;color:var(--muted);margin-top:2px;">Present the voucher code when placing an order to receive your discount.</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach($all_vouchers as $v):
      $today     = date('Y-m-d');
      $expired   = $v['expires_at'] && $v['expires_at'] < $today;
      $used      = (bool)$v['is_used'];
      $inactive  = $used || $expired;
      $card_cls  = $inactive ? '' : 'active-voucher';
      $code_cls  = $inactive ? 'dimmed' : '';
      $amt_cls   = $inactive ? 'dimmed' : '';
      if($used)         { $stripe='used'; $st_cls='vs-used'; $st_lbl='Used'; }
      elseif($expired)  { $stripe='expired'; $st_cls='vs-expired'; $st_lbl='Expired'; }
      else              { $stripe=''; $st_cls='vs-active'; $st_lbl='Active'; }
    ?>
    <div class="voucher-card <?=$card_cls?>">
      <div class="voucher-stripe <?=$stripe?>"></div>
      <div class="voucher-body">
        <div class="voucher-code <?=$code_cls?>">
          <i class="fa-solid fa-ticket"></i>
          <?=e($v['code'])?>
        </div>
        <div class="voucher-amount <?=$amt_cls?>">
          RM <?=number_format($v['amount'],2)?>
        </div>
        <div class="voucher-reason"><?=e($v['reason'])?></div>
      </div>
      <div class="voucher-footer">
        <span class="voucher-status <?=$st_cls?>">● <?=$st_lbl?></span>
        <span style="font-size:.72rem;color:var(--muted);">
          <?php if($v['expires_at']): ?>
            <?=$used ? 'Used' : ($expired ? 'Expired' : 'Valid until')?>:
            <strong style="color:<?=$inactive?'var(--muted)':'var(--white)'?>;">
              <?=date('d M Y', strtotime($v['expires_at']))?>
            </strong>
          <?php else: ?>
            <span style="color:var(--muted);">No expiry</span>
          <?php endif; ?>
        </span>
      </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Empty state -->
    <div style="text-align:center;padding:64px 24px;">
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(200,84,60,.08);border:1px solid rgba(200,84,60,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:var(--accent);">
        <i class="fa-solid fa-ticket"></i>
      </div>
      <h3 style="font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:2px;color:var(--white);margin-bottom:10px;">NO VOUCHERS YET</h3>
      <p style="color:var(--muted);font-size:.875rem;max-width:320px;margin:0 auto 24px;">
        Vouchers will appear here when they are issued to your account, such as refunds for cancelled orders.
      </p>
      <a href="products.php" class="btn btn-primary">Browse Shoes →</a>
    </div>
    <?php endif; ?>

  </div><!-- /tab-vouchers -->

</div><!-- /wrap -->
</section>

<?php include 'includes/footer.php'; ?>

<!-- ── Popup message box: no changes detected ── -->
<div class="apx-modal-overlay" id="noChangeModal">
  <div class="apx-modal">
    <div class="apx-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="apx-modal-title">No Changes Detected</div>
    <div class="apx-modal-text" id="noChangeText">
      Your details are the same as the previous ones. Please change at least one field before saving.
    </div>
    <button type="button" class="btn btn-primary" onclick="closeNoChangeModal()" style="min-width:120px;">
      OK, Got It
    </button>
  </div>
</div>

<script>
// ── Tab switching ──────────────────────────────────────
function switchTab(name, btn){
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.prof-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
    btn.classList.add('active');
}

// ── Auto-open correct tab on load ──────────────────────
document.addEventListener('DOMContentLoaded', function(){
    // URL param: profile.php?tab=vouchers
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    <?php if($success==='password' || (isset($_POST['change_password']) && $error)): ?>
    switchTab('security', document.getElementById('tab-btn-security'));
    <?php else: ?>
    if(urlTab === 'vouchers'){
        switchTab('vouchers', document.getElementById('tab-btn-vouchers'));
    } else if(urlTab === 'security'){
        switchTab('security', document.getElementById('tab-btn-security'));
    }
    <?php endif; ?>
});

// ── Password visibility toggle ─────────────────────────
function togglePw(fieldId, iconId){
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if(f.type==='password'){ f.type='text'; i.className='fa-solid fa-eye-slash'; }
    else { f.type='password'; i.className='fa-solid fa-eye'; }
}

// ── Profile edit: validation + "same as previous" check ─
const originalProfile = {
    name:    <?=json_encode($user['name'])?>,
    email:   <?=json_encode($user['email'])?>,
    phone:   <?=json_encode(preg_replace('/[\s\-]+/', '', (string)($user['phone'] ?? '')))?>,
    pref:    <?=json_encode($user['shopping_preference'] ?? '')?>,
    address: <?=json_encode(trim((string)($user['address'] ?? '')))?>
};

function setFieldErr(id, inputEl, msg){
    const box = document.getElementById(id);
    if(msg){
        box.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + msg;
        box.classList.add('show');
        inputEl.classList.add('invalid');
    } else {
        box.classList.remove('show');
        inputEl.classList.remove('invalid');
    }
    return !msg;
}

function validateProfileForm(){
    const nameEl  = document.getElementById('editName');
    const emailEl = document.getElementById('editEmail');
    const phoneEl = document.getElementById('editPhone');
    const addrEl  = document.getElementById('editAddress');

    const name  = nameEl.value.trim();
    const email = emailEl.value.trim();
    const phone = phoneEl.value.trim().replace(/[\s\-]+/g, '');
    const addr  = addrEl.value.trim();
    let ok = true;

    // Name
    if(!name)                                   ok = setFieldErr('err-name', nameEl, 'Full name is required.') && ok;
    else if(name.length < 2 || name.length > 100) ok = setFieldErr('err-name', nameEl, 'Name must be 2–100 characters.') && ok;
    else if(!/^[\p{L}][\p{L}\s.'\-]*$/u.test(name)) ok = setFieldErr('err-name', nameEl, 'Name can only contain letters, spaces, dots, hyphens and apostrophes.') && ok;
    else setFieldErr('err-name', nameEl, '');

    // Email
    if(!email)                                  ok = setFieldErr('err-email', emailEl, 'Email address is required.') && ok;
    else if(!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) ok = setFieldErr('err-email', emailEl, 'Please enter a valid email address.') && ok;
    else setFieldErr('err-email', emailEl, '');

    // Phone
    if(!phone)                                  ok = setFieldErr('err-phone', phoneEl, 'Phone number is required.') && ok;
    else if(!/^01[0-9]{8,9}$/.test(phone))      ok = setFieldErr('err-phone', phoneEl, 'Must be a valid Malaysian number starting with 01 (10–11 digits).') && ok;
    else setFieldErr('err-phone', phoneEl, '');

    // Address (optional, but must be complete if given)
    if(addr !== '' && addr.length < 10)         ok = setFieldErr('err-address', addrEl, 'Address is too short — enter a complete address.') && ok;
    else if(addr.length > 500)                  ok = setFieldErr('err-address', addrEl, 'Address is too long (max 500 characters).') && ok;
    else setFieldErr('err-address', addrEl, '');

    return ok;
}

document.getElementById('profileForm').addEventListener('submit', function(e){
    if(!validateProfileForm()){
        e.preventDefault();
        return;
    }

    // "Same as previous" check — block if NOTHING changed
    const name  = document.getElementById('editName').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    const phone = document.getElementById('editPhone').value.trim().replace(/[\s\-]+/g, '');
    const addr  = document.getElementById('editAddress').value.trim();
    const pref  = (document.querySelector('input[name="shopping_preference"]:checked') || {}).value || '';

    const same = [];
    if(name === originalProfile.name)                        same.push('Name');
    if(email.toLowerCase() === originalProfile.email.toLowerCase()) same.push('Email');
    if(phone === originalProfile.phone)                      same.push('Phone Number');
    const prefSame = pref === originalProfile.pref;
    const addrSame = addr === originalProfile.address;

    if(same.length === 3 && prefSame && addrSame){
        e.preventDefault();
        showNoChangeModal(same);
    }
});

function showNoChangeModal(sameFields){
    const text = document.getElementById('noChangeText');
    text.innerHTML = 'Cannot proceed — your <strong>' + sameFields.join('</strong>, <strong>') +
                     '</strong> and all other details are the same as the previous ones.<br>' +
                     'Please change at least one field before saving.';
    document.getElementById('noChangeModal').classList.add('show');
}
function closeNoChangeModal(){
    document.getElementById('noChangeModal').classList.remove('show');
}
// Close on overlay click / Escape key
document.getElementById('noChangeModal').addEventListener('click', function(e){
    if(e.target === this) closeNoChangeModal();
});
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeNoChangeModal();
});

<?php if($error === 'no_changes'): ?>
// Server-side detected no changes (JS was bypassed) — show the popup on load
document.addEventListener('DOMContentLoaded', function(){
    showNoChangeModal(<?=json_encode($unchanged_fields)?>);
});
<?php endif; ?>

// ── Live password rules ────────────────────────────────
function checkPwRules(v){
    const rules = [
        { id:'r-len', test: v => v.length>=8 && v.length<=16 },
        { id:'r-up',  test: v => /[A-Z]/.test(v) },
        { id:'r-lo',  test: v => /[a-z]/.test(v) },
        { id:'r-num', test: v => /[0-9]/.test(v) },
        { id:'r-sp',  test: v => /[^A-Za-z0-9]/.test(v) },
    ];
    rules.forEach(function(r){
        const el   = document.getElementById(r.id);
        const icon = el.querySelector('.rule-icon');
        const pass = r.test(v);
        el.classList.toggle('pass', pass);
        el.classList.toggle('fail', !pass && v.length > 0);
        icon.className = pass ? 'fa-solid fa-circle-check rule-icon' : 'fa-solid fa-xmark rule-icon';
    });
}
</script>
</body>
</html>
