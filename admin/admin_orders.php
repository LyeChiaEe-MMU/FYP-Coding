<?php
session_start();
require '../db.php';
if(!is_admin()){ header("Location: admin_login.php"); exit; }

$msg = '';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])){
    $oid        = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $allowed    = ['Processing','Shipped','Completed','Cancelled'];
    if(in_array($new_status,$allowed)){
        // Get old status for history
        $old = $conn->query("SELECT status FROM orders WHERE order_id=$oid")->fetch_assoc()['status'];
        $conn->query("UPDATE orders SET status='$new_status' WHERE order_id=$oid");
        // Log
        $h = $conn->prepare("INSERT INTO order_status_history (order_id,status) VALUES (?,?)");
        $h->bind_param("is",$oid,$new_status);
        $h->execute();
        $msg = "Order #".str_pad($oid,6,'0',STR_PAD_LEFT)." updated to <strong>$new_status</strong>.";
    }
    header("Location: admin_orders.php?msg=".urlencode($msg)); exit;
}

$msg = $_GET['msg'] ?? '';

// Filter
$filter = $_GET['filter'] ?? '';
$where  = $filter ? "WHERE o.status='".$conn->real_escape_string($filter)."'" : '';

$orders = $conn->query("
    SELECT o.*, u.name AS customer_name, u.email,
           COUNT(oi.id) AS item_count
    FROM orders o
    JOIN users u ON o.user_id=u.user_id
    LEFT JOIN order_items oi ON oi.order_id=o.order_id
    $where
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Orders | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>ORDER MANAGEMENT</h1>
      <span style="color:var(--muted);font-size:.875rem;"><?=$orders->num_rows?> orders</span>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-ok"><?=$msg?></div>
      <?php endif; ?>

      <!-- Status filter -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <?php foreach([''=> 'All','Processing'=>'Processing','Shipped'=>'Shipped','Completed'=>'Completed','Cancelled'=>'Cancelled'] as $val=>$label): ?>
        <a href="admin_orders.php?filter=<?=urlencode($val)?>"
           style="padding:7px 18px;border-radius:100px;border:1px solid <?=$filter===$val?'var(--accent)':'var(--border)'?>;color:<?=$filter===$val?'var(--navy)':'var(--muted)'?>;background:<?=$filter===$val?'var(--accent)':'transparent'?>;font-size:.8rem;font-weight:<?=$filter===$val?700:500?>;transition:.2s;">
          <?=e($label)?>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="admin-table-wrap">
        <div class="admin-table-head"><h3>ALL ORDERS</h3></div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Items</th>
              <th>Total</th>
              <th>Status</th>
              <th>Update Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($o=$orders->fetch_assoc()): ?>
            <tr>
              <td style="font-family:'Oswald',sans-serif;color:var(--accent);">#<?=str_pad($o['order_id'],6,'0',STR_PAD_LEFT)?></td>
              <td>
                <div style="font-weight:600;"><?=e($o['customer_name'])?></div>
                <div style="font-size:.75rem;color:var(--muted);"><?=e($o['email'])?></div>
              </td>
              <td style="color:var(--muted);font-size:.82rem;">
                <?=date('d M Y',strtotime($o['order_date']))?><br>
                <span style="font-size:.72rem;"><?=date('h:i A',strtotime($o['order_date']))?></span>
              </td>
              <td style="color:var(--muted);"><?=(int)$o['item_count']?> item<?=$o['item_count']!=1?'s':''?></td>
              <td style="font-weight:600;color:var(--white);">RM <?=number_format($o['total_amount'],2)?></td>
              <td><?=status_badge($o['status'])?></td>
              <td>
                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                  <input type="hidden" name="order_id" value="<?=(int)$o['order_id']?>">
                  <select name="status"
                    style="background:var(--navy2);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:var(--radius);font-size:.82rem;cursor:pointer;">
                    <?php foreach(['Processing','Shipped','Completed','Cancelled'] as $s): ?>
                    <option value="<?=$s?>" <?=$o['status']===$s?'selected':''?>><?=$s?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="update_status" class="btn btn-primary btn-sm">Save</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
