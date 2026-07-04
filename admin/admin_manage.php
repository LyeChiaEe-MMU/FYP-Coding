<?php
require_once 'auth_check.php';

// ── SUPERADMIN ONLY ──────────────────────────────────────────────
if(!is_superadmin()){
    header("Location: admin_dashboard.php"); exit;
}

$msg = ''; $mtype = 'ok';
$me  = (int)$_SESSION['admin_id'];

// Shared password rule check (same rules as customer accounts)
function admin_pw_error($pw){
    if(strlen($pw) < 8 || strlen($pw) > 16) return "Password must be 8-16 characters.";
    if(!preg_match('/[A-Z]/', $pw))         return "Password must contain an uppercase letter.";
    if(!preg_match('/[a-z]/', $pw))         return "Password must contain a lowercase letter.";
    if(!preg_match('/[0-9]/', $pw))         return "Password must contain a number.";
    if(!preg_match('/[^A-Za-z0-9]/', $pw))  return "Password must contain a special character.";
    return '';
}

// ── ADD ADMIN ────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_admin'])){
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $pw       = $_POST['password']  ?? '';
    $pw2      = $_POST['password2'] ?? '';

    if(!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)){
        $msg = "Username must be 3-30 characters — letters, numbers and underscores only."; $mtype='err';
    } elseif(($pw_err = admin_pw_error($pw)) !== ''){
        $msg = $pw_err; $mtype='err';
    } elseif($pw !== $pw2){
        $msg = "Passwords do not match."; $mtype='err';
    } else {
        $dup = $conn->prepare("SELECT admin_id FROM admins WHERE username=?");
        $dup->bind_param("s", $username);
        $dup->execute();
        if($dup->get_result()->num_rows > 0){
            $msg = "An admin named \"$username\" already exists."; $mtype='err';
        } else {
            $hashed = password_hash($pw, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, 'admin')");
            $ins->bind_param("ss", $username, $hashed);
            $ins->execute();
            header("Location: admin_manage.php?msg=".urlencode("Admin \"$username\" created successfully.")."&mtype=ok"); exit;
        }
    }
}

// ── CHANGE MY PASSWORD — own account only ────────────────────────
// Resetting OTHER admins' passwords was removed: with no way to tell the
// admin their new password, the feature made no sense. Each admin owns
// their password; removing an admin is done via Ban → Delete instead.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reset_password'])){
    csrf_check();
    $aid     = (int)$_POST['admin_id'];
    $current = $_POST['current_password'] ?? '';
    $pw      = $_POST['new_password'] ?? '';

    if($aid !== $me){
        $msg = "You can only change your own password. To remove an admin, ban them first, then delete."; $mtype='err';
    } else {
        $tr = $conn->prepare("SELECT password FROM admins WHERE admin_id=?");
        $tr->bind_param("i", $me);
        $tr->execute();
        $stored = $tr->get_result()->fetch_assoc()['password'] ?? '';

        if(!$current || !password_verify($current, $stored)){
            $msg = "Your current password is incorrect."; $mtype='err';
        } elseif(($pw_err = admin_pw_error($pw)) !== ''){
            $msg = $pw_err; $mtype='err';
        } elseif($current === $pw){
            $msg = "New password must be different from your current password."; $mtype='err';
        } else {
            $hashed = password_hash($pw, PASSWORD_DEFAULT);
            // Also clear any remember-me token so old cookies stop working
            $upd = $conn->prepare("UPDATE admins SET password=?, remember_token=NULL, token_expiry=NULL WHERE admin_id=?");
            $upd->bind_param("si", $hashed, $me);
            $upd->execute();
            header("Location: admin_manage.php?msg=".urlencode("Your password has been changed successfully.")."&mtype=ok"); exit;
        }
    }
}

// ── BAN / UNBAN ADMIN ────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_ban_admin'])){
    csrf_check();
    $aid = (int)$_POST['admin_id'];

    $tr = $conn->prepare("SELECT username, role, is_banned FROM admins WHERE admin_id=?");
    $tr->bind_param("i", $aid);
    $tr->execute();
    $target = $tr->get_result()->fetch_assoc();

    if(!$target){
        $msg = "Admin not found."; $mtype='err';
    } elseif($aid === $me){
        $msg = "You cannot ban your own account."; $mtype='err';
    } elseif($target['role'] === 'superadmin'){
        $msg = "The superadmin account cannot be banned."; $mtype='err';
    } else {
        $new = $target['is_banned'] ? 0 : 1;
        // Banning also clears the remember-me token so cookie auto-login dies too;
        // their active session dies on the next click (auth_check re-verifies)
        $upd = $conn->prepare("UPDATE admins SET is_banned=?, remember_token=NULL, token_expiry=NULL WHERE admin_id=?");
        $upd->bind_param("ii", $new, $aid);
        $upd->execute();
        $word = $new ? 'banned — they will see a notice when they try to log in' : 'unbanned — they can log in again';
        header("Location: admin_manage.php?msg=".urlencode("Admin \"{$target['username']}\" {$word}.")."&mtype=ok"); exit;
    }
}

