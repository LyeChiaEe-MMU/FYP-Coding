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

// Order items (with colour-specific image)
$items = $conn->query("
    SELECT oi.*, p.name, p.image_url,
           (SELECT pi.image_url FROM product_images pi
            WHERE pi.product_id=oi.product_id AND pi.color_name=oi.color
            ORDER BY pi.sort_order ASC LIMIT 1) AS color_image
    FROM order_items oi
    JOIN products p ON oi.product_id=p.product_id
    WHERE oi.order_id=$oid
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Order Confirmed | Apex</title>
<link rel="stylesheet" href="css/style.css?v=4">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="success-wrap">
  <div class="success-box">
    <div class="card" style="padding:44px;text-align:center;">
      <h1 style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:2px;color:var(--white);margin-bottom:8px;">ORDER CONFIRMED!</h1>
      <p style="color:var(--muted);margin-bottom:28px;">Thank you <?=e($_SESSION['user_name']??'')?>! Your order has been placed and is being processed.</p>

      <!-- Order meta -->
      <?php
      // Build payment display
      $pm_display = e($order['payment_method'] ?? 'Online Banking');
      $pd_display = !empty($order['payment_detail']) ? e($order['payment_detail']) : '';
      $pay_label  = $pm_display . (!empty($pd_display) ? ' — ' . $pd_display : '');

      // Discount
      $disc = (float)($order['discount_amount'] ?? 0);
      $vc   = $order['voucher_code'] ?? '';
      ?>
      <div style="background:#f9f6f3;border:1px solid #e8ddd6;border-radius:12px;padding:18px 20px;text-align:left;margin-bottom:24px;">
        <?php
        $rows = [
          ['Order ID',    '#'.str_pad($oid,6,'0',STR_PAD_LEFT), false],
          ['Date',        date('d M Y, h:i A',strtotime($order['order_date'])), false],
          ['Payment',     $pay_label, false],
        ];
        if($disc > 0 && $vc !== ''){
            $rows[] = ['Voucher Used', e($vc).'<br><span style="color:#22c55e;font-size:.95em;">− RM '.number_format($disc,2).' saved</span>', false];
        }
        $rows[] = ['Total Paid',  'RM '.number_format($order['total_amount'],2), true];
        $rows[] = ['Status',      status_badge($order['status']), false];
        $rows[] = ['Shipping To', e($order['shipping_address']), false];
        foreach($rows as [$label,$val,$accent]): ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid #ede4dc;font-size:.85rem;">
          <span style="color:#8a7060;font-weight:500;"><?=$label?></span>
          <span style="font-weight:600;color:<?=$accent?'#C8543C':'#2a1a10'?>;text-align:right;max-width:60%;"><?=$val?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Order items preview -->
      <div style="margin-bottom:24px;">
        <?php while($it=$items->fetch_assoc()):
          $img = !empty($it['color_image']) ? e($it['color_image']) : (!empty($it['image_url']) ? e($it['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60');
        ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #ede4dc;text-align:left;">
          <img src="<?=$img?>" style="width:58px;height:58px;border-radius:8px;object-fit:cover;border:1px solid #e8ddd6;flex-shrink:0;">
          <div style="flex:1;">
            <div style="font-weight:600;font-size:.9rem;color:#2a1a10;"><?=e($it['name'])?></div>
            <div style="font-size:.75rem;color:#8a7060;">UK <?=e($it['size'] ?? '—')?><?=!empty($it['color'])&&$it['color']!=='Default'?' · '.e($it['color']):''?> × <?=(int)$it['quantity']?></div>
            <?php $it_orig = (float)($it['original_price'] ?? $it['price']); if($it_orig > (float)$it['price']): ?>
            <div style="font-size:.72rem;margin-top:2px;">
              <span style="text-decoration:line-through;color:#b09a8a;">RM <?=number_format($it_orig,2)?>/pc</span>
              <span style="color:#C8543C;font-weight:600;"> RM <?=number_format($it['price'],2)?>/pc</span>
            </div>
            <?php endif; ?>
          </div>
          <div style="font-family:'Oswald',sans-serif;color:#C8543C;font-size:1.05rem;">
            RM <?=number_format($it['price']*$it['quantity'],2)?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <div class="success-btns">
        <a href="order_history.php" class="btn btn-primary">View My Orders</a>
        <a href="index.php" class="btn btn-outline">Continue Shopping</a>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
