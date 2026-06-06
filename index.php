<?php
session_start();
require 'db.php';

// Hero background — use Apex Gen Fly image from DB, fallback to first active product
$hero_row = $conn->query("SELECT image_url FROM products WHERE is_active=1 AND name LIKE '%Gen Fly%' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
if(empty($hero_row['image_url'])){
    $hero_row = $conn->query("SELECT image_url FROM products WHERE is_active=1 AND image_url != '' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
}
$hero_bg = !empty($hero_row['image_url']) ? $hero_row['image_url'] : '';

// New Arrivals — latest 12 active products
$featured = $conn->query("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC LIMIT 12
");

// Shop by Category — latest active product image per category
$cat_showcase = $conn->query("
    SELECT c.category_id, c.category_name,
           p.product_id, p.name AS prod_name, p.price, p.image_url
    FROM categories c
    LEFT JOIN products p ON p.product_id = (
        SELECT product_id FROM products
        WHERE category_id = c.category_id AND is_active = 1
        ORDER BY created_at DESC LIMIT 1
    )
    ORDER BY c.category_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apex | Premium Sport Shoes</title>
<link rel="stylesheet" href="css/style.css?v=4">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#F5E8DC 0%,#FDF0E8 50%,#F8E8DF 100%);min-height:86vh;display:flex;align-items:center;overflow:hidden;position:relative;">
  <?php if($hero_bg): ?>
  <div style="position:absolute;top:0;right:0;width:52%;height:100%;background:url('<?=e($hero_bg)?>') center/cover;opacity:.55;"></div>
  <div style="position:absolute;top:0;right:0;width:52%;height:100%;background:linear-gradient(to right,#F5E8DC 0%,transparent 45%);"></div>
  <?php endif; ?>
  <div class="wrap" style="position:relative;z-index:1;">
    <div style="max-width:600px;">
      <p style="font-size:.7rem;letter-spacing:4px;text-transform:uppercase;color:#C8543C;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
        <span style="width:30px;height:2px;background:#C8543C;display:inline-block;"></span>
        New Season 2026
      </p>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(52px,7vw,96px);line-height:.9;letter-spacing:-1px;color:#1C1410;margin-bottom:24px;">
        BUILT<br>TO <span style="color:#C8543C;">WIN.</span>
      </h1>
      <p style="font-size:1.05rem;color:#2E1E18;max-width:420px;margin-bottom:40px;line-height:1.75;">
        Premium athletic footwear engineered for the court, the track, and the streets. No excuses — just performance.
      </p>
      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <a href="products.php" class="btn btn-primary btn-lg">Shop All Styles</a>
        <a href="products.php?gender=Men" class="btn btn-lg" style="background:#1C1410;color:#FDF7F2;border:none;font-family:'Oswald',sans-serif;font-weight:700;letter-spacing:1.5px;">Men's →</a>
      </div>
      <div style="display:flex;gap:40px;margin-top:52px;padding-top:36px;border-top:1px solid rgba(150,100,75,.2);">
        <?php foreach([['10+','Models'],['3','Collections'],['RM259','Starting From']] as $s): ?>
        <div>
          <div style="font-family:'Oswald',sans-serif;font-size:1.8rem;color:#C8543C;"><?=e($s[0])?></div>
          <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:#4A3028;margin-top:3px;"><?=e($s[1])?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- New Arrivals -->
<section class="section" style="background:var(--navy2);padding:60px 0;">
  <div class="wrap">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:36px;padding-bottom:16px;border-bottom:1px solid var(--border);">
      <div>
        <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:#9C8B85;margin-bottom:6px;">Fresh Drops</p>
        <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,3vw,38px);letter-spacing:2px;color:#1C1410;">NEW ARRIVALS</h2>
      </div>
      <a href="products.php" style="font-size:.875rem;color:var(--muted);border-bottom:1px solid var(--border);padding-bottom:2px;transition:.2s;"
         onmouseover="this.style.color='var(--accent)';this.style.borderColor='var(--accent)'"
         onmouseout="this.style.color='var(--muted)';this.style.borderColor='var(--border)'">
        View All →
      </a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
      <?php if($featured && $featured->num_rows > 0):
        while($p = $featured->fetch_assoc()):
          $img = !empty($p['image_url']) ? e($p['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';
      ?>
      <div class="prod-card">
        <a href="product_detail.php?id=<?=(int)$p['product_id']?>">
          <div class="prod-img">
            <img src="<?=$img?>" alt="<?=e($p['name'])?>" loading="lazy">
            <span class="prod-badge"><?=e($p['category_name'])?></span>
          </div>
        </a>
        <div class="prod-body">
          <div class="prod-cat"><?=e($p['category_name'])?></div>
          <div class="prod-name"><a href="product_detail.php?id=<?=(int)$p['product_id']?>"><?=e($p['name'])?></a></div>
          <div class="prod-footer">
            <span class="prod-price">RM <?=number_format($p['price'],2)?></span>
            <a href="product_detail.php?id=<?=(int)$p['product_id']?>" class="btn-view">View →</a>
          </div>
        </div>
      </div>
      <?php endwhile; else: ?>
      <p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:40px 0;">
        No products yet. <a href="admin/admin_products.php" style="color:var(--accent);font-weight:600;">Add some in Admin →</a>
      </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Shop by Category -->
<?php if($cat_showcase && $cat_showcase->num_rows > 0): ?>
<section style="padding:60px 0;border-top:1px solid var(--border);">
  <div class="wrap">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:32px;padding-bottom:14px;border-bottom:1px solid var(--border);">
      <div>
        <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:#9C8B85;margin-bottom:6px;">Browse By</p>
        <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(22px,3vw,36px);letter-spacing:2px;color:#1C1410;">SHOP BY CATEGORY</h2>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
      <?php while($cs = $cat_showcase->fetch_assoc()):
        $cimg = !empty($cs['image_url'])
            ? (str_starts_with($cs['image_url'],'http') ? e($cs['image_url']) : e($cs['image_url']))
            : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=70';
      ?>
      <a href="products.php?cat=<?=urlencode($cs['category_name'])?>"
         style="display:block;position:relative;border-radius:14px;overflow:hidden;border:1px solid var(--border);text-decoration:none;aspect-ratio:3/2;transition:all .25s;"
         onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-4px)'"
         onmouseout="this.style.borderColor='var(--border)';this.style.transform='none'">
        <div style="position:absolute;inset:0;background:url('<?=$cimg?>') center/cover;"></div>
        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(28,20,16,.85) 0%,rgba(28,20,16,.2) 60%,transparent 100%);"></div>
        <div style="position:absolute;bottom:0;left:0;right:0;padding:20px 18px;">
          <div style="font-family:'Oswald',sans-serif;font-size:1.15rem;letter-spacing:3px;color:#FDF7F2;text-transform:uppercase;"><?=e($cs['category_name'])?></div>
          <div style="font-size:.72rem;color:var(--accent);margin-top:4px;letter-spacing:1px;">Shop Now →</div>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Why Apex -->
<section style="padding:72px 0;border-top:1px solid var(--border);background:var(--navy);">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border);">
      <?php foreach([
        ['HyperFoam™ Tech','Proprietary midsole returning 68% energy per stride.'],
        ['5-Year Sole Warranty','Premium durability on every pair, guaranteed.'],
        ['Free Shipping >RM300','Fast 2–4 day delivery across Malaysia.'],
        ['30-Day Returns','Hassle-free returns within 30 days.'],
      ] as $f): ?>
      <div style="background:var(--card);padding:36px 28px;text-align:center;">
        <div style="width:36px;height:2px;background:#C8543C;margin:0 auto 20px;"></div>
        <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:#1C1410;margin-bottom:10px;"><?=e($f[0])?></div>
        <div style="font-size:.82rem;color:#4A3028;line-height:1.75;"><?=e($f[1])?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
