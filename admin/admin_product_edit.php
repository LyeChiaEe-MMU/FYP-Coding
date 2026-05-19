<?php
require_once 'auth_check.php';

$pid     = (int)($_GET['id'] ?? 0);
$product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
if(!$product){ header("Location: admin_products.php"); exit; }

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS `product_images` (
    `image_id`   int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `image_url`  varchar(300) NOT NULL,
    `color_name` varchar(80) DEFAULT NULL,
    `sort_order` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`image_id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Handle delete variant image ──────────────────
if(isset($_GET['del_img'])){
    $iid = (int)$_GET['del_img'];
    $row = $conn->query("SELECT image_url FROM product_images WHERE image_id=$iid AND product_id=$pid")->fetch_assoc();
    if($row && !str_starts_with($row['image_url'],'http')){
        $fp = dirname(__DIR__).'/'.$row['image_url'];
        if(file_exists($fp)) unlink($fp);
    }
    $conn->query("DELETE FROM product_images WHERE image_id=$iid AND product_id=$pid");
    header("Location: admin_product_edit.php?id=$pid&msg=Image+removed."); exit;
}

// ── Handle delete size ───────────────────────────
if(isset($_GET['del_size'])){
    $sid = (int)$_GET['del_size'];
    $conn->query("DELETE FROM product_size WHERE size_id=$sid AND product_id=$pid");
    header("Location: admin_product_edit.php?id=$pid&msg=Size+removed."); exit;
}

// ── Handle add size ──────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_size'])){
    $sz  = trim($_POST['new_size']  ?? '');
    $stk = (int)($_POST['new_stock'] ?? 0);
    if($sz){
        $chk = $conn->prepare("SELECT size_id FROM product_size WHERE product_id=? AND size=?");
        $chk->bind_param("is",$pid,$sz); $chk->execute();
        if($chk->get_result()->num_rows > 0){
            $conn->query("UPDATE product_size SET stock_for_size=$stk WHERE product_id=$pid AND size='".addslashes($sz)."'");
        } else {
            $ins = $conn->prepare("INSERT INTO product_size (product_id,size,stock_for_size) VALUES (?,?,?)");
            $ins->bind_param("isi",$pid,$sz,$stk); $ins->execute();
        }
    }
    header("Location: admin_product_edit.php?id=$pid&msg=Size+saved."); exit;
}

// ── Handle add variant image ─────────────────────
// IMPORTANT: This must check add_variant BEFORE update_product so file upload doesn't bleed across
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_variant'])){
    $color_name  = trim($_POST['color_name']  ?? '');
    $variant_url = trim($_POST['variant_url'] ?? '');
    $image_url   = $variant_url;

    if(!$color_name){
        header("Location: admin_product_edit.php?id=$pid&msg=Colour+name+is+required.&mtype=err"); exit;
    }

    if(!empty($_FILES['variant_image']['name'])){
        $file    = $_FILES['variant_image'];
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if(in_array($ext,$allowed) && $file['size'] <= 5*1024*1024){
            $filename  = 'variant_'.$pid.'_'.time().'.'.$ext;
            $uploadDir = dirname(__DIR__).'/uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
            if(move_uploaded_file($file['tmp_name'],$uploadDir.$filename)){
                $image_url = 'uploads/'.$filename;
            }
        }
    }
    if($image_url){
        $max  = $conn->query("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM product_images WHERE product_id=$pid")->fetch_assoc()['n'];
        $stmt = $conn->prepare("INSERT INTO product_images (product_id,image_url,color_name,sort_order) VALUES (?,?,?,?)");
        $stmt->bind_param("issi",$pid,$image_url,$color_name,$max);
        $stmt->execute();
        header("Location: admin_product_edit.php?id=$pid&msg=Variant+added."); exit;
    }
    header("Location: admin_product_edit.php?id=$pid&msg=No+image+provided.&mtype=err"); exit;
}

// ── Handle main product update ───────────────────
$msg = ''; $mtype = 'ok';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_product'])){
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price']   ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $image_url   = trim($_POST['image_url']   ?? $product['image_url']);

    // Only update image if a NEW file was uploaded via the MAIN form
    if(!empty($_FILES['main_image_file']['name'])){
        $file    = $_FILES['main_image_file'];
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if(in_array($ext,$allowed) && $file['size'] <= 5*1024*1024){
            $filename  = 'product_'.time().'_'.preg_replace('/[^a-z0-9._-]/i','_',$file['name']);
            $uploadDir = dirname(__DIR__).'/uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
            if(move_uploaded_file($file['tmp_name'],$uploadDir.$filename)){
                $image_url = 'uploads/'.$filename;
            }
        }
    }

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

$msg   = $msg   ?: ($_GET['msg']   ?? '');
$mtype = $mtype ?: ($_GET['mtype'] ?? 'ok');

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
$var_images = $conn->query("SELECT * FROM product_images WHERE product_id=$pid ORDER BY sort_order");
$prod_sizes = $conn->query("SELECT * FROM product_size WHERE product_id=$pid ORDER BY CAST(size AS DECIMAL)");

$previewSrc = !empty($product['image_url'])
    ? (str_starts_with($product['image_url'],'http') ? $product['image_url'] : '../'.$product['image_url'])
    : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&q=80';

$uk_sizes = ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12'];
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
        <div>

          <!-- ── 1. PRODUCT DETAILS ── -->
          <div class="card a-form" style="margin-bottom:20px;">
            <h2>PRODUCT DETAILS</h2>
            <!-- SEPARATE form with its own file input name: main_image_file -->
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="update_product" value="1">
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
                  <label>Total Stock</label>
                  <input type="number" name="stock" min="0" value="<?=(int)$product['stock']?>">
                </div>
                <div class="form-group span-2">
                  <label>Main Product Image</label>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="background:rgba(100,255,218,.04);border:1px dashed rgba(100,255,218,.3);border-radius:var(--radius);padding:14px;">
                      <div style="font-size:.68rem;letter-spacing:2px;color:var(--accent);font-weight:700;margin-bottom:8px;">✅ Upload File</div>
                      <!-- Named main_image_file — DIFFERENT from variant form -->
                      <input type="file" name="main_image_file" accept=".jpg,.jpeg,.png,.gif,.webp"
                             style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:8px;color:var(--text);font-size:.82rem;"
                             onchange="prevMain(this)">
                    </div>
                    <div style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:14px;">
                      <div style="font-size:.68rem;letter-spacing:2px;color:var(--muted);font-weight:700;margin-bottom:8px;">Or URL / Keep Current</div>
                      <input type="text" name="image_url" id="editImgUrl"
                             value="<?=e($product['image_url'])?>"
                             placeholder="https://... or uploads/filename.jpg"
                             style="width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:8px;color:var(--text);font-size:.82rem;">
                      <div style="font-size:.7rem;color:var(--muted);margin-top:5px;">Leave as-is to keep current image.</div>
                    </div>
                  </div>
                </div>
                <div class="form-group span-2">
                  <label>Description</label>
                  <textarea name="description" rows="4"><?=e($product['description'])?></textarea>
                </div>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top:8px;">SAVE CHANGES</button>
              <a href="admin_products.php" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
            </form>
          </div>

          <!-- ── 2. UK SIZE STOCK ── -->
          <div class="card a-form" style="margin-bottom:20px;">
            <h2>UK SIZE STOCK</h2>

            <?php if($prod_sizes->num_rows > 0): ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
              <?php while($sz=$prod_sizes->fetch_assoc()): ?>
              <div style="background:var(--navy2);border:1px solid <?=$sz['stock_for_size']>0?'var(--border)':'var(--danger)'?>;border-radius:var(--radius);padding:10px 14px;text-align:center;position:relative;min-width:72px;">
                <div style="font-family:'Oswald',sans-serif;font-size:1rem;color:<?=$sz['stock_for_size']>0?'var(--white)':'var(--danger)'?>;">UK <?=e($sz['size'])?></div>
                <div style="font-size:.72rem;color:var(--muted);"><?=(int)$sz['stock_for_size']?> pairs</div>
                <a href="admin_product_edit.php?id=<?=$pid?>&del_size=<?=(int)$sz['size_id']?>"
                   onclick="return confirm('Remove UK <?=e($sz['size'])?>?')"
                   style="position:absolute;top:-7px;right:-7px;background:var(--danger);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.6rem;text-decoration:none;line-height:1;">✕</a>
              </div>
              <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--muted);font-size:.875rem;margin-bottom:16px;">No sizes added yet. Add below.</p>
            <?php endif; ?>

            <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
              <input type="hidden" name="add_size" value="1">
              <div class="form-group" style="margin:0;min-width:140px;">
                <label>UK Size</label>
                <select name="new_size" style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
                  <option value="">-- Pick Size --</option>
                  <?php foreach($uk_sizes as $s): ?>
                  <option value="<?=$s?>">UK <?=$s?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="margin:0;min-width:120px;">
                <label>Stock Qty</label>
                <input type="number" name="new_stock" min="0" value="10"
                       style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
              </div>
              <button type="submit" class="btn btn-primary btn-sm">+ Add Size</button>
            </form>
            <div style="font-size:.72rem;color:var(--muted);margin-top:8px;">Adding an existing size updates its stock.</div>
          </div>

          <!-- ── 3. COLOUR VARIANT IMAGES ── -->
          <div class="card a-form">
            <h2>COLOUR VARIANT IMAGES</h2>
            <p style="color:var(--muted);font-size:.82rem;margin-bottom:20px;">
              Each variant = one slide in the product image gallery. The main image above is always shown first.
            </p>

            <!-- Existing variants -->
            <?php if($var_images->num_rows > 0): ?>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;padding:16px;background:var(--navy);border-radius:var(--radius);">
              <?php while($vi=$var_images->fetch_assoc()):
                $vsrc = str_starts_with($vi['image_url'],'http') ? e($vi['image_url']) : '../'.e($vi['image_url']);
              ?>
              <div style="text-align:center;position:relative;">
                <img src="<?=$vsrc?>" style="width:88px;height:88px;border-radius:8px;object-fit:cover;border:2px solid var(--border);display:block;">
                <div style="font-size:.68rem;color:var(--muted);margin-top:5px;max-width:88px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?=e($vi['color_name'] ?: 'No name')?>
                </div>
                <a href="admin_product_edit.php?id=<?=$pid?>&del_img=<?=(int)$vi['image_id']?>"
                   onclick="return confirm('Remove this variant?')"
                   style="position:absolute;top:-7px;right:-7px;background:var(--danger);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.65rem;text-decoration:none;">✕</a>
              </div>
              <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--muted);font-size:.82rem;margin-bottom:16px;">No colour variants yet.</p>
            <?php endif; ?>

            <!-- Add variant — SEPARATE FORM with its own file input: variant_image -->
            <form method="POST" enctype="multipart/form-data"
                  style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:18px;">
              <input type="hidden" name="add_variant" value="1">
              <div class="form-grid-2" style="gap:14px;">
                <div class="form-group" style="margin:0;">
                  <label>Colour Name (e.g. Red, Navy, White) *</label>
                  <input type="text" name="color_name" placeholder="e.g. Red / Gold" required
                         style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
                </div>
                <div class="form-group" style="margin:0;">
                  <label>Upload Image File</label>
                  <!-- Named variant_image — DIFFERENT from main form -->
                  <input type="file" name="variant_image" accept=".jpg,.jpeg,.png,.gif,.webp"
                         style="width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:8px;color:var(--text);font-size:.82rem;"
                         onchange="prevVariant(this)">
                </div>
                <div class="form-group span-2" style="margin:0;">
                  <label>Or Paste URL (leave blank if uploading file above)</label>
                  <input type="text" name="variant_url" placeholder="https://..."
                         style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:16px;margin-top:14px;">
                <button type="submit" class="btn btn-primary btn-sm">+ Add Variant</button>
                <img id="variantPreview" src="" alt=""
                     style="display:none;width:56px;height:56px;border-radius:6px;object-fit:cover;border:1px solid var(--border);">
              </div>
            </form>
          </div>

        </div><!-- /left col -->

        <!-- ── Right: Preview ── -->
        <div style="position:sticky;top:90px;">
          <div class="card" style="padding:20px;">
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;">Preview</div>
            <img id="editPreview" src="<?=e($previewSrc)?>"
                 style="width:100%;border-radius:8px;object-fit:contain;height:180px;background:var(--navy2);padding:8px;">
            <div style="margin-top:12px;">
              <div style="font-size:.68rem;color:var(--muted);">ID: #<?=$pid?></div>
              <div style="font-weight:600;color:var(--white);margin-top:3px;"><?=e($product['name'])?></div>
              <div style="font-family:'Oswald',sans-serif;font-size:1.3rem;color:var(--accent);margin-top:3px;">RM <?=number_format($product['price'],2)?></div>
              <div style="font-size:.78rem;color:var(--muted);margin-top:3px;">Stock: <?=(int)$product['stock']?> pairs</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
function prevMain(input){
    if(input.files && input.files[0]){
        const r=new FileReader();
        r.onload=e=>document.getElementById('editPreview').src=e.target.result;
        r.readAsDataURL(input.files[0]);
        document.getElementById('editImgUrl').value='';
    }
}
function prevVariant(input){
    const p=document.getElementById('variantPreview');
    if(input.files && input.files[0]){
        const r=new FileReader();
        r.onload=e=>{p.src=e.target.result;p.style.display='block';};
        r.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
