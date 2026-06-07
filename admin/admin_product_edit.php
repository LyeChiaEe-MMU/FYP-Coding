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

// ── Ensure product_stock table exists ────────────
$conn->query("CREATE TABLE IF NOT EXISTS `product_stock` (
    `stock_id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `color_name` varchar(80) NOT NULL DEFAULT 'Default',
    `size` varchar(10) NOT NULL,
    `stock` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`stock_id`),
    UNIQUE KEY `uq_pcs` (`product_id`,`color_name`,`size`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Handle add/update stock (colour + size) ───────
if($_SERVER['REQUEST_METHOD']==='POST') csrf_check();

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_size'])){
    $sz    = trim($_POST['new_size']   ?? '');
    $stk   = (int)($_POST['new_stock'] ?? 0);
    $color = trim($_POST['stock_color'] ?? 'Default');
    if(!$color) $color = 'Default';

    if($sz){
        $chk = $conn->prepare("SELECT stock_id FROM product_stock WHERE product_id=? AND color_name=? AND size=?");
        $chk->bind_param("iss",$pid,$color,$sz); $chk->execute();
        if($chk->get_result()->num_rows > 0){
            $upd = $conn->prepare("UPDATE product_stock SET stock=? WHERE product_id=? AND color_name=? AND size=?");
            $upd->bind_param("iiss",$stk,$pid,$color,$sz); $upd->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO product_stock (product_id,color_name,size,stock) VALUES (?,?,?,?)");
            $ins->bind_param("issi",$pid,$color,$sz,$stk); $ins->execute();
        }
    }
    header("Location: admin_product_edit.php?id=$pid&msg=Stock+saved."); exit;
}

// ── Handle delete stock entry ─────────────────────
if(isset($_GET['del_stock'])){
    $sid = (int)$_GET['del_stock'];
    $conn->query("DELETE FROM product_stock WHERE stock_id=$sid AND product_id=$pid");
    header("Location: admin_product_edit.php?id=$pid&msg=Stock+removed."); exit;
}

// ── Handle add size (legacy - keep for compat) ────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_size_legacy'])){
    $sz  = trim($_POST['new_size']  ?? '');
    $stk = (int)($_POST['new_stock'] ?? 0);
    if($sz){
        $chk = $conn->prepare("SELECT size_id FROM product_size WHERE product_id=? AND size=?");
        $chk->bind_param("is",$pid,$sz); $chk->execute();
        if($chk->get_result()->num_rows > 0){
            $upd2 = $conn->prepare("UPDATE product_size SET stock_for_size=? WHERE product_id=? AND size=?");
            $upd2->bind_param("iis",$stk,$pid,$sz);
            $upd2->execute();
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
        $file = $_FILES['variant_image'];
        $upload_err = '';
        if(valid_image_upload($file, $upload_err)){
            $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename  = 'variant_'.$pid.'_'.time().'.'.$ext;
            $uploadDir = dirname(__DIR__).'/uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
            if(move_uploaded_file($file['tmp_name'],$uploadDir.$filename)){
                $image_url = 'uploads/'.$filename;
            }
        } else {
            header("Location: admin_product_edit.php?id=$pid&msg=".urlencode($upload_err)."&mtype=err"); exit;
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
    $is_on_sale  = isset($_POST['is_on_sale']) ? 1 : 0;
    $image_url   = trim($_POST['image_url']   ?? $product['image_url']);
    $allowed_genders = ['Men','Women','Kids','Unisex'];
    $gender      = in_array($_POST['gender'] ?? '', $allowed_genders) ? $_POST['gender'] : 'Unisex';

    // Only update image if a NEW file was uploaded via the MAIN form
    if(!empty($_FILES['main_image_file']['name'])){
        $file = $_FILES['main_image_file'];
        $upload_err = '';
        if(valid_image_upload($file, $upload_err)){
            $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename  = 'product_'.time().'_'.preg_replace('/[^a-z0-9._-]/i','_',$file['name']);
            $uploadDir = dirname(__DIR__).'/uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
            if(move_uploaded_file($file['tmp_name'],$uploadDir.$filename)){
                $image_url = 'uploads/'.$filename;
            }
        } else {
            $msg = $upload_err; $mtype = 'err';
        }
    }

    if(!$name || $price<=0){
        $msg = "Name and price are required."; $mtype='err';
    } else {
        // If new image was uploaded, delete the OLD local image file
        if($image_url !== $product['image_url'] && !str_starts_with($product['image_url'],'http') && !empty($product['image_url'])){
            $old_file = dirname(__DIR__) . '/' . $product['image_url'];
            if(file_exists($old_file)) unlink($old_file);
        }
        $old_price = (float)$product['price'];
        $stmt = $conn->prepare("UPDATE products SET name=?,description=?,category_id=?,gender=?,price=?,stock=?,is_on_sale=?,image_url=? WHERE product_id=?");
        $stmt->bind_param("ssisdiisi",$name,$description,$category_id,$gender,$price,$stock,$is_on_sale,$image_url,$pid);
        $stmt->execute();
        $product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
        $msg = "Product updated successfully.";

        // ── Price drop → notify wishlisted users ──────────────────
        if($price < $old_price){
            $tbl_chk = $conn->query("SHOW TABLES LIKE 'wishlists'");
            if($tbl_chk && $tbl_chk->num_rows > 0){
                $wl_users = $conn->prepare("SELECT user_id FROM wishlists WHERE product_id=?");
                $wl_users->bind_param("i",$pid);
                $wl_users->execute();
                $wl_result = $wl_users->get_result();
                if($wl_result->num_rows > 0){
                    $notif_msg = "Price drop! \"$name\" is now RM ".number_format($price,2)." (was RM ".number_format($old_price,2).")";
                    $notif_ins = $conn->prepare("INSERT INTO wishlist_notifications (user_id,product_id,message) VALUES (?,?,?)");
                    while($wu = $wl_result->fetch_assoc()){
                        $notif_ins->bind_param("iis",$wu['user_id'],$pid,$notif_msg);
                        $notif_ins->execute();
                    }
                    $msg = "Product updated & price drop notified to ".($wl_result->num_rows > 0 ? $wl_result->num_rows : 'wishlisted')." user(s).";
                }
            }
        }
    }
}

$msg   = $msg   ?: ($_GET['msg']   ?? '');
$mtype = $mtype ?: ($_GET['mtype'] ?? 'ok');

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
$var_images = $conn->query("SELECT * FROM product_images WHERE product_id=$pid ORDER BY sort_order");
$prod_sizes = $conn->query("SELECT * FROM product_size WHERE product_id=$pid ORDER BY CAST(size AS DECIMAL)");
$prod_stocks= $conn->query("SELECT * FROM product_stock WHERE product_id=$pid ORDER BY color_name, CAST(size AS DECIMAL)");

// Get colour list for the stock form dropdown
$available_colors = ['Default'];
if($var_images && $var_images->num_rows > 0){
    $var_images->data_seek(0);
    while($vi=$var_images->fetch_assoc()){
        if(!empty($vi['color_name']) && !in_array($vi['color_name'],$available_colors))
            $available_colors[] = $vi['color_name'];
    }
    $var_images->data_seek(0);
}

// Group existing stock by colour
$stock_by_color = [];
if($prod_stocks && $prod_stocks->num_rows > 0){
    $prod_stocks->data_seek(0);
    while($sr=$prod_stocks->fetch_assoc())
        $stock_by_color[$sr['color_name']][] = $sr;
}

$previewSrc = !empty($product['image_url'])
    ? (str_starts_with($product['image_url'],'http') ? $product['image_url'] : '../'.$product['image_url'])
    : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&q=80';

$uk_sizes = ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Product | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=4">
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
              <?=csrf_field()?>
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
                  <label>Gender</label>
                  <select name="gender">
                    <?php foreach(['Unisex'=>'Unisex (shows in all)','Men'=>'Men','Women'=>'Women','Kids'=>'Kids'] as $gv=>$gl): ?>
                    <option value="<?=$gv?>" <?=($product['gender']??'Unisex')===$gv?'selected':''?>><?=$gl?></option>
                    <?php endforeach; ?>
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
                  <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-top:4px;">
                    <input type="checkbox" name="is_on_sale" value="1" <?=!empty($product['is_on_sale'])?'checked':''?>
                           style="width:18px;height:18px;accent-color:var(--danger);cursor:pointer;">
                    <span>Mark as <strong style="color:var(--danger);">ON SALE</strong> — shows a red SALE badge and appears in the Sale filter</span>
                  </label>
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

          <!-- ── 2. STOCK PER COLOUR + SIZE ── -->
          <div class="card a-form" style="margin-bottom:20px;">
            <h2>STOCK PER COLOUR &amp; SIZE</h2>

            <!-- Step guide -->
            <div style="background:rgba(100,255,218,.05);border:1px solid rgba(100,255,218,.2);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;font-size:.82rem;color:var(--muted);line-height:1.8;">
              <strong style="color:var(--accent);display:block;margin-bottom:6px;">📋 HOW TO ADD STOCK:</strong>
              <strong style="color:var(--white);">Step 1:</strong> First add your colour variants below in the "Colour Variant Images" section.<br>
              <strong style="color:var(--white);">Step 2:</strong> Come back here — the Colour dropdown will show your variants.<br>
              <strong style="color:var(--white);">Step 3:</strong> Pick a Colour → Pick a UK Size → Enter stock quantity → Click Save Stock.<br>
              <strong style="color:var(--white);">Step 4:</strong> Repeat for each size for each colour.<br>
              <em>Use "Default" if your product has only one colour.</em>
            </div>

            <!-- Existing stock table -->
            <?php if(!empty($stock_by_color)): ?>
            <?php foreach($stock_by_color as $col_name => $entries): ?>
            <div style="margin-bottom:20px;">
              <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);font-weight:600;margin-bottom:10px;">
                <?=e($col_name)?>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach($entries as $entry): ?>
                <div style="background:var(--navy2);border:1px solid <?=$entry['stock']>0?'var(--border)':'var(--danger)'?>;border-radius:var(--radius);padding:10px 12px;text-align:center;position:relative;min-width:72px;">
                  <div style="font-family:'Oswald',sans-serif;font-size:.95rem;color:<?=$entry['stock']>0?'var(--white)':'var(--danger)'?>;">UK <?=e($entry['size'])?></div>
                  <div style="font-size:.7rem;color:<?=$entry['stock']<=3&&$entry['stock']>0?'#f97316':'var(--muted)'?>;">
                    <?php if($entry['stock']<=3&&$entry['stock']>0): ?>⚠️ <?php endif; ?>
                    <?=(int)$entry['stock']?> pcs
                  </div>
                  <a href="admin_product_edit.php?id=<?=$pid?>&del_stock=<?=(int)$entry['stock_id']?>"
                     onclick="return confirm('Remove UK <?=e($entry['size'])?> / <?=e($col_name)?>?')"
                     style="position:absolute;top:-7px;right:-7px;background:var(--danger);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.6rem;text-decoration:none;line-height:1;">✕</a>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
            <?php else: ?>
            <p style="color:var(--muted);font-size:.875rem;margin-bottom:16px;">No stock entries yet. Add below.</p>
            <?php endif; ?>

            <!-- Add stock form -->
            <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;">
              <?=csrf_field()?>
              <input type="hidden" name="add_size" value="1">

              <div class="form-group" style="margin:0;min-width:160px;">
                <label>Colour</label>
                <select name="stock_color" style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
                  <?php foreach($available_colors as $ac): ?>
                  <option value="<?=e($ac)?>"><?=e($ac)?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group" style="margin:0;min-width:130px;">
                <label>UK Size</label>
                <select name="new_size" style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
                  <option value="">-- Pick --</option>
                  <?php foreach($uk_sizes as $s): ?>
                  <option value="<?=$s?>">UK <?=$s?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group" style="margin:0;min-width:100px;">
                <label>Stock Qty</label>
                <input type="number" name="new_stock" min="0" value="10"
                       style="background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--text);font-size:.875rem;width:100%;">
              </div>

              <button type="submit" class="btn btn-primary btn-sm">+ Save Stock</button>
            </form>
            <div style="font-size:.72rem;color:var(--muted);margin-top:8px;">
              Adding an existing Colour+Size combination updates its stock.
              Add each colour variant's sizes separately — colours appear above once you add them as Variant Images.
            </div>
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
              <?=csrf_field()?>
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
