<?php
session_start();
require 'db.php';

if(!is_logged()){ header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, c.size,
           p.product_id, p.name, p.price, p.image_url
    FROM cart_items c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = ?
    ORDER BY c.cart_id DESC
");
$stmt->bind_param("i",$uid);
$stmt->execute();
$cart = $stmt->get_result();

$rows=[]; $subtotal=0;
while($r=$cart->fetch_assoc()){ $r['sub']=$r['price']*$r['quantity']; $subtotal+=$r['sub']; $rows[]=$r; }

$shipping = $subtotal>=300 ? 0 : ($subtotal>0 ? 10 : 0);
$total    = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Your Cart | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Cart</span></div>
    <h1>YOUR <span style="color:var(--accent)">CART</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap">

<?php if(empty($rows)): ?>
<div class="empty-cart">
  <div class="ec-icon">🛒</div>
  <h3>Your cart is empty</h3>
  <p style="color:var(--muted)">Looks like you haven't added anything yet.</p>
  <a href="products.php" class="btn btn-primary">Shop Now</a>
</div>

<?php else: ?>
<div class="cart-grid">

  <!-- Items -->
  <div>
    <div class="card">
      <div class="cart-table-head">
        <div>Product</div><div>Size</div><div>Qty</div><div>Subtotal</div><div></div>
      </div>
      <?php foreach($rows as $r):
        $img = !empty($r['image_url']) ? e($r['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&q=70';
      ?>
      <div class="cart-row">
        <!-- Product -->
        <div class="ci-product">
          <img src="<?=$img?>" class="ci-img" alt="">
          <div>
            <div class="ci-name"><?=e($r['name'])?></div>
            <div class="ci-sub">RM <?=number_format($r['price'],2)?> each</div>
          </div>
        </div>
        <!-- Size -->
        <div style="color:var(--muted);font-size:.875rem;">UK <?=e($r['size'])?></div>
        <!-- Qty -->
        <div>
          <form action="cart_action.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="cart_id" value="<?=(int)$r['cart_id']?>">
            <div class="qty-wrap">
              <button type="submit" class="qty-btn" name="qty_action" value="dec"
                onclick="let i=this.form.quantity;i.value=Math.max(1,+i.value-1)">−</button>
              <input type="number" name="quantity" class="qty-val" value="<?=(int)$r['quantity']?>" min="1" onchange="this.form.submit()">
              <button type="submit" class="qty-btn" name="qty_action" value="inc"
                onclick="let i=this.form.quantity;i.value=+i.value+1">+</button>
            </div>
          </form>
        </div>
        <!-- Subtotal -->
        <div class="ci-price">RM <?=number_format($r['sub'],2)?></div>
        <!-- Remove -->
        <div>
          <form action="cart_action.php" method="POST">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="cart_id" value="<?=(int)$r['cart_id']?>">
            <button type="submit" class="ci-remove" title="Remove">✕</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:16px;">
      <a href="products.php" style="color:var(--muted);font-size:.875rem;display:inline-flex;align-items:center;gap:6px;transition:.2s;"
         onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">
        ← Continue Shopping
      </a>
    </div>
  </div>

  <!-- Summary -->
  <div class="card cart-summary-box">
    <div class="cs-title">ORDER SUMMARY</div>
    <div class="cs-row"><span>Subtotal</span><span>RM <?=number_format($subtotal,2)?></span></div>
    <div class="cs-row">
      <span>Shipping</span>
      <span><?=$shipping===0?'<span style="color:var(--accent)">FREE</span>':'RM '.number_format($shipping,2)?></span>
    </div>
    <?php if($subtotal < 300 && $subtotal > 0): ?>
    <div style="font-size:.75rem;color:var(--muted);text-align:right;margin-top:-4px;margin-bottom:10px;">
      Add RM <?=number_format(300-$subtotal,2)?> more for free shipping
    </div>
    <?php endif; ?>
    <div class="cs-total"><span>TOTAL</span><span style="color:var(--accent);">RM <?=number_format($total,2)?></span></div>

    <a href="checkout.php" class="btn btn-primary btn-full">PROCEED TO CHECKOUT →</a>
  </div>

</div>
<?php endif; ?>
</div>
</section>

<?php include 'includes/footer.php'; ?>
