<?php
session_start();
require '../db.php';
if(!is_admin()){ header("Location: admin_login.php"); exit; }

$customers = $conn->query("
    SELECT u.*,
           COUNT(o.order_id) AS order_count,
           COALESCE(SUM(o.total_amount),0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id=u.user_id
    GROUP BY u.user_id
    ORDER BY u.user_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Customers | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>CUSTOMERS</h1>
      <span style="color:var(--muted);font-size:.875rem;"><?=$customers->num_rows?> registered</span>
    </div>
    <div class="admin-content">
      <div class="admin-table-wrap">
        <div class="admin-table-head"><h3>ALL CUSTOMERS</h3></div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php while($c=$customers->fetch_assoc()): ?>
            <tr>
              <td style="color:var(--muted);">#<?=(int)$c['user_id']?></td>
              <td style="font-weight:600;color:var(--white);"><?=e($c['name'])?></td>
              <td style="color:var(--muted);font-size:.82rem;"><?=e($c['email'])?></td>
              <td style="color:var(--muted);"><?=e($c['phone']??'—')?></td>
              <td>
                <span style="font-family:'Oswald',sans-serif;font-size:1.1rem;color:<?=$c['order_count']>0?'var(--accent)':'var(--muted)'?>;">
                  <?=(int)$c['order_count']?>
                </span>
              </td>
              <td style="font-weight:600;color:var(--white);">RM <?=number_format($c['total_spent'],2)?></td>
              <td style="color:var(--muted);font-size:.8rem;"><?=date('d M Y',strtotime($c['created_at']??'now'))?></td>
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
