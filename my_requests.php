<?php
session_start();
require 'db.php';

if (!is_logged()) { header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

$reqs = $conn->query("
    SELECT * FROM design_requests
    WHERE user_id = $uid
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Design Requests | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>My Design Requests</span></div>
    <h1>MY <span style="color:var(--accent)">REQUESTS</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap" style="max-width:900px;">

  <div style="display:flex;justify-content:flex-end;margin-bottom:24px;">
    <a href="design_request.php" class="btn btn-primary">+ Submit New Design</a>
  </div>

<?php if ($reqs->num_rows === 0): ?>
  <div class="empty-cart" style="padding:80px 0;">
    <div class="ec-icon">✏️</div>
    <h3>No Design Requests Yet</h3>
    <p style="color:var(--muted);margin-bottom:24px;">Have a shoe idea? Submit it and our team will review it!</p>
    <a href="design_request.php" class="btn btn-primary">Submit Your First Design</a>
  </div>

<?php else: while ($r = $reqs->fetch_assoc()):
  // Status colours
  $sc = match($r['status']) {
    'Approved'  => ['bg'=>'rgba(82,196,26,.15)',  'color'=>'#73d13d',  'icon'=>'✅'],
    'Rejected'  => ['bg'=>'rgba(255,77,79,.15)',  'color'=>'#ff7070',  'icon'=>'❌'],
    'In Review' => ['bg'=>'rgba(100,149,237,.15)','color'=>'#8ab4f8',  'icon'=>'🔍'],
    default     => ['bg'=>'rgba(255,200,0,.15)',  'color'=>'#ffc800',  'icon'=>'⏳'],
  };
?>
  <div class="card" style="margin-bottom:18px;overflow:hidden;">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:1px;color:var(--white);">
          <?=e($r['shoe_name'])?>
        </div>
        <div style="font-size:.75rem;color:var(--muted);margin-top:3px;">
          <?=e($r['category'])?> &nbsp;·&nbsp; Submitted <?=date('d M Y', strtotime($r['created_at']))?>
        </div>
      </div>
      <span style="background:<?=$sc['bg']?>;color:<?=$sc['color']?>;padding:6px 16px;border-radius:100px;font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;">
        <?=$sc['icon']?> <?=e($r['status'])?>
      </span>
    </div>

    <!-- Body -->
    <div style="padding:20px 24px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;">
        <div>
          <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Colour Preference</div>
          <div style="font-size:.9rem;color:var(--text);"><?=e($r['color_pref'])?></div>
        </div>
        <?php if ($r['specifications']): ?>
        <div>
          <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Specifications</div>
          <div style="font-size:.9rem;color:var(--text);"><?=e($r['specifications'])?></div>
        </div>
        <?php endif; ?>
      </div>

      <div style="margin-bottom:16px;">
        <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Description</div>
        <div style="font-size:.9rem;color:var(--text);line-height:1.75;"><?=e($r['description'])?></div>
      </div>

      <!-- Reference image -->
      <?php if ($r['ref_image']): ?>
      <div style="margin-bottom:16px;">
        <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;">Reference Image</div>
        <img src="<?=e($r['ref_image'])?>" alt="Reference"
             style="max-width:220px;border-radius:8px;border:1px solid var(--border);object-fit:cover;">
      </div>
      <?php endif; ?>

      <!-- Admin note (only shown if rejected or approved with message) -->
      <?php if ($r['admin_note']): ?>
      <div style="background:rgba(100,255,218,.05);border:1px solid rgba(100,255,218,.2);border-radius:8px;padding:14px 18px;">
        <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:6px;">Message from Apex Team</div>
        <div style="font-size:.875rem;color:var(--text);line-height:1.65;"><?=e($r['admin_note'])?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php endwhile; endif; ?>
</div>
</section>

<?php include 'includes/footer.php'; ?>
