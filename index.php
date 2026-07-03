<?php
session_start();
require 'db.php';

// Hero background — managed by admin via Admin › Banners
$hero_bg = '';
$_hb = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='hero_image'");
if($_hb && $_hbr = $_hb->fetch_assoc()) $hero_bg = $_hbr['setting_value'] ?? '';

// New Arrivals — latest 12 active, in-stock products only (with total_sold for NEW badge check)
$featured = $conn->query("
    SELECT p.*, c.category_name, COALESCE(SUM(oi.quantity),0) AS total_sold,
           (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.product_id = p.product_id) AS avg_rating,
           (SELECT COUNT(*) FROM reviews r2 WHERE r2.product_id = p.product_id) AS review_count
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN order_items oi ON oi.product_id = p.product_id
    WHERE p.is_active = 1 AND p.stock > 0
    GROUP BY p.product_id
    ORDER BY p.created_at DESC LIMIT 12
");

// Category sections — top 4 newest per category (active only)
$featured_by_cat = [];
$_af = $conn->query("
    SELECT p.*, c.category_name, COALESCE(SUM(oi.quantity),0) AS total_sold,
           (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.product_id = p.product_id) AS avg_rating,
           (SELECT COUNT(*) FROM reviews r2 WHERE r2.product_id = p.product_id) AS review_count
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN order_items oi ON oi.product_id = p.product_id
    WHERE p.is_active = 1 AND p.stock > 0
    GROUP BY p.product_id
    ORDER BY c.category_name ASC, p.created_at DESC
");
if ($_af) {
    while ($row = $_af->fetch_assoc()) {
        $cn = $row['category_name'];
        if (!isset($featured_by_cat[$cn])) $featured_by_cat[$cn] = [];
        if (count($featured_by_cat[$cn]) < 4) $featured_by_cat[$cn][] = $row;
    }
}


// Hero stats — auto-calculated
$stat_models     = 0;
$stat_collections = 0;
$stat_min_price  = 0;
$sr = $conn->query("SELECT COUNT(*) AS total, MIN(price) AS min_price FROM products WHERE is_active=1");
if($sr){ $row = $sr->fetch_assoc(); $stat_models = (int)$row['total']; $stat_min_price = (float)$row['min_price']; }
$cr = $conn->query("SELECT COUNT(DISTINCT category_id) AS total FROM products WHERE is_active=1");
if($cr){ $stat_collections = (int)$cr->fetch_assoc()['total']; }
$stat_models_label     = $stat_models > 0 ? $stat_models.'+' : '—';
$stat_collections_label = $stat_collections > 0 ? $stat_collections : '—';
$stat_price_label      = $stat_min_price > 0 ? 'RM'.number_format($stat_min_price, 0) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apex | Premium Sport Shoes</title>
<link rel="stylesheet" href="css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Hero animations -->
<style>
@keyframes heroSlide {
  from { opacity:0; transform:translateY(28px); }
  to   { opacity:1; transform:translateY(0); }
}
@keyframes heroShoe {
  from { opacity:0; transform:translateX(55px) scale(.97); }
  to   { opacity:1; transform:translateX(0) scale(1); }
}
</style>

<!-- Hero -->
<section style="background:#F5ECE4;min-height:100vh;display:flex;align-items:center;overflow:hidden;position:relative;">

  <!-- Shoe image: toe fades in, heel 100%; bottom edge fades out -->
  <?php if($hero_bg): ?>
  <div style="position:absolute;top:0;right:0;bottom:0;width:65%;
              background:url('<?=e($hero_bg)?>') right center/contain no-repeat;
              mix-blend-mode:multiply;
              -webkit-mask-image:linear-gradient(to bottom,black 48%,transparent 86%),linear-gradient(to right,transparent 0%,black 48%);
              -webkit-mask-composite:source-in;
              mask-image:linear-gradient(to bottom,black 48%,transparent 86%),linear-gradient(to right,transparent 0%,black 48%);
              mask-composite:intersect;
              animation:heroShoe .95s cubic-bezier(.22,1,.36,1) .1s both;"></div>
  <?php endif; ?>

  <!-- Text: main focus, large and dominant -->
  <div class="wrap" style="position:relative;z-index:2;width:100%;">
    <div style="max-width:680px;">
      <p style="font-size:.75rem;letter-spacing:5px;text-transform:uppercase;color:#C8543C;margin-bottom:24px;display:flex;align-items:center;gap:10px;animation:heroSlide .6s ease .05s both;">
        <span style="width:36px;height:2px;background:#C8543C;display:inline-block;"></span>
        New Season 2026
      </p>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(72px,10vw,138px);line-height:.88;letter-spacing:-2px;color:#1C1410;margin-bottom:28px;animation:heroSlide .65s ease .18s both;">
        BUILT<br>TO <span style="color:#C8543C;">WIN.</span>
      </h1>
      <p style="font-size:1.15rem;color:#2E1E18;max-width:500px;margin-bottom:44px;line-height:1.8;animation:heroSlide .65s ease .32s both;">
        Premium athletic footwear engineered for the court, the track, and the streets. No excuses — just performance.
      </p>
      <div style="display:flex;flex-direction:column;gap:16px;animation:heroSlide .65s ease .44s both;">
        <a href="products.php" class="btn btn-primary btn-lg" style="align-self:flex-start;">Shop All Styles</a>
        <div style="display:flex;gap:24px;">
          <a href="products.php?gender=Men" style="font-family:'Oswald',sans-serif;font-size:.8rem;letter-spacing:2.5px;color:#1C1410;text-transform:uppercase;text-decoration:none;border-bottom:1px solid #1C1410;padding-bottom:2px;transition:.2s;"
             onmouseover="this.style.color='#C8543C';this.style.borderColor='#C8543C'"
             onmouseout="this.style.color='#1C1410';this.style.borderColor='#1C1410'">Men's →</a>
          <a href="products.php?gender=Women" style="font-family:'Oswald',sans-serif;font-size:.8rem;letter-spacing:2.5px;color:#1C1410;text-transform:uppercase;text-decoration:none;border-bottom:1px solid #1C1410;padding-bottom:2px;transition:.2s;"
             onmouseover="this.style.color='#C8543C';this.style.borderColor='#C8543C'"
             onmouseout="this.style.color='#1C1410';this.style.borderColor='#1C1410'">Women's →</a>
          <a href="products.php?gender=Kids" style="font-family:'Oswald',sans-serif;font-size:.8rem;letter-spacing:2.5px;color:#1C1410;text-transform:uppercase;text-decoration:none;border-bottom:1px solid #1C1410;padding-bottom:2px;transition:.2s;"
             onmouseover="this.style.color='#C8543C';this.style.borderColor='#C8543C'"
             onmouseout="this.style.color='#1C1410';this.style.borderColor='#1C1410'">Kids' →</a>
        </div>
      </div>
      <div style="display:flex;gap:40px;margin-top:52px;padding-top:36px;border-top:1px solid rgba(150,100,75,.2);animation:heroSlide .65s ease .58s both;">
        <?php foreach([
          [$stat_models_label,     'Models'],
          [$stat_collections_label,'Collections'],
          [$stat_price_label,      'Starting From'],
        ] as $s): ?>
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
          $img   = !empty($p['image_url']) ? e($p['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';
          $isNew = date('Y-m', strtotime($p['created_at'])) === date('Y-m'); // NEW only during upload month
      ?>
      <?php $sp_pct = (int)($p['sale_percent'] ?? 0); ?>
      <div class="prod-card">
        <a href="product_detail.php?id=<?=(int)$p['product_id']?>">
          <div class="prod-img">
            <img src="<?=$img?>" alt="<?=e($p['name'])?>" loading="lazy">
            <span class="prod-badge"><?=e($p['category_name'])?></span>
            <?php if($sp_pct > 0): ?>
            <span style="position:absolute;top:10px;right:10px;background:var(--danger);color:#fff;font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;"><?=$sp_pct?>% OFF</span>
            <?php elseif($isNew): ?>
            <span style="position:absolute;top:10px;right:10px;background:var(--success);color:#fff;font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;">NEW</span>
            <?php endif; ?>
          </div>
        </a>
        <div class="prod-body">
          <div class="prod-cat"><?=e($p['category_name'])?></div>
          <div class="prod-name"><a href="product_detail.php?id=<?=(int)$p['product_id']?>"><?=e($p['name'])?></a></div>
          <?=star_rating_html($p['avg_rating'] ?? 0, $p['review_count'] ?? 0)?>
          <div class="prod-footer">
            <?=price_html($p['price'], $sp_pct)?>
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

<!-- Category Sections — top 4 per category, each its own section -->
<?php foreach ($featured_by_cat as $catName => $catProducts): ?>
<section style="background:var(--navy);padding:60px 0;border-top:1px solid var(--border);">
  <div class="wrap">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:36px;padding-bottom:16px;border-bottom:1px solid var(--border);">
      <div>
        <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:#9C8B85;margin-bottom:6px;">Shop By Style</p>
        <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,3vw,38px);letter-spacing:2px;color:#1C1410;">
          <?=strtoupper(e($catName))?> <span style="color:var(--accent)">SHOES</span>
        </h2>
      </div>
      <a href="products.php?cat=<?=urlencode($catName)?>"
         style="font-size:.875rem;color:var(--muted);border-bottom:1px solid var(--border);padding-bottom:2px;transition:.2s;"
         onmouseover="this.style.color='var(--accent)';this.style.borderColor='var(--accent)'"
         onmouseout="this.style.color='var(--muted)';this.style.borderColor='var(--border)'">
        View All <?=e($catName)?> →
      </a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
      <?php foreach ($catProducts as $p):
        $img    = !empty($p['image_url']) ? e($p['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';
        $isNew  = date('Y-m', strtotime($p['created_at'])) === date('Y-m'); // NEW only during upload month
        $sp_pct = (int)($p['sale_percent'] ?? 0);
      ?>
      <div class="prod-card">
        <a href="product_detail.php?id=<?=(int)$p['product_id']?>">
          <div class="prod-img">
            <img src="<?=$img?>" alt="<?=e($p['name'])?>" loading="lazy">
            <span class="prod-badge"><?=e($p['category_name'])?></span>
            <?php if($sp_pct > 0): ?>
            <span style="position:absolute;top:10px;right:10px;background:var(--danger);color:#fff;font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;"><?=$sp_pct?>% OFF</span>
            <?php elseif($isNew): ?>
            <span style="position:absolute;top:10px;right:10px;background:var(--success);color:#fff;font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;">NEW</span>
            <?php endif; ?>
          </div>
        </a>
        <div class="prod-body">
          <div class="prod-cat"><?=e($p['category_name'])?></div>
          <div class="prod-name"><a href="product_detail.php?id=<?=(int)$p['product_id']?>"><?=e($p['name'])?></a></div>
          <?=star_rating_html($p['avg_rating'] ?? 0, $p['review_count'] ?? 0)?>
          <div class="prod-footer">
            <?=price_html($p['price'], $sp_pct)?>
            <a href="product_detail.php?id=<?=(int)$p['product_id']?>" class="btn-view">View →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>


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
