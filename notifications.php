<?php
session_start();
require 'db.php';
if(!is_logged()){ header("Location: login.php"); exit; }

$uid = (int)$_SESSION['user_id'];

// Fetch all notifications BEFORE marking as read so is_read reflects original state
$notifs = $conn->query("
    SELECT * FROM notifications
    WHERE user_id = $uid
    ORDER BY created_at DESC
    LIMIT 80
");

// Count unread BEFORE marking as read (so we can show the "you have X new" message)
$unread_before = 0;
$ucnt = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$uid AND is_read=0");
if($ucnt) $unread_before = (int)$ucnt->fetch_assoc()['c'];

// Mark all as read
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid AND is_read=0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notifications | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.notif-card {
    background:#FFFFFF;border:1px solid rgba(150,100,75,.15);border-radius:12px;
    padding:20px 22px;margin-bottom:14px;display:flex;gap:16px;align-items:flex-start;
    transition:.2s;position:relative;
}
.notif-card.unread {
    border-left:3px solid var(--accent);
    background:rgba(200,84,60,.04);
}
.notif-icon {
    width:44px;height:44px;border-radius:10px;display:flex;align-items:center;
    justify-content:center;font-size:1.1rem;flex-shrink:0;
}
.notif-icon.refund  { background:rgba(42,155,90,.1); color:#2a9b5a; border:1px solid rgba(42,155,90,.2); }
.notif-icon.info    { background:rgba(200,84,60,.08); color:var(--accent); border:1px solid rgba(200,84,60,.15); }
.notif-icon.warning { background:rgba(234,179,8,.1); color:#ca8a04; border:1px solid rgba(234,179,8,.2); }
.notif-title {
    font-family:'Oswald',sans-serif;font-size:.95rem;letter-spacing:1px;
    color:var(--white);margin-bottom:5px;
}
.notif-msg  { font-size:.865rem;color:var(--muted);line-height:1.7; }
.notif-time { font-size:.72rem;color:#9A8880;margin-top:8px;letter-spacing:.5px; }
.notif-badge-new {
    position:absolute;top:16px;right:16px;
    background:var(--accent);color:#fff;font-size:.58rem;font-weight:700;
    letter-spacing:1.5px;text-transform:uppercase;padding:2px 8px;border-radius:100px;
}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Notifications</span></div>
    <h1>MY <span style="color:var(--accent)">NOTIFICATIONS</span></h1>
  </div>
</div>

<section class="section" style="padding-top:36px;padding-bottom:60px;">
<div class="wrap" style="max-width:760px;">

  <?php if($unread_before > 0): ?>
  <div class="flash flash-ok" style="margin-bottom:20px;">
    <i class="fa-solid fa-bell"></i>
    You had <strong><?=$unread_before?></strong> new notification<?=$unread_before>1?'s':''?>. All marked as read.
  </div>
  <?php endif; ?>

  <!-- Header row -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
      <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:var(--accent);margin-bottom:4px;">Your Account</p>
      <h2 style="font-family:'Oswald',sans-serif;font-size:1.4rem;letter-spacing:2px;color:var(--white);margin:0;">
        ALL NOTIFICATIONS
        <?php if($notifs && $notifs->num_rows > 0): ?>
        <span style="font-size:.8rem;color:var(--muted);font-family:'Inter',sans-serif;font-weight:400;letter-spacing:0;margin-left:8px;"><?=$notifs->num_rows?> total</span>
        <?php endif; ?>
      </h2>
    </div>
    <a href="profile.php?tab=vouchers" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-ticket"></i> My Vouchers
    </a>
  </div>

  <?php if($notifs && $notifs->num_rows > 0):
    // Re-fetch since we already counted; but notifs result is still valid
    $notifs->data_seek(0);
    while($n = $notifs->fetch_assoc()):
      $icon_class = match($n['type']) {
          'refund'  => 'refund',
          'warning' => 'warning',
          default   => 'info',
      };
      $icon_fa = match($n['type']) {
          'refund'  => 'fa-solid fa-rotate-left',
          'warning' => 'fa-solid fa-triangle-exclamation',
          default   => 'fa-solid fa-bell',
      };
      $ts = strtotime($n['created_at']);
  ?>
  <div class="notif-card<?=(!$n['is_read'] ? ' unread' : '')?>">
    <div class="notif-icon <?=$icon_class?>">
      <i class="<?=$icon_fa?>"></i>
    </div>
    <div style="flex:1;min-width:0;">
      <div class="notif-title"><?=e($n['title'])?></div>
      <div class="notif-msg"><?=e($n['message'])?></div>
      <div class="notif-time">
        <i class="fa-regular fa-clock" style="margin-right:4px;"></i>
        <?php
        $diff = time() - $ts;
        if     ($diff < 60)         echo 'Just now';
        elseif ($diff < 3600)       echo floor($diff/60).' min ago';
        elseif ($diff < 86400)      echo floor($diff/3600).' hour'.($diff<7200?'':'s').' ago';
        elseif ($diff < 604800)     echo floor($diff/86400).' day'.($diff<172800?'':'s').' ago';
        else                        echo date('d M Y, h:i A', $ts);
        ?>
      </div>
    </div>
  </div>
  <?php endwhile; else: ?>
  <!-- Empty state -->
  <div style="text-align:center;padding:72px 24px;">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(200,84,60,.08);border:1px solid rgba(200,84,60,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:var(--accent);">
      <i class="fa-solid fa-bell-slash"></i>
    </div>
    <h3 style="font-family:'Oswald',sans-serif;font-size:1.2rem;letter-spacing:2px;color:var(--white);margin-bottom:10px;">NO NOTIFICATIONS YET</h3>
    <p style="color:var(--muted);font-size:.9rem;max-width:320px;margin:0 auto 28px;">
      You're all caught up! Notifications about your orders, refunds, and account activity will appear here.
    </p>
    <a href="products.php" class="btn btn-primary">Browse Shoes →</a>
  </div>
  <?php endif; ?>

</div>
</section>

<?php include 'includes/footer.php'; ?>
</body>
</html>
