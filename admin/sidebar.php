<?php
// admin/sidebar.php
$pg = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
  <div class="logo-area">
    APE<span>X</span>
    <small>Admin Panel</small>
  </div>

  <div class="sidebar-section">Dashboard</div>
  <a href="admin_dashboard.php" class="sidebar-link <?=$pg==='admin_dashboard.php'?'active':''?>">
    Overview
  </a>

  <div class="sidebar-section">Store</div>
  <a href="admin_products.php" class="sidebar-link <?=in_array($pg,['admin_products.php','admin_product_edit.php'])?'active':''?>">
    Products
  </a>
  <a href="admin_orders.php" class="sidebar-link <?=$pg==='admin_orders.php'?'active':''?>">
    Orders
  </a>
  <a href="admin_customers.php" class="sidebar-link <?=$pg==='admin_customers.php'?'active':''?>">
    Customers
  </a>
  <a href="admin_requests.php" class="sidebar-link <?=$pg==='admin_requests.php'?'active':''?>">
    Design Requests
  </a>
  <?php
  $sb_unread_msgs = 0;
  $sb_tbl = $conn->query("SHOW TABLES LIKE 'contact_messages'");
  if($sb_tbl && $sb_tbl->num_rows > 0){
      $sb_unread_msgs = (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'];
  }
  ?>
  <a href="admin_messages.php" class="sidebar-link <?=$pg==='admin_messages.php'?'active':''?>"
     style="display:flex;align-items:center;justify-content:space-between;">
    <span>Messages</span>
    <?php if($sb_unread_msgs > 0): ?>
    <span style="background:var(--accent);color:#fff;border-radius:100px;font-size:.58rem;font-weight:700;padding:2px 7px;letter-spacing:.5px;"><?=$sb_unread_msgs?></span>
    <?php endif; ?>
  </a>
  <a href="admin_banners.php" class="sidebar-link <?=$pg==='admin_banners.php'?'active':''?>">
    Banners
  </a>

  <div class="sidebar-section">Account</div>
  <a href="../index.php" class="sidebar-link">View Store</a>
  <a href="admin_logout.php" class="sidebar-link" style="color:var(--danger);">Logout</a>
</aside>
