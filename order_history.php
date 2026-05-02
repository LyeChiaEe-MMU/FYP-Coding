<?php
session_start();
require 'db.php';
if(!is_logged()){ header("Location: login.php"); exit; }

$uid = (int)$_SESSION['user_id'];
$orders = $conn->prepare("
    SELECT o.order_id, o.total_amount, o.status, o.order_date, o.shipping_address,
           COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");
$orders->bind_param("i",$uid);
$orders->execute();
$result = $orders->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Orders | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>My Orders</span></div>
    <h1>MY <span style="color:var(--accent)">ORDERS</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap" style="max-width:860px;">

<?php if($result->num_rows===0): ?>
<div class="empty-cart" style="padding:80px 0;">
  <div class="ec-icon">📦</div>
  <h3>No Orders Yet</h3>
  <p>You haven't placed any orders yet.</p>
  <a href="products.php" class="btn btn-primary" style="margin-top:8px;">Start Shopping</a>
</div>

<?php else: while($o=$result->fetch_assoc()):
  $oid = (int)$o['order_id'];
  $status = $o['status'];
  $steps = ['Processing','Shipped','Completed'];
  $cur = array_search($status, $steps);
  if($cur===false) $cur=-1;

  // Fetch items for this order
  $oit = $conn->query("
      SELECT oi.size, oi.quantity, oi.price, p.name, p.image_url
      FROM order_items oi JOIN products p ON oi.product_id=p.product_id
      WHERE oi.order_id=$oid
  ");
?>
<div class="card" style="margin-bottom:18px;overflow:hidden;">
  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px;">
    <div>
      <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);">
        ORDER #<?=str_pad($oid,6,'0',STR_PAD_LEFT)?>
      </div>
      <div style="font-size:.75rem;color:var(--muted);margin-top:3px;">
        <?=date('d M Y, h:i A',strtotime($o['order_date']))?>
        &nbsp;·&nbsp; <?=(int)$o['item_count']?> item<?=$o['item_count']!=1?'s':''?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
      <span style="font-family:'Oswald',sans-serif;font-size:1.2rem;color:var(--accent);">
        RM <?=number_format($o['total_amount'],2)?>
      </span>
      <?=status_badge($status)?>
    </div>
  </div>

  <!-- Items -->
  <div style="padding:16px 22px;border-bottom:1px solid var(--border);">
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php while($it=$oit->fetch_assoc()):
        $img = !empty($it['image_url']) ? e($it['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60';
      ?>
      <div style="display:flex;align-items:center;gap:10px;background:var(--navy2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;min-width:200px;">
        <img src="<?=$img?>" style="width:44px;height:44px;border-radius:6px;object-fit:cover;flex-shrink:0;">
        <div>
          <div style="font-size:.82rem;font-weight:600;color:var(--white);"><?=e($it['name'])?></div>
          <div style="font-size:.72rem;color:var(--muted);">UK <?=e($it['size'])?> × <?=(int)$it['quantity']?> — RM <?=number_format($it['price'],2)?>/pc</div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- Timeline -->
  <div style="padding:18px 22px;">
    <div style="display:flex;align-items:center;">
      <?php foreach($steps as $i=>$step):
        $done = $i <= $cur;
        $isLast = $i === count($steps)-1;
      ?>
      <div style="flex:1;text-align:center;position:relative;">
        <?php if(!$isLast): ?>
        <div style="position:absolute;top:13px;left:50%;width:100%;height:2px;background:<?=$done&&$i<$cur?'var(--accent)':'var(--border)'?>;z-index:0;"></div>
        <?php endif; ?>
        <div style="width:27px;height:27px;border-radius:50%;background:<?=$done?'var(--accent)':'var(--navy3)'?>;border:2px solid <?=$done?'var(--accent)':'var(--border)'?>;margin:0 auto 7px;position:relative;z-index:1;display:flex;align-items:center;justify-content:center;font-size:.65rem;color:<?=$done?'var(--navy)':'var(--muted)'?>;font-weight:700;">
          <?=$done?'✓':($i+1)?>
        </div>
        <div style="font-size:.65rem;letter-spacing:.5px;color:<?=$done?'var(--white)':'var(--muted)'?>;text-transform:uppercase;"><?=e($step)?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if($status==='Shipped'): ?>
    <div style="margin-top:14px;background:rgba(100,149,237,.08);border:1px solid rgba(100,149,237,.2);border-radius:6px;padding:10px 14px;font-size:.82rem;color:#8ab4f8;display:flex;align-items:center;gap:8px;">
      📦 Estimated delivery: <?=date('d M Y',strtotime('+5 days',strtotime($o['order_date'])))?>
    </div>
    <?php endif; ?>

    <div style="margin-top:12px;font-size:.78rem;color:var(--muted);">
      📍 <?=e($o['shipping_address'])?>
    </div>
  </div>
</div>
<?php endwhile; endif; ?>

</div>
</section>

<?php include 'includes/footer.php'; ?>
