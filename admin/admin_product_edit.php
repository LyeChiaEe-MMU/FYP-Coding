<?php
// Use the new auth check instead of old method
require_once 'auth_check.php';

$pid = (int)($_GET['id'] ?? 0);
$product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
if(!$product){ header("Location: admin_products.php"); exit; }

$msg = ''; $mtype = 'ok';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price']    ?? 0);
    $stock       = (int)($_POST['stock']       ?? 0);
    $image_url   = trim($_POST['image_url']    ?? '');

    if(!$name || $price<=0){
        $msg = "Name and price are required."; $mtype='err';
    } else {
        $stmt = $conn->prepare("UPDATE products SET name=?,description=?,category_id=?,price=?,stock=?,image_url=? WHERE product_id=?");
        $stmt->bind_param("ssidisi",$name,$description,$category_id,$price,$stock,$image_url,$pid);
        $stmt->execute();
        // Refresh product
        $product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
        $msg = "Product updated successfully.";
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Product | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>EDIT PRODUCT</h1>
      <a href="admin_products.php" class="btn btn-secondary btn-sm">← Back to Products</a>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-<?=$mtype?>"><?=e($msg)?></div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start;">

        <!-- Form -->
        <div class="card a-form">
          <h2>EDITING: <?=e($product['name'])?></h2>
          <form method="POST">
            <div class="form-grid-2">
              <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" value="<?=e($product['name'])?>" required>
              </div>
              <div class="form-group">
                <label>Category</label>
                <select name="category_id">
                  <?php while($c=$categories->fetch_assoc()): ?>
                  <option value="<?=(int)$c['category_id']?>" <?=$product['category_id']==$c['category_id']?'selected':''?>>
                    <?=e($c['category_name'])?>
                  </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Price (RM) *</label>
                <input type="number" name="price" step="0.01" min="0.01" value="<?=e($product['price'])?>" required>
              </div>
              <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" name="stock" min="0" value="<?=(int)$product['stock']?>">
              </div>
              <div class="form-group span-2">
                <label>Image URL</label>
                <input type="url" name="image_url" value="<?=e($product['image_url'])?>" placeholder="https://...">
              </div>
              <div class="form-group span-2">
                <label>Description</label>
                <textarea name="description" rows="4"><?=e($product['description'])?></textarea>
              </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px;">
              <button type="submit" class="btn btn-primary">SAVE CHANGES</button>
              <a href="admin_products.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>

        <!-- Preview -->
        <div class="card" style="padding:20px;">
          <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:14px;">Preview</div>
          <img src="<?=!empty($product['image_url'])?e($product['image_url']):'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&q=80'?>"
               style="width:100%;border-radius:8px;object-fit:cover;height:200px;background:var(--navy2);">
          <div style="margin-top:14px;">
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);">ID: #<?=$pid?></div>
            <div style="font-weight:600;color:var(--white);margin-top:4px;"><?=e($product['name'])?></div>
            <div style="font-family:'Oswald',sans-serif;font-size:1.4rem;color:var(--accent);margin-top:4px;">RM <?=number_format($product['price'],2)?></div>
            <div style="font-size:.78rem;color:var(--muted);margin-top:4px;">Stock: <?=(int)$product['stock']?> pairs</div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>

