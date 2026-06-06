<?php
require_once 'auth_check.php';

$msg = ''; $mtype = 'ok';

// ── DELETE USER ACCOUNT ──────────────────────────────────────
if(isset($_POST['confirm_delete_user'])){
    csrf_check();
    $uid      = (int)$_POST['user_id'];
    $password = $_POST['admin_password'] ?? '';

    $admin = $conn->query("SELECT password FROM admins WHERE admin_id=".(int)$_SESSION['admin_id'])->fetch_assoc();
    if($admin && password_verify($password, $admin['password'])){
        // Delete in correct order to satisfy foreign key constraints
        $conn->query("DELETE FROM wishlist_notifications WHERE user_id=$uid");
        $conn->query("DELETE FROM wishlists WHERE user_id=$uid");
        $conn->query("DELETE FROM cart_items WHERE user_id=$uid");
        $conn->query("DELETE FROM design_requests WHERE user_id=$uid");
        // Delete order items first, then orders
        $orders = $conn->query("SELECT order_id FROM orders WHERE user_id=$uid");
        if($orders) while($o=$orders->fetch_assoc()){
            $conn->query("DELETE FROM order_items WHERE order_id=".(int)$o['order_id']);
        }
        $conn->query("DELETE FROM orders WHERE user_id=$uid");
        $conn->query("DELETE FROM users WHERE user_id=$uid");
        header("Location: admin_customers.php?msg=Customer+account+deleted+successfully.&mtype=err"); exit;
    } else {
        $msg = "Incorrect password. Account was NOT deleted."; $mtype='err';
    }
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
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Customers | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=4">
<style>
/* Modal */
.del-modal-bg{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);
    z-index:9999;align-items:center;justify-content:center;
}
.del-modal-bg.open{display:flex}
.del-modal{
    background:var(--navy2);border:1px solid var(--border);border-radius:12px;
    padding:32px;max-width:420px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.5);
}
.del-modal h3{
    font-family:'Oswald',sans-serif;font-size:1.2rem;letter-spacing:2px;
    color:var(--white);margin-bottom:8px;
}
.del-modal p{color:var(--muted);font-size:.875rem;margin-bottom:20px;line-height:1.6;}
.del-modal input{
    width:100%;background:var(--navy);border:1px solid var(--border);
    border-radius:var(--radius);padding:11px 14px;color:var(--white);
    font-size:.9rem;margin-bottom:16px;outline:none;transition:border-color .2s;
    font-family:'Inter',sans-serif;
}
.del-modal input:focus{border-color:var(--danger);}
.del-modal-btns{display:flex;gap:10px;}
.del-modal-btns button{flex:1;}
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
            <?php while($c=$customers->fetch_assoc()): ?>
            <tr>
              <td style="color:var(--muted);">#<?=(int)$c['user_id']?></td>
              <td style="font-weight:600;color:var(--white);"><?=e($c['name'])?></td>
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
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="openDeleteModal(<?=(int)$c['user_id']?>, '<?=e(addslashes($c['name']))?>', '<?=e(addslashes($c['email']))?>', <?=(int)$c['order_count']?>)">
                  Delete
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

<!-- ── Delete Confirmation Modal ── -->
<div class="del-modal-bg" id="deleteUserModal">
  <div class="del-modal">
    <div style="font-size:1.8rem;margin-bottom:12px;">🗑️</div>
    <h3>DELETE CUSTOMER ACCOUNT</h3>
    <p>
      You are about to permanently delete:<br>
      <strong id="delUserName" style="color:var(--white);"></strong><br>
      <span id="delUserEmail" style="font-size:.8rem;"></span>
    </p>
    <div id="delOrderWarning" style="display:none;background:rgba(214,64,64,.08);border:1px solid rgba(214,64,64,.25);border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:.82rem;color:var(--danger);">
      ⚠️ This customer has existing orders. All their order history will also be deleted.
    </div>
    <p>Enter your admin password to confirm:</p>
    <form method="POST" id="deleteUserForm">
      <?=csrf_field()?>
      <input type="hidden" name="confirm_delete_user" value="1">
      <input type="hidden" name="user_id" id="delUserId">
      <input type="password" name="admin_password" id="delUserPassword"
             placeholder="Enter your admin password" autocomplete="current-password" required>
      <div class="del-modal-btns">
        <button type="submit" class="btn btn-danger">CONFIRM DELETE</button>
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
    <div style="font-size:.72rem;color:var(--muted);margin-top:12px;text-align:center;">
      Logged in as: <strong style="color:var(--white);"><?=e($_SESSION['admin_username']??'Admin')?></strong>
    </div>
  </div>
</div>

<script>
function openDeleteModal(id, name, email, orderCount){
    document.getElementById('delUserId').value      = id;
    document.getElementById('delUserName').textContent  = name;
    document.getElementById('delUserEmail').textContent = email;
    document.getElementById('delUserPassword').value    = '';
    document.getElementById('delOrderWarning').style.display = orderCount > 0 ? 'block' : 'none';
    document.getElementById('deleteUserModal').classList.add('open');
    setTimeout(()=>document.getElementById('delUserPassword').focus(), 100);
}
function closeDeleteModal(){
    document.getElementById('deleteUserModal').classList.remove('open');
}
document.getElementById('deleteUserModal').addEventListener('click', function(e){
    if(e.target === this) closeDeleteModal();
});
</script>
</body>
</html>
