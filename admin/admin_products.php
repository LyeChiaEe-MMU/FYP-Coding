<?php
require_once 'auth_check.php';

$msg = ''; $mtype = 'ok';

// ── DELETE with password verification ────────────
if(isset($_POST['confirm_delete']) || (isset($_POST['add_product']) && $_SERVER['REQUEST_METHOD']==='POST')){
    csrf_check();
}
if(isset($_POST['confirm_delete'])){
    $id       = (int)$_POST['delete_id'];
    $password = $_POST['admin_password'] ?? '';

    // Verify password against current admin account
    $admin = $conn->query("SELECT password FROM admins WHERE admin_id=" . (int)$_SESSION['admin_id'])->fetch_assoc();
    if($admin && password_verify($password, $admin['password'])){
        // Get image path before deleting
        $prod = $conn->query("SELECT image_url FROM products WHERE product_id=$id")->fetch_assoc();

        // Delete variant images from disk
        $variants = $conn->query("SELECT image_url FROM product_images WHERE product_id=$id");
        if($variants) while($v=$variants->fetch_assoc()){
            if(!empty($v['image_url']) && !str_starts_with($v['image_url'],'http')){
                $fp = dirname(__DIR__) . '/' . $v['image_url'];
                if(file_exists($fp)) unlink($fp);
            }
        }

        // Delete main image from disk
        if($prod && !empty($prod['image_url']) && !str_starts_with($prod['image_url'],'http')){
            $fp = dirname(__DIR__) . '/' . $prod['image_url'];
            if(file_exists($fp)) unlink($fp);
        }

        // Delete product (cascade deletes product_size, product_images, product_stock)
        $conn->query("DELETE FROM products WHERE product_id=$id");
        header("Location: admin_products.php?msg=Product+deleted+and+images+removed.&mtype=err"); exit;
    } else {
        $msg = "Incorrect password. Product was NOT deleted."; $mtype='err';
    }
}

// ── Add Product ───────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])){
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price']   ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $image_url   = trim($_POST['image_url']   ?? '');
    $allowed_genders = ['Men','Women','Kids','Unisex'];
    $gender      = in_array($_POST['gender'] ?? '', $allowed_genders) ? $_POST['gender'] : 'Unisex';

    if(!empty($_FILES['image_file']['name'])){
        $file = $_FILES['image_file'];
        $upload_err = '';
        if(!valid_image_upload($file, $upload_err)){
            $msg = $upload_err; $mtype='err';
        } else {
            $filename  = 'product_' . time() . '_' . preg_replace('/[^a-z0-9._-]/i','_',$file['name']);
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if(move_uploaded_file($file['tmp_name'], $uploadDir . $filename)){
                $image_url = 'uploads/' . $filename;
            } else {
                $msg = "Upload failed. Check folder permissions."; $mtype='err';
            }
        }
    }

    if(!$msg){
        if(!$name || !$description || !$category_id || $price <= 0){
            $msg = "Name, description, category and price are required."; $mtype='err';
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name,description,category_id,gender,price,stock,image_url) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssisdis",$name,$description,$category_id,$gender,$price,$stock,$image_url);
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
<link rel="stylesheet" href="../css/style.css?v=3">
<style>
/* Password modal */
.del-modal-bg{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);
    z-index:9999;align-items:center;justify-content:center;
}
.del-modal-bg.open{display:flex}
.del-modal{
    background:var(--navy2);border:1px solid var(--border);border-radius:12px;
    padding:32px;max-width:420px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.8);
}
.del-modal h3{
    font-family:'Oswald',sans-serif;font-size:1.2rem;letter-spacing:2px;
    color:var(--white);margin-bottom:8px;
}
.del-modal p{color:var(--muted);font-size:.875rem;margin-bottom:20px;line-height:1.6}
.del-modal input{
    width:100%;background:var(--navy);border:1px solid var(--border);
    border-radius:var(--radius);padding:11px 14px;color:var(--white);
    font-size:.9rem;margin-bottom:16px;outline:none;
    transition:border-color .2s;
}
.del-modal input:focus{border-color:var(--danger)}
.del-modal-btns{display:flex;gap:10px}
.del-modal-btns button{flex:1}
</style>
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
          <?=csrf_field()?>
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
              <label>Gender *</label>
              <select name="gender" required>
                <option value="Unisex">Unisex (shows in all)</option>
                <option value="Men">Men</option>
                <option value="Women">Women</option>
                <option value="Kids">Kids</option>
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
              <label>Product Image</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
                <div style="background:rgba(100,255,218,.04);border:1px dashed rgba(100,255,218,.3);border-radius:var(--radius);padding:16px;">
                  <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;font-weight:600;">✅ Upload From Computer</div>
                  <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp"
                         style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;"
                         onchange="previewImg(this)">
                  <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">JPG, PNG, GIF, WEBP</div>
                  <img id="imgPreview" src="" alt="" style="display:none;margin-top:10px;max-width:100%;height:100px;object-fit:contain;border-radius:6px;border:1px solid var(--border);">
                </div>
                <div style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;">
                  <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:600;">Or Paste Image URL</div>
                  <input type="text" name="image_url" id="imageUrlInput" placeholder="https://images.unsplash.com/..."
                         style="width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;">
                  <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">Used only if no file uploaded above.</div>
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
            <tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php $products->data_seek(0); while($p=$products->fetch_assoc()):
              $imgSrc = !empty($p['image_url'])
                ? (str_starts_with($p['image_url'],'http') ? e($p['image_url']) : '../' . e($p['image_url']))
                : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=60&q=60';
            ?>
            <tr>
              <td><img src="<?=$imgSrc?>" alt="" style="width:52px;height:52px;border-radius:6px;object-fit:cover;"></td>
              <td style="font-weight:600;color:var(--white);"><?=e($p['name'])?></td>
              <td style="color:var(--muted);"><?=e($p['category_name']??'—')?></td>
              <td style="font-family:'Oswald',sans-serif;color:var(--accent);">RM <?=number_format($p['price'],2)?></td>
              <td><span style="color:<?=$p['stock']>0?'var(--white)':'var(--danger)'?>;font-weight:600;"><?=(int)$p['stock']?></span></td>
              <td>
                <div style="display:flex;gap:8px;">
                  <a href="admin_product_edit.php?id=<?=(int)$p['product_id']?>" class="btn btn-secondary btn-sm">Edit</a>
                  <button type="button" class="btn btn-danger btn-sm"
                          onclick="openDeleteModal(<?=(int)$p['product_id']?>, '<?=e(addslashes($p['name']))?>')">
                    Delete
                  </button>
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