// ── DELETE ADMIN — only allowed after the admin has been banned ──
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_admin'])){
    csrf_check();
    $aid = (int)$_POST['admin_id'];

    $tr = $conn->prepare("SELECT username, role, is_banned FROM admins WHERE admin_id=?");
    $tr->bind_param("i", $aid);
    $tr->execute();
    $target = $tr->get_result()->fetch_assoc();

    if(!$target){
        $msg = "Admin not found."; $mtype='err';
    } elseif($aid === $me){
        $msg = "You cannot delete your own account."; $mtype='err';
    } elseif($target['role'] === 'superadmin'){
        $msg = "The superadmin account cannot be deleted."; $mtype='err';
    } elseif(empty($target['is_banned'])){
        $msg = "Ban \"{$target['username']}\" first — delete is only available for banned admins."; $mtype='err';
    } else {
        $del = $conn->prepare("DELETE FROM admins WHERE admin_id=?");
        $del->bind_param("i", $aid);
        $del->execute();
        header("Location: admin_manage.php?msg=".urlencode("Admin \"{$target['username']}\" deleted permanently.")."&mtype=ok"); exit;
    }
}

$msg = $msg ?: ($_GET['msg'] ?? '');
// Whitelist — GET may override the 'ok' default, but only to 'err'
if($mtype !== 'err') $mtype = (($_GET['mtype'] ?? '') === 'err') ? 'err' : 'ok';

