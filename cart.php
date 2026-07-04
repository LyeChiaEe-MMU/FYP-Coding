<?php
session_start();
require 'db.php';

if(!is_logged()){ header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

// Section for fetching the user's cart items
$stmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, c.size, c.color,
           p.product_id, p.name, p.price, p.sale_percent, p.image_url,
           COALESCE(ps.stock,0) AS avail_stock,
           (SELECT pi.image_url FROM product_images pi
            WHERE pi.product_id = c.product_id AND pi.color_name = c.color
            ORDER BY pi.sort_order ASC LIMIT 1) AS color_image
    FROM cart_items c
    JOIN products p ON c.product_id = p.product_id
    LEFT JOIN product_stock ps ON ps.product_id=c.product_id AND ps.color_name=c.color AND ps.size=c.size
    WHERE c.user_id = ?
    ORDER BY c.cart_id DESC
");
$stmt->bind_param("i",$uid);
$stmt->execute();
$cart = $stmt->get_result();

$rows=[]; $subtotal=0;
while($r=$cart->fetch_assoc()){
    $ep = effective_price($r['price'], (int)($r['sale_percent']??0));
    $r['eff_price'] = $ep;
    $r['sub'] = $ep * $r['quantity'];
    $subtotal += $r['sub'];
    $rows[] = $r;
}

// Section for calculating subtotal, shipping and total
$shipping = $subtotal>=300 ? 0 : ($subtotal>0 ? 10 : 0);
$total    = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Your Cart | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
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
<?php if(!empty($_SESSION['cart_msg'])): ?>
<div class="flash flash-<?=e($_SESSION['cart_msg_type']??'ok')?>" style="margin-bottom:18px;">
  <?=e($_SESSION['cart_msg'])?>
</div>
<?php unset($_SESSION['cart_msg'],$_SESSION['cart_msg_type']); endif; ?>

<?php if(empty($rows)): ?>
<div class="empty-cart">
  <div class="ec-icon" style="font-family:'Oswald',sans-serif;font-size:1.5rem;letter-spacing:2px;opacity:.3;">CART</div>
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
        $img = !empty($r['color_image']) ? e($r['color_image']) : (!empty($r['image_url']) ? e($r['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&q=70');
      ?>
      <div class="cart-row">
        <!-- Product -->
        <div class="ci-product">
          <img src="<?=$img?>" class="ci-img" alt="">
          <div>
            <div class="ci-name"><?=e($r['name'])?></div>
            <?php $sp_pct_c = (int)($r['sale_percent']??0); ?>
            <div class="ci-sub">
              <?php if($sp_pct_c > 0): ?>
              <span style="text-decoration:line-through;color:var(--muted);font-size:.75rem;">RM <?=number_format($r['price'],2)?></span>
              <span style="color:var(--danger);font-weight:600;">RM <?=number_format($r['eff_price'],2)?></span>
              <?php else: ?>
              RM <?=number_format($r['price'],2)?>
              <?php endif; ?>
              each
            </div>
          </div>
        </div>
        <!-- Size / Colour -->
        <div style="color:var(--muted);font-size:.875rem;">
          UK <?=e($r['size'])?>
          <?php if(!empty($r['color']) && $r['color'] !== 'Default'): ?>
          &nbsp;·&nbsp; <?=e($r['color'])?>
          <?php endif; ?>
        </div>
        <!-- Qty -->
        <div data-avail="<?=(int)$r['avail_stock']?>">
          <form action="cart_action.php" method="POST">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="cart_id" value="<?=(int)$r['cart_id']?>">
            <div class="qty-wrap">
              <button type="submit" class="qty-btn" name="qty_action" value="dec"
                onclick="clearStockWarn(this);let i=this.form.quantity;i.value=Math.max(1,+i.value-1)">−</button>
              <input type="number" name="quantity" class="qty-val" value="<?=(int)$r['quantity']?>" min="1"
                     onchange="cartQtyChange(this)">
              <button type="button" class="qty-btn" onclick="cartQtyInc(this)">+</button>
            </div>
          </form>
          <div class="cart-stock-warn" style="display:none;font-size:.7rem;color:#ef4444;font-weight:600;margin-top:4px;line-height:1.4;"></div>
          <?php $avail = (int)$r['avail_stock']; if($avail <= 5 && $avail > 0): ?>
          <div style="font-size:.68rem;color:var(--muted);margin-top:3px;">Only <?=$avail?> left in stock</div>
          <?php endif; ?>
        </div>
        <!-- Subtotal -->
        <div class="ci-price">RM <?=number_format($r['sub'],2)?></div>
        <!-- Remove -->
        <div>
          <form action="cart_action.php" method="POST">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="cart_id" value="<?=(int)$r['cart_id']?>">
            <button type="submit" class="ci-remove" title="Remove">&times;</button>
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

<script>
function cartQtyInc(btn){
    const outer = btn.closest('[data-avail]');
    const form  = btn.closest('form');
    const input = form.querySelector('input[name="quantity"]');
    const warn  = outer ? outer.querySelector('.cart-stock-warn') : null;
    const avail = outer ? (parseInt(outer.dataset.avail) || 0) : 9999;
    const cur   = parseInt(input.value) || 1;

    if(avail > 0 && cur >= avail){
        if(warn){
            warn.textContent = 'Only ' + avail + ' available in stock — cannot add more.';
            warn.style.display = 'block';
        }
        return; // block increment
    }
    if(warn) warn.style.display = 'none';
    input.value = cur + 1;
    form.submit();
}

function cartQtyChange(input){
    const outer = input.closest('[data-avail]');
    const warn  = outer ? outer.querySelector('.cart-stock-warn') : null;
    const avail = outer ? (parseInt(outer.dataset.avail) || 0) : 9999;
    let   cur   = parseInt(input.value) || 1;

    if(avail > 0 && cur > avail){
        cur = avail;
        input.value = avail;
        if(warn){
            warn.textContent = 'Only ' + avail + ' available in stock. Quantity adjusted.';
            warn.style.display = 'block';
        }
    } else {
        if(warn) warn.style.display = 'none';
    }
    input.form.submit();
}

function clearStockWarn(btn){
    const outer = btn.closest('[data-avail]');
    const warn  = outer ? outer.querySelector('.cart-stock-warn') : null;
    if(warn) warn.style.display = 'none';
}
</script>
