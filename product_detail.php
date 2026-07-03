<?php
session_start();
require 'db.php';

if(empty($_GET['id'])){ header("Location: products.php"); exit; }
$pid = (int)$_GET['id'];

// Gender context passed from the listing page — used to build correct "Back to" links
$_allowed_genders = ['Men','Women','Kids'];
$back_gender = (isset($_GET['gender']) && in_array($_GET['gender'], $_allowed_genders)) ? $_GET['gender'] : '';

$stmt = $conn->prepare("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id=c.category_id WHERE p.product_id=? AND p.is_active=1");
$stmt->bind_param("i",$pid); $stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if(!$product){ header("Location: products.php"); exit; }

// ── Back-in-stock alert: subscribe / unsubscribe ─────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['stock_alert']) && is_logged() && !is_admin()){
    csrf_check();
    $sa_uid = (int)$_SESSION['user_id'];
    if($_POST['stock_alert'] === 'off'){
        $sa_del = $conn->prepare("DELETE FROM stock_alerts WHERE user_id=? AND product_id=?");
        $sa_del->bind_param("ii", $sa_uid, $pid);
        $sa_del->execute();
        $_SESSION['cart_msg'] = "Restock alert removed.";
    } else {
        $sa_ins = $conn->prepare("INSERT IGNORE INTO stock_alerts (user_id, product_id) VALUES (?,?)");
        $sa_ins->bind_param("ii", $sa_uid, $pid);
        $sa_ins->execute();
        $_SESSION['cart_msg'] = "Got it! We'll email you as soon as this shoe is back in stock.";
    }
    $_SESSION['cart_msg_type'] = 'ok';
    header("Location: product_detail.php?id=$pid" . ($back_gender ? '&gender='.urlencode($back_gender) : '')); exit;
}

// Is this user already waiting for a restock?
$has_stock_alert = false;
if(is_logged()){
    $sa_uid_c = (int)$_SESSION['user_id'];
    $sa_chk = $conn->prepare("SELECT alert_id FROM stock_alerts WHERE user_id=? AND product_id=?");
    $sa_chk->bind_param("ii", $sa_uid_c, $pid);
    $sa_chk->execute();
    $has_stock_alert = $sa_chk->get_result()->num_rows > 0;
}

