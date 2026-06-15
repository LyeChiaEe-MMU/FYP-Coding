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
    if(empty($_GET['_csrf']) || !hash_equals(csrf_token(), $_GET['_csrf']))
        die('Invalid request. Please go back and try again.');
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
    if(empty($_GET['_csrf']) || !hash_equals(csrf_token(), $_GET['_csrf']))
        die('Invalid request. Please go back and try again.');
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
    $tot = (int)$conn->query("SELECT COALESCE(SUM(stock),0) AS t FROM product_stock WHERE product_id=$pid")->fetch_assoc()['t'];
    $conn->query("UPDATE products SET stock=$tot WHERE product_id=$pid");
    header("Location: admin_product_edit.php?id=$pid&msg=Stock+saved."); exit;
}

// ── Handle delete stock entry ─────────────────────
if(isset($_GET['del_stock'])){
    if(empty($_GET['_csrf']) || !hash_equals(csrf_token(), $_GET['_csrf']))
        die('Invalid request. Please go back and try again.');
    $sid = (int)$_GET['del_stock'];
    $conn->query("DELETE FROM product_stock WHERE stock_id=$sid AND product_id=$pid");
    $tot = (int)$conn->query("SELECT COALESCE(SUM(stock),0) AS t FROM product_stock WHERE product_id=$pid")->fetch_assoc()['t'];
    $conn->query("UPDATE products SET stock=$tot WHERE product_id=$pid");
    header("Location: admin_product_edit.php?id=$pid&msg=Stock+removed."); exit;
}

