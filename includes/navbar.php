<?php
// includes/navbar.php
$cc    = cart_count($conn);
$pg    = basename($_SERVER['PHP_SELF']);
$depth = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
$q     = e($_GET['q'] ?? '');
?>
<nav class="navbar">
  <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;height:100%;gap:16px;">

    <!-- Logo -->
    <a href="<?=$depth?>index.php" class="nav-logo" style="flex-shrink:0;">APE<span>X</span></a>

    <!-- ── Search Bar ── -->
    <div style="position:relative;">
          <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.9rem;pointer-events:none;">
            <i class="fa-solid fa-magnifying-glass"></i>
          </span>
          <input
            type="text"
            name="q"
            id="searchInput"
            placeholder="Search shoes... e.g. Apex Gen"
            value="<?=$q?>"
            style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:100px;padding:9px 18px 9px 40px;color:var(--white);font-size:.875rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--accent)'"
            onblur="setTimeout(()=>hideDrop(),200)"
            oninput="liveSearch(this.value)"
          >
          <!-- Clear button -->
          <?php if ($q && $pg === 'products.php'): ?>
          <a href="<?=$depth?>products.php"
             style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:1rem;line-height:1;text-decoration:none;"
             title="Clear search">✕</a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Live dropdown -->
      <div id="searchDrop"
           style="display:none;position:absolute;top:calc(100% + 8px);left:0;right:0;background:var(--navy2);border:1px solid var(--border);border-radius:10px;overflow:hidden;z-index:9999;box-shadow:0 16px 48px rgba(0,0,0,.6);">
        <!-- results injected here -->
      </div>
    </div>

    <!-- Nav Links -->
    <ul class="nav-links" id="navLinks">
      <li><a href="<?=$depth?>index.php"    class="<?=$pg==='index.php'   ?'active':''?>">Home</a></li>
      <li><a href="<?=$depth?>products.php" class="<?=$pg==='products.php'?'active':''?>">Shop</a></li>
      <?php if (is_logged()): ?>
        <li><a href="<?=$depth?>order_history.php" class="<?=$pg==='order_history.php'?'active':''?>">My Orders</a></li>
        <li><a href="<?=$depth?>logout.php">Logout</a></li>
      <?php else: ?>
        <li><a href="<?=$depth?>login.php"    class="<?=$pg==='login.php'   ?'active':''?>">Login</a></li>
        <li><a href="<?=$depth?>register.php">Register</a></li>
      <?php endif; ?>
      <li>
        <a href="<?=$depth?>cart.php" class="nav-cart <?=$pg==='cart.php'?'active':''?>">
          🛒 Cart <?php if ($cc > 0): ?><span class="cart-badge"><?=$cc?></span><?php endif; ?>
        </a>
      </li>
    </ul>

    <button class="burger" onclick="document.getElementById('navLinks').classList.toggle('open')" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<script>
const drop  = document.getElementById('searchDrop');
const input = document.getElementById('searchInput');
let timer;

function liveSearch(val) {
    clearTimeout(timer);
    const q = val.trim();
    if (q.length < 2) { hideDrop(); return; }
    timer = setTimeout(() => {
        fetch('<?=$depth?>search_ajax.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) { hideDrop(); return; }
                drop.innerHTML = data.map(p => `
                    <a href="<?=$depth?>product_detail.php?id=${p.id}"
                       style="display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);transition:background .15s;"
                       onmouseover="this.style.background='rgba(100,255,218,.06)'"
                       onmouseout="this.style.background='transparent'">
                        <img src="${p.image}" style="width:46px;height:46px;border-radius:6px;object-fit:cover;flex-shrink:0;background:var(--navy3);">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.88rem;font-weight:600;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${highlight(p.name, '${q.replace(/'/g,"\\'")}')}</div>
                            <div style="font-size:.72rem;color:var(--muted);margin-top:2px;">${p.category}</div>
                        </div>
                        <div style="font-family:'Oswald',sans-serif;font-size:1rem;color:var(--accent);flex-shrink:0;">RM ${parseFloat(p.price).toFixed(2)}</div>
                    </a>
                `).join('') +
                `<a href="<?=$depth?>products.php?q=${encodeURIComponent(q)}"
                   style="display:block;text-align:center;padding:11px;font-size:.82rem;color:var(--accent);font-weight:600;letter-spacing:1px;border-top:1px solid var(--border);text-decoration:none;transition:background .15s;"
                   onmouseover="this.style.background='rgba(100,255,218,.06)'"
                   onmouseout="this.style.background='transparent'">
                   🔍 See all results for "${q.replace(/</g,'&lt;')}"
                </a>`;
                drop.style.display = 'block';
            });
    }, 220);
}

function highlight(text, query) {
    // Highlight each keyword in the result name
    const words = query.split(/\s+/).filter(w => w.length > 0);
    let result = text;
    words.forEach(w => {
        const re = new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
        result = result.replace(re, '<span style="color:var(--accent);font-weight:700;">$1</span>');
    });
    return result;
}

function hideDrop() { drop.style.display = 'none'; }

// Submit on Enter (already handled by form), hide on click outside
document.addEventListener('click', e => {
    if (!document.getElementById('searchWrap').contains(e.target)) hideDrop();
});

// Keep dropdown open while interacting with it
drop?.addEventListener('mousedown', e => e.preventDefault());
</script>
