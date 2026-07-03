<?php
require_once 'auth_check.php';

$msg = ''; $mtype = 'ok';

// ── Helper: verify admin password ─────────────────────────────────
function verify_admin_pw($conn, $admin_id, $pw){
    $a = $conn->query("SELECT password FROM admins WHERE admin_id=".(int)$admin_id)->fetch_assoc();
    return $a && password_verify($pw, $a['password']);
}

// ── Upload helper ─────────────────────────────────────────────────
function upload_banner($file, $prefix, &$error){
    if(!valid_image_upload($file, $error)) return false;
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fname    = $prefix.'_'.time().'.'.$ext;
    $dir      = dirname(__DIR__).'/uploads/banners/';
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    if(!move_uploaded_file($file['tmp_name'], $dir.$fname)){
        $error = "Upload failed — check folder permissions."; return false;
    }
    return 'uploads/banners/'.$fname;
}

// ── Handle hero image save ─────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_hero'])){
    csrf_check();
    if(!verify_admin_pw($conn, $_SESSION['admin_id'], $_POST['admin_pw'] ?? '')){
        $msg = "Incorrect admin password. Hero image not changed."; $mtype='err';
    } elseif(!empty($_FILES['hero_file']['name'])){
        $err = '';
        $path = upload_banner($_FILES['hero_file'], 'hero', $err);
        if($path){
            // Delete old hero image if it's a local file
            $old = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='hero_image'");
            if($old && $ov = $old->fetch_assoc()){
                $old_path = dirname(__DIR__).'/'.$ov['setting_value'];
                if($ov['setting_value'] && file_exists($old_path)) @unlink($old_path);
            }
            $conn->query("INSERT INTO site_settings (setting_key,setting_value) VALUES ('hero_image','".
                $conn->real_escape_string($path)."') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $msg = "Hero image updated successfully.";
        } else {
            $msg = $err; $mtype='err';
        }
    } else {
        $msg = "Please select an image file to upload."; $mtype='err';
    }
    header("Location: admin_banners.php?msg=".urlencode($msg)."&mtype=$mtype"); exit;
}

// ── Handle hero image remove ──────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_hero'])){
    csrf_check();
    if(!verify_admin_pw($conn, $_SESSION['admin_id'], $_POST['admin_pw'] ?? '')){
        $msg = "Incorrect admin password."; $mtype='err';
    } else {
        $old = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='hero_image'");
        if($old && $ov = $old->fetch_assoc()){
            $old_path = dirname(__DIR__).'/'.$ov['setting_value'];
            if($ov['setting_value'] && file_exists($old_path)) @unlink($old_path);
        }
        $conn->query("DELETE FROM site_settings WHERE setting_key='hero_image'");
        $msg = "Hero image removed. Homepage hero will show a plain gradient.";
    }
    header("Location: admin_banners.php?msg=".urlencode($msg)."&mtype=$mtype"); exit;
}

// ── Load current data ─────────────────────────────────────────────
if(!$msg){
    $msg   = $_GET['msg'] ?? '';
    // Whitelist — only 'ok' or 'err' may reach the class attribute
    $mtype = (($_GET['mtype'] ?? '') === 'err') ? 'err' : 'ok';
}

$hero_img = '';
$_hb = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='hero_image'");
if($_hb && $_hbr = $_hb->fetch_assoc()) $hero_img = $_hbr['setting_value'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Banner Management | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
<style>
.banner-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:22px;}
.banner-preview{width:100%;max-height:200px;object-fit:cover;border-radius:8px;border:1px solid var(--border);margin-bottom:14px;display:block;}
.banner-empty{width:100%;height:140px;border-radius:8px;border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.82rem;margin-bottom:14px;background:var(--navy2);}
.pw-gate{background:rgba(200,84,60,.06);border:1px solid rgba(200,84,60,.2);border-radius:8px;padding:14px 16px;margin-top:14px;}
.pw-gate label{font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:6px;}
.pw-gate input[type=password]{width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;outline:none;transition:border-color .2s;box-sizing:border-box;}
.pw-gate input[type=password]:focus{border-color:var(--accent);}
.remove-btn{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:var(--radius);padding:7px 14px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .2s;width:100%;margin-top:8px;}
.remove-btn:hover{background:rgba(239,68,68,.2);}
</style>
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-topbar">
      <h1>BANNER MANAGEMENT</h1>
      <span style="color:var(--muted);font-size:.875rem;">Homepage images</span>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-<?=$mtype?>" style="margin-bottom:20px;"><?=e($msg)?></div>
      <?php endif; ?>

      <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:14px 18px;margin-bottom:24px;font-size:.82rem;color:#f59e0b;">
        <strong>Admin password required</strong> for every change — banners cannot be modified without re-entering your password.
      </div>

      <!-- ── Hero Image ── -->
      <div class="banner-card">
        <h2 style="font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:2px;color:var(--white);margin-bottom:4px;">HERO SECTION IMAGE</h2>
        <p style="font-size:.8rem;color:var(--muted);margin-bottom:16px;">Displayed as the full background of the homepage hero section. Recommended: 1920×1080px or wider.</p>

        <?php if($hero_img): ?>
        <img src="../<?=e($hero_img)?>" class="banner-preview" alt="Hero image">
        <div style="font-size:.75rem;color:var(--muted);margin-bottom:14px;">Current: <code><?=e($hero_img)?></code></div>
        <?php else: ?>
        <div class="banner-empty">No hero image set — homepage shows a plain gradient</div>
        <?php endif; ?>

        <!-- Upload new -->
        <form method="POST" enctype="multipart/form-data">
          <?=csrf_field()?>
          <div class="form-group" style="margin-bottom:12px;">
            <label style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:6px;">Upload New Hero Image</label>
            <input type="file" name="hero_file" accept="image/*" required
                   style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;color:var(--text);width:100%;font-size:.82rem;box-sizing:border-box;">
          </div>
          <div class="pw-gate">
            <label>Admin Password to Confirm Change</label>
            <input type="password" name="admin_pw" placeholder="Enter your admin password" required>
          </div>
          <button type="submit" name="save_hero" class="btn btn-primary btn-sm" style="margin-top:12px;">Upload Hero Image</button>
        </form>

        <?php if($hero_img): ?>
        <form method="POST" onsubmit="return confirm('Remove the hero image? The homepage will show a plain gradient.');">
          <?=csrf_field()?>
          <div class="pw-gate" style="margin-top:14px;">
            <label>Admin Password to Remove</label>
            <input type="password" name="admin_pw" placeholder="Enter your admin password" required>
          </div>
          <button type="submit" name="remove_hero" class="remove-btn">Remove Hero Image</button>
        </form>
        <?php endif; ?>
      </div>


    </div>
  </main>
</div>
</body>
</html>