// Ensure product_stock table exists
$conn->query("CREATE TABLE IF NOT EXISTS `product_stock` (
    `stock_id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `color_name` varchar(80) NOT NULL DEFAULT 'Default',
    `size` varchar(10) NOT NULL,
    `stock` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`stock_id`),
    UNIQUE KEY `uq_pcs` (`product_id`,`color_name`,`size`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get all colours for this product from product_images
$tbl = $conn->query("SHOW TABLES LIKE 'product_images'");
$images = [];
if($tbl->num_rows > 0){
    $is = $conn->prepare("SELECT image_url, color_name FROM product_images WHERE product_id=? ORDER BY sort_order ASC");
    $is->bind_param("i",$pid); $is->execute();
    $images = $is->get_result()->fetch_all(MYSQLI_ASSOC);
}
$main_img = !empty($product['image_url']) ? $product['image_url'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80';

// Get ALL stock data for this product grouped by color
$stock_data = [];
$sr = $conn->query("SELECT color_name, size, stock FROM product_stock WHERE product_id=$pid ORDER BY color_name, CAST(size AS DECIMAL)");
while($row = $sr->fetch_assoc()){
    $stock_data[$row['color_name']][$row['size']] = (int)$row['stock'];
}

// Fallback: if no product_stock data, try old product_size table
if(empty($stock_data)){
    $old = $conn->query("SELECT size, stock_for_size FROM product_size WHERE product_id=$pid ORDER BY CAST(size AS DECIMAL)");
    if($old && $old->num_rows > 0){
        while($r=$old->fetch_assoc()){
            $stock_data['Default'][$r['size']] = (int)$r['stock_for_size'];
        }
    }
}

// Main image always first — label it with whichever stock colour has no dedicated variant image
// (stock_data must be built first so we can check which colour has no variant image)
$_variant_colors = array_column($images, 'color_name');
$_main_color = 'Default'; // fallback
foreach($stock_data as $_col => $_sizes){
    if(!in_array($_col, $_variant_colors)){ $_main_color = $_col; break; }
}
array_unshift($images, ['image_url' => $main_img, 'color_name' => $_main_color]);
// Deduplicate
$seen=[]; $uniq=[];
foreach($images as $img){ if(!in_array($img['image_url'],$seen)){ $seen[]=$img['image_url']; $uniq[]=$img; } }
$images = $uniq;

// Get all unique sizes across all colours
$all_sizes = [];
foreach($stock_data as $col => $sizes){ foreach($sizes as $sz => $stk){ if(!in_array($sz,$all_sizes)) $all_sizes[]=$sz; } }
usort($all_sizes, fn($a,$b) => floatval($a) <=> floatval($b));

// First colour name
$first_color = $images[0]['color_name'] ?? 'Default';

// ── Wishlist state ───────────────────────────────────────────────
$is_wishlisted = false;
$conn->query("CREATE TABLE IF NOT EXISTS `wishlists` (
    `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,`product_id` int(11) NOT NULL,
    `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wishlist_id`), UNIQUE KEY `uq_up` (`user_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if(is_logged()){
    $wl_uid = (int)$_SESSION['user_id'];
    $wl_chk = $conn->prepare("SELECT wishlist_id FROM wishlists WHERE user_id=? AND product_id=?");
    $wl_chk->bind_param("ii", $wl_uid, $pid);
    $wl_chk->execute();
    $is_wishlisted = $wl_chk->get_result()->num_rows > 0;
}

// ── Related products — same category first, fill with others ──────
$related_stmt = $conn->prepare("
    SELECT p.product_id, p.name, p.price, p.sale_percent, p.image_url, c.category_name,
           IF(p.category_id = ?, 0, 1) AS sort_order
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.product_id != ? AND p.is_active = 1
    ORDER BY sort_order ASC, p.created_at DESC
    LIMIT 4
");
$related_stmt->bind_param("ii", $product['category_id'], $pid);
$related_stmt->execute();
$related_products = $related_stmt->get_result();

// ── Pre-load wishlist state for all related products (single query, no N+1) ──
$related_wishlisted = [];
if(is_logged() && $related_products && $related_products->num_rows > 0){
    $related_products->data_seek(0);
    $rp_ids = [];
    while($rp = $related_products->fetch_assoc()) $rp_ids[] = (int)$rp['product_id'];
    $related_products->data_seek(0);
    if(!empty($rp_ids)){
        $in_placeholders = implode(',', $rp_ids); // safe — all ints
        $wl_uid_r = (int)$_SESSION['user_id'];
        $rw = $conn->query("SELECT product_id FROM wishlists WHERE user_id=$wl_uid_r AND product_id IN($in_placeholders)");
        if($rw) while($rwr = $rw->fetch_assoc()) $related_wishlisted[(int)$rwr['product_id']] = true;
    }
}

// ── Reviews ─────────────────────────────────────────────────────
$rev_stats = $conn->query("SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS avg_rating FROM reviews WHERE product_id=$pid");
$rev_row   = $rev_stats ? $rev_stats->fetch_assoc() : ['total'=>0,'avg_rating'=>0];
$rev_count = (int)$rev_row['total'];
$rev_avg   = (float)$rev_row['avg_rating'];

$reviews_res = $conn->query("
    SELECT r.rating, r.comment, r.created_at, u.name AS user_name
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.product_id = $pid
    ORDER BY r.created_at DESC
    LIMIT 20
");

$flash=''; $ftype='';
if(isset($_SESSION['cart_msg'])){ $flash=$_SESSION['cart_msg']; $ftype=$_SESSION['cart_msg_type']??'ok'; unset($_SESSION['cart_msg'],$_SESSION['cart_msg_type']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($product['name'])?> | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<style>
.slider-wrap{position:relative;border-radius:14px;overflow:hidden;border:1px solid var(--border);background:#fff;width:100%;max-width:520px;aspect-ratio:1/1}
.slider-main{width:100%;height:100%;object-fit:cover;object-position:center top;display:block;transition:opacity .25s ease}
.sl-btn{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.85);border:1px solid var(--border);color:#1C1410;width:42px;height:42px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.3rem;transition:all .2s;z-index:2;backdrop-filter:blur(4px)}
.sl-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.sl-btn:disabled{opacity:.25;cursor:default;pointer-events:none}
.sl-prev{left:12px}.sl-next{right:12px}
.sl-counter{position:absolute;bottom:10px;right:12px;background:rgba(255,255,255,.88);color:#1C1410;font-size:.7rem;padding:4px 10px;border-radius:100px;border:1px solid var(--border);backdrop-filter:blur(4px)}
.thumb-row{display:flex;gap:10px;margin-top:12px;overflow-x:auto;padding-bottom:4px}
.thumb-img{width:76px;height:76px;border-radius:8px;object-fit:cover;object-position:center top;cursor:pointer;border:2px solid var(--border);transition:all .2s;flex-shrink:0}
.thumb-img:hover{border-color:var(--accent)}
.thumb-img.on{border-color:var(--accent);box-shadow:0 0 0 3px rgba(200,84,60,.18)}

/* Colour swatches */
.color-swatch-btn{display:flex;align-items:center;gap:8px;padding:7px 12px;border-radius:var(--radius);border:2px solid var(--border);background:var(--navy2);cursor:pointer;transition:all .2s}
.color-swatch-btn:hover{border-color:rgba(100,255,218,.5)}
.color-swatch-btn:hover span{color:var(--white)!important}
.color-swatch-btn.color-active{border-color:var(--accent)!important;background:rgba(100,255,218,.08)!important}
.color-swatch-btn.color-active span{color:var(--accent)!important;font-weight:600!important}

/* UK Size Buttons */
.uk-size-grid{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.uk-btn{width:52px;height:52px;border-radius:var(--radius);border:1px solid var(--border);background:var(--navy2);color:var(--text);cursor:pointer;transition:all .2s;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;padding:0;flex-shrink:0;position:relative}
.uk-btn:hover:not(.oos):not(:disabled){border-color:var(--accent);color:var(--white);background:rgba(100,255,218,.08)}
.uk-btn.active{background:var(--accent);border-color:var(--accent);color:var(--navy);font-weight:700}
.uk-btn.oos,.uk-btn:disabled{opacity:.35;cursor:not-allowed;text-decoration:line-through;border-style:dashed}
.uk-num{font-size:.82rem;font-weight:700;line-height:1}
.uk-lbl{font-size:.46rem;letter-spacing:.5px;text-transform:uppercase;opacity:.65}

/* Stock badge on size button */
.sz-stock{position:absolute;top:-5px;right:-5px;background:var(--navy);border:1px solid var(--border);color:var(--muted);font-size:.48rem;border-radius:100px;padding:1px 5px;white-space:nowrap}
.uk-btn.active .sz-stock{background:var(--accent);border-color:var(--accent);color:var(--navy)}
.uk-btn.low-stock .sz-stock{background:rgba(255,100,0,.15);border-color:rgba(255,150,0,.4);color:#f97316}

/* Stock info bar */
.stock-bar{margin-bottom:20px;padding:12px 16px;border-radius:var(--radius);border:1px solid var(--border);background:var(--card);font-size:.875rem;transition:all .3s}
.stock-bar.ok{border-color:rgba(100,255,218,.2);background:rgba(100,255,218,.04);color:var(--accent)}
.stock-bar.low{border-color:rgba(249,115,22,.3);background:rgba(249,115,22,.05);color:#f97316}
.stock-bar.out{border-color:var(--danger);background:rgba(239,68,68,.05);color:var(--danger)}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div style="background:var(--navy2);border-bottom:1px solid var(--border);padding:28px 0;">
  <div class="wrap">
    <div class="breadcrumb">
      <a href="index.php">Home</a><span class="sep">/</span>
      <?php if($back_gender): ?>
        <a href="products.php?gender=<?=urlencode($back_gender)?>"><?=e($back_gender)?>'s Shoes</a><span class="sep">/</span>
        <a href="products.php?cat=<?=urlencode($product['category_name']).'&gender='.urlencode($back_gender)?>"><?=e($product['category_name'])?></a>
      <?php else: ?>
        <a href="products.php?cat=<?=urlencode($product['category_name'])?>"><?=e($product['category_name'])?></a>
      <?php endif; ?>
      <span class="sep">/</span><span><?=e($product['name'])?></span>
    </div>
  </div>
</div>

<div class="wrap">
<?php if($flash): ?><div class="flash flash-<?=$ftype?>" style="margin-top:20px;"><?=e($flash)?></div><?php endif; ?>

<div class="detail-grid">

  <!-- ── Image Slider ── -->
  <div>
    <div class="slider-wrap">
      <img id="mainImg" src="<?=e($images[0]['image_url'])?>" alt="<?=e($product['name'])?>" class="slider-main">
      <?php if(count($images)>1): ?>
      <button class="sl-btn sl-prev" id="prevBtn" onclick="slide(-1)" disabled>&#8249;</button>
      <button class="sl-btn sl-next" id="nextBtn" onclick="slide(1)">&#8250;</button>
      <div class="sl-counter" id="slCounter">1 / <?=count($images)?></div>
      <?php endif; ?>
    </div>
    <?php if(count($images)>1): ?>
    <div class="thumb-row">
      <?php foreach($images as $i=>$img): ?>
      <img src="<?=e($img['image_url'])?>" class="thumb-img <?=$i===0?'on':''?>"
           onclick="goTo(<?=$i?>)" title="<?=e($img['color_name']??'')?>" alt="">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Product Info ── -->
  <div>
    <div class="detail-cat"><?=e($product['category_name'])?></div>
    <h1 class="detail-name"><?=e($product['name'])?></h1>
    <?=price_html($product['price'], (int)($product['sale_percent']??0), 'detail')?>

    <?php
    // Sale countdown — only when a sale is active with a future end time
    $sale_left = 0;
    if(!empty($product['is_on_sale']) && (int)($product['sale_percent']??0) > 0 && !empty($product['sale_ends_at'])){
        $sale_left = strtotime($product['sale_ends_at']) - time();
    }
    if($sale_left > 0): ?>
    <div style="display:inline-flex;align-items:center;gap:10px;margin-bottom:16px;padding:10px 18px;background:rgba(214,64,64,.08);border:1.5px solid rgba(214,64,64,.35);border-radius:10px;">
      <span style="font-size:1.1rem;">⏰</span>
      <div>
        <div style="font-size:.62rem;letter-spacing:2px;text-transform:uppercase;color:var(--danger);font-weight:700;">Sale ends in</div>
        <div id="saleCountdown" style="font-family:'Oswald',sans-serif;font-size:1.25rem;letter-spacing:2px;color:var(--danger);line-height:1.2;">--:--:--</div>
      </div>
    </div>
    <script>
    (function(){
        let left = <?=(int)$sale_left?>;
        const el = document.getElementById('saleCountdown');
        function pad(n){ return String(n).padStart(2,'0'); }
        function tick(){
            if(left <= 0){
                el.parentElement.parentElement.innerHTML =
                    '<span style="color:var(--danger);font-size:.85rem;font-weight:700;">SALE ENDED — refresh to see the normal price</span>';
                return;
            }
            const d = Math.floor(left/86400),
                  h = Math.floor((left%86400)/3600),
                  m = Math.floor((left%3600)/60),
                  s = left%60;
            el.textContent = (d > 0 ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
            left--;
            setTimeout(tick, 1000);
        }
        tick();
    })();
    </script>
    <?php endif; ?>
    <?php if(is_admin()): ?>
    <div style="background:rgba(255,200,0,.1);border:1px solid rgba(255,200,0,.3);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:.8rem;color:#fbbf24;font-weight:600;letter-spacing:.5px;">
      Admin View — purchase actions are disabled
    </div>
    <?php endif; ?>

    <!-- Inline rating summary -->
    <?php if($rev_count > 0): ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
      <div style="display:flex;gap:2px;">
        <?php for($s=1;$s<=5;$s++): $filled = $s <= round($rev_avg); ?>
        <span style="color:<?=$filled?'#f59e0b':'var(--border)'?>;font-size:1rem;">★</span>
        <?php endfor; ?>
      </div>
      <span style="font-size:.88rem;font-weight:700;color:var(--white);"><?=number_format($rev_avg,1)?></span>
      <a href="#reviews" style="font-size:.78rem;color:var(--muted);border-bottom:1px dashed var(--border);"><?=$rev_count?> review<?=$rev_count!=1?'s':''?></a>
    </div>
    <?php else: ?>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:14px;">No reviews yet — be the first!</div>
    <?php endif; ?>

    <p class="detail-desc"><?=nl2br(e($product['description']))?></p>

    <form action="cart_action.php" method="POST" id="addCartForm">
      <?=csrf_field()?>
      <input type="hidden" name="action"      value="add">
      <input type="hidden" name="product_id"  value="<?=$pid?>">
      <input type="hidden" name="size"        id="sizeInput"  value="">
      <input type="hidden" name="color"       id="colorInput" value="<?=e($first_color)?>">
      <input type="hidden" name="back_gender" value="<?=e($back_gender)?>">

      <!-- ── COLOUR SELECTOR ── -->
      <?php $variants = array_slice($images,1); if(!empty($variants)): ?>
      <div style="margin-bottom:22px;">
        <div class="size-label" style="margin-bottom:10px;">
          SELECT COLOUR:
          <span id="colorSelectedLbl" style="color:var(--accent);font-weight:700;font-size:.8rem;margin-left:6px;text-transform:none;letter-spacing:0;"><?=e($first_color)?></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <!-- First/main colour -->
          <?php $fc_label = e($first_color); $fc_js = htmlspecialchars(addslashes($first_color), ENT_QUOTES); ?>
          <button type="button" class="color-swatch-btn color-active" id="colorBtn_0"
                  onclick="pickColor(this,0,'<?=$fc_js?>')" title="<?=$fc_label?>">
            <img src="<?=e($images[0]['image_url'])?>" style="width:30px;height:30px;border-radius:4px;object-fit:cover;border:1px solid var(--border);" alt="">
            <span style="font-size:.82rem;color:var(--muted);"><?=$fc_label?></span>
          </button>
          <?php foreach($images as $idx=>$img):
            if($idx===0) continue;
            $clabel_raw = !empty($img['color_name']) ? $img['color_name'] : 'Colour '.$idx;
            $clabel     = e($clabel_raw);
          ?>
          <button type="button" class="color-swatch-btn" id="colorBtn_<?=$idx?>"
                  onclick="pickColor(this,<?=$idx?>,'<?=htmlspecialchars(addslashes($clabel_raw),ENT_QUOTES)?>')" title="<?=$clabel?>">
            <img src="<?=e($img['image_url'])?>" style="width:30px;height:30px;border-radius:4px;object-fit:cover;border:1px solid var(--border);" alt="">
            <span style="font-size:.82rem;color:var(--muted);"><?=$clabel /* already e()-encoded */ ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── SIZE SELECTOR (updates dynamically when colour changes) ── -->
      <div class="size-label" style="margin-bottom:10px;">SELECT SIZE (UK)</div>
      <div class="uk-size-grid" id="sizeGrid">
        <?php
        // Render initial sizes for first colour
        $init_color = $first_color;
        $init_sizes = $stock_data[$init_color] ?? [];
        if(empty($init_sizes) && !empty($all_sizes)){
            // Show all sizes as OOS if no stock data for this colour
            foreach($all_sizes as $sz){
                $init_sizes[$sz] = 0;
            }
        }
        // Sort by numeric value so sizes display in correct ascending order
        uksort($init_sizes, fn($a,$b) => floatval($a) <=> floatval($b));
        if(!empty($init_sizes)):
            foreach($init_sizes as $sz => $stk):
                $oos = $stk < 1;
                $low = $stk > 0 && $stk <= 3;
        ?>
        <button type="button"
          class="uk-btn <?=$oos?'oos':''?> <?=$low&&!$oos?'low-stock':''?>"
          <?=$oos?'disabled':''?>
          onclick="pickSize(this,'<?=e($sz)?>')"
          title="UK <?=e($sz)?> — <?=$oos?'Out of stock':$stk.' pairs left'?>">
          <span class="uk-num"><?=e($sz)?></span>
          <span class="uk-lbl">UK</span>
          <?php if(!$oos): ?>
          <span class="sz-stock"><?=$stk?></span>
          <?php endif; ?>
        </button>
        <?php endforeach;
        else:
            // Fallback default UK range
            foreach(['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12'] as $s): ?>
        <button type="button" class="uk-btn" onclick="pickSize(this,'<?=$s?>')">
          <span class="uk-num"><?=$s?></span>
          <span class="uk-lbl">UK</span>
        </button>
        <?php endforeach; endif; ?>
      </div>

      <!-- Stock info bar -->
      <div class="stock-bar" id="stockBar" style="display:none;"></div>
      <div style="font-size:.78rem;color:var(--muted);margin-bottom:20px;" id="totalStockInfo">
        Total stock: <strong><?=(int)$product['stock']?> pairs</strong>
      </div>

      <?php if(is_admin()): ?>
      <button class="btn btn-secondary btn-full" disabled style="margin-bottom:12px;opacity:.5;cursor:not-allowed;">
        Admin — Purchase Disabled
      </button>
      <?php elseif($product['stock']>0): ?>
      <button type="submit" class="btn btn-primary btn-full" id="addCartBtn" disabled style="margin-bottom:12px;">
        SELECT A SIZE TO ADD TO CART
      </button>
      <?php else: ?>
      <button class="btn btn-secondary btn-full" disabled style="margin-bottom:12px;">OUT OF STOCK</button>
      <?php endif; ?>
    </form>

    <?php if($product['stock'] <= 0 && !is_admin()): ?>
    <!-- ── Back-in-stock alert ── -->
    <?php if(is_logged()): ?>
      <?php if($has_stock_alert): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;padding:12px 16px;border-radius:var(--radius);background:rgba(42,155,90,.08);border:1px solid rgba(42,155,90,.3);">
        <span style="font-size:.85rem;color:#2a9b5a;font-weight:600;">✓ You'll be emailed when this shoe is restocked.</span>
        <form method="POST" style="margin:0;">
          <?=csrf_field()?>
          <input type="hidden" name="stock_alert" value="off">
          <button type="submit" style="background:none;border:none;color:var(--muted);font-size:.75rem;cursor:pointer;text-decoration:underline;padding:0;">Cancel</button>
        </form>
      </div>
      <?php else: ?>
      <form method="POST" style="margin:0 0 12px;">
        <?=csrf_field()?>
        <input type="hidden" name="stock_alert" value="on">
        <button type="submit" class="btn btn-primary btn-full">
          &#128276; NOTIFY ME WHEN BACK IN STOCK
        </button>
      </form>
      <?php endif; ?>
    <?php else: ?>
    <a href="login.php" class="btn btn-primary btn-full" style="margin-bottom:12px;">
      &#128276; LOGIN TO GET RESTOCK ALERTS
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php
      // back_cat_url still used for the "View All" link in You May Also Like
      $back_cat_url = 'products.php?cat='.urlencode($product['category_name']);
      if($back_gender) $back_cat_url .= '&gender='.urlencode($back_gender);
    ?>
    <button type="button" class="btn btn-outline btn-full"
            onclick="if(document.referrer && document.referrer !== window.location.href){ history.back(); } else { window.location='products.php'; }">
      &#8592; Back
    </button>

    <?php if(is_logged() && !is_admin()): ?>
    <button class="btn btn-full wl-toggle-btn <?=$is_wishlisted?'wl-active':''?>"
            id="wlBtn" data-pid="<?=$pid?>"
            style="margin-top:10px;background:<?=$is_wishlisted?'rgba(239,68,68,.15)':'var(--card)'?>;border:1px solid <?=$is_wishlisted?'#ef4444':'var(--border)'?>;color:<?=$is_wishlisted?'#ef4444':'var(--muted)'?>;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;">
      <i class="fa-<?=$is_wishlisted?'solid':'regular'?> fa-heart"></i>
      <span><?=$is_wishlisted?'Saved to Wishlist':'Add to Wishlist'?></span>
    </button>
    <?php else: ?>
    <a href="login.php" class="btn btn-full"
       style="margin-top:10px;background:var(--card);border:1px solid var(--border);color:var(--muted);display:flex;align-items:center;justify-content:center;gap:8px;">
      <i class="fa-regular fa-heart"></i> <span>Login to save to Wishlist</span>
    </a>
    <?php endif; ?>

    <!-- Accordion -->
    <div style="margin-top:28px;border-top:1px solid var(--border);">
      <?php foreach([
        ['Free Shipping','Free standard shipping on orders above RM300. Estimated 2–4 business days.'],
        ['Returns Policy','30-day hassle-free returns on all unworn shoes in original packaging.'],
        ['Authenticity','Every Apex pair includes a certificate of authenticity and quality-control seal.'],
      ] as $i=>[$title,$body]): ?>
      <div style="border-bottom:1px solid var(--border);">
        <button onclick="toggleAcc(this)" style="display:flex;justify-content:space-between;width:100%;background:none;border:none;color:var(--text);padding:14px 0;font-size:.9rem;font-weight:500;cursor:pointer;">
          <?=e($title)?> <span class="acc-ico" style="font-size:1.1rem;color:var(--muted);">+</span>
        </button>
        <div style="display:<?=$i===0?'block':'none'?>;padding-bottom:14px;color:var(--muted);font-size:.875rem;line-height:1.75;"><?=e($body)?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
</div>

<!-- ── Customer Reviews ── -->
<section id="reviews" style="padding:56px 0;border-top:1px solid var(--border);">
  <div class="wrap" style="max-width:800px;">
    <div style="margin-bottom:32px;padding-bottom:14px;border-bottom:1px solid var(--border);">
      <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Verified Buyers</p>
      <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(20px,2.5vw,30px);letter-spacing:2px;color:var(--white);">
        CUSTOMER REVIEWS
        <?php if($rev_count > 0): ?>
        <span style="font-size:.9rem;color:var(--muted);font-weight:400;letter-spacing:0;margin-left:12px;"><?=$rev_count?> review<?=$rev_count!=1?'s':''?></span>
        <?php endif; ?>
      </h2>
      <?php if($rev_count > 0): ?>
      <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
        <div style="display:flex;gap:2px;">
          <?php for($s=1;$s<=5;$s++): $filled=$s<=round($rev_avg); ?>
          <span style="color:<?=$filled?'#f59e0b':'var(--border)'?>;font-size:1.4rem;">★</span>
          <?php endfor; ?>
        </div>
        <span style="font-family:'Oswald',sans-serif;font-size:1.8rem;color:var(--white);"><?=number_format($rev_avg,1)?></span>
        <span style="font-size:.78rem;color:var(--muted);">out of 5</span>
      </div>
      <?php endif; ?>
    </div>

    <?php if($reviews_res && $reviews_res->num_rows > 0):
      while($rv = $reviews_res->fetch_assoc()):
        $rstars = (int)$rv['rating'];
    ?>
    <div style="padding:20px 0;border-bottom:1px solid var(--border);">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div>
          <div style="font-weight:600;color:var(--white);font-size:.9rem;margin-bottom:4px;"><?=e($rv['user_name'])?></div>
          <div style="display:flex;gap:2px;margin-bottom:6px;">
            <?php for($s=1;$s<=5;$s++): ?>
            <span style="color:<?=$s<=$rstars?'#f59e0b':'var(--border)'?>;font-size:.9rem;">★</span>
            <?php endfor; ?>
            <span style="font-size:.75rem;color:var(--muted);margin-left:6px;font-weight:600;"><?=number_format($rstars,0)?>/5</span>
          </div>
        </div>
        <div style="font-size:.72rem;color:var(--muted);"><?=date('d M Y',strtotime($rv['created_at']))?></div>
      </div>
      <?php if(!empty($rv['comment'])): ?>
      <p style="font-size:.875rem;color:var(--text);line-height:1.75;margin:0;"><?=nl2br(e($rv['comment']))?></p>
      <?php else: ?>
      <p style="font-size:.82rem;color:var(--muted);font-style:italic;margin:0;">No comment left.</p>
      <?php endif; ?>
    </div>
    <?php endwhile; else: ?>
    <div style="text-align:center;padding:48px 20px;color:var(--muted);">
      <div style="font-size:.9rem;">No reviews yet. Purchase this product to leave a review!</div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php if($related_products && $related_products->num_rows > 0): ?>
<!-- ── You May Also Like ── -->
<section style="padding:56px 0;border-top:1px solid var(--border);">
  <div class="wrap">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:32px;padding-bottom:14px;border-bottom:1px solid var(--border);">
      <div>
        <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">From <?=e($product['category_name'])?></p>
        <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(20px,2.5vw,30px);letter-spacing:2px;color:var(--white);">YOU MAY ALSO LIKE</h2>
      </div>
      <a href="<?=$back_cat_url?>"
         style="font-size:.82rem;color:var(--muted);border-bottom:1px solid var(--border);padding-bottom:2px;transition:.2s;"
         onmouseover="this.style.color='var(--accent)';this.style.borderColor='var(--accent)'"
         onmouseout="this.style.color='var(--muted)';this.style.borderColor='var(--border)'">
        View All →
      </a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
      <?php while($rp = $related_products->fetch_assoc()):
        $rimg = !empty($rp['image_url'])
            ? (str_starts_with($rp['image_url'],'http') ? e($rp['image_url']) : e($rp['image_url']))
            : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';
        // Use pre-loaded wishlist map — no extra DB query per card
        $rp_wishlisted = isset($related_wishlisted[(int)$rp['product_id']]);
      ?>
      <div class="prod-card" style="position:relative;">
        <!-- Wishlist heart on card -->
        <?php if(is_logged()): ?>
        <button class="card-heart-btn <?=$rp_wishlisted?'card-heart-active':''?>"
                data-pid="<?=(int)$rp['product_id']?>"
                title="<?=$rp_wishlisted?'Remove from wishlist':'Add to wishlist'?>"
                style="position:absolute;top:10px;right:10px;z-index:2;background:rgba(10,25,47,.8);border:1px solid var(--border);color:<?=$rp_wishlisted?'#ef4444':'var(--muted)'?>;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.9rem;transition:all .2s;">
          <i class="fa-<?=$rp_wishlisted?'solid':'regular'?> fa-heart"></i>
        </button>
        <?php endif; ?>

        <a href="product_detail.php?id=<?=(int)$rp['product_id']?><?=$back_gender?'&gender='.urlencode($back_gender):''?>">
          <div class="prod-img">
            <img src="<?=$rimg?>" alt="<?=e($rp['name'])?>" loading="lazy">
            <span class="prod-badge"><?=e($rp['category_name'])?></span>
          </div>
        </a>
        <div class="prod-body">
          <div class="prod-cat"><?=e($rp['category_name'])?></div>
          <div class="prod-name"><a href="product_detail.php?id=<?=(int)$rp['product_id']?><?=$back_gender?'&gender='.urlencode($back_gender):''?>"><?=e($rp['name'])?></a></div>
          <div class="prod-footer">
            <?=price_html($rp['price'], (int)($rp['sale_percent']??0))?>
            <a href="product_detail.php?id=<?=(int)$rp['product_id']?><?=$back_gender?'&gender='.urlencode($back_gender):''?>" class="btn-view">View →</a>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script>
const _csrfToken = '<?=csrf_token()?>';

// ── Main product wishlist toggle ──
const wlBtn = document.getElementById('wlBtn');
if(wlBtn){
  wlBtn.addEventListener('click', function(){
    const pid = this.dataset.pid;
    fetch('wishlist_action.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'product_id='+pid+'&csrf_token='+encodeURIComponent(_csrfToken)
    })
    .then(r=>r.json())
    .then(d=>{
      if(d.status==='error'){ alert(d.msg); return; }
      const icon = this.querySelector('i');
      const span = this.querySelector('span');
      if(d.wishlisted){
        icon.className='fa-solid fa-heart';
        span.textContent='Saved to Wishlist';
        this.style.background='rgba(239,68,68,.15)';
        this.style.borderColor='#ef4444';
        this.style.color='#ef4444';
      } else {
        icon.className='fa-regular fa-heart';
        span.textContent='Add to Wishlist';
        this.style.background='var(--card)';
        this.style.borderColor='var(--border)';
        this.style.color='var(--muted)';
      }
    });
  });
}

// ── Card heart buttons (related products) ──
document.querySelectorAll('.card-heart-btn').forEach(btn=>{
  btn.addEventListener('click', function(e){
    e.preventDefault(); e.stopPropagation();
    const pid = this.dataset.pid;
    fetch('wishlist_action.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'product_id='+pid+'&csrf_token='+encodeURIComponent(_csrfToken)
    })
    .then(r=>r.json())
    .then(d=>{
      if(d.status==='error'){ return; }
      const icon = this.querySelector('i');
      if(d.wishlisted){
        icon.className='fa-solid fa-heart';
        this.style.color='#ef4444';
        this.title='Remove from wishlist';
      } else {
        icon.className='fa-regular fa-heart';
        this.style.color='var(--muted)';
        this.title='Add to wishlist';
      }
    });
  });
});
</script>

<script>
// ── All image data ──
const imgs     = <?=json_encode(array_values($images))?>;
// ── All stock data: { colorName: { size: stock } } ──
const stockAll = <?=json_encode($stock_data)?>;
// ── All unique sizes across every colour (sorted) — used to keep grid complete on colour switch ──
const allSizes = <?=json_encode(array_values($all_sizes))?>;
let cur         = 0;
let activeColor = <?=json_encode($first_color)?>;
let activeSize  = null;

// ── Slider ──
function goTo(i){
    if(i<0||i>=imgs.length) return;
    cur=i;
    const mainImg=document.getElementById('mainImg');
    const counter=document.getElementById('slCounter');
    const prevBtn=document.getElementById('prevBtn');
    const nextBtn=document.getElementById('nextBtn');
    mainImg.style.opacity='0';
    setTimeout(()=>{ mainImg.src=imgs[cur].image_url; mainImg.style.opacity='1'; },200);
    if(counter) counter.textContent=(cur+1)+' / '+imgs.length;
    if(prevBtn) prevBtn.disabled=(cur===0);
    if(nextBtn) nextBtn.disabled=(cur===imgs.length-1);
    document.querySelectorAll('.thumb-img').forEach((t,idx)=>t.classList.toggle('on',idx===cur));
}
function slide(dir){ goTo(cur+dir); }

// ── Colour selection ──
function pickColor(btn, slideIdx, colorName){
    document.querySelectorAll('.color-swatch-btn').forEach(b=>b.classList.remove('color-active'));
    btn.classList.add('color-active');
    activeColor = colorName;
    activeSize  = null;
    document.getElementById('colorInput').value = colorName;
    document.getElementById('colorSelectedLbl').textContent = colorName;
    goTo(slideIdx);
    updateSizeGrid(colorName);
    updateAddBtn();
}

// ── Update size grid based on selected colour ──
function updateSizeGrid(colorName){
    const grid    = document.getElementById('sizeGrid');
    const stockForColor = stockAll[colorName] || {};

    if(Object.keys(stockForColor).length === 0){
        // No stock data — fetch from server
        fetch('get_size_stock.php?product_id=<?=$pid?>&color='+encodeURIComponent(colorName))
            .then(r=>r.json())
            .then(data=>{
                if(data.length>0){
                    const obj={};
                    data.forEach(r=>{ obj[r.size]=parseInt(r.stock); });
                    stockAll[colorName]=obj;
                    renderSizes(grid, obj);
                } else {
                    grid.innerHTML='<div style="color:var(--muted);font-size:.82rem;padding:10px 0;">No sizes set for this colour yet.<br><small>Admin: go to Edit Product → Stock Per Colour & Size to add stock.</small></div>';
                }
            });
        return;
    }
    renderSizes(grid, stockForColor);
}

function renderSizes(grid, stockForColor){
    grid.innerHTML='';
    // Always show every size that exists across all colours — mark unavailable for this colour as OOS
    const sizesToShow = allSizes.length > 0
        ? allSizes
        : Object.keys(stockForColor).sort((a,b)=>parseFloat(a)-parseFloat(b));
    sizesToShow.forEach(sz=>{
        const raw = stockForColor[sz];
        const stk = (raw !== undefined && raw !== null) ? parseInt(raw) : 0;
        const oos = stk < 1;
        const low = stk > 0 && stk <= 3;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'uk-btn' + (oos?' oos':'') + (low&&!oos?' low-stock':'');
        btn.disabled = oos;
        btn.title = 'UK '+sz+' — '+(oos?'Out of stock':stk+' pairs left');
        btn.innerHTML = `<span class="uk-num">${sz}</span><span class="uk-lbl">UK</span>`
                      + (!oos ? `<span class="sz-stock">${stk}</span>` : '');
        if(!oos) btn.onclick = ()=>{ pickSize(btn, sz); };
        grid.appendChild(btn);
    });
    if(sizesToShow.length===0){
        grid.innerHTML='<span style="color:var(--muted);font-size:.875rem;">No stock data for this colour.</span>';
    }
}

// ── Size selection ──
function pickSize(btn, size){
    if(btn.disabled) return;
    document.querySelectorAll('.uk-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    activeSize = size;
    document.getElementById('sizeInput').value = size;

    // Show stock info bar
    const stk = (stockAll[activeColor]||{})[size];
    const bar = document.getElementById('stockBar');
    bar.style.display='block';
    if(stk === undefined || stk === null){
        bar.className='stock-bar'; bar.textContent='Stock info not set up yet for this colour.';
    } else if(stk===0){
        bar.className='stock-bar out'; bar.textContent='Out of stock for this size.';
    } else if(stk<=3){
        bar.className='stock-bar low'; bar.textContent='Only '+stk+' pairs left for UK '+size+' in '+activeColor+'!';
    } else {
        bar.className='stock-bar ok'; bar.textContent=stk+' pairs available — UK '+size+', '+activeColor;
    }
    updateAddBtn();
}

// ── Enable/disable Add to Cart button ──
function updateAddBtn(){
    const btn  = document.getElementById('addCartBtn');
    if(!btn) return;
    const hasVariants = imgs.length > 1;
    if(hasVariants && activeSize){
        btn.disabled=false; btn.textContent='ADD TO CART';
    } else if(!hasVariants && activeSize){
        btn.disabled=false; btn.textContent='ADD TO CART';
    } else if(hasVariants && !activeSize){
        btn.disabled=true; btn.textContent='SELECT A SIZE';
    } else {
        btn.disabled=true; btn.textContent='SELECT A SIZE';
    }
}

// Accordion
function toggleAcc(btn){
    const body=btn.nextElementSibling;
    const ico=btn.querySelector('.acc-ico');
    const open=body.style.display==='block';
    body.style.display=open?'none':'block';
    ico.textContent=open?'+':'−';
}

// Auto-select first available size on load
document.addEventListener('DOMContentLoaded',()=>{
    const firstAvail = document.querySelector('.uk-btn:not(.oos):not(:disabled)');
    if(firstAvail) firstAvail.click();
});
</script>