// ── Handle bulk stock grid save ───────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_stock_update'])){
    csrf_check();

    // ── Process stock grid ──
    // blank input = size not carried (skip); "0" = size carried but OOS (store/update)
    $csg = $_POST['color_size_stock'] ?? [];
    foreach($csg as $col_name => $sizes){
        $col_name = trim($col_name) ?: 'Default';
        foreach((array)$sizes as $sz => $raw){
            if($raw === '' || $raw === null) continue; // blank = leave untouched
            $qty_int = max(0,(int)$raw);
            $chk = $conn->prepare("SELECT stock_id FROM product_stock WHERE product_id=? AND color_name=? AND size=?");
            $chk->bind_param("iss",$pid,$col_name,$sz); $chk->execute();
            if($chk->get_result()->num_rows > 0){
                $u = $conn->prepare("UPDATE product_stock SET stock=? WHERE product_id=? AND color_name=? AND size=?");
                $u->bind_param("iiss",$qty_int,$pid,$col_name,$sz); $u->execute();
            } else {
                $ins = $conn->prepare("INSERT INTO product_stock (product_id,color_name,size,stock) VALUES (?,?,?,?)");
                $ins->bind_param("issi",$pid,$col_name,$sz,$qty_int); $ins->execute();
            }
        }
    }
    $tot = (int)$conn->query("SELECT COALESCE(SUM(stock),0) AS t FROM product_stock WHERE product_id=$pid")->fetch_assoc()['t'];
    $conn->query("UPDATE products SET stock=$tot WHERE product_id=$pid");
    header("Location: admin_product_edit.php?id=$pid&msg=Stock+updated+successfully."); exit;
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
    $sale_percent = max(0, min(99, (int)($_POST['sale_percent'] ?? 0)));
    $is_on_sale   = $sale_percent > 0 ? 1 : 0;
    $image_url   = trim($_POST['image_url']   ?? $product['image_url']);
    $allowed_genders = ['Men','Women','Kids'];
    $gender      = in_array($_POST['gender'] ?? '', $allowed_genders) ? $_POST['gender'] : 'Men';
    // Auto-calculate stock from product_stock entries — never entered manually
    $stock = (int)$conn->query("SELECT COALESCE(SUM(stock),0) AS t FROM product_stock WHERE product_id=$pid")->fetch_assoc()['t'];

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
        // Check duplicate product name (exclude current product)
        $dup = $conn->prepare("SELECT product_id FROM products WHERE name=? AND product_id != ?");
        $dup->bind_param("si",$name,$pid); $dup->execute();
        if($dup->get_result()->num_rows > 0){
            $msg = "Another product named \"".htmlspecialchars($name)."\" already exists. Please use a different name."; $mtype='err';
        }
    }
    if(!$msg && $_POST['update_product']){
        // If new image was uploaded, delete the OLD local image file
        if($image_url !== $product['image_url'] && !str_starts_with($product['image_url'],'http') && !empty($product['image_url'])){
            $old_file = dirname(__DIR__) . '/' . $product['image_url'];
            if(file_exists($old_file)) unlink($old_file);
        }
        $old_price = (float)$product['price'];
        $stmt = $conn->prepare("UPDATE products SET name=?,description=?,category_id=?,gender=?,price=?,stock=?,is_on_sale=?,sale_percent=?,image_url=? WHERE product_id=?");
        $stmt->bind_param("ssisdiiisi",$name,$description,$category_id,$gender,$price,$stock,$is_on_sale,$sale_percent,$image_url,$pid);
        $stmt->execute();
        $product = $conn->query("SELECT * FROM products WHERE product_id=$pid")->fetch_assoc();
        $msg = "Product updated successfully.";

        // ── Price drop → notify wishlisted users via notifications table ──
        if($price < $old_price){
            $tbl_chk = $conn->query("SHOW TABLES LIKE 'wishlists'");
            if($tbl_chk && $tbl_chk->num_rows > 0){
                $wl_users = $conn->prepare("SELECT user_id FROM wishlists WHERE product_id=?");
                $wl_users->bind_param("i",$pid);
                $wl_users->execute();
                $wl_result = $wl_users->get_result();
                if($wl_result->num_rows > 0){
                    $notif_count = 0;
                    while($wu = $wl_result->fetch_assoc()){
                        add_notification(
                            $conn, (int)$wu['user_id'],
                            'Price Drop — ' . $name,
                            '"' . $name . '" is now RM ' . number_format($price,2) . ' (was RM ' . number_format($old_price,2) . '). Check it out before it\'s gone!',
                            'info'
                        );
                        $notif_count++;
                    }
                    $msg = "Product updated. Price drop notification sent to {$notif_count} wishlisted user(s).";
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

// Get colour list: build from product_stock first (so renames reflect immediately),
// then add any extra colours from product_images, fall back to ['Default'] if none.
$available_colors = [];
if($prod_stocks && $prod_stocks->num_rows > 0){
    $prod_stocks->data_seek(0);
    while($sr=$prod_stocks->fetch_assoc()){
        if(!in_array($sr['color_name'],$available_colors))
            $available_colors[] = $sr['color_name'];
    }
    $prod_stocks->data_seek(0);
}
if($var_images && $var_images->num_rows > 0){
    $var_images->data_seek(0);
    while($vi=$var_images->fetch_assoc()){
        if(!empty($vi['color_name']) && !in_array($vi['color_name'],$available_colors))
            $available_colors[] = $vi['color_name'];
    }
    $var_images->data_seek(0);
}
if(empty($available_colors)) $available_colors = ['Default'];

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

$uk_sizes = match($product['gender'] ?? 'Men') {
    'Women' => ['3','3.5','4','4.5','5','5.5','6','6.5','7','7.5','8'],
    'Kids'  => ['1','1.5','2','2.5','3','3.5','4','4.5','5','5.5','6'],
    default => ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12','13'],
};

// Flat map: [color_name][size] = qty  (for grid pre-fill)
$stock_map = [];
foreach($stock_by_color as $col => $entries)
    foreach($entries as $e)
        $stock_map[$col][$e['size']] = (int)$e['stock'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Product | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.sz-stock-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:8px;padding:14px;background:var(--navy2);border-radius:8px;border:1px solid var(--border);margin-bottom:10px;}
.sz-entry-box{display:flex;flex-direction:column;align-items:center;gap:4px;}
.sz-entry-label{font-size:.62rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);font-weight:700;}
.sz-entry-input{width:100%;background:#fff;border:1.5px solid var(--border);border-radius:6px;padding:8px 4px;color:#1C1410;font-size:.82rem;text-align:center;outline:none;transition:border-color .2s;}
.sz-entry-input:focus{border-color:var(--accent);box-shadow:0 0 0 2px rgba(200,84,60,.12);}
.sz-entry-input.has-stock{background:rgba(200,84,60,.06);border-color:rgba(200,84,60,.35);}
.color-group-block{margin-bottom:14px;padding:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);}
.color-group-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding:8px 12px;background:var(--navy2);border-radius:8px;border:1px solid var(--border);}
.color-group-title{font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);font-weight:700;}
</style>
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
                  <select name="gender" id="editGenderSelect" onchange="updateEditSizeGrid(this.value)">
                    <?php foreach(['Men'=>'Men','Women'=>'Women','Kids'=>'Kids'] as $gv=>$gl): ?>
                    <option value="<?=$gv?>" <?=($product['gender']??'Men')===$gv?'selected':''?>><?=$gl?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Price (RM) *</label>
                  <input type="number" name="price" step="0.01" min="0.01" value="<?=e($product['price'])?>" required>
                </div>
                <div class="form-group span-2">
                  <div style="display:inline-flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(200,84,60,.06);border:1px solid rgba(200,84,60,.2);border-radius:8px;font-size:.82rem;color:var(--muted);">
                    <i class="fa-solid fa-layer-group" style="color:var(--accent);"></i>
                    Total Stock: <strong style="color:var(--accent);font-size:1rem;"><?=(int)$product['stock']?> pairs</strong>
                    <span style="color:var(--muted);font-size:.72rem;">— auto-calculated from size entries below</span>
                  </div>
                </div>
                <div class="form-group">
                  <label>Sale Discount %
                    <span style="font-size:.72rem;color:var(--muted);font-weight:400;">(0 = no sale)</span>
                  </label>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <input type="number" name="sale_percent" min="0" max="99"
                           value="<?=(int)($product['sale_percent']??0)?>"
                           style="width:90px;text-align:center;"
                           oninput="this.value=Math.min(99,Math.max(0,parseInt(this.value)||0))">
                    <span style="color:var(--muted);font-size:.82rem;">% off — sets a SALE badge and discounted price. 0 = remove sale.</span>
                  </div>
                  <?php if((int)($product['sale_percent']??0) > 0): ?>
                  <div style="margin-top:6px;font-size:.8rem;color:var(--danger);">
                    Currently: RM <?=number_format($product['price'],2)?> → RM <?=number_format(effective_price($product['price'],$product['sale_percent']),2)?> (<?=(int)$product['sale_percent']?>% off)
                  </div>
                  <?php endif; ?>
                </div>
                <div class="form-group span-2">
                  <label>Main Product Image</label>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="background:rgba(100,255,218,.04);border:1px dashed rgba(100,255,218,.3);border-radius:var(--radius);padding:14px;">
                      <div style="font-size:.68rem;letter-spacing:2px;color:var(--accent);font-weight:700;margin-bottom:8px;">Upload File</div>
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
            <p style="color:var(--muted);font-size:.82rem;margin-bottom:20px;">
              Enter stock for each UK size per colour. Leave a box blank or 0 for sizes you don't carry.
              Click <strong style="color:var(--accent);">Save All Stock</strong> when done — total updates automatically.
            </p>

            <form method="POST">
              <?=csrf_field()?>
              <input type="hidden" name="bulk_stock_update" value="1">
              <div id="editColorGroups">
              <?php foreach($available_colors as $col_name): ?>
              <div class="color-group-block" data-color="<?=e($col_name)?>">
                <div class="color-group-header">
                  <span class="color-group-title"><?=e($col_name)?></span>
                  <span style="font-size:.68rem;color:var(--muted);">
                    Total: <strong style="color:var(--accent);"><?=array_sum(array_column($stock_by_color[$col_name] ?? [], 'stock'))?></strong> pairs
                  </span>
                </div>
                <div class="sz-stock-grid">
                  <?php foreach($uk_sizes as $sz):
                    $qty = $stock_map[$col_name][$sz] ?? 0;
                  ?>
                  <div class="sz-entry-box">
                    <div class="sz-entry-label">UK <?=$sz?></div>
                    <input type="number"
                           name="color_size_stock[<?=e($col_name)?>][<?=$sz?>]"
                           min="0" value="<?=$qty?>" placeholder="0"
                           class="sz-entry-input <?=$qty>0?'has-stock':''?>"
                           oninput="this.classList.toggle('has-stock',parseInt(this.value)>0)">
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>

              <!-- JS-added new colour groups appear here -->
              </div>

              <div style="margin-top:8px;">
                <button type="submit" class="btn btn-primary">Save All Stock</button>
              </div>
            </form>
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
                <a href="admin_product_edit.php?id=<?=$pid?>&del_img=<?=(int)$vi['image_id']?>&_csrf=<?=urlencode(csrf_token())?>"
                   onclick="return confirm('Remove this variant?')"
                   style="position:absolute;top:-7px;right:-7px;background:var(--danger);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.7rem;text-decoration:none;">&times;</a>
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
// ── Size ranges ──
const EDIT_SIZE_RANGES = {
    'Men':    ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12','13'],
    'Women':  ['3','3.5','4','4.5','5','5.5','6','6.5','7','7.5','8'],
    'Kids':   ['1','1.5','2','2.5','3','3.5','4','4.5','5','5.5','6'],
    'Unisex': ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12','13'],
};
function getEditSizes(gender){ return EDIT_SIZE_RANGES[gender] || EDIT_SIZE_RANGES['Men']; }

// Rebuild all grids when gender changes
function updateEditSizeGrid(gender){
    const sizes = getEditSizes(gender);
    document.querySelectorAll('#editColorGroups .color-group-block').forEach(block => {
        const colName = block.dataset.color || '';
        const grid    = block.querySelector('.sz-stock-grid');
        if(!grid) return;
        // Preserve values already typed in this session
        const saved = {};
        grid.querySelectorAll('input[type=number]').forEach(inp => {
            const m = inp.name.match(/\[([^\]]+)\]$/);
            if(m && inp.value !== '') saved[m[1]] = inp.value;
        });
        grid.innerHTML = sizes.map(sz => {
            const val = saved[sz] ?? '';
            const hasStock = parseInt(val) > 0;
            return `<div class="sz-entry-box">
                <div class="sz-entry-label">UK ${sz}</div>
                <input type="number" name="color_size_stock[${colName}][${sz}]"
                       min="0" value="${val}" placeholder="0"
                       class="sz-entry-input${hasStock?' has-stock':''}"
                       oninput="this.classList.toggle('has-stock',parseInt(this.value)>0)">
            </div>`;
        }).join('');
    });
}

