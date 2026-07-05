<?php
require_once 'auth_check.php';

// ── View mode: 'month' or 'year' ────────────────────────────────────
$view_mode = (($_GET['view'] ?? 'month') === 'year') ? 'year' : 'month';
$year_num  = (int)($_GET['year']  ?? date('Y'));
$month_num = (int)($_GET['month'] ?? date('n'));
if($year_num  < 2020 || $year_num  > 2035) $year_num  = (int)date('Y');
if($month_num < 1    || $month_num > 12)   $month_num = (int)date('n');

$period_label = $view_mode === 'year'
    ? (string)$year_num
    : date('F Y', mktime(0,0,0,$month_num,1,$year_num));

// ── Prev / Next navigation ───────────────────────────────────────────
if($view_mode === 'year'){
    $prev_url = "admin_sales_report.php?view=year&year=".($year_num-1);
    $next_url = "admin_sales_report.php?view=year&year=".($year_num+1);
} else {
    $pm = $month_num-1; $py = $year_num; if($pm<1){$pm=12;$py--;}
    $nm = $month_num+1; $ny = $year_num; if($nm>12){$nm=1;$ny++;}
    $prev_url = "admin_sales_report.php?view=month&month=$pm&year=$py";
    $next_url = "admin_sales_report.php?view=month&month=$nm&year=$ny";
}

// ── Date condition ───────────────────────────────────────────────────
$date_cond = $view_mode === 'year'
    ? "YEAR(o.order_date) = $year_num"
    : "YEAR(o.order_date) = $year_num AND MONTH(o.order_date) = $month_num";

