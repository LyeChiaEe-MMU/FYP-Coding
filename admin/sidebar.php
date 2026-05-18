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
    📊 &nbsp; Overview
  </a>

  <div class="sidebar-section">Store</div>
  <a href="admin_products.php" class="sidebar-link <?=in_array($pg,['admin_products.php','admin_product_edit.php'])?'active':''?>">
    👟 &nbsp; Products
  </a>
  <a href="admin_orders.php" class="sidebar-link <?=$pg==='admin_orders.php'?'active':''?>">
    📦 &nbsp; Orders
  </a>
  <a href="admin_customers.php" class="sidebar-link <?=$pg==='admin_customers.php'?'active':''?>">
    👥 &nbsp; Customers
  </a>
  <a href="admin_requests.php" class="sidebar-link <?=$pg==='admin_requests.php'?'active':''?>">
    ✏️ &nbsp; Design Requests
  </a>

  <div class="sidebar-section">Account</div>
  <a href="../index.php" class="sidebar-link">🏠 &nbsp; View Store</a>
  <a href="admin_logout.php" class="sidebar-link" style="color:var(--danger);">🚪 &nbsp; Logout</a>
</aside>