// ── Add new colour group (edit page) ──
let editColorNewIdx = 0;
function addEditColorGroup(){
    editColorNewIdx++;
    const colorName = prompt('Enter colour name (e.g. Black, White, Neon Green):');
    if(!colorName || !colorName.trim()) return;
    const name    = colorName.trim();
    const gender  = document.getElementById('editGenderSelect')?.value || 'Men';
    const sizes   = getEditSizes(gender);
    const sizesHtml = sizes.map(sz => `
        <div class="sz-entry-box">
            <div class="sz-entry-label">UK ${sz}</div>
            <input type="number" name="color_size_stock[${name}][${sz}]" min="0" value="" placeholder="0"
                   class="sz-entry-input" oninput="this.classList.toggle('has-stock',parseInt(this.value)>0)">
        </div>`).join('');
    const block = document.createElement('div');
    block.className = 'color-group-block';
    block.dataset.color = name;
    block.innerHTML = `
        <div class="color-group-header">
            <span class="color-group-title">${name}</span>
            <button type="button" class="btn btn-sm" onclick="this.closest('.color-group-block').remove()"
                    style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ef4444;">
                &times; Remove
            </button>
        </div>
        <div class="sz-stock-grid">${sizesHtml}</div>`;
    document.getElementById('editColorGroups').appendChild(block);
    block.scrollIntoView({behavior:'smooth',block:'nearest'});
}

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
