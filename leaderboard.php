<?php
session_start();
require 'db.php';

// ── NEW Leaderboard Score Formula ──────────────────────────────
//
//  RULES (as per lecturer feedback):
//  1. Only show shoes that have AT LEAST 1 unit sold
//  2. Good reviews  (rating 4-5) → ADDS +10 pts each
//  3. Bad reviews   (rating 1-2) → DEDUCTS -10 pts each
//  4. Neutral       (rating 3)   → 0 pts (no effect)
//  5. Average rating             → × 40 pts (quality measure)
//  6. Units sold                 → × 3 pts each (sales performance)
//
//  Final Score = (avg_rating × 40)
//              + (good_reviews × 10)
//              - (bad_reviews  × 10)
//              + (units_sold   × 3)
//
//  Minimum score is capped at 0 (cannot go negative)
// ──────────────────────────────────────────────────────────────

// ── Filter & Sort parameters ─────────────────────────────────────
$sort_options = [
    'score_desc'  => ['label' => '🏆 Top Score',     'sql' => 'score DESC'],
    'score_asc'   => ['label' => '📉 Lowest Score',  'sql' => 'score ASC'],
    'rating_desc' => ['label' => '⭐ Highest Rated', 'sql' => 'avg_rating DESC'],
    'rating_asc'  => ['label' => '☆ Lowest Rated',  'sql' => 'avg_rating ASC'],
    'sold_desc'   => ['label' => '🛒 Most Sold',     'sql' => 'units_sold DESC'],
];
$sort = $_GET['sort'] ?? 'score_desc';
if(!array_key_exists($sort, $sort_options)) $sort = 'score_desc';
$sort_sql = $sort_options[$sort]['sql'];

$allowed_cats = ['Running','Basketball','Training','Lifestyle'];
$cat_filter   = $_GET['cat'] ?? '';
if($cat_filter && !in_array($cat_filter, $allowed_cats)) $cat_filter = '';
$cat_where = $cat_filter ? "AND c.category_name = '" . $conn->real_escape_string($cat_filter) . "'" : '';

