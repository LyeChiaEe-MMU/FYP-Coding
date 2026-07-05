<?php
// Use the new auth check instead of old method
require_once 'auth_check.php';

$msg = '';

// Section for updating the order status (with customer notification)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])){
    csrf_check();
    $oid        = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $allowed    = ['Delivered','Cancelled']; // Completed is set by customer only
    $order_num  = '#' . str_pad($oid, 6, '0', STR_PAD_LEFT);

    // Load the order first — a status change is only allowed while Processing.
    // Delivered / Cancelled / Completed are FINAL (also prevents double refunds)
    $ow = $conn->prepare("SELECT user_id, total_amount, COALESCE(discount_amount,0) AS discount_amount, status FROM orders WHERE order_id=?");
    $ow->bind_param("i", $oid);
    $ow->execute();
    $row_o = $ow->get_result()->fetch_assoc();

    if(!$row_o){
        $_SESSION['admin_flash'] = "Order $order_num not found.";
    } elseif($row_o['status'] !== 'Processing'){
        $_SESSION['admin_flash'] = "Order $order_num is already {$row_o['status']} — this status is final and cannot be changed.";
    } elseif(!in_array($new_status, $allowed)){
        $_SESSION['admin_flash'] = "No change made to order $order_num.";
    } else {
        $upd = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
        $upd->bind_param("si",$new_status,$oid);
        $upd->execute();
        $h = $conn->prepare("INSERT INTO order_status_history (order_id,status) VALUES (?,?)");
        $h->bind_param("is",$oid,$new_status);
        $h->execute();
        $_SESSION['admin_flash'] = "Order $order_num updated to $new_status.";

        $cust_uid = (int)$row_o['user_id'];

        if($new_status === 'Delivered'){
            add_notification(
                $conn, $cust_uid,
                'Order ' . $order_num . ' Has Been Delivered!',
                'Great news! Your order ' . $order_num . ' has been delivered to your address. Please click "Mark as Received" on your order once you have the package in hand.',
                'delivery'
            );

        } elseif($new_status === 'Cancelled'){
            // Shared cancel bundle: restores stock (per size + product totals),
            // issues one voucher per item + shipping + apology, one summary email
            $v_count = cancel_order_refund($conn, $oid);
            $_SESSION['admin_flash'] = "Order $order_num cancelled — stock restored and {$v_count} vouchers issued (per-item refunds + apology gift).";
        }
    }
    header("Location: admin_orders.php"); exit;
}

if(!empty($_SESSION['admin_flash'])){
    $msg = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

// Filter — validated against allowlist to prevent injection
$filter          = $_GET['filter'] ?? '';
$allowed_filters = ['Processing','Delivered','Completed','Cancelled'];
if($filter && !in_array($filter, $allowed_filters)) $filter = '';
$where = $filter ? "WHERE o.status='".$conn->real_escape_string($filter)."'" : '';

// Section for fetching all orders with customer info
$orders = $conn->query("
    SELECT o.*, u.name AS customer_name, u.email,
           COUNT(oi.order_item_id) AS item_count
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
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Orders | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
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
      <div class="flash flash-ok"><?=e($msg)?></div>
      <?php endif; ?>

      <!-- Status filter -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <?php foreach([''=> 'All','Processing'=>'Processing','Delivered'=>'Delivered','Completed'=>'Completed','Cancelled'=>'Cancelled'] as $val=>$label): ?>
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
                <?php if($o['status'] !== 'Processing'):
                  // Delivered / Completed / Cancelled are FINAL — no more admin actions
                  $final_note = match($o['status']){
                      'Delivered' => 'Waiting for customer to confirm receipt',
                      'Completed' => 'Marked received by the customer',
                      'Cancelled' => 'Refund vouchers issued & stock restored',
                      default     => '',
                  };
                ?>
                <div style="font-size:.72rem;color:var(--muted);line-height:1.6;">
                  🔒 <strong>Status is final</strong><br><?=$final_note?>
                </div>
                <?php else: ?>
                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                  <?=csrf_field()?>
                  <input type="hidden" name="order_id" value="<?=(int)$o['order_id']?>">
                  <select name="status"
                    style="background:var(--navy2);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:var(--radius);font-size:.82rem;cursor:pointer;">
                    <?php // Completed is set by the customer via "Mark as Received" — not by admin
                    foreach(['Processing','Delivered','Cancelled'] as $s): ?>
                    <option value="<?=$s?>" <?=$o['status']===$s?'selected':''?>><?=$s?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="update_status" class="btn btn-primary btn-sm">Save</button>
                </form>
                <?php endif; ?>
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