$admins = $conn->query("SELECT admin_id, username, role, is_banned FROM admins ORDER BY role='superadmin' DESC, admin_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Admins | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
<style>
.adm-row{
    display:flex;align-items:center;gap:14px;
    padding:14px 18px;background:var(--card);border:1px solid var(--border);
    border-radius:10px;margin-bottom:10px;flex-wrap:wrap;
}
.adm-avatar{
    width:40px;height:40px;border-radius:50%;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-family:'Oswald',sans-serif;font-weight:700;color:#fff;
}
.role-badge{
    font-size:.6rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;
    padding:3px 10px;border-radius:100px;
}
.role-super{ background:rgba(202,138,4,.15);color:#ca8a04;border:1px solid rgba(202,138,4,.35); }
.role-admin{ background:rgba(100,149,237,.12);color:#8ab4f8;border:1px solid rgba(100,149,237,.3); }
.role-banned{ background:rgba(214,64,64,.12);color:var(--danger);border:1px solid rgba(214,64,64,.35); }
.adm-row.banned{ border-color:rgba(214,64,64,.35);background:rgba(214,64,64,.03); }
.adm-form-inline{ display:none;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px;width:100%; }
.adm-form-inline.open{ display:flex; }
.adm-input{
    background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);
    padding:8px 12px;color:var(--white);font-size:.82rem;outline:none;
}
.adm-input:focus{ border-color:var(--accent); }
.pw-hint{ font-size:.7rem;color:var(--muted);width:100%; }

/* ── Design-styled confirm modal ── */
@keyframes apxModalIn {
    from { opacity:0; transform:translateY(16px) scale(.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
.apx-confirm-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;
    display:none;align-items:center;justify-content:center;padding:20px;
}
.apx-confirm-overlay.show{ display:flex; }
.apx-confirm-box{
    background:var(--card);border:1px solid var(--border);
    border-radius:14px;max-width:420px;width:100%;padding:30px;text-align:center;
    box-shadow:0 24px 60px rgba(0,0,0,.45);
    animation:apxModalIn .25s cubic-bezier(.22,1,.36,1);
}
.apx-confirm-icon{
    width:60px;height:60px;border-radius:50%;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;font-size:1.5rem;
}
.apx-confirm-icon.warn  { background:rgba(202,138,4,.12);border:1.5px solid rgba(202,138,4,.35);color:#ca8a04; }
.apx-confirm-icon.ok    { background:rgba(42,155,90,.12);border:1.5px solid rgba(42,155,90,.35);color:#2a9b5a; }
.apx-confirm-icon.danger{ background:rgba(214,64,64,.12);border:1.5px solid rgba(214,64,64,.35);color:var(--danger); }
.apx-confirm-title{
    font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:2px;
    text-transform:uppercase;color:var(--white);margin-bottom:10px;
}
.apx-confirm-text{ font-size:.875rem;color:var(--muted);line-height:1.8;margin-bottom:22px; }
.apx-confirm-text strong{ color:var(--white); }
.apx-confirm-btns{ display:flex;gap:10px;justify-content:center; }
.btn-confirm-warn  { background:#ca8a04!important;border-color:#ca8a04!important;color:#fff!important; }
.btn-confirm-ok    { background:#2a9b5a!important;border-color:#2a9b5a!important;color:#fff!important; }
.btn-confirm-danger{ background:var(--danger)!important;border-color:var(--danger)!important;color:#fff!important; }
</style>
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>MANAGE ADMINS</h1>
      <span style="color:var(--muted);font-size:.875rem;"><?=$admins->num_rows?> account<?=$admins->num_rows!=1?'s':''?></span>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-<?=$mtype?>" style="margin-bottom:16px;"><?=e($msg)?></div>
      <?php endif; ?>

      <!-- ── Role explanation ── -->
      <div class="card" style="padding:16px 20px;margin-bottom:20px;font-size:.82rem;color:var(--muted);line-height:1.8;">
        <strong style="color:#ca8a04;">Superadmin</strong> (you) — full access <em>plus</em> managing admin accounts and banning customers.<br>
        <strong style="color:#8ab4f8;">Admin</strong> — daily operations: products, orders, messages, design requests, banners.<br>
        <strong style="color:var(--danger);">Removing an admin is a 2-step safety process:</strong>
        ban first (they are locked out instantly and see a notice at login) — the <strong>Delete</strong> button
        only appears for banned admins. Unban anytime to restore access.
      </div>

      <!-- ── Add admin ── -->
      <div class="card" style="padding:22px 24px;margin-bottom:24px;">
        <div style="font-family:'Oswald',sans-serif;font-size:.9rem;letter-spacing:2px;color:var(--white);margin-bottom:16px;">+ ADD NEW ADMIN</div>
        <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
          <?=csrf_field()?>
          <input type="hidden" name="add_admin" value="1">
          <input type="text" name="username" class="adm-input" placeholder="Username (3-30 chars)"
                 value="<?=e($_POST['username'] ?? '')?>" required style="min-width:180px;">
          <input type="password" name="password" class="adm-input" placeholder="Password" required style="min-width:180px;">
          <input type="password" name="password2" class="adm-input" placeholder="Confirm password" required style="min-width:180px;">
          <button type="submit" class="btn btn-primary btn-sm">Create Admin</button>
          <div class="pw-hint">Password: 8-16 characters with uppercase, lowercase, number and special character. New accounts are regular admins — there is only one superadmin.</div>
        </form>
      </div>

      <!-- ── Admin list ── -->
      <?php while($a = $admins->fetch_assoc()):
        $is_super  = $a['role'] === 'superadmin';
        $is_me     = (int)$a['admin_id'] === $me;
        $is_banned = !empty($a['is_banned']);
      ?>
      <div class="adm-row <?=$is_banned?'banned':''?>">
        <div class="adm-avatar" style="background:<?=$is_banned?'#888':($is_super?'#ca8a04':'var(--accent)')?>;">
          <?=strtoupper(substr($a['username'],0,1))?>
        </div>
        <div style="flex:1;min-width:140px;">
          <div style="font-weight:700;color:var(--white);font-size:.9rem;">
            <?=e($a['username'])?>
            <?php if($is_me): ?><span style="font-size:.7rem;color:var(--muted);font-weight:400;">(you)</span><?php endif; ?>
          </div>
          <div style="font-size:.72rem;color:var(--muted);">ID #<?=(int)$a['admin_id']?></div>
        </div>
        <span class="role-badge <?=$is_super?'role-super':'role-admin'?>">
          <?=$is_super?'★ Superadmin':'Admin'?>
        </span>
        <?php if($is_banned): ?>
        <span class="role-badge role-banned">⛔ Banned</span>
        <?php endif; ?>

        <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;">
          <?php if($is_me): ?>
          <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReset(<?=(int)$a['admin_id']?>)">
            Change My Password
          </button>
          <?php endif; ?>

          <?php if(!$is_super && !$is_me): ?>
          <!-- Ban / Unban -->
          <form method="POST" style="margin:0;" id="ban-form-<?=(int)$a['admin_id']?>">
            <?=csrf_field()?>
            <input type="hidden" name="toggle_ban_admin" value="1">
            <input type="hidden" name="admin_id" value="<?=(int)$a['admin_id']?>">
            <button type="button" class="btn btn-sm"
                    onclick="openConfirm('ban-form-<?=(int)$a['admin_id']?>', '<?=$is_banned?'unban':'ban'?>', '<?=e(addslashes($a['username']))?>')"
                    style="<?=$is_banned
                        ? 'background:rgba(42,155,90,.1);border:1px solid rgba(42,155,90,.3);color:#2a9b5a;'
                        : 'background:rgba(202,138,4,.1);border:1px solid rgba(202,138,4,.3);color:#ca8a04;'?>">
              <?=$is_banned?'Unban':'Ban'?>
            </button>
          </form>

          <?php if($is_banned): ?>
          <!-- Delete — only appears once the admin is banned -->
          <form method="POST" style="margin:0;" id="del-form-<?=(int)$a['admin_id']?>">
            <?=csrf_field()?>
            <input type="hidden" name="delete_admin" value="1">
            <input type="hidden" name="admin_id" value="<?=(int)$a['admin_id']?>">
            <button type="button" class="btn btn-sm"
                    onclick="openConfirm('del-form-<?=(int)$a['admin_id']?>', 'delete', '<?=e(addslashes($a['username']))?>')"
                    style="background:rgba(214,64,64,.1);border:1px solid rgba(214,64,64,.25);color:var(--danger);">
              Delete
            </button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if($is_me): ?>
        <!-- Inline change-my-password form -->
        <form method="POST" class="adm-form-inline" id="reset-<?=(int)$a['admin_id']?>">
          <?=csrf_field()?>
          <input type="hidden" name="reset_password" value="1">
          <input type="hidden" name="admin_id" value="<?=(int)$a['admin_id']?>">
          <input type="password" name="current_password" class="adm-input" placeholder="Current password" required style="min-width:180px;">
          <input type="password" name="new_password" class="adm-input" placeholder="New password" required style="min-width:180px;">
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReset(<?=(int)$a['admin_id']?>)">Cancel</button>
          <div class="pw-hint">8-16 characters with uppercase, lowercase, number and special character.</div>
        </form>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>

    </div>
  </main>
</div>

<!-- ── Confirm modal (design style) ── -->
<div class="apx-confirm-overlay" id="confirmModal">
  <div class="apx-confirm-box">
    <div class="apx-confirm-icon warn" id="confirmIcon">⚠</div>
    <div class="apx-confirm-title" id="confirmTitle">ARE YOU SURE?</div>
    <div class="apx-confirm-text" id="confirmText"></div>
    <div class="apx-confirm-btns">
      <button type="button" class="btn btn-sm" id="confirmYesBtn" onclick="confirmYes()">Confirm</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeConfirm()">Cancel</button>
    </div>
  </div>
</div>

<script>
function toggleReset(id){
    document.getElementById('reset-' + id).classList.toggle('open');
}

// ── Design-styled confirmation for Ban / Unban / Delete ──
let confirmFormId = null;

const confirmConfigs = {
    ban: {
        icon:'⚠', iconClass:'warn', btnClass:'btn-confirm-warn', btnLabel:'Yes, Ban',
        title:'BAN THIS ADMIN?',
        text: n => '<strong>' + n + '</strong> will be logged out immediately and will see a ban notice when they try to log in.<br>You can unban them anytime to restore access.'
    },
    unban: {
        icon:'✓', iconClass:'ok', btnClass:'btn-confirm-ok', btnLabel:'Yes, Unban',
        title:'UNBAN THIS ADMIN?',
        text: n => '<strong>' + n + '</strong> will be able to log in and access the admin panel again.'
    },
    delete: {
        icon:'🗑', iconClass:'danger', btnClass:'btn-confirm-danger', btnLabel:'Yes, Delete',
        title:'DELETE THIS ADMIN?',
        text: n => 'Permanently delete banned admin <strong>' + n + '</strong>?<br>This action <strong>cannot be undone</strong>.'
    }
};

function openConfirm(formId, mode, name){
    const cfg = confirmConfigs[mode];
    if(!cfg) return;
    confirmFormId = formId;
    const icon = document.getElementById('confirmIcon');
    icon.textContent = cfg.icon;
    icon.className   = 'apx-confirm-icon ' + cfg.iconClass;
    document.getElementById('confirmTitle').textContent = cfg.title;
    document.getElementById('confirmText').innerHTML    = cfg.text(name);
    const yes = document.getElementById('confirmYesBtn');
    yes.textContent = cfg.btnLabel;
    yes.className   = 'btn btn-sm ' + cfg.btnClass;
    document.getElementById('confirmModal').classList.add('show');
}
function confirmYes(){
    if(confirmFormId) document.getElementById(confirmFormId).submit();
}
function closeConfirm(){
    confirmFormId = null;
    document.getElementById('confirmModal').classList.remove('show');
}
document.getElementById('confirmModal').addEventListener('click', function(e){
    if(e.target === this) closeConfirm();
});
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeConfirm();
});
</script>
</body>
</html>
