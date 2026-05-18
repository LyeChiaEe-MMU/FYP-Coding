<?php
require_once 'auth_check.php';

$total_sales     = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS s FROM orders WHERE status != 'Cancelled'")->fetch_assoc()['s'];
$total_orders    = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$total_customers = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_products  = $conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$pending_designs = $conn->query("SELECT COUNT(*) AS c FROM design_requests WHERE status='Pending'")->fetch_assoc()['c'];

$recent_orders = $conn->query("
    SELECT o.order_id, u.name, o.total_amount, o.status, o.order_date
    FROM orders o JOIN users u ON o.user_id=u.user_id
    ORDER BY o.order_date DESC LIMIT 8
");

// Sales by status
$by_status = $conn->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>DASHBOARD</h1>
      <span style="color:var(--muted);font-size:.875rem;">Welcome, <?=e($_SESSION['admin_username']??'Admin')?></span>
    </div>
    <div class="admin-content">

      <!-- KPIs -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Total Revenue</div>
          <div class="kpi-value">RM <?=number_format($total_sales,0)?></div>
          <div class="kpi-sub">Completed orders only</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Total Orders</div>
          <div class="kpi-value"><?=$total_orders?></div>
          <div class="kpi-sub"><a href="admin_orders.php" style="color:var(--accent);">Manage →</a></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Customers</div>
          <div class="kpi-value"><?=$total_customers?></div>
          <div class="kpi-sub"><a href="admin_customers.php" style="color:var(--accent);">View All →</a></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Products</div>
          <div class="kpi-value"><?=$total_products?></div>
          <div class="kpi-sub"><a href="admin_products.php" style="color:var(--accent);">Manage →</a></div>
        </div>
        <div class="kpi-card" style="<?=$pending_designs>0?'border-color:rgba(255,200,0,.4);':''?>">
          <div class="kpi-label">Design Requests</div>
          <div class="kpi-value" style="<?=$pending_designs>0?'color:#ffc800':''?>"><?=$pending_designs?></div>
          <div class="kpi-sub"><a href="admin_requests.php?filter=Pending" style="color:var(--accent);">Pending Review →</a></div>
        </div>
      </div>

      <!-- Orders by status mini-bar -->
      <div class="card" style="padding:20px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <span style="font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-right:8px;">Orders by Status:</span>
        <?php while($s=$by_status->fetch_assoc()): ?>
        <span><?=status_badge($s['status'])?> <span style="font-size:.82rem;color:var(--muted);margin-left:4px;"><?=$s['cnt']?></span></span>
        <?php endwhile; ?>
      </div>

      <!-- Recent Orders Table -->
      <div class="admin-table-wrap">
        <div class="admin-table-head">
          <h3>RECENT ORDERS</h3>
          <a href="admin_orders.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while($o=$recent_orders->fetch_assoc()): ?>
            <tr>
              <td style="font-family:'Oswald',sans-serif;color:var(--accent);">#<?=str_pad($o['order_id'],6,'0',STR_PAD_LEFT)?></td>
              <td><?=e($o['name'])?></td>
              <td style="color:var(--muted);font-size:.82rem;"><?=date('d M Y',strtotime($o['order_date']))?></td>
              <td style="font-weight:600;">RM <?=number_format($o['total_amount'],2)?></td>
              <td><?=status_badge($o['status'])?></td>
              <td><a href="admin_orders.php?highlight=<?=$o['order_id']?>" class="btn btn-secondary btn-sm">Manage</a></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
