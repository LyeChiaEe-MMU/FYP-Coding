<?php
require_once 'auth_check.php';

$msg = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $rid    = (int)$_POST['request_id'];
    $status = $_POST['status'];
    $note   = $conn->real_escape_string(trim($_POST['admin_note'] ?? ''));
    $allowed = ['Pending','In Review','Approved','Rejected'];

    if (in_array($status, $allowed)) {
        $conn->query("UPDATE design_requests SET status='$status', admin_note='$note' WHERE request_id=$rid");
        $msg = "Request #$rid updated to <strong>$status</strong>.";
    }
}

// Filter
$filter = $_GET['filter'] ?? '';
$where  = $filter ? "WHERE dr.status='" . $conn->real_escape_string($filter) . "'" : '';

$reqs = $conn->query("
    SELECT dr.*, u.name AS customer_name, u.email
    FROM design_requests dr
    JOIN users u ON dr.user_id = u.user_id
    $where
    ORDER BY dr.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Design Requests | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>DESIGN REQUESTS</h1>
      <span style="color:var(--muted);font-size:.875rem;"><?=$reqs->num_rows?> submission<?=$reqs->num_rows!=1?'s':''?></span>
    </div>
    <div class="admin-content">

      <?php if ($msg): ?>
      <div class="flash flash-ok"><?=$msg?></div>
      <?php endif; ?>

      <!-- Status filters -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
        <?php foreach ([''=> 'All', 'Pending'=>'Pending', 'In Review'=>'In Review', 'Approved'=>'Approved', 'Rejected'=>'Rejected'] as $val=>$label): ?>
        <a href="admin_requests.php?filter=<?=urlencode($val)?>"
           style="padding:7px 18px;border-radius:100px;font-size:.8rem;font-weight:<?=$filter===$val?'700':'500'?>;
                  border:1px solid <?=$filter===$val?'var(--accent)':'var(--border)'?>;
                  color:<?=$filter===$val?'var(--navy)':'var(--muted)'?>;
                  background:<?=$filter===$val?'var(--accent)':'transparent'?>;
                  text-decoration:none;transition:.2s;">
          <?=e($label)?>
        </a>
        <?php endforeach; ?>
      </div>

      <?php if ($reqs->num_rows === 0): ?>
      <div style="text-align:center;padding:60px;color:var(--muted);">
        <div style="font-size:2.5rem;margin-bottom:14px;">📭</div>
        <p>No design requests<?=$filter?" with status \"$filter\"":''?> yet.</p>
      </div>

      <?php else: while ($r = $reqs->fetch_assoc()):
        $sc = match($r['status']) {
          'Approved'  => 'st-completed',
          'Rejected'  => 'st-cancelled',
          'In Review' => 'st-shipped',
          default     => 'st-processing',
        };
      ?>
      <div class="card" style="margin-bottom:20px;overflow:hidden;">
        <!-- Header -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border);gap:16px;flex-wrap:wrap;">
          <div>
            <div style="font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:1px;color:var(--white);">
              <?=e($r['shoe_name'])?>
              <span style="font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-left:10px;"><?=e($r['category'])?></span>
            </div>
            <div style="font-size:.78rem;color:var(--muted);margin-top:4px;">
              By <strong style="color:var(--text);"><?=e($r['customer_name'])?></strong>
              (<?=e($r['email'])?>) &nbsp;·&nbsp;
              <?=date('d M Y, h:i A', strtotime($r['created_at']))?>
            </div>
          </div>
          <span class="status-badge <?=$sc?>"><?=e($r['status'])?></span>
        </div>

        <!-- Content -->
        <div style="padding:20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <div>
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Colour Preference</div>
            <div style="font-size:.875rem;color:var(--text);margin-bottom:16px;"><?=e($r['color_pref'])?></div>

            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Description</div>
            <div style="font-size:.875rem;color:var(--text);line-height:1.7;margin-bottom:16px;"><?=e($r['description'])?></div>

            <?php if ($r['specifications']): ?>
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Specifications</div>
            <div style="font-size:.875rem;color:var(--text);line-height:1.7;"><?=e($r['specifications'])?></div>
            <?php endif; ?>
          </div>

          <div>
            <?php if ($r['ref_image']): ?>
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;">Reference Image</div>
            <img src="../<?=e($r['ref_image'])?>" alt="Reference"
                 style="max-width:100%;border-radius:8px;border:1px solid var(--border);object-fit:cover;max-height:200px;margin-bottom:16px;">
            <?php endif; ?>

            <!-- Update form -->
            <form method="POST" style="background:rgba(17,34,64,.5);border:1px solid var(--border);border-radius:8px;padding:18px;">
              <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>">
              <div style="font-size:.72px;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;font-size:.7rem;">UPDATE REQUEST</div>

              <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.72rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Status</label>
                <select name="status"
                        style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--white);font-size:.875rem;">
                  <?php foreach(['Pending','In Review','Approved','Rejected'] as $s): ?>
                  <option value="<?=$s?>" <?=$r['status']===$s?'selected':''?>><?=$s?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div style="margin-bottom:14px;">
                <label style="display:block;font-size:.72rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                  Message to Customer <span style="color:var(--muted);text-transform:none;letter-spacing:0;">(optional)</span>
                </label>
                <textarea name="admin_note" rows="3"
                          placeholder="e.g. Great idea! We love the colour concept..."
                          style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--white);font-size:.875rem;resize:vertical;"><?=e($r['admin_note']??'')?></textarea>
              </div>

              <button type="submit" name="update_request" class="btn btn-primary btn-sm btn-full">
                SAVE UPDATE
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endwhile; endif; ?>

    </div>
  </main>
</div>
</body>
</html>
