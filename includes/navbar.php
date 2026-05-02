<?php
// includes/navbar.php — include AFTER session_start() and require db.php
$cc  = cart_count($conn);
$pg  = basename($_SERVER['PHP_SELF']);
$depth = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
?>
<nav class="navbar">
  <div class="wrap">
    <a href="<?=$depth?>index.php" class="nav-logo">APE<span>X</span></a>

    <ul class="nav-links" id="navLinks">
      <li><a href="<?=$depth?>index.php"    class="<?=$pg==='index.php'?'active':''?>">Home</a></li>
      <li><a href="<?=$depth?>products.php" class="<?=$pg==='products.php'?'active':''?>">Shop</a></li>
      <?php if(is_logged()): ?>
        <li><a href="<?=$depth?>order_history.php" class="<?=$pg==='order_history.php'?'active':''?>">My Orders</a></li>
        <li><a href="<?=$depth?>logout.php">Logout</a></li>
      <?php else: ?>
        <li><a href="<?=$depth?>login.php"    class="<?=$pg==='login.php'?'active':''?>">Login</a></li>
        <li><a href="<?=$depth?>register.php" class="<?=$pg==='register.php'?'active':''?>">Register</a></li>
      <?php endif; ?>
      <li>
        <a href="<?=$depth?>cart.php" class="nav-cart <?=$pg==='cart.php'?'active':''?>">
          🛒 Cart <?php if($cc>0): ?><span class="cart-badge"><?=$cc?></span><?php endif; ?>
        </a>
      </li>
    </ul>

    <button class="burger" onclick="document.getElementById('navLinks').classList.toggle('open')" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
