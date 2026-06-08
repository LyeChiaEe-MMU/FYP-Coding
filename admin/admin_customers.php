<?php
require_once 'auth_check.php';

$msg = ''; $mtype = 'ok';

// ── BAN / UNBAN USER ──────────────────────────────────────────────
if(isset($_POST['toggle_ban'])){
    csrf_check();
    $uid     = (int)$_POST['user_id'];
    $current = (int)$_POST['current_ban'];
    $new     = $current ? 0 : 1;
    $conn->query("UPDATE users SET is_banned=$new WHERE user_id=$uid");
    $label = $new ? 'banned' : 'unbanned';
    header("Location: admin_customers.php?msg=Customer+$label+successfully.&mtype=ok"); exit;
}

$msg   = $msg   ?: ($_GET['msg']   ?? '');
$mtype = $mtype ?: ($_GET['mtype'] ?? 'ok');

$customers = $conn->query("
    SELECT u.*,
           COUNT(o.order_id) AS order_count,
           COALESCE(SUM(o.total_amount),0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id=u.user_id
    GROUP BY u.user_id
    ORDER BY u.user_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Customers | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=4">
<style>
.ban-modal-bg{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);
    z-index:9999;align-items:center;justify-content:center;
}
.ban-modal-bg.open{display:flex;}
.ban-modal{
    background:var(--navy2);border:1px solid var(--border);border-radius:12px;
    padding:32px;max-width:400px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.5);
}
.ban-modal h3{font-family:'Oswald',sans-serif;font-size:1.2rem;letter-spacing:2px;color:var(--white);margin-bottom:8px;}
.ban-modal p{color:var(--muted);font-size:.875rem;margin-bottom:20px;line-height:1.6;}
.ban-modal-btns{display:flex;gap:10px;}
.ban-modal-btns button{flex:1;}
.badge-banned{display:inline-block;padding:2px 8px;border-radius:100px;font-size:.65rem;font-weight:700;letter-spacing:1px;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3);}
</style>
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>CUSTOMERS</h1>
      <span style="color:var(--muted);font-size:.875rem;"><?=$customers->num_rows?> registered</span>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-<?=$mtype?>" style="margin-bottom:18px;"><?=e($msg)?></div>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <div class="admin-table-head"><h3>ALL CUSTOMERS</h3></div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($c=$customers->fetch_assoc()):
              $banned = !empty($c['is_banned']);
            ?>
            <tr style="<?=$banned?'opacity:.6;':''?>">
              <td style="color:var(--muted);">#<?=(int)$c['user_id']?></td>
              <td style="font-weight:600;color:var(--white);">
                <?=e($c['name'])?>
                <?php if($banned): ?> <span class="badge-banned">BANNED</span><?php endif; ?>
              </td>
              <td style="color:var(--muted);font-size:.82rem;"><?=e($c['email'])?></td>
              <td style="color:var(--muted);"><?=e($c['phone']??'—')?></td>
              <td>
                <span style="font-family:'Oswald',sans-serif;font-size:1.1rem;color:<?=$c['order_count']>0?'var(--accent)':'var(--muted)'?>;">
                  <?=(int)$c['order_count']?>
                </span>
              </td>
              <td style="font-weight:600;color:var(--white);">RM <?=number_format($c['total_spent'],2)?></td>
              <td style="color:var(--muted);font-size:.8rem;"><?=date('d M Y',strtotime($c['created_at']??'now'))?></td>
              <td>
                <button type="button"
                        class="btn btn-sm <?=$banned?'btn-secondary':'btn-danger'?>"
                        onclick="openBanModal(<?=(int)$c['user_id']?>, '<?=e(addslashes($c['name']))?>', <?=$banned?1:0?>)">
                  <?=$banned?'Unban':'Ban'?>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>

<!-- ── Ban/Unban Confirmation Modal ── -->
<div class="ban-modal-bg" id="banModal">
  <div class="ban-modal">
    <div style="font-size:1.8rem;margin-bottom:12px;" id="banModalIcon">🚫</div>
    <h3 id="banModalTitle">BAN CUSTOMER</h3>
    <p id="banModalDesc"></p>
    <form method="POST" id="banForm">
      <?=csrf_field()?>
      <input type="hidden" name="toggle_ban" value="1">
      <input type="hidden" name="user_id" id="banUserId">
      <input type="hidden" name="current_ban" id="banCurrentVal">
      <div class="ban-modal-btns">
        <button type="submit" class="btn btn-danger" id="banConfirmBtn">CONFIRM BAN</button>
        <button type="button" class="btn btn-secondary" onclick="closeBanModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBanModal(id, name, isBanned){
    document.getElementById('banUserId').value    = id;
    document.getElementById('banCurrentVal').value = isBanned;
    if(isBanned){
        document.getElementById('banModalIcon').textContent  = '✅';
        document.getElementById('banModalTitle').textContent = 'UNBAN CUSTOMER';
        document.getElementById('banModalDesc').innerHTML    = 'Restore access for <strong style="color:#fff;">'+name+'</strong>? They will be able to log in again.';
        document.getElementById('banConfirmBtn').textContent = 'CONFIRM UNBAN';
        document.getElementById('banConfirmBtn').className   = 'btn btn-primary';
    } else {
        document.getElementById('banModalIcon').textContent  = '🚫';
        document.getElementById('banModalTitle').textContent = 'BAN CUSTOMER';
        document.getElementById('banModalDesc').innerHTML    = 'Ban <strong style="color:#fff;">'+name+'</strong>? They will not be able to log in until unbanned.';
        document.getElementById('banConfirmBtn').textContent = 'CONFIRM BAN';
        document.getElementById('banConfirmBtn').className   = 'btn btn-danger';
    }
    document.getElementById('banModal').classList.add('open');
}
function closeBanModal(){
    document.getElementById('banModal').classList.remove('open');
}
document.getElementById('banModal').addEventListener('click', function(e){
    if(e.target === this) closeBanModal();
});
</script>
</body>
</html>
