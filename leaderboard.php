<?php
session_start();
require 'db.php';

// ── Leaderboard Score Formula ─────────────────────────────────
// Score = (avg_rating * 40) + (review_count * 5) + (units_sold * 3)
// Uses real column names: product_id, order_item_id, user_id from apex_store

$leaderboard = $conn->query("
    SELECT
        p.product_id,
        p.name,
        p.price,
        p.image_url,
        c.category_name,

        COALESCE(AVG(r.rating), 0)              AS avg_rating,
        COUNT(DISTINCT r.review_id)              AS review_count,
        COALESCE(SUM(oi.quantity), 0)            AS units_sold,

        ROUND(
            (COALESCE(AVG(r.rating), 0)        * 40) +
            (COUNT(DISTINCT r.review_id)       *  5) +
            (COALESCE(SUM(oi.quantity), 0)     *  3)
        , 1) AS score

    FROM products p
    JOIN categories c        ON p.category_id  = c.category_id
    LEFT JOIN reviews r      ON r.product_id   = p.product_id
    LEFT JOIN order_items oi ON oi.product_id  = p.product_id
    GROUP BY p.product_id, p.name, p.price, p.image_url, c.category_name
    ORDER BY c.category_name ASC, score DESC
");

// Group by category
$by_cat = [];
while ($row = $leaderboard->fetch_assoc()) {
    $by_cat[$row['category_name']][] = $row;
}

$medals = ['🥇','🥈','🥉'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Shoe Leaderboard | Apex</title>
<link rel="stylesheet" href="css/style.css">
<style>
.lb-section  { margin-bottom:56px; }
.lb-cat-head {
    font-family:'Oswald',sans-serif;
    font-size:clamp(20px,2.8vw,34px);
    letter-spacing:3px; color:var(--white);
    margin-bottom:22px; padding-bottom:12px;
    border-bottom:2px solid var(--accent);
    display:flex; align-items:center; gap:12px;
}
.lb-row {
    background:var(--card); border:1px solid var(--border);
    border-radius:10px; overflow:hidden;
    margin-bottom:12px; transition:all .2s;
    display:grid;
    grid-template-columns:56px 80px 1fr 180px;
    align-items:center;
}
.lb-row:hover { border-color:rgba(100,255,218,.4); transform:translateX(4px); }
.lb-row.gold   { border-color:rgba(255,215,0,.4);   background:rgba(255,215,0,.03); }
.lb-row.silver { border-color:rgba(192,192,192,.3); }
.lb-row.bronze { border-color:rgba(205,127,50,.3);  }

.lb-rank {
    text-align:center; font-family:'Oswald',sans-serif;
    font-size:1.35rem; color:var(--muted); padding:0 8px;
}
.lb-row.gold   .lb-rank { color:#ffd700; font-size:1.6rem; }
.lb-row.silver .lb-rank { color:#c0c0c0; }
.lb-row.bronze .lb-rank { color:#cd7f32; }

.lb-img { width:80px; height:80px; object-fit:cover; display:block; background:var(--navy2); }
.lb-info { padding:14px 18px; }
.lb-name { font-weight:600; font-size:.95rem; color:var(--white); margin-bottom:6px; }
.lb-name a { color:inherit; transition:color .2s; }
.lb-name a:hover { color:var(--accent); }
.lb-stats {
    display:flex; gap:18px; font-size:.78rem;
    color:var(--muted); flex-wrap:wrap;
}
.lb-stats .v { color:var(--text); font-weight:600; }
.stars-s { color:var(--accent); font-size:.8rem; letter-spacing:1px; }

.lb-score-col {
    padding:14px 20px 14px 0; text-align:right;
}
.lb-score-num {
    font-family:'Oswald',sans-serif; font-size:1.6rem;
    color:var(--accent); letter-spacing:1px;
}
.lb-score-lbl {
    font-size:.62rem; letter-spacing:2px;
    text-transform:uppercase; color:var(--muted); margin-bottom:6px;
}
.lb-bar { height:6px; background:var(--border); border-radius:100px; margin-top:8px; overflow:hidden; }
.lb-bar-fill {
    height:100%; border-radius:100px;
    background:linear-gradient(90deg,var(--accent),#0ea5e9);
    transition:width .8s ease;
}
.lb-row.gold .lb-bar-fill { background:linear-gradient(90deg,#ffd700,#ffb300); }

.lb-breakdown {
    display:none; margin-top:6px; font-size:.7rem;
    color:var(--muted); gap:10px; flex-wrap:wrap;
}
.lb-row:hover .lb-breakdown { display:flex; }

.lb-empty {
    text-align:center; padding:36px;
    color:var(--muted); font-size:.875rem;
    background:var(--card); border:1px solid var(--border);
    border-radius:10px;
}

@media(max-width:640px){
    .lb-row { grid-template-columns:46px 68px 1fr; }
    .lb-score-col { display:none; }
}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Leaderboard</span></div>
    <h1>SHOE <span style="color:var(--accent)">LEADERBOARD</span></h1>
    <p style="color:var(--muted);margin-top:8px;max-width:540px;">
      Auto-ranked by real customer data — ratings, reviews and sales. Updated live. No bias, just numbers.
    </p>
  </div>
</div>

<!-- Score formula -->
<div style="background:var(--navy);border-bottom:1px solid var(--border);padding:20px 0;">
  <div class="wrap">
    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
      <span style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);">Score =</span>
      <?php foreach([
        ['⭐ Rating','× 40 pts'],
        ['📝 Reviews','× 5 pts each'],
        ['🛒 Units Sold','× 3 pts each'],
      ] as $f): ?>
      <div style="display:flex;align-items:center;gap:8px;background:rgba(100,255,218,.06);border:1px solid rgba(100,255,218,.15);border-radius:6px;padding:7px 14px;">
        <span style="font-size:.85rem;"><?=$f[0]?></span>
        <span style="font-family:'Oswald',sans-serif;color:var(--accent);font-size:.85rem;"><?=$f[1]?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<section class="section" style="padding-top:44px;">
<div class="wrap">

<?php if (empty($by_cat)): ?>
  <div style="text-align:center;padding:80px 0;color:var(--muted);">
    <div style="font-size:3rem;margin-bottom:14px;">🏆</div>
    <h3 style="font-family:'Oswald',sans-serif;font-size:1.2rem;color:var(--white);margin-bottom:8px;">No Data Yet</h3>
    <p>Rankings will appear once products are added and customers start buying and reviewing.</p>
    <a href="products.php" class="btn btn-primary" style="margin-top:24px;">Browse Shoes</a>
  </div>

<?php else:
  foreach ($by_cat as $category => $shoes):
    $max_score = max(array_column($shoes, 'score'));
    $max_score = max($max_score, 1);
    $cat_icons = ['Running'=>'🏃','Basketball'=>'🏀','Training'=>'💪','Lifestyle'=>'✨'];
    $icon = $cat_icons[$category] ?? '👟';
?>
<div class="lb-section">
  <div class="lb-cat-head">
    <?=$icon?> <?=strtoupper(e($category))?> RANKING
  </div>

  <?php foreach ($shoes as $idx => $shoe):
    $rank  = $idx + 1;
    $cls   = match($rank){ 1=>'gold', 2=>'silver', 3=>'bronze', default=>'' };
    $medal = $medals[$idx] ?? "#$rank";
    $pct   = round(($shoe['score'] / $max_score) * 100);
    $img   = !empty($shoe['image_url']) ? e($shoe['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&q=70';

    // Build star display
    $avg = round((float)$shoe['avg_rating'], 1);
    $full = floor($avg); $half = ($avg - $full) >= 0.5 ? 1 : 0; $empty = 5 - $full - $half;
    $stars = str_repeat('★',$full) . ($half?'½':'') . str_repeat('☆',$empty);
  ?>
  <div class="lb-row <?=$cls?>">
    <div class="lb-rank"><?=$rank<=3?$medals[$idx]:"#$rank"?></div>

    <a href="product_detail.php?id=<?=(int)$shoe['product_id']?>">
      <img src="<?=$img?>" class="lb-img" alt="<?=e($shoe['name'])?>">
    </a>

    <div class="lb-info">
      <div class="lb-name">
        <a href="product_detail.php?id=<?=(int)$shoe['product_id']?>"><?=e($shoe['name'])?></a>
      </div>
      <div class="lb-stats">
        <span>
          <span class="stars-s"><?=$shoe['avg_rating']>0?$stars:'☆☆☆☆☆'?></span>
          <span class="v">&nbsp;<?=$avg>0?$avg:'—'?></span>
        </span>
        <span>📝 <span class="v"><?=(int)$shoe['review_count']?></span> review<?=$shoe['review_count']!=1?'s':''?></span>
        <span>🛒 <span class="v"><?=(int)$shoe['units_sold']?></span> sold</span>
        <span>RM <span class="v"><?=number_format($shoe['price'],2)?></span></span>
      </div>
      <div class="lb-breakdown">
        <span>Rating pts: <strong style="color:var(--accent)"><?=round($avg*40,1)?></strong></span>
        <span>Review pts: <strong style="color:var(--accent)"><?=(int)$shoe['review_count']*5?></strong></span>
        <span>Sales pts:  <strong style="color:var(--accent)"><?=(int)$shoe['units_sold']*3?></strong></span>
      </div>
    </div>

    <div class="lb-score-col">
      <div class="lb-score-lbl">Score</div>
      <div class="lb-score-num"><?=number_format((float)$shoe['score'],0)?></div>
      <div class="lb-bar">
        <div class="lb-bar-fill" style="width:<?=$pct?>%"></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <div style="text-align:right;margin-top:10px;">
    <a href="products.php?cat=<?=urlencode($category)?>"
       style="font-size:.82rem;color:var(--muted);transition:color .2s;"
       onmouseover="this.style.color='var(--accent)'"
       onmouseout="this.style.color='var(--muted)'">
      Shop All <?=e($category)?> →
    </a>
  </div>
</div>
<?php endforeach; endif; ?>

</div>
</section>

<?php include 'includes/footer.php'; ?>
