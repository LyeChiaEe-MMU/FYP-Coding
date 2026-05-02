<?php
session_start();
require 'db.php';
if(!is_logged()){ header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

$user = $conn->prepare("SELECT name,email,phone,address FROM users WHERE user_id=?");
$user->bind_param("i",$uid); $user->execute();
$u = $user->get_result()->fetch_assoc();

$cs = $conn->prepare("SELECT c.quantity,c.size,p.name,p.price,p.image_url FROM cart_items c JOIN products p ON c.product_id=p.product_id WHERE c.user_id=?");
$cs->bind_param("i",$uid); $cs->execute();
$cart = $cs->get_result();
if($cart->num_rows===0){ header("Location: cart.php"); exit; }

$items=[]; $subtotal=0;
while($r=$cart->fetch_assoc()){ $r['sub']=$r['price']*$r['quantity']; $subtotal+=$r['sub']; $items[]=$r; }
$shipping = $subtotal>=300 ? 0 : 10;
$total    = $subtotal+$shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Checkout | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><a href="cart.php">Cart</a><span class="sep">/</span><span>Checkout</span></div>
    <h1>CHECK<span style="color:var(--accent)">OUT</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap">
<form action="process_checkout.php" method="POST">
<div class="checkout-grid">

  <!-- Left -->
  <div>
    <!-- Shipping -->
    <div class="card checkout-section">
      <h3>SHIPPING INFORMATION</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" value="<?=e($u['name'])?>" readonly style="opacity:.6;">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" value="<?=e($u['phone'])?>" readonly style="opacity:.6;">
        </div>
      </div>
      <div class="form-group">
        <label>Shipping Address *</label>
        <textarea name="shipping_address" rows="3" required><?=e($u['address'])?></textarea>
      </div>
    </div>

    <!-- Payment -->
    <div class="card checkout-section">
      <h3>PAYMENT METHOD</h3>
      <div class="payment-options">
        <?php foreach([
          ['online_banking','🏦','Online Banking','FPX — All major banks'],
          ['credit_card',   '💳','Credit / Debit','Visa, Mastercard'],
          ['ewallet',       '📱','E-Wallet','GrabPay, Touch n Go'],
          ['cod',           '💵','Cash on Delivery','Pay upon receiving'],
        ] as $pm): ?>
        <label class="pay-opt" onclick="this.classList.add('sel');document.querySelectorAll('.pay-opt').forEach(e=>e!==this&&e.classList.remove('sel'))">
          <input type="radio" name="payment_method" value="<?=e($pm[0])?>" required style="display:none;">
          <span style="font-size:1.4rem;"><?=$pm[1]?></span>
          <div>
            <div style="font-weight:600;font-size:.875rem;color:var(--white);"><?=e($pm[2])?></div>
            <div style="font-size:.75rem;color:var(--muted);"><?=e($pm[3])?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="sim-note">⚠️ This is a simulated payment gateway for FYP purposes — no real money is processed.</div>
    </div>
  </div>

  <!-- Summary -->
  <div class="card cart-summary-box" style="position:sticky;top:90px;">
    <div class="cs-title">ORDER SUMMARY</div>
    <?php foreach($items as $it): ?>
    <div style="display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
      <img src="<?=!empty($it['image_url'])?e($it['image_url']):'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60'?>"
           style="width:52px;height:52px;border-radius:6px;object-fit:cover;flex-shrink:0;">
      <div style="flex:1;">
        <div style="font-size:.875rem;font-weight:600;color:var(--white);"><?=e($it['name'])?></div>
        <div style="font-size:.75rem;color:var(--muted);">UK <?=e($it['size'])?> × <?=(int)$it['quantity']?></div>
      </div>
      <div style="font-family:'Oswald',sans-serif;color:var(--accent);">RM <?=number_format($it['sub'],2)?></div>
    </div>
    <?php endforeach; ?>
    <div class="cs-row" style="margin-top:14px;"><span>Subtotal</span><span>RM <?=number_format($subtotal,2)?></span></div>
    <div class="cs-row">
      <span>Shipping</span>
      <span><?=$shipping===0?'<span style="color:var(--accent)">FREE</span>':'RM '.number_format($shipping,2)?></span>
    </div>
    <div class="cs-total"><span>TOTAL</span><span style="color:var(--accent);">RM <?=number_format($total,2)?></span></div>
    <button type="submit" class="btn btn-primary btn-full">PLACE ORDER →</button>
    <a href="cart.php" class="btn btn-outline btn-full" style="margin-top:10px;">← Back to Cart</a>
  </div>

</div>
</form>
</div>
</section>

<?php include 'includes/footer.php'; ?>
