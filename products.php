<?php
session_start();
require 'db.php';

$cat  = $_GET['cat']  ?? '';
$sort = $_GET['sort'] ?? 'newest';
$q    = trim($_GET['q'] ?? '');    // ← search query

// ── Build WHERE ──────────────────────────────────────────────
$conditions = ['1=1'];

// Category filter
if ($cat) {
    $cat_safe = $conn->real_escape_string($cat);
    $conditions[] = "c.category_name = '$cat_safe'";
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

$where = 'WHERE ' . implode(' AND ', $conditions);

// ── Sort ─────────────────────────────────────────────────────
$order = 'p.created_at DESC';
if ($sort === 'price_asc')  $order = 'p.price ASC';
if ($sort === 'price_desc') $order = 'p.price DESC';
if ($sort === 'name_az')    $order = 'p.name ASC';
if ($sort === 'name_za')    $order = 'p.name DESC';

$products   = $conn->query("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    $where
    ORDER BY $order
");
$categories = $conn->query("SELECT DISTINCT category_name FROM categories ORDER BY category_name");
$total      = $products ? $products->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $q ? 'Search: '.e($q) : ($cat ? e($cat) : 'Shop All') ?> | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- Page Header -->
<div style="background:var(--navy2);border-bottom:1px solid var(--border);padding:36px 0;">
  <div class="wrap">
    <div class="breadcrumb">
      <a href="index.php">Home</a><span class="sep">/</span>
      <?php if ($q): ?>
        <a href="products.php">Shop</a><span class="sep">/</span><span>Search</span>
      <?php elseif ($cat): ?>
        <a href="products.php">Shop</a><span class="sep">/</span><span><?=e($cat)?></span>
      <?php else: ?>
        <span>All Shoes</span>
      <?php endif; ?>
    </div>

    <?php if ($q): ?>
      <!-- Search heading -->
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        RESULTS FOR <span style="color:var(--accent)">"<?=strtoupper(e($q))?>"</span>
      </h1>
      <p style="color:var(--muted);margin-top:8px;font-size:.875rem;">
        <?php if ($total > 0): ?>
          <?=$total?> shoe<?=$total!=1?'s':''?> found
          <?php if ($cat): ?> in <strong style="color:var(--white)"><?=e($cat)?></strong><?php endif; ?>
        <?php else: ?>
          No shoes matched your search
        <?php endif; ?>
      </p>
    <?php else: ?>
      <h1 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,4vw,44px);letter-spacing:2px;color:var(--white);">
        <?= $cat ? strtoupper(e($cat)) : 'ALL <span style="color:var(--accent)">SHOES</span>' ?>
      </h1>
      <p style="color:var(--muted);margin-top:6px;font-size:.875rem;"><?=$total?> style<?=$total!=1?'s':''?> found</p>
    <?php endif; ?>
  </div>
</div>

<section class="section" style="padding-top:36px;">
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
        ?>
        <a href="products.php?sort=<?=e($sort)?><?=$qParam?>"
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
        <a href="products.php?cat=<?=urlencode($c['category_name'])?>&sort=<?=e($sort)?><?=$qParam?>"
           style="padding:8px 20px;border-radius:100px;border:1px solid <?=$border?>;color:<?=$color?>;background:<?=$bg?>;font-size:.82rem;font-weight:<?=$fw?>;transition:.2s;">
          <?=e($c['category_name'])?>
        </a>
        <?php endwhile; ?>
      </div>

      <!-- Sort + clear search -->
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <?php if ($q): ?>
        <a href="products.php<?=$cat?'?cat='.urlencode($cat):''?>"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:100px;border:1px solid var(--border);color:var(--muted);font-size:.8rem;transition:.2s;"
           onmouseover="this.style.borderColor='var(--danger)';this.style.color='var(--danger)'"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
          ✕ Clear "<?=e($q)?>"
        </a>
        <?php endif; ?>
        <form method="GET" style="display:flex;align-items:center;gap:8px;">
          <?php if ($cat): ?><input type="hidden" name="cat" value="<?=e($cat)?>"> <?php endif; ?>
          <?php if ($q):   ?><input type="hidden" name="q"   value="<?=e($q)?>">   <?php endif; ?>
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
    <?php if (!$products || $total === 0): ?>
      <div style="text-align:center;padding:80px 0;">
        <div style="font-size:3rem;margin-bottom:16px;">🔍</div>
        <h3 style="font-family:'Oswald',sans-serif;font-size:1.3rem;color:var(--white);margin-bottom:8px;">
          <?= $q ? 'No results for "'.e($q).'"' : 'No Shoes Found' ?>
        </h3>
        <p style="color:var(--muted);margin-bottom:8px;">
          <?php if ($q): ?>
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

      <!-- Keyword highlight note -->
      <?php if ($q): ?>
      <div style="margin-bottom:20px;padding:10px 16px;background:rgba(100,255,218,.06);border:1px solid rgba(100,255,218,.2);border-radius:var(--radius);font-size:.82rem;color:var(--muted);display:flex;align-items:center;gap:8px;">
        <span style="color:var(--accent);">🔍</span>
        Showing all shoes matching
        <?php
        $words = preg_split('/\s+/', $q);
        foreach ($words as $w) {
            echo '<strong style="color:var(--accent);margin:0 3px;">"'.e($w).'"</strong>';
        }
        echo '— ' . $total . ' result' . ($total!=1?'s':'') . ' found';
        ?>
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
        <?php while ($p = $products->fetch_assoc()):
          $img = !empty($p['image_url']) ? e($p['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';

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
        <div class="prod-card">
          <a href="product_detail.php?id=<?=(int)$p['product_id']?>">
            <div class="prod-img">
              <img src="<?=$img?>" alt="<?=e($p['name'])?>" loading="lazy">
              <span class="prod-badge"><?=e($p['category_name'])?></span>
            </div>
          </a>
          <div class="prod-body">
            <div class="prod-cat"><?=e($p['category_name'])?></div>
            <div class="prod-name">
              <a href="product_detail.php?id=<?=(int)$p['product_id']?>"><?=$display_name?></a>
            </div>
            <div class="prod-footer">
              <span class="prod-price">RM <?=number_format($p['price'],2)?></span>
              <a href="product_detail.php?id=<?=(int)$p['product_id']?>" class="btn-view">View →</a>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
