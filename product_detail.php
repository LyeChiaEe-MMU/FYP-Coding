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
$sizes_arr = $sz_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Extra images (from product_images table if exists)
$images = [];
$tbl_check = $conn->query("SHOW TABLES LIKE 'product_images'");
if($tbl_check->num_rows > 0){
    $img_stmt = $conn->prepare("SELECT image_url, color_name FROM product_images WHERE product_id=? ORDER BY sort_order ASC");
    $img_stmt->bind_param("i",$pid);
    $img_stmt->execute();
    $images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ALWAYS put main product image as FIRST slide
$main_img = !empty($product['image_url']) ? $product['image_url'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80';
array_unshift($images, ['image_url' => $main_img, 'color_name' => '']);

// Remove duplicates (if main image was also added as variant)
$seen = []; $unique = [];
foreach($images as $img){
    if(!in_array($img['image_url'], $seen)){
        $seen[]   = $img['image_url'];
        $unique[] = $img;
    }
}
$images = $unique;

$flash = ''; $ftype = '';
if(isset($_SESSION['cart_msg'])){
    $flash = $_SESSION['cart_msg']; $ftype = $_SESSION['cart_msg_type'] ?? 'ok';
    unset($_SESSION['cart_msg'], $_SESSION['cart_msg_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($product['name'])?> | Apex</title>
<link rel="stylesheet" href="css/style.css">
<style>
/* ── Slider ── */
.slider-wrap{
    position:relative;border-radius:14px;overflow:hidden;
    border:1px solid var(--border);background:var(--navy2);
    width:100%;max-width:520px;height:480px;
}
.slider-main{
    width:100%;height:100%;object-fit:contain;display:block;
    padding:12px;
    transition:opacity .25s ease;
}
.sl-btn{
    position:absolute;top:50%;transform:translateY(-50%);
    background:rgba(10,25,47,.8);border:1px solid var(--border);
    color:var(--white);width:42px;height:42px;border-radius:50%;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;transition:all .2s;z-index:2;
}
.sl-btn:hover{background:var(--accent);color:var(--navy);border-color:var(--accent)}
.sl-btn:disabled{opacity:.25;cursor:default;pointer-events:none}
.sl-prev{left:12px}
.sl-next{right:12px}
.sl-counter{
    position:absolute;bottom:10px;right:12px;
    background:rgba(10,25,47,.7);color:var(--muted);
    font-size:.7rem;padding:4px 10px;border-radius:100px;
    border:1px solid var(--border);
}
/* Thumbnails */
.thumb-row{display:flex;gap:10px;margin-top:12px;overflow-x:auto;padding-bottom:4px}
.thumb-row::-webkit-scrollbar{height:4px}
.thumb-row::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.thumb-img{
    width:72px;height:72px;border-radius:8px;object-fit:cover;
    cursor:pointer;border:2px solid var(--border);
    transition:all .2s;flex-shrink:0;
}
.thumb-img:hover{border-color:rgba(100,255,218,.5)}
.thumb-img.on{border-color:var(--accent)}

/* ── UK Size Buttons ── */
.uk-size-grid{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px}
.uk-btn{
    width:48px;height:48px;
    border-radius:var(--radius);
    border:1px solid var(--border);
    background:var(--navy2);
    color:var(--text);
    cursor:pointer;transition:all .2s;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;gap:1px;
    padding:0;flex-shrink:0;
}
.uk-btn:hover:not(.oos){border-color:var(--accent);color:var(--white);background:rgba(100,255,218,.08)}
.uk-btn.active{background:var(--accent);border-color:var(--accent);color:var(--navy);font-weight:700}
.uk-btn.oos{opacity:.35;cursor:not-allowed;text-decoration:line-through;border-style:dashed}
.uk-num{font-size:.82rem;font-weight:700;line-height:1}
.uk-lbl{font-size:.48rem;letter-spacing:.5px;text-transform:uppercase;opacity:.65}

/* ── Colour Swatch Buttons ── */
.color-swatch-btn:hover{border-color:rgba(100,255,218,.5)!important}
.color-swatch-btn:hover span{color:var(--white)!important}
.color-swatch-btn.color-active{border-color:var(--accent)!important;background:rgba(100,255,218,.08)!important}
.color-swatch-btn.color-active span{color:var(--accent)!important;font-weight:600!important}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div style="background:var(--navy2);border-bottom:1px solid var(--border);padding:28px 0;">
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

  <!-- ── LEFT: Image Slider ── -->
  <div>
    <div class="slider-wrap">
      <img id="mainImg" src="<?=e($images[0]['image_url'])?>" alt="<?=e($product['name'])?>" class="slider-main">

      <?php if(count($images) > 1): ?>
      <button class="sl-btn sl-prev" id="prevBtn" onclick="slide(-1)" disabled>&#8249;</button>
      <button class="sl-btn sl-next" id="nextBtn" onclick="slide(1)">&#8250;</button>
      <div class="sl-counter" id="slCounter">1 / <?=count($images)?></div>
      <?php endif; ?>
    </div>

    <!-- Color name -->
    <?php if(!empty($images[0]['color_name'])): ?>
    <div style="font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-top:10px;">
      Colour: <span id="colorLbl" style="color:var(--white);font-weight:600;"><?=e($images[0]['color_name'])?></span>
    </div>
    <?php endif; ?>

    <!-- Thumbnails -->
    <?php if(count($images) > 1): ?>
    <div class="thumb-row">
      <?php foreach($images as $i => $img): ?>
      <img src="<?=e($img['image_url'])?>"
           class="thumb-img <?=$i===0?'on':''?>"
           onclick="goTo(<?=$i?>)"
           title="<?=e($img['color_name'] ?: 'View ' . ($i+1))?>"
           alt="">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── RIGHT: Info ── -->
  <div>
    <div class="detail-cat"><?=e($product['category_name'])?></div>
    <h1 class="detail-name"><?=e($product['name'])?></h1>
    <div class="detail-price">RM <?=number_format($product['price'],2)?></div>
    <p class="detail-desc"><?=nl2br(e($product['description']))?></p>

    <form action="cart_action.php" method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?=$pid?>">
      <input type="hidden" name="size" id="sizeInput" value="">
      <input type="hidden" name="color" id="colorInput" value="">

      <!-- ── COLOUR SELECTOR ── -->
      <?php
      // Only show colour selector if there are named variants (skip the main image which has no color_name)
      $named_variants = array_filter($images, fn($img) => !empty($img['color_name']));
      if(!empty($named_variants)):
      ?>
      <div style="margin-bottom:22px;">
        <div class="size-label" style="margin-bottom:10px;">
          SELECT COLOUR:
          <span id="colorSelectedLbl" style="color:var(--accent);font-weight:700;font-size:.8rem;margin-left:6px;text-transform:none;letter-spacing:0;">— None selected —</span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php foreach($images as $idx => $img):
            if(empty($img['color_name'])) continue;
            $csrc = str_starts_with($img['image_url'],'http') ? e($img['image_url']) : e($img['image_url']);
          ?>
          <button type="button"
            class="color-swatch-btn"
            onclick="pickColor(this, <?=$idx?>, '<?=e(addslashes($img['color_name']))?>')"
            title="<?=e($img['color_name'])?>"
            style="display:flex;align-items:center;gap:8px;padding:7px 12px;border-radius:var(--radius);border:2px solid var(--border);background:var(--navy2);cursor:pointer;transition:all .2s;">
            <img src="<?=$csrc?>" alt="<?=e($img['color_name'])?>"
                 style="width:30px;height:30px;border-radius:4px;object-fit:cover;flex-shrink:0;border:1px solid var(--border);">
            <span style="font-size:.82rem;color:var(--muted);transition:color .2s;"><?=e($img['color_name'])?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="size-label" style="margin-bottom:12px;">SELECT SIZE (UK)</div>
      <div class="uk-size-grid">
        <?php if(!empty($sizes_arr)): ?>
          <?php foreach($sizes_arr as $sz):
            $oos = $sz['stock_for_size'] < 1;
          ?>
          <button type="button"
            class="uk-btn <?=$oos?'oos':''?>"
            <?=$oos?'disabled':''?>
            onclick="pickSize(this,'<?=e($sz['size'])?>')"
            title="<?=$oos?'Out of stock':'UK '.$sz['size'].' — '.$sz['stock_for_size'].' pairs left'?>">
            <span class="uk-num"><?=e($sz['size'])?></span>
            <span class="uk-lbl">UK</span>
          </button>
          <?php endforeach; ?>
        <?php else: ?>
          <!-- Default UK range if no sizes set up yet -->
          <?php foreach(['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','12'] as $s): ?>
          <button type="button" class="uk-btn" onclick="pickSize(this,'<?=$s?>')">
            <span class="uk-num"><?=$s?></span>
            <span class="uk-lbl">UK</span>
          </button>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="stock-info" style="margin-bottom:20px;">
        Total stock: <strong><?=(int)$product['stock']?> pairs</strong>
      </div>

      <?php if($product['stock'] > 0): ?>
      <button type="submit" class="btn btn-primary btn-full" style="margin-bottom:12px;" id="addCartBtn" disabled>
        SELECT A SIZE TO ADD TO CART
      </button>
      <?php else: ?>
      <button class="btn btn-secondary btn-full" disabled style="margin-bottom:12px;">OUT OF STOCK</button>
      <?php endif; ?>
    </form>

    <a href="products.php?cat=<?=urlencode($product['category_name'])?>" class="btn btn-outline btn-full">
      &#8592; Back to <?=e($product['category_name'])?>
    </a>

    <!-- Accordion -->
    <div style="margin-top:28px;border-top:1px solid var(--border);">
      <?php foreach([
        ['Free Shipping','Free standard shipping on orders above RM300. Estimated 2–4 business days.'],
        ['Returns Policy','30-day hassle-free returns on all unworn shoes in original packaging.'],
        ['Authenticity','Every Apex pair includes a certificate of authenticity and quality-control seal.'],
      ] as $i=>[$title,$body]): ?>
      <div style="border-bottom:1px solid var(--border);">
        <button onclick="toggleAcc(this)"
          style="display:flex;justify-content:space-between;width:100%;background:none;border:none;color:var(--text);padding:14px 0;font-size:.9rem;font-weight:500;cursor:pointer;">
          <?=e($title)?> <span class="acc-ico" style="font-size:1.1rem;color:var(--muted);">+</span>
        </button>
        <div style="display:<?=$i===0?'block':'none'?>;padding-bottom:14px;color:var(--muted);font-size:.875rem;line-height:1.75;">
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
// Build images array — fix local paths to include full relative path from root
const imgs = <?php
    $js_imgs = array_map(function($img){
        $url = $img['image_url'];
        // Local path stays as-is — browser resolves from site root
        return ['image_url' => $url, 'color_name' => $img['color_name'] ?? ''];
    }, $images);
    echo json_encode(array_values($js_imgs));
?>;
let cur = 0;

function goTo(i){
    if(i < 0 || i >= imgs.length) return;
    cur = i;
    const mainImg = document.getElementById('mainImg');
    const counter = document.getElementById('slCounter');
    const colorLbl= document.getElementById('colorLbl');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    mainImg.style.opacity = '0';
    setTimeout(()=>{ mainImg.src = imgs[cur].image_url; mainImg.style.opacity = '1'; }, 200);

    if(counter)  counter.textContent = (cur+1) + ' / ' + imgs.length;
    if(colorLbl){ colorLbl.textContent = imgs[cur].color_name || '—'; }
    if(prevBtn)  prevBtn.disabled = (cur === 0);
    if(nextBtn)  nextBtn.disabled = (cur === imgs.length - 1);

    document.querySelectorAll('.thumb-img').forEach((t,idx)=>t.classList.toggle('on', idx===cur));
}

function slide(dir){ goTo(cur + dir); }

// Colour selection — also jumps slider to that image
function pickColor(btn, slideIdx, colorName){
    document.querySelectorAll('.color-swatch-btn').forEach(b => b.classList.remove('color-active'));
    btn.classList.add('color-active');
    document.getElementById('colorInput').value = colorName;
    const lbl = document.getElementById('colorSelectedLbl');
    if(lbl) lbl.textContent = colorName;
    // Jump slider to that colour's image
    goTo(slideIdx);
}

// Size selection
function pickSize(btn, size){
    document.querySelectorAll('.uk-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sizeInput').value = size;
    const addBtn = document.getElementById('addCartBtn');
    if(addBtn){ addBtn.disabled = false; addBtn.textContent = 'ADD TO CART'; }
}

// Accordion
function toggleAcc(btn){
    const body = btn.nextElementSibling;
    const ico  = btn.querySelector('.acc-ico');
    const open = body.style.display === 'block';
    body.style.display = open ? 'none' : 'block';
    ico.textContent    = open ? '+' : '−';
}

// Auto-click first available size
document.querySelectorAll('.uk-btn:not(.oos)')[0]?.click();
</script>
