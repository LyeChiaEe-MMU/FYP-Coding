<?php
session_start();
require 'db.php';

$cat    = $_GET['cat']    ?? '';
$sort   = $_GET['sort']   ?? 'newest';
$q      = trim($_GET['q'] ?? '');
$gender = $_GET['gender'] ?? '';
$sale   = isset($_GET['sale']) ? 1 : 0;   // sale filter flag

// ── Build WHERE ──────────────────────────────────────────────
$conditions = []; // show all products incl. unavailable — filtered only on homepage

// Category filter
if ($cat) {
    $cat_safe = $conn->real_escape_string($cat);
    $conditions[] = "c.category_name = '$cat_safe'";
}

// Gender filter — Men/Women/Kids show their own + Unisex products
$allowed_genders = ['Men','Women','Kids'];
if ($gender && in_array($gender, $allowed_genders)) {
    $g_safe = $conn->real_escape_string($gender);
    $conditions[] = "(p.gender = '$g_safe' OR p.gender = 'Unisex')";
}

// Sale filter
if ($sale) {
    $conditions[] = "p.is_on_sale = 1";
}

// Keyword search — split on spaces, every word must match
if ($q) {
    $keywords = preg_split('/\s+/', $q);
    $keywords = array_filter($keywords, fn($w) => strlen($w) >= 1);
    foreach ($keywords as $word) {
        $safe = $conn->real_escape_string($word);
        $conditions[] = "(p.name LIKE '%$safe%' OR c.category_name LIKE '%$safe%' OR p.description LIKE '%$safe%')";
    }
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ── Sort ─────────────────────────────────────────────────────
$order        = 'p.is_active DESC, p.created_at DESC';
$extra_select = '';
$extra_join   = '';
$group_by     = '';

if ($sort === 'price_asc')  $order = 'p.is_active DESC, ROUND(p.price*(1-p.sale_percent/100)) ASC, p.price ASC';
if ($sort === 'price_desc') $order = 'p.is_active DESC, ROUND(p.price*(1-p.sale_percent/100)) DESC, p.price DESC';
if ($sort === 'name_az')    $order = 'p.is_active DESC, p.name ASC';
if ($sort === 'name_za')    $order = 'p.is_active DESC, p.name DESC';
if ($sort === 'popular') {
    $extra_select = ', COALESCE(SUM(oi.quantity), 0) AS total_sold';
    $extra_join   = 'LEFT JOIN order_items oi ON oi.product_id = p.product_id';
    // HAVING >= 50: only products with at least 50 units sold qualify
    $group_by     = 'GROUP BY p.product_id HAVING total_sold >= 50';
    // order by category first so the PHP top-5-per-category cut works correctly
    $order        = 'c.category_name ASC, total_sold DESC';
}

$products   = $conn->query("
    SELECT p.*, c.category_name $extra_select
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    $extra_join
    $where
    $group_by
    ORDER BY $order
");
$categories = $conn->query("SELECT DISTINCT category_name FROM categories ORDER BY category_name");

// ── Pull all rows into a PHP array so we can post-process ────
$product_rows = [];
if ($products) {
    while ($row = $products->fetch_assoc()) {
        $product_rows[] = $row;
    }
}

// ── Best Sellers: keep only top 5 per category ───────────────
if ($sort === 'popular') {
    $cat_counts   = [];
    $filtered     = [];
    foreach ($product_rows as $row) {
        $row_cat = $row['category_name'];
        $cat_counts[$row_cat] = $cat_counts[$row_cat] ?? 0;
        if ($cat_counts[$row_cat] < 5) {
            $filtered[] = $row;
            $cat_counts[$row_cat]++;
        }
    }
    $product_rows = $filtered;
}

$total = count($product_rows);

// ── Page label helpers ────────────────────────────────────────
$page_mode = 'shop'; // default
if ($sale)                                                   $page_mode = 'sale';
elseif ($sort === 'popular')                                 $page_mode = 'bestsellers';
elseif ($sort === 'newest' && !$cat && !$gender && !$q)     $page_mode = 'newarrivals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $q ? 'Search: '.e($q) : ($cat ? e($cat) : 'Shop All') ?> | Apex</title>
<link rel="stylesheet" href="css/style.css?v=4">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- Page Header -->
<div style="background:var(--navy3);border-bottom:1px solid var(--border);padding:36px 0;">
  <div class="wrap">
    <?php
      // Build breadcrumb + title pieces
      $gLabel   = $gender ? strtoupper(e($gender)) . ($gender==='Kids' ? "'" : "'S") : '';
      $catUpper = $cat    ? strtoupper(e($cat)) : '';
    ?>
    <div class="breadcrumb">
      <a href="index.php">Home</a><span class="sep">/</span>
      <?php if ($q): ?>
        <a href="products.php">Shop</a><span class="sep">/</span><span>Search</span>
      <?php elseif ($gender && $cat): ?>
        <a href="products.php">Shop</a><span class="sep">/</span>
        <a href="products.php?gender=<?=urlencode($gender)?>"><?=e($gender)?>'s</a><span class="sep">/</span>
        <span><?=e($cat)?></span>
      <?php elseif ($gender): ?>
        <a href="products.php">Shop</a><span class="sep">/</span><span><?=e($gender)?>'s Shoes</span>
      <?php elseif ($cat): ?>
        <a href="products.php">Shop</a><span class="sep">/</span><span><?=e($cat)?></span>
      <?php else: ?>
        <span>All Shoes</span>
      <?php endif; ?>
    </div>

    <?php if ($q): ?>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        RESULTS FOR <span style="color:var(--accent)">"<?=strtoupper(e($q))?>"</span>
      </h1>
      <p style="color:var(--muted);margin-top:8px;font-size:.875rem;">
        <?= $total > 0 ? "$total shoe".($total!=1?'s':'')." found" : "No shoes matched your search" ?>
        <?php if ($cat): ?> in <strong style="color:var(--white)"><?=e($cat)?></strong><?php endif; ?>
      </p>

    <?php elseif ($page_mode === 'sale'): ?>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        <span style="color:var(--danger)">SALE</span> — <?=$total?> style<?=$total!=1?'s':''?>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;">Limited-time discounts. Grab them before they're gone.</p>

    <?php elseif ($page_mode === 'bestsellers'): ?>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        BEST <span style="color:var(--accent)">SELLERS</span>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;">Ranked by units sold — the shoes customers love most.</p>

    <?php elseif ($page_mode === 'newarrivals'): ?>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        NEW <span style="color:var(--accent)">ARRIVALS</span>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;">Fresh drops — <?=$total?> latest style<?=$total!=1?'s':''?>.</p>

    <?php elseif ($cat && $gender): ?>
      <!-- e.g. MEN'S RUNNING -->
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        <?=$gLabel?> <span style="color:var(--accent)"><?=$catUpper?></span>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;"><?=$total?> style<?=$total!=1?'s':''?> found</p>

    <?php elseif ($cat): ?>
      <!-- e.g. BASKETBALL / RUNNING / TRAINING / LIFESTYLE -->
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        <?=$catUpper?> <span style="color:var(--accent)">SHOES</span>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;"><?=$total?> style<?=$total!=1?'s':''?> found</p>

    <?php elseif ($gender): ?>
      <!-- e.g. MEN'S SHOES / WOMEN'S SHOES / KIDS' SHOES -->
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        <?=$gLabel?> <span style="color:var(--accent)">SHOES</span>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;"><?=$total?> style<?=$total!=1?'s':''?> available</p>

    <?php else: ?>
      <!-- All shoes, no filter -->
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        ALL <span style="color:var(--accent)">SHOES</span>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;"><?=$total?> style<?=$total!=1?'s':''?> found</p>
    <?php endif; ?>
  </div>
</div>

<section class="section" style="padding-top:36px;background:var(--navy);">
  <div class="wrap">

    <!-- ── Filters Row ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:32px;">

      <!-- Category tabs -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
        $allActive = (!$cat) ? 'var(--accent)' : 'transparent';
        $allColor  = (!$cat) ? 'var(--navy)'   : 'var(--muted)';
        $allBorder = (!$cat) ? 'var(--accent)'  : 'var(--border)';
        $allFw     = (!$cat) ? '700' : '500';
        $qParam    = $q ? '&q='.urlencode($q) : '';
        $gParam    = $gender ? '&gender='.urlencode($gender) : '';
        $saleParam = $sale ? '&sale=1' : '';
        ?>
        <a href="products.php?sort=<?=e($sort)?><?=$gParam?><?=$saleParam?><?=$qParam?>"
           style="padding:8px 20px;border-radius:100px;border:1px solid <?=$allBorder?>;color:<?=$allColor?>;background:<?=$allActive?>;font-size:.82rem;font-weight:<?=$allFw?>;transition:.2s;">
          All
        </a>
        <?php
        $categories->data_seek(0);
        while ($c = $categories->fetch_assoc()):
            $isActive = ($cat === $c['category_name']);
            $bg     = $isActive ? 'var(--accent)' : 'transparent';
            $color  = $isActive ? 'var(--navy)'   : 'var(--muted)';
            $border = $isActive ? 'var(--accent)'  : 'var(--border)';
            $fw     = $isActive ? '700' : '500';
        ?>
        <a href="products.php?cat=<?=urlencode($c['category_name'])?>&sort=<?=e($sort)?><?=$gParam?><?=$saleParam?><?=$qParam?>"
           style="padding:8px 20px;border-radius:100px;border:1px solid <?=$border?>;color:<?=$color?>;background:<?=$bg?>;font-size:.82rem;font-weight:<?=$fw?>;transition:.2s;">
          <?=e($c['category_name'])?>
        </a>
        <?php endwhile; ?>
      </div>

      <!-- Sort + clear search -->
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <?php if ($q): ?>
        <a href="products.php?<?=$cat?'cat='.urlencode($cat).'&':''?><?=$gender?'gender='.urlencode($gender):''?>"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:100px;border:1px solid var(--border);color:var(--muted);font-size:.8rem;transition:.2s;"
           onmouseover="this.style.borderColor='var(--danger)';this.style.color='var(--danger)'"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
          &times; Clear "<?=e($q)?>"
        </a>
        <?php endif; ?>
        <form method="GET" style="display:flex;align-items:center;gap:8px;">
          <?php if ($cat):    ?><input type="hidden" name="cat"    value="<?=e($cat)?>">    <?php endif; ?>
          <?php if ($q):      ?><input type="hidden" name="q"      value="<?=e($q)?>">      <?php endif; ?>
          <?php if ($gender): ?><input type="hidden" name="gender" value="<?=e($gender)?>"> <?php endif; ?>
          <?php if ($sale):   ?><input type="hidden" name="sale"   value="1">               <?php endif; ?>
          <select name="sort" onchange="this.form.submit()"
            style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:8px 14px;color:var(--text);font-size:.82rem;cursor:pointer;">
            <option value="newest"    <?=$sort==='newest'   ?'selected':''?>>Newest</option>
            <option value="price_asc" <?=$sort==='price_asc'?'selected':''?>>Price: Low–High</option>
            <option value="price_desc"<?=$sort==='price_desc'?'selected':''?>>Price: High–Low</option>
            <option value="name_az"   <?=$sort==='name_az'  ?'selected':''?>>Name: A–Z</option>
            <option value="name_za"   <?=$sort==='name_za'  ?'selected':''?>>Name: Z–A</option>
          </select>
        </form>
      </div>
    </div>

    <!-- ── Results ── -->
    <?php if ($total === 0): ?>
      <div style="text-align:center;padding:80px 0;">
        <h3 style="font-family:'Oswald',sans-serif;font-size:1.3rem;color:var(--white);margin-bottom:8px;">
          <?php
            if ($page_mode === 'bestsellers') echo 'No Best Sellers Yet';
            elseif ($page_mode === 'sale')     echo 'No Sale Items Right Now';
            elseif ($q)                        echo 'No results for "'.e($q).'"';
            else                               echo 'No Shoes Found';
          ?>
        </h3>
        <p style="color:var(--muted);margin-bottom:8px;">
          <?php if ($page_mode === 'bestsellers'): ?>
            A shoe needs at least <strong style="color:var(--white);">50 units sold</strong> to appear here. Keep selling!
          <?php elseif ($page_mode === 'sale'): ?>
            Check back soon — new deals are added regularly.
          <?php elseif ($q): ?>
            Try different keywords, check your spelling, or browse all shoes below.
          <?php else: ?>
            Try a different category or add products in Admin.
          <?php endif; ?>
        </p>

        <?php if ($q): ?>
        <!-- Helpful suggestions when no results -->
        <div style="margin:24px auto;max-width:400px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px;text-align:left;">
          <div style="font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;">Try searching for:</div>
          <?php
          $suggest_cats = $conn->query("SELECT category_name FROM categories LIMIT 4");
          while ($sc = $suggest_cats->fetch_assoc()):
          ?>
          <a href="products.php?cat=<?=urlencode($sc['category_name'])?>"
             style="display:inline-block;margin:4px;padding:6px 14px;border-radius:100px;border:1px solid var(--border);color:var(--muted);font-size:.8rem;transition:.2s;"
             onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
             onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
            <?=e($sc['category_name'])?>
          </a>
          <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <a href="products.php" class="btn btn-primary" style="margin-top:8px;">Browse All Shoes</a>
      </div>

    <?php else: ?>


      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
        <?php
        $bs_cat_rank = []; // track per-category rank for bestsellers medal badge
        foreach ($product_rows as $p):
          $img = !empty($p['image_url']) ? e($p['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';

          // Per-category rank counter (bestsellers only)
          $cat_key = $p['category_name'];
          $bs_cat_rank[$cat_key] = ($bs_cat_rank[$cat_key] ?? 0) + 1;
          $cat_rank = $bs_cat_rank[$cat_key];

          // Highlight matching keywords in product name
          $display_name = e($p['name']);
          if ($q) {
              $words = preg_split('/\s+/', $q);
              foreach ($words as $word) {
                  if (strlen($word) < 1) continue;
                  $display_name = preg_replace(
                      '/(' . preg_quote(htmlspecialchars($word, ENT_QUOTES), '/') . ')/i',
                      '<span style="color:var(--accent);font-weight:700;">$1</span>',
                      $display_name
                  );
              }
          }
        ?>
        <?php $isActive = (int)($p['is_active'] ?? 1); ?>
        <div class="prod-card<?= $isActive ? '' : ' prod-unavailable' ?>">
          <?php if($isActive): ?>
          <a href="product_detail.php?id=<?=(int)$p['product_id']?>">
          <?php else: ?>
          <div>
          <?php endif; ?>
            <div class="prod-img">
              <img src="<?=$img?>" alt="<?=e($p['name'])?>" loading="lazy">
              <span class="prod-badge"><?=e($p['category_name'])?></span>
              <?php if(!$isActive): ?>
              <span class="badge-unavailable">Unavailable</span>
              <?php elseif($page_mode === 'bestsellers'): ?>
                <?php $medal = ['#1','#2','#3','#4','#5'][$cat_rank-1] ?? ''; ?>
                <span style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);color:#fff;font-size:.65rem;font-weight:700;letter-spacing:1px;padding:3px 8px;border-radius:4px;">
                  <?=$medal?> <?=(int)($p['total_sold'] ?? 0)?> sold
                </span>
              <?php elseif(!empty($p['is_on_sale'])): ?>
              <?php $sp_badge=(int)($p['sale_percent']??0); ?>
              <span style="position:absolute;top:10px;right:10px;background:var(--danger);color:#fff;font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;"><?=$sp_badge>0?$sp_badge.'% OFF':'SALE'?></span>
              <?php elseif(strtotime($p['created_at']) >= strtotime('-30 days')): ?>
              <span style="position:absolute;top:10px;right:10px;background:var(--success);color:#fff;font-size:.62rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:4px;">NEW</span>
              <?php endif; ?>
            </div>
          <?php if($isActive): ?></a><?php else: ?></div><?php endif; ?>
          <div class="prod-body">
            <div class="prod-cat"><?=e($p['category_name'])?></div>
            <div class="prod-name">
              <?php if($isActive): ?>
              <a href="product_detail.php?id=<?=(int)$p['product_id']?>"><?=$display_name?></a>
              <?php else: ?>
              <span style="color:var(--muted);"><?=$display_name?></span>
              <?php endif; ?>
            </div>
            <div class="prod-footer">
              <?php if(!$isActive): ?>
              <span class="prod-price" style="color:var(--muted);text-decoration:line-through;">RM <?=number_format($p['price'],2)?></span>
              <?php else: ?>
              <?=price_html($p['price'], (int)($p['sale_percent']??0))?>
              <?php endif; ?>
              <?php if($isActive): ?>
              <a href="product_detail.php?id=<?=(int)$p['product_id']?>" class="btn-view">View →</a>
              <?php else: ?>
              <span style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:#888;font-weight:600;">Unavailable</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