$leaderboard = $conn->query("
    SELECT
        p.product_id,
        p.name,
        p.price,
        p.image_url,
        c.category_name,

        COALESCE(AVG(r.rating), 0)                                    AS avg_rating,
        COUNT(DISTINCT r.review_id)                                    AS review_count,
        COUNT(DISTINCT CASE WHEN r.rating >= 4 THEN r.review_id END)  AS good_reviews,
        COUNT(DISTINCT CASE WHEN r.rating <= 2 THEN r.review_id END)  AS bad_reviews,
        COUNT(DISTINCT CASE WHEN r.rating = 3  THEN r.review_id END)  AS neutral_reviews,
        COALESCE(SUM(oi.quantity), 0)                                  AS units_sold,

        GREATEST(0, ROUND(
            (COALESCE(AVG(r.rating), 0)                                      * 40) +
            (COUNT(DISTINCT CASE WHEN r.rating >= 4 THEN r.review_id END)    * 10) -
            (COUNT(DISTINCT CASE WHEN r.rating <= 2 THEN r.review_id END)    * 10) +
            (COALESCE(SUM(oi.quantity), 0)                                   *  3)
        , 1)) AS score

    FROM products p
    JOIN categories c        ON p.category_id = c.category_id
    LEFT JOIN reviews r      ON r.product_id  = p.product_id
    LEFT JOIN order_items oi ON oi.product_id = p.product_id
    WHERE 1=1 $cat_where
    GROUP BY p.product_id, p.name, p.price, p.image_url, c.category_name
    HAVING units_sold > 0
    ORDER BY c.category_name ASC, $sort_sql
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
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Shoe Leaderboard | Apex</title>
<link rel="stylesheet" href="css/style.css?v=4">
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
    grid-template-columns:56px 80px 1fr 200px;
    align-items:center;
}
.lb-row:hover { border-color:rgba(100,255,218,.4); transform:translateX(4px); }
.lb-row.gold   { border-color:rgba(255,215,0,.4);  background:rgba(255,215,0,.03); }
.lb-row.silver { border-color:rgba(192,192,192,.3); }
.lb-row.bronze { border-color:rgba(205,127,50,.3); }

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
.lb-stats { display:flex; gap:14px; font-size:.78rem; color:var(--muted); flex-wrap:wrap; }
.lb-stats .v { color:var(--text); font-weight:600; }
.stars-s { color:var(--accent); font-size:.8rem; letter-spacing:1px; }
.good-rev { color:#22c55e; font-weight:600; }
.bad-rev  { color:#ef4444; font-weight:600; }

.lb-score-col { padding:14px 20px 14px 0; text-align:right; }
.lb-score-num {
    font-family:'Oswald',sans-serif; font-size:1.6rem;
    color:var(--accent); letter-spacing:1px;
}
.lb-score-lbl { font-size:.62rem; letter-spacing:2px; text-transform:uppercase; color:var(--muted); margin-bottom:6px; }
.lb-bar { height:6px; background:var(--border); border-radius:100px; margin-top:8px; overflow:hidden; }
.lb-bar-fill {
    height:100%; border-radius:100px;
    background:linear-gradient(90deg,var(--accent),#0ea5e9);
    transition:width .8s ease;
}
.lb-row.gold .lb-bar-fill { background:linear-gradient(90deg,#ffd700,#ffb300); }

/* Breakdown shown on hover */
.lb-breakdown {
    display:none; margin-top:8px; font-size:.72rem;
    color:var(--muted); gap:10px; flex-wrap:wrap;
    border-top:1px solid var(--border); padding-top:8px;
}
.lb-row:hover .lb-breakdown { display:flex; }
.bd-good { color:#22c55e; }
.bd-bad  { color:#ef4444; }

.lb-empty {
    text-align:center; padding:48px;
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
  </div>
</div>

<!-- Score formula explainer -->
<div style="background:var(--navy);border-bottom:1px solid var(--border);padding:20px 0;">
  <div class="wrap">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
      <span style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);">Score =</span>
      <div style="display:flex;align-items:center;gap:8px;background:rgba(100,255,218,.06);border:1px solid rgba(100,255,218,.15);border-radius:6px;padding:7px 14px;">
        <span style="font-size:.85rem;">⭐ Rating</span>
        <span style="font-family:'Oswald',sans-serif;color:var(--accent);font-size:.85rem;">× 40 pts</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:6px;padding:7px 14px;">
        <span style="font-size:.85rem;">👍 Good Review (4–5★)</span>
        <span style="font-family:'Oswald',sans-serif;color:#22c55e;font-size:.85rem;">+10 pts</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:6px;padding:7px 14px;">
        <span style="font-size:.85rem;">👎 Bad Review (1–2★)</span>
        <span style="font-family:'Oswald',sans-serif;color:#ef4444;font-size:.85rem;">−10 pts</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;background:rgba(100,255,218,.06);border:1px solid rgba(100,255,218,.15);border-radius:6px;padding:7px 14px;">
        <span style="font-size:.85rem;">🛒 Units Sold</span>
        <span style="font-family:'Oswald',sans-serif;color:var(--accent);font-size:.85rem;">× 3 pts</span>
      </div>
    </div>
  </div>
</div>

<!-- Filter & Sort bar -->
<div style="background:var(--navy2);border-bottom:1px solid var(--border);padding:16px 0;">
  <div class="wrap">
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;justify-content:space-between;">

      <!-- Category filter -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <span style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);flex-shrink:0;">Category:</span>
        <?php
        $cats_nav = ['' => 'All'] + array_combine($allowed_cats, $allowed_cats);
        foreach($cats_nav as $val => $label):
          $active = ($cat_filter === $val);
          $url = '?cat='.urlencode($val).'&sort='.urlencode($sort);
        ?>
        <a href="<?=$url?>"
           style="padding:5px 14px;border-radius:100px;font-size:.78rem;font-weight:<?=$active?'700':'500'?>;text-decoration:none;border:1px solid <?=$active?'var(--accent)':'var(--border)'?>;color:<?=$active?'var(--navy)':'var(--muted)'?>;background:<?=$active?'var(--accent)':'transparent'?>;transition:.2s;">
          <?=e($label)?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Sort dropdown -->
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);">Sort by:</span>
        <form method="GET" style="margin:0;">
          <input type="hidden" name="cat" value="<?=e($cat_filter)?>">
          <select name="sort" onchange="this.form.submit()"
                  style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:7px 12px;color:var(--text);font-size:.82rem;cursor:pointer;">
            <?php foreach($sort_options as $key => $opt): ?>
            <option value="<?=$key?>" <?=$sort===$key?'selected':''?>><?=$opt['label']?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

    </div>
  </div>
</div>

<section class="section" style="padding-top:44px;">
<div class="wrap">

<?php if (empty($by_cat)): ?>
  <div style="text-align:center;padding:80px 0;color:var(--muted);">
    <div style="font-size:3rem;margin-bottom:14px;">🏆</div>
    <h3 style="font-family:'Oswald',sans-serif;font-size:1.2rem;color:var(--white);margin-bottom:8px;">No Rankings Yet</h3>
    <p style="margin-bottom:20px;">Rankings appear automatically once customers start purchasing shoes.</p>
    <a href="products.php" class="btn btn-primary">Browse Shoes</a>
  </div>

<?php else:
  foreach ($by_cat as $category => $shoes):
    $max_score = max(array_column($shoes, 'score'));
    $max_score = max((float)$max_score, 1);
    $cat_icons = ['Running'=>'🏃','Basketball'=>'🏀','Training'=>'💪','Lifestyle'=>'✨'];
    $icon = $cat_icons[$category] ?? '👟';
?>
<div class="lb-section">
  <div class="lb-cat-head">
    <?=$icon?> <?=strtoupper(e($category))?> RANKING
    <span style="font-size:.7rem;letter-spacing:1px;color:var(--muted);font-family:'Inter',sans-serif;font-weight:400;">
      — <?=count($shoes)?> shoe<?=count($shoes)!=1?'s':''?> ranked
    </span>
  </div>

  <?php foreach ($shoes as $idx => $shoe):
    $rank     = $idx + 1;
    $cls      = match($rank){ 1=>'gold', 2=>'silver', 3=>'bronze', default=>'' };
    $pct      = $max_score > 0 ? round(((float)$shoe['score'] / $max_score) * 100) : 0;
    $img      = !empty($shoe['image_url'])
                    ? (str_starts_with($shoe['image_url'],'http') ? e($shoe['image_url']) : e($shoe['image_url']))
                    : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&q=70';

    $avg      = round((float)$shoe['avg_rating'], 1);
    $full     = floor($avg);
    $half     = ($avg - $full) >= 0.5 ? 1 : 0;
    $empty    = 5 - $full - $half;
    $stars    = str_repeat('★',$full) . ($half?'½':'') . str_repeat('☆',$empty);

    $good     = (int)$shoe['good_reviews'];
    $bad      = (int)$shoe['bad_reviews'];
    $neutral  = (int)$shoe['neutral_reviews'];
    $sold     = (int)$shoe['units_sold'];
    $score    = (float)$shoe['score'];

    // Score breakdown
    $rating_pts  = round($avg * 40, 1);
    $good_pts    = $good * 10;
    $bad_pts     = $bad  * 10;
    $sales_pts   = $sold * 3;
  ?>
  <div class="lb-row <?=$cls?>">

    <!-- Rank -->
    <div class="lb-rank"><?=$rank<=3?$medals[$idx]:"#$rank"?></div>

    <!-- Image -->
    <a href="product_detail.php?id=<?=(int)$shoe['product_id']?>">
      <img src="<?=$img?>" class="lb-img" alt="<?=e($shoe['name'])?>">
    </a>

    <!-- Info -->
    <div class="lb-info">
      <div class="lb-name">
        <a href="product_detail.php?id=<?=(int)$shoe['product_id']?>"><?=e($shoe['name'])?></a>
      </div>
      <div class="lb-stats">
        <span>
          <span class="stars-s"><?=$avg>0?$stars:'☆☆☆☆☆'?></span>
          <span class="v">&nbsp;<?=$avg>0?number_format($avg,1):'—'?></span>
        </span>
        <?php if($good > 0): ?>
        <span>👍 <span class="good-rev"><?=$good?> good</span></span>
        <?php endif; ?>
        <?php if($bad > 0): ?>
        <span>👎 <span class="bad-rev"><?=$bad?> bad</span></span>
        <?php endif; ?>
        <span>🛒 <span class="v"><?=$sold?></span> sold</span>
        <span>RM <span class="v"><?=number_format($shoe['price'],2)?></span></span>
      </div>
      <!-- Score breakdown on hover -->
      <div class="lb-breakdown">
        <span>Rating pts: <strong style="color:var(--accent)"><?=$rating_pts?></strong></span>
        <span class="bd-good">+Good review pts: <strong><?=$good_pts?></strong></span>
        <?php if($bad_pts > 0): ?>
        <span class="bd-bad">−Bad review pts: <strong><?=$bad_pts?></strong></span>
        <?php endif; ?>
        <span>+Sales pts: <strong style="color:var(--accent)"><?=$sales_pts?></strong></span>
        <span style="color:var(--white);font-weight:700;">= <?=number_format($score,0)?></span>
      </div>
    </div>

    <!-- Score -->
    <div class="lb-score-col">
      <div class="lb-score-lbl">Score</div>
      <div class="lb-score-num"><?=number_format($score,0)?></div>
      <div class="lb-bar">
        <div class="lb-bar-fill" style="width:<?=$pct?>%"></div>
      </div>
      <?php if($bad > 0): ?>
      <div style="font-size:.65rem;color:#ef4444;margin-top:4px;">−<?=$bad_pts?> from bad reviews</div>
      <?php endif; ?>
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