// ── Top shoes (limit 10) ─────────────────────────────────────────────
$top_shoes = $conn->query("
    SELECT p.product_id, p.name, p.image_url,
           SUM(oi.quantity)            AS units_sold,
           SUM(oi.quantity * oi.price) AS revenue
    FROM order_items oi
    JOIN products p ON p.product_id = oi.product_id
    JOIN orders   o ON o.order_id   = oi.order_id
    WHERE $date_cond
      AND o.status = 'Completed'
    GROUP BY oi.product_id
    ORDER BY units_sold DESC
    LIMIT 10
");
$shoe_names = []; $shoe_units = []; $shoe_revenue = []; $rows = [];
while($r = $top_shoes->fetch_assoc()){
    $shoe_names[]   = $r['name'];
    $shoe_units[]   = (int)$r['units_sold'];
    $shoe_revenue[] = round((float)$r['revenue'], 2);
    $rows[]         = $r;
}

// ── Category breakdown ───────────────────────────────────────────────
$cat_names = []; $cat_units_arr = []; $cat_rev_arr = [];
$top_cat_name = '—'; $top_cat_pct = 0; $cat_total_units = 0;
$cat_query = $conn->query("
    SELECT c.category_name,
           SUM(oi.quantity)            AS units_sold,
           SUM(oi.quantity * oi.price) AS revenue
    FROM order_items oi
    JOIN products   p ON p.product_id  = oi.product_id
    JOIN categories c ON c.category_id = p.category_id
    JOIN orders     o ON o.order_id    = oi.order_id
    WHERE $date_cond
      AND o.status = 'Completed'
    GROUP BY c.category_id, c.category_name
    ORDER BY units_sold DESC
");
if($cat_query) while($c = $cat_query->fetch_assoc()){
    $cat_names[]     = $c['category_name'];
    $cat_units_arr[] = (int)$c['units_sold'];
    $cat_rev_arr[]   = round((float)$c['revenue'], 2);
    $cat_total_units += (int)$c['units_sold'];
}
if(!empty($cat_names)){
    $top_cat_name = $cat_names[0];
    $top_cat_pct  = $cat_total_units > 0 ? round($cat_units_arr[0] / $cat_total_units * 100) : 0;
}

// ── Trend chart data ─────────────────────────────────────────────────
$trend_labels = []; $trend_values = []; $trend_title = '';
if($view_mode === 'year'){
    $trend_title = "Monthly Revenue — $year_num";
    $mo_names   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $mo_revenue = array_fill(1, 12, 0.0);
    $yr_trend = $conn->query("
        SELECT MONTH(o.order_date) AS mo,
               COALESCE(SUM(o.total_amount),0) AS revenue
        FROM orders o
        WHERE YEAR(o.order_date) = $year_num
          AND o.status = 'Completed'
        GROUP BY mo
    ");
    if($yr_trend) while($t=$yr_trend->fetch_assoc())
        $mo_revenue[(int)$t['mo']] = round((float)$t['revenue'],2);
    for($m=1;$m<=12;$m++){
        $trend_labels[] = $mo_names[$m-1];
        $trend_values[] = $mo_revenue[$m];
    }
} else {
    $trend_title = 'Revenue Trend — Last 6 Months';
    $mo_trend = $conn->query("
        SELECT DATE_FORMAT(o.order_date,'%b %Y') AS mo_label,
               YEAR(o.order_date)*100+MONTH(o.order_date) AS mo_sort,
               COALESCE(SUM(o.total_amount),0) AS revenue
        FROM orders o
        WHERE o.status = 'Completed'
          AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mo_sort, mo_label
        ORDER BY mo_sort ASC
    ");
    if($mo_trend) while($t=$mo_trend->fetch_assoc()){
        $trend_labels[] = $t['mo_label'];
        $trend_values[] = round((float)$t['revenue'],2);
    }
}
$show_trend = count($trend_labels) > 0 && ($view_mode==='year' || count($trend_labels)>1);

// ── Period totals ────────────────────────────────────────────────────
// TOTAL REVENUE = actual money collected (orders.total_amount = items + shipping
// − voucher discounts) — the SAME definition as the Dashboard and the trend chart.
// The per-shoe/category charts above show gross item value (qty × price) for
// comparison, and the shoe chart is capped to the top 10 — so the period totals
// use their own unlimited queries instead of summing the chart data.
$_pt = $conn->query("
    SELECT COALESCE(SUM(o.total_amount),0) AS net_revenue
    FROM orders o
    WHERE $date_cond AND o.status = 'Completed'
")->fetch_assoc();
$period_total_revenue = (float)$_pt['net_revenue'];

$_pu = $conn->query("
    SELECT COALESCE(SUM(oi.quantity),0)    AS units,
           COUNT(DISTINCT oi.product_id)   AS prods
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    WHERE $date_cond AND o.status = 'Completed'
")->fetch_assoc();
$period_total_units    = (int)$_pu['units'];
$period_total_products = (int)$_pu['prods'];

// Chart colours (PHP mirror for progress bars)
$bar_colors = ['#C8543C','#3b82f6','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ec4899','#84cc16','#f97316','#6366f1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sales Report | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<style>
.chart-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:24px;}
.chart-card h3{font-family:'Oswald',sans-serif;font-size:.85rem;letter-spacing:2px;color:var(--muted);margin-bottom:18px;text-transform:uppercase;}
.period-nav{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.period-nav a{padding:7px 16px;border:1px solid var(--border);border-radius:var(--radius);color:var(--muted);font-size:.82rem;text-decoration:none;transition:.18s;white-space:nowrap;}
.period-nav a:hover{border-color:var(--accent);color:var(--accent);}
.period-nav strong{font-family:'Oswald',sans-serif;font-size:1.1rem;color:var(--white);min-width:130px;text-align:center;}
.view-toggle{display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.view-toggle a{padding:7px 16px;font-size:.78rem;font-weight:700;letter-spacing:1px;text-decoration:none;color:var(--muted);transition:.18s;}
.view-toggle a.active{background:var(--accent);color:#fff;}
.view-toggle a:not(.active):hover{background:var(--navy2);}
.rank-badge{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:.72rem;font-weight:700;}
.kpi-sm{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px 20px;text-align:center;}
.kpi-sm .val{font-family:'Oswald',sans-serif;font-size:1.5rem;color:var(--accent);}
.kpi-sm .lbl{font-size:.7rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-top:4px;}
.kpi-top-cat{background:linear-gradient(135deg,rgba(200,84,60,.18),rgba(200,84,60,.06));border-color:rgba(200,84,60,.45);}
.kpi-top-cat .val{color:#FFD700;}
.cat-bar-wrap{margin-bottom:14px;}
.cat-bar-label{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;}
.cat-bar-label .cat-name{font-size:.85rem;font-weight:600;color:var(--white);}
.cat-bar-label .cat-meta{font-size:.72rem;color:var(--muted);}
.cat-bar-track{height:9px;background:rgba(255,255,255,.07);border-radius:100px;overflow:hidden;}
.cat-bar-fill{height:100%;border-radius:100px;transition:width .5s ease;}
.cat-bar-pct{text-align:right;font-size:.68rem;color:var(--muted);margin-top:3px;font-weight:600;}
.top-cat-crown{font-size:1.1rem;margin-bottom:4px;}
</style>
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar" style="flex-wrap:wrap;gap:12px;">
      <h1>SALES REPORT</h1>
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div class="view-toggle">
          <a href="admin_sales_report.php?view=month&month=<?=$month_num?>&year=<?=$year_num?>"
             class="<?=$view_mode==='month'?'active':''?>">MONTHLY</a>
          <a href="admin_sales_report.php?view=year&year=<?=$year_num?>"
             class="<?=$view_mode==='year'?'active':''?>">YEARLY</a>
        </div>
        <div class="period-nav">
          <a href="<?=$prev_url?>">&#8592; Prev</a>
          <strong><?=e($period_label)?></strong>
          <a href="<?=$next_url?>">Next &#8594;</a>
        </div>
      </div>
    </div>
    <div class="admin-content">

      <?php if(empty($rows)): ?>
      <div class="card" style="padding:48px;text-align:center;color:var(--muted);">
        <div style="font-size:3rem;margin-bottom:14px;opacity:.4;">📊</div>
        <div style="font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:2px;">No sales data for <?=e($period_label)?>.</div>
        <div style="font-size:.82rem;margin-top:8px;">Only Completed orders are counted.</div>
      </div>
      <?php else: ?>

      <!-- KPI row (both views) -->
      <div style="display:grid;grid-template-columns:repeat(<?=$view_mode==='year'?'4':'3'?>,1fr);gap:16px;margin-bottom:22px;">
        <!-- Top Category highlight -->
        <div class="kpi-sm kpi-top-cat">
          <div class="top-cat-crown">&#x1F451;</div>
          <div class="val" style="font-size:1.1rem;line-height:1.2;"><?=e($top_cat_name)?></div>
          <div class="lbl">Top Category · <?=$top_cat_pct?>% of sales</div>
        </div>
        <div class="kpi-sm">
          <div class="val"><?=number_format($period_total_units)?></div>
          <div class="lbl">Total Pairs Sold</div>
        </div>
        <div class="kpi-sm">
          <div class="val">RM <?=number_format($period_total_revenue,0)?></div>
          <div class="lbl">Total Revenue</div>
        </div>
        <?php if($view_mode === 'year'): ?>
        <div class="kpi-sm">
          <div class="val"><?=$period_total_products?></div>
          <div class="lbl">Products Sold</div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Bar + Pie side by side -->
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;">
        <div class="chart-card">
          <h3>Units Sold — Top <?=count($rows)?> Shoes (<?=e($period_label)?>)</h3>
          <div style="position:relative;height:300px;">
            <canvas id="barChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Revenue Share</h3>
          <div style="position:relative;height:300px;">
            <canvas id="pieChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <?php if(!empty($cat_names)): ?>
      <div class="chart-card" style="margin-bottom:24px;">
        <h3>Category Breakdown — <?=e($period_label)?></h3>
        <?php foreach($cat_names as $i => $cname):
          $pct = $cat_total_units > 0 ? round($cat_units_arr[$i] / $cat_total_units * 100) : 0;
          $col = $bar_colors[$i % count($bar_colors)];
          $is_top = $i === 0;
        ?>
        <div class="cat-bar-wrap">
          <div class="cat-bar-label">
            <span class="cat-name">
              <?php if($is_top): ?><span style="color:#FFD700;margin-right:5px;">&#x1F451;</span><?php endif; ?>
              <?=e($cname)?>
              <?php if($is_top): ?><span style="font-size:.65rem;font-weight:400;color:var(--accent);margin-left:6px;background:rgba(200,84,60,.15);padding:2px 7px;border-radius:100px;">BEST</span><?php endif; ?>
            </span>
            <span class="cat-meta"><?=$cat_units_arr[$i]?> pairs &nbsp;·&nbsp; RM <?=number_format($cat_rev_arr[$i],0)?></span>
          </div>
          <div class="cat-bar-track">
            <div class="cat-bar-fill" style="width:<?=$pct?>%;background:<?=$col?>;"></div>
          </div>
          <div class="cat-bar-pct"><?=$pct?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if($show_trend): ?>
      <div class="chart-card" style="margin-bottom:24px;">
        <h3><?=e($trend_title)?></h3>
        <div style="position:relative;height:200px;">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
      <?php endif; ?>

      <!-- Data Table -->
      <div class="admin-table-wrap">
        <div class="admin-table-head">
          <h3>TOP SHOES — <?=e($period_label)?></h3>
          <span style="font-size:.75rem;color:var(--muted);">Sorted by units sold (Completed orders only)</span>
        </div>
        <table class="admin-table">
          <thead>
            <tr><th>#</th><th>Image</th><th>Shoe Name</th><th>Units Sold</th><th>Revenue</th></tr>
          </thead>
          <tbody>
          <?php foreach($rows as $i => $r):
            $rank = $i+1;
            $m_bg = ['#FFD700','#C0C0C0','#CD7F32'];
            $bg   = $rank<=3 ? $m_bg[$rank-1] : 'var(--navy2)';
            $fg   = $rank<=3 ? '#1a1a1a' : 'var(--muted)';
            $img  = !empty($r['image_url'])
              ? (str_starts_with($r['image_url'],'http') ? e($r['image_url']) : '../'.e($r['image_url']))
              : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=60&q=60';
          ?>
          <tr>
            <td><span class="rank-badge" style="background:<?=$bg?>;color:<?=$fg?>;"><?=$rank?></span></td>
            <td><img src="<?=$img?>" alt="" style="width:46px;height:46px;border-radius:6px;object-fit:cover;"></td>
            <td style="font-weight:600;color:var(--white);"><?=e($r['name'])?></td>
            <td>
              <span style="font-family:'Oswald',sans-serif;font-size:1.15rem;color:var(--accent);"><?=(int)$r['units_sold']?></span>
              <span style="font-size:.75rem;color:var(--muted);margin-left:4px;">pairs</span>
            </td>
            <td style="font-weight:600;color:var(--white);">RM <?=number_format((float)$r['revenue'],2)?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php endif; ?>
    </div>
  </main>
</div>

<script>
// Register datalabels plugin globally — explicitly disable it for bar/line charts below
Chart.register(ChartDataLabels);

const COLORS = [
    '#C8543C','#3b82f6','#10b981','#f59e0b','#8b5cf6',
    '#06b6d4','#ec4899','#84cc16','#f97316','#6366f1'
];
const GRID   = 'rgba(255,255,255,.06)';
const TICK   = '#8da0b5';
const ACCENT = '#C8543C';

const shoeNames   = <?=json_encode($shoe_names)?>;
const shoeUnits   = <?=json_encode($shoe_units)?>;
const shoeRevenue = <?=json_encode($shoe_revenue)?>;
const trendLabels = <?=json_encode($trend_labels)?>;
const trendValues = <?=json_encode($trend_values)?>;

<?php if(!empty($rows)): ?>
// ── Bar chart ─────────────────────────────────────────────────────────
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: shoeNames.map(n => n.length > 20 ? n.slice(0,18)+'…' : n),
        datasets:[{
            label: 'Units Sold',
            data: shoeUnits,
            backgroundColor: shoeNames.map((_,i) => COLORS[i % COLORS.length] + 'bb'),
            borderColor:     shoeNames.map((_,i) => COLORS[i % COLORS.length]),
            borderWidth: 1.5,
            borderRadius: 6,
        }]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{
            legend:{ display:false },
            tooltip:{ callbacks:{ label: ctx => ` ${ctx.parsed.y} pairs` }},
            datalabels:{ display:false }
        },
        scales:{
            x:{ ticks:{ color:TICK, maxRotation:35, font:{size:11} }, grid:{ color:GRID }},
            y:{ ticks:{ color:TICK, stepSize:1 }, grid:{ color:GRID }, beginAtZero:true }
        }
    }
});

// ── Doughnut chart — % labels on slices ──────────────────────────────
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: shoeNames.map(n => n.length > 16 ? n.slice(0,14)+'…' : n),
        datasets:[{
            data: shoeRevenue,
            backgroundColor: COLORS.map(c => c+'bb'),
            borderColor:     COLORS,
            borderWidth: 1.5,
            hoverOffset: 8,
        }]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{
            legend:{ position:'bottom', labels:{ color:TICK, font:{size:10}, padding:8, boxWidth:12 }},
            tooltip:{
                callbacks:{
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                        const pct   = (ctx.parsed / total * 100).toFixed(1);
                        return ` RM ${ctx.parsed.toFixed(2)} (${pct}%)`;
                    }
                }
            },
            datalabels:{
                color: '#fff',
                font:{ weight:'bold', size:11 },
                textShadowBlur: 4,
                textShadowColor: 'rgba(0,0,0,.6)',
                formatter: (value, ctx) => {
                    const total = ctx.dataset.data.reduce((a,b)=>a+b, 0);
                    const pct   = value / total * 100;
                    return pct >= 5 ? pct.toFixed(1)+'%' : '';
                }
            }
        }
    }
});
<?php endif; ?>

<?php if($show_trend): ?>
// ── Trend chart ───────────────────────────────────────────────────────
new Chart(document.getElementById('trendChart'), {
    type: <?=$view_mode==='year' ? "'bar'" : "'line'"?>,
    data: {
        labels: trendLabels,
        datasets:[{
            label: 'Revenue (RM)',
            data:  trendValues,
            <?php if($view_mode==='year'): ?>
            backgroundColor: trendLabels.map((_,i) => COLORS[i % COLORS.length]+'bb'),
            borderColor:     trendLabels.map((_,i) => COLORS[i % COLORS.length]),
            borderWidth: 1.5,
            borderRadius: 6,
            <?php else: ?>
            borderColor: ACCENT,
            backgroundColor: 'rgba(200,84,60,.12)',
            pointBackgroundColor: ACCENT,
            pointRadius: 5,
            tension: 0.35,
            fill: true,
            <?php endif; ?>
        }]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{
            legend:{ display:false },
            tooltip:{ callbacks:{ label: ctx => ` RM ${ctx.parsed.y.toFixed(2)}` }},
            datalabels:{ display:false }
        },
        scales:{
            x:{ ticks:{ color:TICK, font:{size:11} }, grid:{ color:GRID }},
            y:{ ticks:{ color:TICK, callback: v => 'RM'+v }, grid:{ color:GRID }, beginAtZero:true }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
