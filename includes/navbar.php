<?php
$cc    = cart_count($conn);
$pg    = basename($_SERVER['PHP_SELF']);
$depth = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
$q     = e($_GET['q'] ?? '');

// Mega menu structure: Gender → Column → Links
$mega = [
    'Men' => [
        'New & Trending' => [
            'New Arrivals'      => '?gender=Men&sort=newest',
            'Best Sellers'      => '?gender=Men&sort=popular',
            'Sale'              => '?gender=Men&sale=1',
        ],
        'Shoes' => [
            'Running'           => '?gender=Men&cat=Running',
            'Basketball'        => '?gender=Men&cat=Basketball',
            'Training'          => '?gender=Men&cat=Training',
            'Lifestyle'         => '?gender=Men&cat=Lifestyle',
            "All Men's Shoes"   => '?gender=Men',
        ],
        'By Sport' => [
            'Running'           => '?gender=Men&cat=Running',
            'Basketball'        => '?gender=Men&cat=Basketball',
            'Gym & Training'    => '?gender=Men&cat=Training',
            'Street & Casual'   => '?gender=Men&cat=Lifestyle',
        ],
    ],
    'Women' => [
        'New & Trending' => [
            'New Arrivals'        => '?gender=Women&sort=newest',
            'Best Sellers'        => '?gender=Women&sort=popular',
            'Sale'                => '?gender=Women&sale=1',
        ],
        'Shoes' => [
            'Running'             => '?gender=Women&cat=Running',
            'Training'            => '?gender=Women&cat=Training',
            'Lifestyle'           => '?gender=Women&cat=Lifestyle',
            "All Women's Shoes"   => '?gender=Women',
        ],
        'By Sport' => [
            'Running'             => '?gender=Women&cat=Running',
            'Yoga & Training'     => '?gender=Women&cat=Training',
            'Walking & Lifestyle' => '?gender=Women&cat=Lifestyle',
        ],
    ],
    'Kids' => [
        'New & Trending' => [
            'New Arrivals'      => '?gender=Kids&sort=newest',
            'Best Sellers'      => '?gender=Kids&sort=popular',
        ],
        'Shoes' => [
            'Running'           => '?gender=Kids&cat=Running',
            'Training'          => '?gender=Kids&cat=Training',
            'Lifestyle'         => '?gender=Kids&cat=Lifestyle',
            "All Kids' Shoes"   => '?gender=Kids',
        ],
        'By Age' => [
            'Infant (0–2 yrs)'  => '?gender=Kids&age=infant',
            'Kids (3–8 yrs)'    => '?gender=Kids&age=kids',
            'Junior (9–14 yrs)' => '?gender=Kids&age=junior',
        ],
    ],
];
?>
<style>
/* ═══════ APEX MEGA NAV ═══════ */
.apex-nav {
    position:sticky;top:0;z-index:1000;
    background:rgba(10,25,47,.98);
    backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border);
}

/* ── Top bar ── */
.apex-top {
    max-width:1200px;margin:0 auto;padding:0 24px;
    height:64px;display:flex;align-items:center;
    justify-content:space-between;gap:20px;
}
.apex-logo {
    font-family:'Oswald',sans-serif;font-size:1.7rem;
    letter-spacing:4px;color:var(--white);flex-shrink:0;text-decoration:none;
}
.apex-logo span{color:var(--accent)}

