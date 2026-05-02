<?php
session_start();
require 'db.php';

if(empty($_GET['id'])){ header("Location: products.php"); exit; }
$pid = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id=c.category_id WHERE p.product_id=?");
$stmt->bind_param("i",$pid);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if(!$product){ header("Location: products.php"); exit; }

// Sizes
$sz_stmt = $conn->prepare("SELECT size, stock_for_size FROM product_size WHERE product_id=? ORDER BY CAST(size AS DECIMAL)");
$sz_stmt->bind_param("i",$pid);
$sz_stmt->execute();
$sizes = $sz_stmt->get_result();

$flash = ''; $ftype = '';
if(isset($_SESSION['cart_msg'])){
    $flash = $_SESSION['cart_msg']; $ftype = $_SESSION['cart_msg_type'] ?? 'ok';
    unset($_SESSION['cart_msg'], $_SESSION['cart_msg_type']);
}
$img = !empty($product['image_url']) ? e($product['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($product['name'])?> | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div style="background:var(--navy2);border-bottom:1px solid var(--border);padding:32px 0;">
  <div class="wrap">
    <div class="breadcrumb">
      <a href="index.php">Home</a><span class="sep">/</span>
      <a href="products.php?cat=<?=urlencode($product['category_name'])?>"><?=e($product['category_name'])?></a>
      <span class="sep">/</span><span><?=e($product['name'])?></span>
    </div>
  </div>
</div>

<div class="wrap">
<?php if($flash): ?>
  <div class="flash flash-<?=$ftype?>" style="margin-top:20px;"><?=e($flash)?></div>
<?php endif; ?>

<div class="detail-grid">
  <!-- Image -->
  <div class="detail-img">
    <img src="<?=$img?>" alt="<?=e($product['name'])?>">
  </div>

  <!-- Info -->
  <div>
    <div class="detail-cat"><?=e($product['category_name'])?></div>
    <h1 class="detail-name"><?=e($product['name'])?></h1>
    <div class="detail-price">RM <?=number_format($product['price'],2)?></div>
    <p class="detail-desc"><?=nl2br(e($product['description']))?></p>

    <form action="cart_action.php" method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?=$pid?>">

      <div class="size-label">SELECT SIZE (UK)</div>
      <div class="size-grid" id="sizeGrid">
        <?php
        $sizes->data_seek(0);
        $has_sizes = false;
        while($sz = $sizes->fetch_assoc()):
          $has_sizes = true;
          $oos = $sz['stock_for_size'] < 1;
        ?>
        <button type="button"
          class="size-btn <?=$oos?'oos':''?>"
          <?=$oos?'disabled':''?>
          onclick="selectSize(this,'<?=e($sz['size'])?>')"
          title="<?=$oos?'Out of stock':'UK '.$sz['size'].' — '.$sz['stock_for_size'].' left'?>">
          <?=e($sz['size'])?>
        </button>
        <?php endwhile;
        if(!$has_sizes): ?>
        <button type="button" class="size-btn active" onclick="selectSize(this,'Standard')">Standard</button>
        <?php endif; ?>
      </div>
      <input type="hidden" name="size" id="sizeInput" value="">

      <div class="stock-info">
        Total stock: <strong><?=(int)$product['stock']?> pairs</strong>
      </div>

      <?php if($product['stock'] > 0): ?>
      <button type="submit" class="btn btn-primary btn-full" style="margin-bottom:12px;">ADD TO CART</button>
      <?php else: ?>
      <button class="btn btn-secondary btn-full" disabled style="margin-bottom:12px;">OUT OF STOCK</button>
      <?php endif; ?>
    </form>

    <a href="products.php?cat=<?=urlencode($product['category_name'])?>" class="btn btn-outline btn-full">
      ← Back to <?=e($product['category_name'])?>
    </a>

    <!-- Accordion info -->
    <div style="margin-top:28px;border-top:1px solid var(--border);">
      <?php foreach([
        ['Free Shipping','Free standard shipping on orders above RM300. Estimated delivery 2–4 business days.'],
        ['Returns Policy','30-day hassle-free returns on all unworn shoes in original packaging.'],
        ['Authenticity','Every Apex pair comes with a certificate of authenticity and quality-control seal.'],
      ] as $i=>[$title,$body]): ?>
      <div style="border-bottom:1px solid var(--border);">
        <button onclick="toggleAccord(this)"
          style="display:flex;justify-content:space-between;width:100%;background:none;border:none;color:var(--text);padding:14px 0;font-size:.9rem;font-weight:500;cursor:pointer;">
          <?=e($title)?> <span class="acc-ico">+</span>
        </button>
        <div class="acc-body" style="display:<?=$i===0?'block':'none'?>;padding-bottom:14px;color:var(--muted);font-size:.875rem;line-height:1.75;">
          <?=e($body)?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function selectSize(btn, size) {
    document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sizeInput').value = size;
}
function toggleAccord(btn) {
    const body = btn.nextElementSibling;
    const ico  = btn.querySelector('.acc-ico');
    const open = body.style.display === 'block';
    body.style.display = open ? 'none' : 'block';
    ico.textContent    = open ? '+' : '−';
}
// Auto-select first available size
document.querySelectorAll('.size-btn:not(.oos)')[0]?.click();
</script>
