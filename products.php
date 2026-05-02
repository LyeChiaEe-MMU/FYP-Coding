<?php
session_start();
require 'db.php';

$cat  = $_GET['cat']  ?? '';
$sort = $_GET['sort'] ?? 'newest';

$where = "WHERE 1=1";
if ($cat) {
    $cat_safe = $conn->real_escape_string($cat);
    $where .= " AND c.category_name = '$cat_safe'";
}

$order = 'p.created_at DESC';
if ($sort === 'price_asc')  $order = 'p.price ASC';
if ($sort === 'price_desc') $order = 'p.price DESC';

$products   = $conn->query("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id = c.category_id $where ORDER BY $order");
$categories = $conn->query("SELECT DISTINCT category_name FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Shop All | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div style="background:var(--navy2);border-bottom:1px solid var(--border);padding:40px 0;">
  <div class="wrap">
    <div class="breadcrumb">
      <a href="index.php">Home</a><span class="sep">/</span>
      <span>Shop<?php if ($cat) echo ' / ' . e($cat); ?></span>
    </div>
    <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(28px,4vw,48px);letter-spacing:2px;color:var(--white);">
      <?php if ($cat): ?>
        <?php echo strtoupper(e($cat)); ?>
      <?php else: ?>
        ALL <span style="color:var(--accent)">SHOES</span>
      <?php endif; ?>
    </h1>
    <?php if ($products): ?>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;">
        <?php echo $products->num_rows; ?> style<?php echo $products->num_rows != 1 ? 's' : ''; ?> found
      </p>
    <?php endif; ?>
  </div>
</div>

<section class="section">
  <div class="wrap">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:36px;">
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
        $allActive = !$cat ? 'var(--accent)' : 'transparent';
        $allColor  = !$cat ? 'var(--navy)' : 'var(--muted)';
        $allBorder = !$cat ? 'var(--accent)' : 'var(--border)';
        $allWeight = !$cat ? '700' : '500';
        ?>
        <a href="products.php"
           style="padding:8px 20px;border-radius:100px;border:1px solid <?php echo $allBorder; ?>;color:<?php echo $allColor; ?>;background:<?php echo $allActive; ?>;font-size:.82rem;font-weight:<?php echo $allWeight; ?>;transition:.2s;">
          All
        </a>
        <?php
        $categories->data_seek(0);
        while ($c = $categories->fetch_assoc()):
            $active = ($cat === $c['category_name']);
            $bg     = $active ? 'var(--accent)' : 'transparent';
            $color  = $active ? 'var(--navy)'   : 'var(--muted)';
            $border = $active ? 'var(--accent)'  : 'var(--border)';
            $fw     = $active ? '700' : '500';
        ?>
        <a href="products.php?cat=<?php echo urlencode($c['category_name']); ?>&sort=<?php echo e($sort); ?>"
           style="padding:8px 20px;border-radius:100px;border:1px solid <?php echo $border; ?>;color:<?php echo $color; ?>;background:<?php echo $bg; ?>;font-size:.82rem;font-weight:<?php echo $fw; ?>;transition:.2s;">
          <?php echo e($c['category_name']); ?>
        </a>
        <?php endwhile; ?>
      </div>

      <form method="GET">
        <?php if ($cat): ?>
          <input type="hidden" name="cat" value="<?php echo e($cat); ?>">
        <?php endif; ?>
        <select name="sort" onchange="this.form.submit()"
          style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:8px 14px;color:var(--text);font-size:.82rem;cursor:pointer;">
          <option value="newest"     <?php echo $sort === 'newest'     ? 'selected' : ''; ?>>Newest</option>
          <option value="price_asc"  <?php echo $sort === 'price_asc'  ? 'selected' : ''; ?>>Price: Low-High</option>
          <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High-Low</option>
        </select>
      </form>
    </div>

    <?php if (!$products || $products->num_rows === 0): ?>
      <div style="text-align:center;padding:80px 0;">
        <div style="font-size:3rem;margin-bottom:16px;">👟</div>
        <h3 style="font-family:'Oswald',sans-serif;font-size:1.3rem;color:var(--white);margin-bottom:8px;">No Shoes Found</h3>
        <p style="color:var(--muted);margin-bottom:24px;">Try a different category or add products in Admin.</p>
        <a href="products.php" class="btn btn-primary">View All</a>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
        <?php while ($p = $products->fetch_assoc()):
          $img = !empty($p['image_url']) ? e($p['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';
        ?>
        <div class="prod-card">
          <a href="product_detail.php?id=<?php echo (int)$p['product_id']; ?>">
            <div class="prod-img">
              <img src="<?php echo $img; ?>" alt="<?php echo e($p['name']); ?>" loading="lazy">
              <span class="prod-badge"><?php echo e($p['category_name']); ?></span>
            </div>
          </a>
          <div class="prod-body">
            <div class="prod-cat"><?php echo e($p['category_name']); ?></div>
            <div class="prod-name">
              <a href="product_detail.php?id=<?php echo (int)$p['product_id']; ?>"><?php echo e($p['name']); ?></a>
            </div>
            <div class="prod-footer">
              <span class="prod-price">RM <?php echo number_format($p['price'], 2); ?></span>
              <a href="product_detail.php?id=<?php echo (int)$p['product_id']; ?>" class="btn-view">View</a>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php include 'includes/footer.php'; ?>