/* Search */
.apex-search{flex:1;max-width:360px;position:relative}
.apex-search input{
    width:100%;background:var(--navy2);
    border:1px solid var(--border);border-radius:100px;
    padding:9px 18px 9px 40px;color:var(--white);
    font-size:.875rem;outline:none;transition:border-color .2s;
    font-family:'Inter',sans-serif;
}
.apex-search input:focus{border-color:var(--accent)}
.apex-search input::placeholder{color:var(--muted)}
.apex-s-ico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}
#apex-sdrop{
    display:none;position:absolute;top:calc(100% + 8px);left:0;right:0;
    background:var(--navy2);border:1px solid var(--border);
    border-radius:10px;overflow:hidden;z-index:9999;
    box-shadow:0 20px 56px rgba(0,0,0,.7);
}
#apex-sdrop a{
    display:flex;align-items:center;gap:12px;
    padding:11px 16px;border-bottom:1px solid var(--border);
    text-decoration:none;color:var(--text);transition:background .15s;
}
#apex-sdrop a:last-child{border-bottom:none}
#apex-sdrop a:hover{background:rgba(100,255,218,.06)}
#apex-sdrop img{width:44px;height:44px;border-radius:6px;object-fit:cover;flex-shrink:0}

/* Right links */
.apex-right{display:flex;align-items:center;gap:4px}
.apex-right a{
    padding:8px 12px;font-size:.875rem;font-weight:500;
    color:var(--muted);border-radius:var(--radius);
    text-decoration:none;transition:color .2s;white-space:nowrap;
}
.apex-right a:hover,.apex-right a.on{color:var(--white)}
.apex-cart{
    display:flex!important;align-items:center;gap:6px;
    background:var(--accent)!important;color:var(--navy)!important;
    font-weight:700!important;border-radius:var(--radius)!important;
    padding:8px 16px!important;
}
.apex-cart:hover{background:#4ee8c8!important}
.apex-cbadge{
    background:var(--danger);color:#fff;border-radius:50%;
    width:16px;height:16px;font-size:.58rem;font-weight:700;
    display:inline-flex;align-items:center;justify-content:center;
}

/* ── Main nav row ── */
.apex-main-nav{
    border-top:1px solid var(--border);
    background:rgba(8,12,24,.95);
}
.apex-main-inner{
    max-width:1200px;margin:0 auto;padding:0 24px;
    display:flex;align-items:stretch;
}

/* Nav items with mega dropdown */
.mni{position:static}
.mni-btn{
    display:block;padding:14px 22px;
    font-family:'Oswald',sans-serif;font-size:.95rem;letter-spacing:2px;
    color:var(--muted);background:none;border:none;cursor:pointer;
    border-bottom:3px solid transparent;transition:all .2s;
    white-space:nowrap;font-weight:500;
}
.mni:hover .mni-btn{color:var(--white);border-bottom-color:var(--accent)}

/* Plain nav links */
.mni-link{
    display:block;padding:14px 22px;
    font-family:'Oswald',sans-serif;font-size:.95rem;letter-spacing:2px;
    color:var(--muted);text-decoration:none;
    border-bottom:3px solid transparent;transition:all .2s;white-space:nowrap;
}
.mni-link:hover,.mni-link.on{color:var(--white);border-bottom-color:var(--accent)}

/* ── MEGA DROPDOWN PANEL ── */
.mega-panel{
    display:none;
    position:fixed;
    left:0;right:0;
    background:var(--navy2);
    border-top:2px solid var(--accent);
    border-bottom:1px solid var(--border);
    box-shadow:0 24px 60px rgba(0,0,0,.7);
    z-index:998;
    animation:mpFade .18s ease;
}
@keyframes mpFade{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.mni:hover .mega-panel{display:block}

.mega-inner{
    max-width:1200px;margin:0 auto;
    padding:36px 24px 32px;
    display:flex;gap:48px;align-items:flex-start;
}

/* Columns */
.mega-col{min-width:160px}
.mega-col-head{
    font-size:.65rem;letter-spacing:3px;text-transform:uppercase;
    color:var(--muted);font-weight:700;
    margin-bottom:14px;padding-bottom:10px;
    border-bottom:1px solid var(--border);
}
.mega-col ul{list-style:none;padding:0;margin:0}
.mega-col ul li{margin-bottom:4px}
.mega-col ul li a{
    font-size:.875rem;color:var(--muted);
    text-decoration:none;transition:all .15s;
    display:block;padding:4px 0;
}
.mega-col ul li a:hover{color:var(--accent);padding-left:8px}

/* Featured cards */
.mega-feat{margin-left:auto;display:flex;gap:14px;flex-shrink:0}
.mega-feat-card{
    width:190px;border-radius:10px;overflow:hidden;
    border:1px solid var(--border);text-decoration:none;
    color:var(--text);transition:all .2s;background:var(--navy);
    display:block;
}
.mega-feat-card:hover{border-color:rgba(100,255,218,.4);transform:translateY(-3px)}
.mega-feat-card img{width:100%;height:130px;object-fit:cover;display:block}
.mega-feat-body{padding:10px 12px}
.mega-feat-lbl{font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted)}
.mega-feat-name{font-size:.82rem;font-weight:600;color:var(--white);margin-top:3px;line-height:1.3}
.mega-feat-price{font-family:'Oswald',sans-serif;color:var(--accent);font-size:.9rem;margin-top:3px}

/* Bottom bar of dropdown */
.mega-footer{
    border-top:1px solid var(--border);
    padding:12px 24px;
    max-width:1200px;margin:0 auto;
    display:flex;align-items:center;justify-content:space-between;
}
.mega-view-all{
    display:inline-flex;align-items:center;gap:6px;
    color:var(--accent);font-size:.82rem;font-weight:600;
    text-decoration:none;letter-spacing:.5px;
    border-bottom:1px solid rgba(100,255,218,.3);padding-bottom:2px;
}
.mega-view-all:hover{border-color:var(--accent)}

/* Burger */
.apex-burger{
    display:none;flex-direction:column;gap:5px;
    background:none;border:none;cursor:pointer;padding:6px;
}
.apex-burger span{width:22px;height:2px;background:var(--white);border-radius:2px;display:block}

/* Mobile drawer */
.apex-mob{
    display:none;flex-direction:column;gap:4px;
    position:fixed;top:64px;left:0;right:0;
    background:var(--navy2);border-bottom:1px solid var(--border);
    padding:14px;z-index:997;
    max-height:calc(100vh - 64px);overflow-y:auto;
}
.apex-mob.mob-open{display:flex}
.apex-mob a{
    padding:11px 14px;border-radius:var(--radius);
    color:var(--muted);font-size:.9rem;font-weight:500;
    text-decoration:none;transition:all .15s;
}
.apex-mob a:hover{color:var(--white);background:rgba(255,255,255,.04)}
.apex-mob .mob-group{
    font-size:.65rem;letter-spacing:3px;text-transform:uppercase;
    color:var(--muted);padding:12px 14px 4px;font-weight:700;
}

@media(max-width:960px){
    .apex-right{display:none}
    .apex-main-inner{display:none}
    .apex-burger{display:flex}
    .apex-search{max-width:220px}
}
@media(max-width:560px){.apex-search{display:none}}
</style>

<header class="apex-nav">

  <!-- ── Top bar ── -->
  <div class="apex-top">
    <a href="<?=$depth?>index.php" class="apex-logo">APE<span>X</span></a>

    <!-- Search -->
    <div class="apex-search" id="apexSrchWrap">
      <form action="<?=$depth?>products.php" method="GET" autocomplete="off">
        <span class="apex-s-ico"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="text" name="q" id="apexSrch"
               placeholder="Search shoes... e.g. Apex Gen"
               value="<?=$q?>"
               onfocus="this.style.borderColor='var(--accent)'"
               onblur="setTimeout(hideApexDrop,200)"
               oninput="apexSearch(this.value)">
      </form>
      <div id="apex-sdrop"></div>
    </div>

    <!-- Right icons -->
    <div class="apex-right">
      <?php if(is_logged()): ?>
        <a href="<?=$depth?>order_history.php" class="<?=$pg==='order_history.php'?'on':''?>">My Orders</a>
        <a href="<?=$depth?>design_request.php">✏️ Design</a>
        <a href="<?=$depth?>logout.php">Logout</a>
      <?php else: ?>
        <a href="<?=$depth?>login.php"    class="<?=$pg==='login.php'?'on':''?>">Login</a>
        <a href="<?=$depth?>register.php" class="<?=$pg==='register.php'?'on':''?>">Register</a>
      <?php endif; ?>
      <a href="<?=$depth?>cart.php" class="apex-cart <?=$pg==='cart.php'?'on':''?>">
        🛒&nbsp;Cart
        <?php if($cc>0): ?><span class="apex-cbadge"><?=$cc?></span><?php endif; ?>
      </a>
    </div>

    <button class="apex-burger" id="apexBurger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>

  <!-- ── Main nav row ── -->
  <nav class="apex-main-nav">
    <div class="apex-main-inner">

      <?php foreach($mega as $gender => $columns): ?>
      <div class="mni">
        <button class="mni-btn"><?=strtoupper($gender)?></button>

        <div class="mega-panel">
          <div class="mega-inner">

            <!-- Columns -->
            <?php foreach($columns as $head => $links): ?>
            <div class="mega-col">
              <div class="mega-col-head"><?=htmlspecialchars($head)?></div>
              <ul>
                <?php foreach($links as $label => $url): ?>
                <li><a href="<?=$depth?>products.php<?=htmlspecialchars($url)?>"><?=htmlspecialchars($label)?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endforeach; ?>

            <!-- Featured products -->
            <div class="mega-feat">
              <?php
              $fp = $conn->query("SELECT p.product_id,p.name,p.price,p.image_url,c.category_name FROM products p JOIN categories c ON p.category_id=c.category_id ORDER BY p.created_at DESC LIMIT 2");
              if($fp && $fp->num_rows>0): while($fr=$fp->fetch_assoc()):
                $fimg = !empty($fr['image_url']) ? e($fr['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&q=70';
              ?>
              <a href="<?=$depth?>product_detail.php?id=<?=(int)$fr['product_id']?>" class="mega-feat-card">
                <img src="<?=$fimg?>" alt="<?=e($fr['name'])?>">
                <div class="mega-feat-body">
                  <div class="mega-feat-lbl"><?=e($fr['category_name'])?></div>
                  <div class="mega-feat-name"><?=e($fr['name'])?></div>
                  <div class="mega-feat-price">RM <?=number_format($fr['price'],2)?></div>
                </div>
              </a>
              <?php endwhile; endif; ?>
            </div>

          </div>

          <!-- Footer -->
          <div class="mega-footer">
            <a href="<?=$depth?>products.php?gender=<?=urlencode($gender)?>" class="mega-view-all">
              View All <?=$gender?>'s Shoes →
            </a>
            <span style="font-size:.75rem;color:var(--muted);">
              <?=$gender==='Men'?'Running · Basketball · Training · Lifestyle':($gender==='Women'?'Running · Training · Lifestyle':'Running · Training · Lifestyle')?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Static links -->
      <a href="<?=$depth?>products.php" class="mni-link <?=$pg==='products.php'&&empty($_GET['gender'])?'on':''?>">SHOP ALL</a>
      <a href="<?=$depth?>leaderboard.php" class="mni-link <?=$pg==='leaderboard.php'?'on':''?>">🏆 LEADERBOARD</a>

    </div>
  </nav>

</header>

<!-- Mobile drawer -->
<div class="apex-mob" id="apexMob">
  <a href="<?=$depth?>index.php">Home</a>
  <div class="mob-group">Men</div>
  <a href="<?=$depth?>products.php?gender=Men">All Men's Shoes</a>
  <a href="<?=$depth?>products.php?gender=Men&cat=Running">  › Running</a>
  <a href="<?=$depth?>products.php?gender=Men&cat=Basketball">  › Basketball</a>
  <a href="<?=$depth?>products.php?gender=Men&cat=Training">  › Training</a>
  <div class="mob-group">Women</div>
  <a href="<?=$depth?>products.php?gender=Women">All Women's Shoes</a>
  <a href="<?=$depth?>products.php?gender=Women&cat=Running">  › Running</a>
  <a href="<?=$depth?>products.php?gender=Women&cat=Training">  › Training</a>
  <a href="<?=$depth?>products.php?gender=Women&cat=Lifestyle">  › Lifestyle</a>
  <div class="mob-group">Kids</div>
  <a href="<?=$depth?>products.php?gender=Kids">All Kids' Shoes</a>
  <a href="<?=$depth?>products.php?gender=Kids&cat=Running">  › Running</a>
  <a href="<?=$depth?>products.php?gender=Kids&cat=Lifestyle">  › Lifestyle</a>
  <div class="mob-group">More</div>
  <a href="<?=$depth?>products.php">Shop All</a>
  <a href="<?=$depth?>leaderboard.php">🏆 Leaderboard</a>
  <?php if(is_logged()): ?>
    <a href="<?=$depth?>order_history.php">My Orders</a>
    <a href="<?=$depth?>design_request.php">✏️ Design Your Shoe</a>
    <a href="<?=$depth?>logout.php">Logout</a>
  <?php else: ?>
    <a href="<?=$depth?>login.php">Login</a>
    <a href="<?=$depth?>register.php">Register</a>
  <?php endif; ?>
  <a href="<?=$depth?>cart.php" class="apex-cart" style="justify-content:center;margin-top:6px;border-radius:var(--radius);">
    🛒 Cart <?php if($cc>0): ?><span class="apex-cbadge"><?=$cc?></span><?php endif; ?>
  </a>
</div>

<script>
// Burger
document.getElementById('apexBurger')?.addEventListener('click',()=>{
    document.getElementById('apexMob').classList.toggle('mob-open');
});

// Live search
const apexSrchEl = document.getElementById('apexSrch');
const apexDropEl = document.getElementById('apex-sdrop');
let apexTimer;

function apexSearch(val){
    clearTimeout(apexTimer);
    const q=val.trim();
    if(q.length<2){hideApexDrop();return;}
    apexTimer=setTimeout(()=>{
        fetch('<?=$depth?>search_ajax.php?q='+encodeURIComponent(q))
            .then(r=>r.json())
            .then(data=>{
                if(!data.length){hideApexDrop();return;}
                apexDropEl.innerHTML=data.map(p=>`
                    <a href="<?=$depth?>product_detail.php?id=${p.id}">
                        <img src="${p.image}" alt="">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.88rem;font-weight:600;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${apexHl(p.name,q)}</div>
                            <div style="font-size:.72rem;color:var(--muted);margin-top:2px;">${p.category}</div>
                        </div>
                        <div style="font-family:'Oswald',sans-serif;font-size:1rem;color:var(--accent);flex-shrink:0;">RM ${parseFloat(p.price).toFixed(2)}</div>
                    </a>
                `).join('')+`
                <a href="<?=$depth?>products.php?q=${encodeURIComponent(q)}"
                   style="display:block;text-align:center;padding:11px;font-size:.82rem;color:var(--accent);font-weight:600;border-top:1px solid var(--border);">
                   🔍 See all results for "${q.replace(/</g,'&lt;')}"
                </a>`;
                apexDropEl.style.display='block';
            });
    },220);
}

function apexHl(text,query){
    const words=query.split(/\s+/).filter(w=>w.length>0);
    let r=text;
    words.forEach(w=>{
        const re=new RegExp('('+w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi');
        r=r.replace(re,'<span style="color:var(--accent);font-weight:700;">$1</span>');
    });
    return r;
}

function hideApexDrop(){if(apexDropEl)apexDropEl.style.display='none';}
document.addEventListener('click',e=>{
    if(!document.getElementById('apexSrchWrap')?.contains(e.target))hideApexDrop();
});
apexDropEl?.addEventListener('mousedown',e=>e.preventDefault());
</script>