<!-- ── Password Confirmation Modal ── -->
<div class="del-modal-bg" id="deleteModal">
  <div class="del-modal">
    <div style="font-size:1.8rem;margin-bottom:12px;">🗑️</div>
    <h3>CONFIRM DELETE</h3>
    <p>
      You are about to delete <strong id="delProductName" style="color:var(--white);"></strong>.<br>
      This will also remove all variant images from the server.<br><br>
      Enter your admin password to confirm:
    </p>
    <form method="POST" id="deleteForm">
      <?=csrf_field()?>
      <input type="hidden" name="confirm_delete" value="1">
      <input type="hidden" name="delete_id" id="deleteProductId">
      <input type="password" name="admin_password" id="adminPasswordInput"
             placeholder="Enter your password" autocomplete="current-password" required>
      <div class="del-modal-btns">
        <button type="submit" class="btn btn-danger">CONFIRM DELETE</button>
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
    <div style="font-size:.72rem;color:var(--muted);margin-top:12px;text-align:center;">
      Logged in as: <strong style="color:var(--white);"><?=e($_SESSION['admin_username']??'Admin')?></strong>
    </div>
  </div>
</div>

<script>
function previewImg(input){
    const preview = document.getElementById('imgPreview');
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => { preview.src=e.target.result; preview.style.display='block'; };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('imageUrlInput').value = '';
    }
}

function openDeleteModal(id, name){
    document.getElementById('deleteProductId').value = id;
    document.getElementById('delProductName').textContent = name;
    document.getElementById('adminPasswordInput').value = '';
    document.getElementById('deleteModal').classList.add('open');
    setTimeout(()=>document.getElementById('adminPasswordInput').focus(), 100);
}

function closeDeleteModal(){
    document.getElementById('deleteModal').classList.remove('open');
}

// Close modal on background click
document.getElementById('deleteModal').addEventListener('click', function(e){
    if(e.target === this) closeDeleteModal();
});

// Block right-click context menu on delete buttons
document.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('contextmenu', e => e.preventDefault());
});
</script>
</body>
</html>
