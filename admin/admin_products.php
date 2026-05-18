<?php
require_once 'auth_check.php';

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

    // Handle file upload — takes priority over URL if file is chosen
    if(!empty($_FILES['image_file']['name'])){
        $file    = $_FILES['image_file'];
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $maxSize = 5 * 1024 * 1024; // 5MB

        if(!in_array($ext, $allowed)){
            $msg = "Image must be JPG, PNG, GIF or WEBP."; $mtype='err';
        } elseif($file['size'] > $maxSize){
            $msg = "Image must be under 5MB."; $mtype='err';
        } else {
            $filename  = 'product_' . time() . '_' . preg_replace('/[^a-z0-9._-]/i','_',$file['name']);
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if(move_uploaded_file($file['tmp_name'], $uploadDir . $filename)){
                $image_url = 'uploads/' . $filename;
            } else {
                $msg = "Upload failed. Check folder permissions on uploads/."; $mtype='err';
            }
        }
    }

    if(!$msg){
        if(!$name || !$description || !$category_id || $price <= 0){
            $msg = "Name, description, category and price are required."; $mtype='err';
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name,description,category_id,price,stock,image_url) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssidis",$name,$description,$category_id,$price,$stock,$image_url);
            $stmt->execute();
            header("Location: admin_products.php?msg=Product+added+successfully."); exit;
        }
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
        <form method="POST" enctype="multipart/form-data">
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

            <!-- IMAGE: Upload file OR paste URL -->
            <div class="form-group span-2">
              <label>Product Image</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">

                <!-- Option A: Upload from computer -->
                <div style="background:rgba(100,255,218,.04);border:1px dashed rgba(100,255,218,.3);border-radius:var(--radius);padding:16px;">
                  <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;font-weight:600;">
                    ✅ Option A — Upload From Computer
                  </div>
                  <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp"
                         style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;"
                         onchange="previewImg(this)">
                  <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">JPG, PNG, GIF, WEBP — max 5MB</div>
                  <img id="imgPreview" src="" alt="" style="display:none;margin-top:10px;max-width:100%;height:100px;object-fit:contain;border-radius:6px;border:1px solid var(--border);">
                </div>

                <!-- Option B: Paste URL -->
                <div style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;">
                  <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:600;">
                    Option B — Paste Image URL
                  </div>
                  <input type="text" name="image_url" id="imageUrlInput"
                         placeholder="https://images.unsplash.com/..."
                         style="width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;">
                  <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">Used only if no file is uploaded above.</div>
                </div>

              </div>
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
            <?php $products->data_seek(0); while($p=$products->fetch_assoc()):
              // Handle both local paths and full URLs
              $imgSrc = !empty($p['image_url'])
                ? (str_starts_with($p['image_url'],'http') ? e($p['image_url']) : '../' . e($p['image_url']))
                : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=60&q=60';
            ?>
            <tr>
              <td>
                <img src="<?=$imgSrc?>" alt="" style="width:52px;height:52px;border-radius:6px;object-fit:cover;">
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

<script>
function previewImg(input){
    const preview = document.getElementById('imgPreview');
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
        // Clear URL field when file is chosen
        document.getElementById('imageUrlInput').value = '';
    }
}
</script>
</body>
</html>
