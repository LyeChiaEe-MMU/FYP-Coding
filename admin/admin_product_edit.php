<?php
require_once 'auth_check.php';

$pid     = (int)($_GET['id'] ?? 0);
$product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
if(!$product){ header("Location: admin_products.php"); exit; }

// Handle delete variant image
if(isset($_GET['del_img'])){
    $iid = (int)$_GET['del_img'];
    $tbl = $conn->query("SHOW TABLES LIKE 'product_images'");
    if($tbl->num_rows > 0){
        $row = $conn->query("SELECT image_url FROM product_images WHERE image_id=$iid")->fetch_assoc();
        if($row && !str_starts_with($row['image_url'],'http')){
            $fp = dirname(__DIR__) . '/' . $row['image_url'];
            if(file_exists($fp)) unlink($fp);
        }
        $conn->query("DELETE FROM product_images WHERE image_id=$iid AND product_id=$pid");
    }
    header("Location: admin_product_edit.php?id=$pid&msg=Image+removed."); exit;
}

$msg = ''; $mtype = 'ok';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price']    ?? 0);
    $stock       = (int)($_POST['stock']       ?? 0);
    $image_url   = trim($_POST['image_url']    ?? $product['image_url']);

    // Handle file upload — takes priority over URL if file chosen
    if(!empty($_FILES['image_file']['name'])){
        $file    = $_FILES['image_file'];
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $maxSize = 5 * 1024 * 1024;

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
        if(!$name || $price<=0){
            $msg = "Name and price are required."; $mtype='err';
        } else {
            $stmt = $conn->prepare("UPDATE products SET name=?,description=?,category_id=?,price=?,stock=?,image_url=? WHERE product_id=?");
            $stmt->bind_param("ssidisi",$name,$description,$category_id,$price,$stock,$image_url,$pid);
            $stmt->execute();
            $product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
            $msg = "Product updated successfully.";
        }
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Build image src for preview
$previewSrc = !empty($product['image_url'])
    ? (str_starts_with($product['image_url'],'http') ? $product['image_url'] : '../' . $product['image_url'])
    : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&q=80';
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

      <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

        <!-- Form -->
        <div class="card a-form">
          <h2>EDITING: <?=e($product['name'])?></h2>
          <form method="POST" enctype="multipart/form-data">
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

              <!-- IMAGE: Upload or URL -->
              <div class="form-group span-2">
                <label>Product Image</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                  <!-- Upload -->
                  <div style="background:rgba(100,255,218,.04);border:1px dashed rgba(100,255,218,.3);border-radius:var(--radius);padding:16px;">
                    <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;font-weight:600;">
                      ✅ Upload New Image
                    </div>
                    <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp"
                           style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;"
                           onchange="updatePreview(this)">
                    <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">Replaces current image. Max 5MB.</div>
                  </div>

                  <!-- URL -->
                  <div style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;">
                    <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:600;">
                      Or Keep / Change URL
                    </div>
                    <input type="text" name="image_url" id="editImgUrl"
                           value="<?=e($product['image_url'])?>"
                           placeholder="https://... or uploads/filename.jpg"
                           style="width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;">
                    <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">Leave blank to keep current. Used only if no file uploaded.</div>
                  </div>
                </div>
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

        <!-- Live Preview -->
        <div class="card" style="padding:20px;">
          <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:14px;">Current Image</div>
          <img id="editPreview" src="<?=e($previewSrc)?>"
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

<script>
function updatePreview(input){
    const preview = document.getElementById('editPreview');
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => preview.src = e.target.result;
        reader.readAsDataURL(input.files[0]);
        document.getElementById('editImgUrl').value = '';
    }
}
</script>
</body>
</html>
