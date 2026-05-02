<?php
session_start();
require 'db.php';

if(!is_logged() || empty($_GET['order_id'])){ header("Location: index.php"); exit; }

$oid = (int)$_GET['order_id'];
$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id=? AND user_id=?");
$stmt->bind_param("ii",$oid,$uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if(!$order){ header("Location: index.php"); exit; }

// Order items
$items = $conn->query("
    SELECT oi.*, p.name, p.image_url
    FROM order_items oi
    JOIN products p ON oi.product_id=p.product_id
    WHERE oi.order_id=$oid
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Order Confirmed | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="success-wrap">
  <div class="success-box">
    <div class="card" style="padding:44px;text-align:center;">
      <div class="success-icon">✅</div>
      <h1 style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:2px;color:var(--white);margin-bottom:8px;">ORDER CONFIRMED!</h1>
      <p style="color:var(--muted);margin-bottom:28px;">Thank you <?=e($_SESSION['user_name']??'')?>! Your order has been placed and is being processed.</p>

      <!-- Order items preview -->
      <div style="margin-bottom:24px;">
        <?php while($it=$items->fetch_assoc()):
          $img = !empty($it['image_url']) ? e($it['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60';
        ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border);text-align:left;">
          <img src="<?=$img?>" style="width:58px;height:58px;border-radius:8px;object-fit:cover;flex-shrink:0;">
          <div style="flex:1;">
            <div style="font-weight:600;font-size:.9rem;color:var(--white);"><?=e($it['name'])?></div>
            <div style="font-size:.75rem;color:var(--muted);">UK <?=e($it['size'] ?? '—')?> × <?=(int)$it['quantity']?></div>
          </div>
          <div style="font-family:'Oswald',sans-serif;color:var(--accent);font-size:1.05rem;">
            RM <?=number_format($it['price']*$it['quantity'],2)?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <!-- Order meta -->
      <div class="order-meta" style="text-align:left;">
        <div class="om-row">
          <span>Order ID</span>
          <span>#<?=str_pad($oid,6,'0',STR_PAD_LEFT)?></span>
        </div>
        <div class="om-row">
          <span>Date</span>
          <span><?=date('d M Y, h:i A',strtotime($order['order_date']))?></span>
        </div>
        <div class="om-row">
          <span>Total Paid</span>
          <span style="color:var(--accent);">RM <?=number_format($order['total_amount'],2)?></span>
        </div>
        <div class="om-row">
          <span>Status</span>
          <span><?=status_badge($order['status'])?></span>
        </div>
        <div class="om-row">
          <span>Shipping To</span>
          <span style="max-width:220px;text-align:right;font-size:.8rem;"><?=e($order['shipping_address'])?></span>
        </div>
      </div>

      <div class="success-btns">
        <a href="order_history.php" class="btn btn-primary">View My Orders</a>
        <a href="index.php" class="btn btn-outline">Continue Shopping</a>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
