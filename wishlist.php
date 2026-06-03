<?php
session_start();
require 'db.php';

if (!is_logged()) { header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS `wishlists` (
    `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id`     int(11) NOT NULL,
    `product_id`  int(11) NOT NULL,
    `added_at`    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wishlist_id`),
    UNIQUE KEY `uq_up` (`user_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `wishlist_notifications` (
    `notif_id`   int(11) NOT NULL AUTO_INCREMENT,
    `user_id`    int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `message`    varchar(300) NOT NULL,
    `is_read`    tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notif_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Mark all notifications as read
$conn->prepare("UPDATE wishlist_notifications SET is_read=1 WHERE user_id=?")
    ->bind_param("i", $uid) || null;
$mark = $conn->prepare("UPDATE wishlist_notifications SET is_read=1 WHERE user_id=?");
$mark->bind_param("i", $uid);
$mark->execute();

// Fetch unread notifications (before marking read, captured first)
$notifs_stmt = $conn->prepare("
    SELECT wn.*, p.name AS product_name, p.image_url
    FROM wishlist_notifications wn
    JOIN products p ON wn.product_id = p.product_id
    WHERE wn.user_id = ?
    ORDER BY wn.created_at DESC
    LIMIT 10
");
$notifs_stmt->bind_param("i", $uid);
$notifs_stmt->execute();
$notifs = $notifs_stmt->get_result();

// Fetch wishlist items
$wl_stmt = $conn->prepare("
    SELECT w.wishlist_id, w.added_at, p.product_id, p.name, p.price, p.image_url, p.stock,
           c.category_name
    FROM wishlists w
    JOIN products p ON w.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
");
$wl_stmt->bind_param("i", $uid);
$wl_stmt->execute();
$wishlist = $wl_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Wishlist | Apex</title>
<link rel="stylesheet" href="css/style.css?v=2">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.notif-card {
    display:flex;align-items:center;gap:14px;padding:14px 18px;
    background:rgba(100,255,218,.06);border:1px solid rgba(100,255,218,.25);
    border-radius:10px;margin-bottom:10px;
}
.notif-card img { width:48px;height:48px;border-radius:6px;object-fit:cover;flex-shrink:0; }
.notif-card .notif-msg { flex:1;font-size:.875rem;color:var(--text);line-height:1.5; }
.notif-card .notif-time { font-size:.72rem;color:var(--muted);white-space:nowrap; }
.wl-grid {
    display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;margin-top:24px;
}
.wl-card { background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:all .2s;position:relative; }
.wl-card:hover { border-color:rgba(100,255,218,.35);transform:translateY(-3px); }
.wl-card img { width:100%;height:200px;object-fit:cover;display:block; }
.wl-card-body { padding:14px 16px; }
.wl-card-cat { font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:4px; }
.wl-card-name { font-weight:600;color:var(--white);font-size:.9rem;margin-bottom:8px;line-height:1.3; }
.wl-card-price { font-family:'Oswald',sans-serif;font-size:1.15rem;color:var(--accent);margin-bottom:12px; }
.wl-remove-btn {
    position:absolute;top:10px;right:10px;
    background:rgba(10,25,47,.85);border:1px solid var(--border);
    color:#ef4444;width:34px;height:34px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:1rem;transition:all .2s;
}
.wl-remove-btn:hover { background:#ef4444;color:#fff;border-color:#ef4444; }
.out-badge {
    position:absolute;top:10px;left:10px;
    background:rgba(239,68,68,.9);color:#fff;
    font-size:.62rem;font-weight:700;letter-spacing:1px;
    padding:3px 9px;border-radius:100px;text-transform:uppercase;
}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>My Wishlist</span></div>
    <h1>MY <span style="color:var(--accent)">WISHLIST</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap" style="max-width:1100px;">

  <?php if ($notifs->num_rows > 0): ?>
  <!-- Price drop notifications -->
  <div style="margin-bottom:40px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
      <i class="fa-solid fa-bell" style="color:var(--accent);font-size:1.1rem;"></i>
      <h3 style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);">PRICE DROP ALERTS</h3>
    </div>
    <?php while ($n = $notifs->fetch_assoc()):
      $nimg = !empty($n['image_url']) ? e($n['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100&q=60';
    ?>
    <div class="notif-card">
      <img src="<?=$nimg?>" alt="">
      <div class="notif-msg">
        <i class="fa-solid fa-tag" style="color:var(--accent);margin-right:6px;"></i>
        <?=e($n['message'])?>
      </div>
      <div class="notif-time"><?=date('d M', strtotime($n['created_at']))?></div>
      <a href="product_detail.php?id=<?=(int)$n['product_id']?>" class="btn btn-primary btn-sm" style="margin-left:10px;">View</a>
    </div>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>

  <!-- Wishlist items -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
    <h3 style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);">
      SAVED ITEMS <span style="color:var(--muted);font-weight:400;font-family:'Inter',sans-serif;font-size:.8rem;letter-spacing:0;">(<?=$wishlist->num_rows?>)</span>
    </h3>
    <a href="products.php" class="btn btn-outline btn-sm">Continue Shopping</a>
  </div>

  <?php if ($wishlist->num_rows === 0): ?>
  <div class="empty-cart" style="padding:80px 0;">
    <div class="ec-icon">🤍</div>
    <h3>Your wishlist is empty</h3>
    <p style="color:var(--muted);margin-bottom:24px;">Save shoes you love — you'll be notified when prices drop!</p>
    <a href="products.php" class="btn btn-primary">Browse Shoes</a>
  </div>

  <?php else: ?>
  <div class="wl-grid">
    <?php while ($item = $wishlist->fetch_assoc()):
      $wimg = !empty($item['image_url'])
          ? (str_starts_with($item['image_url'],'http') ? e($item['image_url']) : e($item['image_url']))
          : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=70';
    ?>
    <div class="wl-card" id="wl-card-<?=(int)$item['product_id']?>">
      <?php if ($item['stock'] < 1): ?>
      <div class="out-badge">Out of Stock</div>
      <?php endif; ?>

      <a href="product_detail.php?id=<?=(int)$item['product_id']?>">
        <img src="<?=$wimg?>" alt="<?=e($item['name'])?>">
      </a>

      <!-- Remove button -->
      <button class="wl-remove-btn" title="Remove from wishlist"
              onclick="removeFromWishlist(<?=(int)$item['product_id']?>)">
        <i class="fa-solid fa-heart-crack"></i>
      </button>

      <div class="wl-card-body">
        <div class="wl-card-cat"><?=e($item['category_name'])?></div>
        <div class="wl-card-name">
          <a href="product_detail.php?id=<?=(int)$item['product_id']?>" style="color:inherit;"><?=e($item['name'])?></a>
        </div>
        <div class="wl-card-price">RM <?=number_format($item['price'],2)?></div>
        <?php if ($item['stock'] > 0): ?>
        <a href="product_detail.php?id=<?=(int)$item['product_id']?>" class="btn btn-primary btn-full btn-sm">View &amp; Buy</a>
        <?php else: ?>
        <button class="btn btn-secondary btn-full btn-sm" disabled>Out of Stock</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>

</div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
const csrfToken = '<?=csrf_token()?>';

function removeFromWishlist(pid){
    if(!confirm('Remove this shoe from your wishlist?')) return;
    fetch('wishlist_action.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'product_id='+pid+'&csrf_token='+encodeURIComponent(csrfToken)
    })
    .then(r=>r.json())
    .then(d=>{
        if(d.wishlisted===false){
            const card = document.getElementById('wl-card-'+pid);
            if(card){ card.style.opacity='0'; card.style.transform='scale(.95)'; card.style.transition='.3s'; setTimeout(()=>card.remove(),300); }
        }
    });
}
</script>
</body>
</html>
