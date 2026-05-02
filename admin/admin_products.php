<?php
session_start();
require '../db.php';
if(!is_admin()){ header("Location: admin_login.php"); exit; }

$msg = ''; $mtype = 'ok';

// Delete
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE product_id=$id");
    header("Location: admin_products.php?msg=Product+deleted.&mtype=err"); exit;
}

// Add Product
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])){
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price']    ?? 0);
    $stock       = (int)($_POST['stock']       ?? 0);
    $image_url   = trim($_POST['image_url']    ?? '');

    if(!$name || !$description || !$category_id || $price <= 0){
        $msg = "All fields are required and price must be > 0."; $mtype='err';
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name,description,category_id,price,stock,image_url) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssidis",$name,$description,$category_id,$price,$stock,$image_url);
        $stmt->execute();
        header("Location: admin_products.php?msg=Product+added+successfully."); exit;
    }
}

$msg   = $msg   ?: ($_GET['msg']   ?? '');
$mtype = $mtype ?: ($_GET['mtype'] ?? 'ok');

$products   = $conn->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id=c.category_id ORDER BY p.created_at DESC");
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Products | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>PRODUCT CATALOG</h1>
      <span style="color:var(--muted);font-size:.875rem;"><?=$products->num_rows?> products</span>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-<?=$mtype?>"><?=e($msg)?></div>
      <?php endif; ?>

      <!-- Add Form -->
      <div class="card a-form" style="max-width:100%;margin-bottom:24px;">
        <h2>ADD NEW PRODUCT</h2>
        <form method="POST">
          <div class="form-grid-2">
            <div class="form-group">
              <label>Product Name *</label>
              <input type="text" name="name" placeholder="e.g. Apex Velocity Pro" required>
            </div>
            <div class="form-group">
              <label>Category *</label>
              <select name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
                <option value="<?=(int)$c['category_id']?>"><?=e($c['category_name'])?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Price (RM) *</label>
              <input type="number" name="price" step="0.01" min="0.01" placeholder="299.00" required>
            </div>
            <div class="form-group">
              <label>Total Stock Qty *</label>
              <input type="number" name="stock" min="0" placeholder="50" required>
            </div>
            <div class="form-group span-2">
              <label>Image URL</label>
              <input type="url" name="image_url" placeholder="https://images.unsplash.com/...">
            </div>
            <div class="form-group span-2">
              <label>Description *</label>
              <textarea name="description" rows="3" placeholder="Describe the shoe..." required></textarea>
            </div>
          </div>
          <button type="submit" name="add_product" class="btn btn-primary" style="margin-top:8px;">+ ADD PRODUCT</button>
        </form>
      </div>

      <!-- Products Table -->
      <div class="admin-table-wrap">
        <div class="admin-table-head"><h3>ALL PRODUCTS</h3></div>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $products->data_seek(0); while($p=$products->fetch_assoc()): ?>
            <tr>
              <td>
                <img src="<?=!empty($p['image_url'])?e($p['image_url']):'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=60&q=60'?>"
                     alt="" style="width:52px;height:52px;border-radius:6px;object-fit:cover;">
              </td>
              <td style="font-weight:600;color:var(--white);"><?=e($p['name'])?></td>
              <td style="color:var(--muted);"><?=e($p['category_name']??'—')?></td>
              <td style="font-family:'Oswald',sans-serif;color:var(--accent);">RM <?=number_format($p['price'],2)?></td>
              <td>
                <span style="color:<?=$p['stock']>0?'var(--white)':'var(--danger)'?>;font-weight:600;">
                  <?=(int)$p['stock']?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:8px;">
                  <a href="admin_product_edit.php?id=<?=(int)$p['product_id']?>" class="btn btn-secondary btn-sm">Edit</a>
                  <a href="admin_products.php?delete=<?=(int)$p['product_id']?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete \'<?=e(addslashes($p['name']))?>'? This cannot be undone.')">Delete</a>
                </div>
              </td>
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
