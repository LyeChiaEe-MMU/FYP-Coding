<?php
session_start();
require 'db.php';

// Featured products
$featured = $conn->query("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.created_at DESC LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apex | Premium Sport Shoes</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#0a192f 0%,#112240 50%,#1d3461 100%);min-height:88vh;display:flex;align-items:center;overflow:hidden;position:relative;">
  <div style="position:absolute;top:0;right:0;width:50%;height:100%;background:url('https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=900&q=80') center/cover;opacity:.18;"></div>
  <div class="wrap" style="position:relative;z-index:1;">
    <div style="max-width:600px;">
      <p style="font-size:.7rem;letter-spacing:4px;text-transform:uppercase;color:var(--accent);margin-bottom:20px;display:flex;align-items:center;gap:8px;">
        <span style="width:30px;height:1px;background:var(--accent);display:inline-block;"></span>
        New Season 2026
      </p>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(52px,7vw,96px);line-height:.9;letter-spacing:-1px;color:var(--white);margin-bottom:24px;">
        BUILT<br>TO <span style="color:var(--accent);">WIN.</span>
      </h1>
      <p style="font-size:1.05rem;color:var(--muted);max-width:420px;margin-bottom:40px;line-height:1.75;">
        Premium athletic footwear engineered for the court, the track, and the streets. No excuses — just performance.
      </p>
      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <a href="products.php" class="btn btn-primary btn-lg">Shop All Styles</a>
        <a href="products.php?gender=Men" class="btn btn-outline btn-lg">Men's →</a>
      </div>
      <div style="display:flex;gap:40px;margin-top:52px;padding-top:36px;border-top:1px solid var(--border);">
        <?php foreach([['10+','Models'],['3','Collections'],['RM259','Starting From']] as $s): ?>
        <div>
          <div style="font-family:'Oswald',sans-serif;font-size:1.8rem;color:var(--accent);"><?=e($s[0])?></div>
          <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-top:3px;"><?=e($s[1])?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- New Arrivals -->
<section class="section" style="background:rgba(17,34,64,.4);padding:60px 0;">
  <div class="wrap">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:36px;padding-bottom:16px;border-bottom:1px solid var(--border);">
      <div>
        <p style="font-size:.68rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Fresh Drops</p>
        <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,3vw,38px);letter-spacing:2px;color:var(--white);">NEW ARRIVALS</h2>
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
        No products yet. <a href="admin/admin_products.php" style="color:var(--accent);">Add some in Admin →</a>
      </p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Why Apex -->
<section style="padding:72px 0;border-top:1px solid var(--border);">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border);">
      <?php foreach([
        ['HyperFoam™ Tech','Proprietary midsole returning 68% energy per stride.'],
        ['5-Year Sole Warranty','Premium durability on every pair, guaranteed.'],
        ['Free Shipping >RM300','Fast 2–4 day delivery across Malaysia.'],
        ['30-Day Returns','Hassle-free returns within 30 days.'],
      ] as $f): ?>
      <div style="background:var(--navy);padding:36px 28px;text-align:center;">
        <div style="width:36px;height:2px;background:var(--accent);margin:0 auto 20px;"></div>
        <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);margin-bottom:10px;"><?=e($f[0])?></div>
        <div style="font-size:.82rem;color:var(--muted);line-height:1.75;"><?=e($f[1])?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
