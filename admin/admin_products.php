<?php
require_once 'auth_check.php';

$msg = ''; $mtype = 'ok';

// ── TOGGLE ACTIVE / INACTIVE ─────────────────────
if(isset($_POST['toggle_status'])){
    csrf_check();
    $id         = (int)$_POST['product_id'];
    $new_status = (int)$_POST['new_status']; // 1 = activate, 0 = deactivate

    if($new_status === 0){
        // Deactivating: verify admin password
        $password = $_POST['admin_password'] ?? '';
        $admin    = $conn->query("SELECT password FROM admins WHERE admin_id=".(int)$_SESSION['admin_id'])->fetch_assoc();
        if($admin && password_verify($password, $admin['password'])){

            // Get product name for notification messages
            $prod_row  = $conn->query("SELECT name FROM products WHERE product_id=$id")->fetch_assoc();
            $prod_name = $prod_row ? $prod_row['name'] : 'a product';

            // ── Auto-cancel Processing orders & issue refund vouchers ──────
            $affected = $conn->query("
                SELECT DISTINCT o.order_id, o.user_id, o.total_amount
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.order_id
                WHERE oi.product_id = $id AND o.status = 'Processing'
            ");

            $refund_count = 0;
            $conn->begin_transaction();
            try {
                if($affected && $affected->num_rows > 0){
                    $osh_exists = (bool)$conn->query("SHOW TABLES LIKE 'order_status_history'")->num_rows;
                    while($ord = $affected->fetch_assoc()){
                        $oid       = (int)$ord['order_id'];
                        $uid_c     = (int)$ord['user_id'];
                        $amt       = (float)$ord['total_amount'];
                        $ord_label = str_pad($oid, 6, '0', STR_PAD_LEFT);

                        // Cancel the order
                        $conn->query("UPDATE orders SET status='Cancelled' WHERE order_id=$oid");

                        // Log into order_status_history
                        if($osh_exists){
                            $conn->query("INSERT INTO order_status_history (order_id,status) VALUES ($oid,'Cancelled')");
                        }

                        // Generate a unique voucher code: APEX-XXXXXXXX
                        do {
                            $vcode    = 'APEX-'.strtoupper(bin2hex(random_bytes(4)));
                            $vcode_e  = $conn->real_escape_string($vcode);
                            $v_exists = $conn->query("SELECT voucher_id FROM vouchers WHERE code='$vcode_e'");
                        } while($v_exists && $v_exists->num_rows > 0);

                        $expires  = date('Y-m-d', strtotime('+90 days'));
                        $v_reason = "Refund — Order #$ord_label ($prod_name discontinued)";

                        $sv = $conn->prepare("INSERT INTO vouchers (user_id,code,amount,reason,expires_at) VALUES (?,?,?,?,?)");
                        $sv->bind_param("isdss", $uid_c, $vcode, $amt, $v_reason, $expires);
                        $sv->execute();

                        // Create in-app notification for the customer
                        $n_title   = "Order Cancelled — Refund Voucher Issued";
                        $n_message = "Your order #$ord_label has been cancelled because \"$prod_name\" is no longer available. "
                                   . "We apologise for the inconvenience. A full refund voucher of RM ".number_format($amt,2)
                                   . " has been added to your account (Voucher Code: $vcode). Valid for 90 days. "
                                   . "Check My Vouchers in your profile to use it on your next purchase!";

                        $sn = $conn->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'refund')");
                        $sn->bind_param("iss", $uid_c, $n_title, $n_message);
                        $sn->execute();

                        $refund_count++;
                    }
                }

                // Deactivate the product
                $stmt = $conn->prepare("UPDATE products SET is_active=0 WHERE product_id=?");
                $stmt->bind_param("i", $id); $stmt->execute();

                $conn->commit();
            } catch(Exception $e){
                $conn->rollback();
                $redir_err = "Error during deactivation: " . $e->getMessage();
                header("Location: admin_products.php?msg=".urlencode($redir_err)."&mtype=err"); exit;
            }

            $redir = $refund_count > 0
                ? "Product deactivated. {$refund_count} order(s) cancelled — refund vouchers issued to customers."
                : "Product deactivated. It is now hidden from the shop.";
            header("Location: admin_products.php?msg=".urlencode($redir)); exit;

        } else {
            $msg = "Incorrect password. Product status was NOT changed."; $mtype='err';
        }
    } else {
        // Activating: safe — no password required
        $stmt = $conn->prepare("UPDATE products SET is_active=1 WHERE product_id=?");
        $stmt->bind_param("i", $id); $stmt->execute();
        header("Location: admin_products.php?msg=Product+activated.+It+is+now+visible+in+the+shop."); exit;
    }
}

// ── Add Product ───────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])){
    csrf_check();
    $name             = trim($_POST['name']        ?? '');
    $description      = trim($_POST['description'] ?? '');
    $category_id      = (int)($_POST['category_id'] ?? 0);
    $price            = floatval($_POST['price']   ?? 0);
    $sale_percent     = max(0, min(99, (int)($_POST['sale_percent'] ?? 0)));
    $is_on_sale       = $sale_percent > 0 ? 1 : 0;
    $sale_ends_raw    = trim($_POST['sale_ends_at'] ?? '');
    $sale_ends_at     = $sale_ends_raw !== '' ? str_replace('T', ' ', $sale_ends_raw) . (strlen($sale_ends_raw) === 16 ? ':00' : '') : null;
    $image_url        = trim($_POST['image_url']   ?? '');
    $allowed_genders  = ['Men','Women','Kids'];
    $gender           = in_array($_POST['gender'] ?? '', $allowed_genders) ? $_POST['gender'] : 'Men';
    // Stock is derived from per-size entries — never typed manually
    $color_names_post = $_POST['color_name']  ?? ['Default'];
    $size_stock_post  = $_POST['size_stock']  ?? [];
    $stock = 0;
    foreach($size_stock_post as $sizes){
        foreach((array)$sizes as $qty){ $stock += max(0,(int)$qty); }
    }

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
        } elseif($sale_percent > 0 && (!$sale_ends_at || strtotime($sale_ends_at) === false || strtotime($sale_ends_at) <= time())){
            $msg = "Please set a sale end date/time in the future — customers will see a countdown until then."; $mtype='err';
        } else {
            if($sale_percent === 0) $sale_ends_at = null; // no sale = no end date
            // Check for duplicate product name
            $dup = $conn->prepare("SELECT product_id FROM products WHERE name=?");
            $dup->bind_param("s",$name); $dup->execute();
            if($dup->get_result()->num_rows > 0){
                $msg = "A product named \"".htmlspecialchars($name)."\" already exists. Please use a different name."; $mtype='err';
            }
        }
        if(!$msg){
            $stmt = $conn->prepare("INSERT INTO products (name,description,category_id,gender,price,stock,is_on_sale,sale_percent,sale_ends_at,image_url) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssisdiiiss",$name,$description,$category_id,$gender,$price,$stock,$is_on_sale,$sale_percent,$sale_ends_at,$image_url);
            $stmt->execute();
            $new_pid = (int)$conn->insert_id;

            // Insert per-size-per-colour stock into product_stock
            // blank input = size not carried (skip); "0" = size carried but OOS (store)
            foreach($size_stock_post as $col_idx => $sizes){
                $col_idx  = (int)$col_idx;
                $col_name = isset($color_names_post[$col_idx]) ? trim($color_names_post[$col_idx]) : 'Default';
                if(!$col_name) $col_name = 'Default';
                foreach((array)$sizes as $sz => $raw){
                    if($raw === '' || $raw === null) continue; // blank = skip
                    $qty_int = max(0,(int)$raw);
                    $ss = $conn->prepare("INSERT INTO product_stock (product_id,color_name,size,stock) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE stock=VALUES(stock)");
                    $ss->bind_param("issi",$new_pid,$col_name,$sz,$qty_int);
                    $ss->execute();
                }
            }
            header("Location: admin_products.php?msg=Product+added+successfully."); exit;
        }
    }
}

$msg = $msg ?: ($_GET['msg'] ?? '');
// Whitelist — GET may override the 'ok' default, but only to 'err'
if($mtype !== 'err') $mtype = (($_GET['mtype'] ?? '') === 'err') ? 'err' : 'ok';

// Product list filters
$pf_q     = trim($_GET['q'] ?? '');
$pf_stock = $_GET['stock_filter'] ?? '';
if(!in_array($pf_stock, ['nostock','lowstock'])) $pf_stock = '';
$_pf_parts = [];
if($pf_q !== ''){ $_pf_e = $conn->real_escape_string($pf_q); $_pf_parts[] = "p.name LIKE '%$_pf_e%'"; }
// Filter by per-size stock in product_stock, not the overall total
if($pf_stock === 'nostock')  $_pf_parts[] = "EXISTS (SELECT 1 FROM product_stock ps WHERE ps.product_id=p.product_id AND ps.stock=0)";
if($pf_stock === 'lowstock') $_pf_parts[] = "EXISTS (SELECT 1 FROM product_stock ps WHERE ps.product_id=p.product_id AND ps.stock BETWEEN 1 AND 5)";
$_pf_where = $_pf_parts ? 'WHERE '.implode(' AND ',$_pf_parts) : '';

$products = $conn->query("
    SELECT p.*, c.category_name,
        (SELECT GROUP_CONCAT(
            IF(ps_oos.color_name='Default' OR ps_oos.color_name='',
               CONCAT('UK ',ps_oos.size),
               CONCAT(ps_oos.color_name,' UK',ps_oos.size))
            ORDER BY ps_oos.color_name, CAST(ps_oos.size AS DECIMAL(5,2)) SEPARATOR ',  ')
         FROM product_stock ps_oos
         WHERE ps_oos.product_id=p.product_id AND ps_oos.stock=0
        ) AS oos_sizes,
        (SELECT GROUP_CONCAT(
            IF(ps_low.color_name='Default' OR ps_low.color_name='',
               CONCAT('UK ',ps_low.size,' (',ps_low.stock,')'),
               CONCAT(ps_low.color_name,' UK',ps_low.size,' (',ps_low.stock,')'))
            ORDER BY ps_low.color_name, CAST(ps_low.size AS DECIMAL(5,2)) SEPARATOR ',  ')
         FROM product_stock ps_low
         WHERE ps_low.product_id=p.product_id AND ps_low.stock BETWEEN 1 AND 5
        ) AS low_sizes
    FROM products p
    LEFT JOIN categories c ON p.category_id=c.category_id
    $_pf_where
    ORDER BY p.is_active DESC, p.created_at DESC
");
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Products | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
<style>
/* ── Size / Stock grid ── */
.sz-stock-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:8px;padding:14px;background:var(--navy2);border-radius:8px;border:1px solid var(--border);margin-bottom:10px;}
.sz-entry-box{display:flex;flex-direction:column;align-items:center;gap:4px;}
.sz-entry-label{font-size:.62rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);font-weight:700;}
.sz-entry-input{width:100%;background:#fff;border:1.5px solid var(--border);border-radius:6px;padding:8px 4px;color:#1C1410;font-size:.82rem;text-align:center;outline:none;transition:border-color .2s;}
.sz-entry-input:focus{border-color:var(--accent);box-shadow:0 0 0 2px rgba(200,84,60,.12);}
.sz-entry-input:not([value=""]):valid{background:rgba(200,84,60,.05);border-color:rgba(200,84,60,.35);}
.color-group-block{margin-bottom:14px;padding:14px;border:1px solid var(--border);border-radius:10px;background:var(--card);}
.color-name-row{display:flex;align-items:flex-end;gap:10px;margin-bottom:10px;padding:10px 12px;background:var(--navy2);border-radius:8px;border:1px solid var(--border);}
.color-name-field{flex:1;}
.color-name-field label{font-size:.62rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:4px;display:block;}
.color-name-field input{width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:9px 12px;color:var(--white);font-size:.875rem;outline:none;transition:border-color .2s;}
.color-name-field input:focus{border-color:var(--accent);}

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
              <input type="text" name="name" placeholder="e.g. Apex Velocity Pro"
                     value="<?=e($_POST['name']??'')?>" required>
            </div>
            <div class="form-group">
              <label>Category *</label>
              <select name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
                <option value="<?=(int)$c['category_id']?>" <?=(isset($_POST['category_id'])&&(int)$_POST['category_id']===$c['category_id'])?'selected':''?>><?=e($c['category_name'])?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Gender *</label>
              <?php $prevGender = $_POST['gender'] ?? 'Men'; ?>
              <select name="gender" id="genderSelect" required onchange="updateAddSizeGrid(this.value)">
                <option value="Men"   <?=$prevGender==='Men'  ?'selected':''?>>Men</option>
                <option value="Women" <?=$prevGender==='Women'?'selected':''?>>Women</option>
                <option value="Kids"  <?=$prevGender==='Kids' ?'selected':''?>>Kids</option>
              </select>
            </div>
            <div class="form-group">
              <label>Price (RM) *</label>
              <input type="number" name="price" step="0.01" min="0.01" placeholder="299.00"
                     value="<?=e($_POST['price']??'')?>" required>
            </div>
            <div class="form-group span-2">
              <label>Stock Per Size &amp; Colour</label>
              <div id="colorGroups">
                <!-- Default colour group -->
                <div class="color-group-block" data-idx="0">
                  <div class="color-name-row">
                    <div class="color-name-field">
                      <label>Colour Name</label>
                      <input type="text" name="color_name[]" value="Default" placeholder="e.g. Black, White, Neon Green">
                    </div>
                    <button type="button" class="btn btn-sm remove-color-btn"
                            style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ef4444;display:none;"
                            onclick="removeColorGroup(this)">&times; Remove</button>
                  </div>
                  <div class="sz-stock-grid" id="defaultSizeGrid">
                    <?php foreach(['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12','13'] as $sz): ?>
                    <div class="sz-entry-box">
                      <div class="sz-entry-label">UK <?=$sz?></div>
                      <input type="number" name="size_stock[0][<?=$sz?>]" min="0" value="" placeholder="0"
                             class="sz-entry-input" oninput="recalcTotal()">
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div style="margin-top:4px;display:flex;align-items:center;gap:14px;">
                <span style="font-size:.82rem;color:var(--muted);">
                  Total Stock: <strong id="totalStockDisplay" style="color:var(--accent);">0</strong> pairs
                </span>
                <input type="hidden" name="stock" id="stockHidden" value="0">
              </div>
            </div>
            <div class="form-group span-2">
              <label>Product Image</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
                <div style="background:rgba(100,255,218,.04);border:1px dashed rgba(100,255,218,.3);border-radius:var(--radius);padding:16px;">
                  <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;font-weight:600;">Upload From Computer</div>
                  <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp"
                         style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;"
                         onchange="previewImg(this)">
                  <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">JPG, PNG, GIF, WEBP</div>
                  <img id="imgPreview" src="" alt="" style="display:none;margin-top:10px;max-width:100%;height:100px;object-fit:contain;border-radius:6px;border:1px solid var(--border);">
                </div>
                <div style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;">
                  <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:600;">Or Paste Image URL</div>
                  <input type="text" name="image_url" id="imageUrlInput" placeholder="https://images.unsplash.com/..."
                         value="<?=e($_POST['image_url']??'')?>"
                         style="width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:10px;color:var(--text);font-size:.85rem;">
                  <div style="font-size:.72rem;color:var(--muted);margin-top:6px;">Used only if no file uploaded above.</div>
                </div>
              </div>
            </div>
            <div class="form-group span-2">
              <label>Description *</label>
              <textarea name="description" rows="3" placeholder="Describe the shoe..." required><?=e($_POST['description']??'')?></textarea>
            </div>
          </div>
          <div class="form-group" style="margin-top:10px;">
            <label>Sale Discount %
              <span style="font-size:.72rem;color:var(--muted);font-weight:400;">(0 = no sale)</span>
            </label>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
              <input type="number" name="sale_percent" min="0" max="99"
                     value="<?=(int)($_POST['sale_percent']??0)?>"
                     style="width:90px;text-align:center;"
                     oninput="this.value=Math.min(99,Math.max(0,parseInt(this.value)||0))">
              <span style="color:var(--muted);font-size:.875rem;">% off</span>
              <label style="font-size:.72rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin:0;">Sale ends</label>
              <input type="datetime-local" name="sale_ends_at"
                     value="<?=e($_POST['sale_ends_at']??'')?>"
                     min="<?=date('Y-m-d\TH:i')?>"
                     style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;color:var(--text);font-size:.82rem;">
            </div>
            <div style="margin-top:6px;font-size:.75rem;color:var(--muted);">
              A discount requires an end date — customers see a live countdown, the sale ends automatically,
              and the price/discount are locked until then.
            </div>
          </div>
          <br>
          <button type="submit" name="add_product" class="btn btn-primary" style="margin-top:8px;">+ ADD PRODUCT</button>
        </form>
      </div>

      <!-- Product Filter -->
      <form method="GET" id="pfForm" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px;">
        <input type="text" name="q" value="<?=e($pf_q)?>" placeholder="Search by shoe name..."
               style="flex:1;min-width:200px;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:9px 14px;color:var(--text);font-size:.875rem;outline:none;transition:border-color .2s;"
               onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
        <select name="stock_filter" onchange="document.getElementById('pfForm').submit()"
                style="background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:9px 14px;color:var(--text);font-size:.875rem;cursor:pointer;">
          <option value=""         <?=$pf_stock===''         ?'selected':''?>>All Stock Levels</option>
          <option value="nostock"  <?=$pf_stock==='nostock'  ?'selected':''?>>Has OOS Size (stock = 0)</option>
          <option value="lowstock" <?=$pf_stock==='lowstock' ?'selected':''?>>Has Low Size (stock 1–5)</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <?php if($pf_q||$pf_stock): ?>
        <a href="admin_products.php" class="btn btn-sm" style="background:transparent;border:1px solid var(--border);color:var(--muted);">Clear ×</a>
        <?php endif; ?>
      </form>

      <!-- Products Table -->
      <div class="admin-table-wrap">
        <div class="admin-table-head">
          <h3>ALL PRODUCTS<?=($pf_q||$pf_stock)?' <span style="font-size:.72rem;font-weight:400;color:var(--muted);">(filtered)</span>':''?></h3>
          <span style="font-size:.75rem;color:var(--muted);">Active products show in the shop. Inactive ones are hidden from customers.</span>
        </div>
        <table class="admin-table">
          <thead>
            <tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php $products->data_seek(0); while($p=$products->fetch_assoc()):
              $active = !isset($p['is_active']) || $p['is_active'];
              $imgSrc = !empty($p['image_url'])
                ? (str_starts_with($p['image_url'],'http') ? e($p['image_url']) : '../' . e($p['image_url']))
                : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=60&q=60';
            ?>
            <tr style="<?=$active?'':'opacity:.5;'?>">
              <td><img src="<?=$imgSrc?>" alt="" style="width:52px;height:52px;border-radius:6px;object-fit:cover;<?=$active?'':'filter:grayscale(1);'?>"></td>
              <td style="font-weight:600;color:var(--white);"><?=e($p['name'])?></td>
              <td style="color:var(--muted);"><?=e($p['category_name']??'—')?></td>
              <td style="font-family:'Oswald',sans-serif;">
                <?php $sp=(int)($p['sale_percent']??0); if($sp>0): ?>
                  <span style="text-decoration:line-through;color:var(--muted);font-size:.8rem;">RM <?=number_format($p['price'],2)?></span><br>
                  <span style="color:var(--danger);">RM <?=number_format(effective_price($p['price'],$sp),2)?></span>
                  <span style="background:var(--danger);color:#fff;font-size:.6rem;padding:1px 5px;border-radius:3px;font-weight:700;"><?=$sp?>% OFF</span>
                <?php else: ?>
                  <span style="color:<?=$active?'var(--accent)':'var(--muted)'?>;">RM <?=number_format($p['price'],2)?></span>
                <?php endif; ?>
              </td>
              <td style="min-width:130px;">
                <span style="color:<?=$p['stock']>0?'var(--white)':'var(--danger)'?>;font-weight:600;"><?=(int)$p['stock']?></span>
                <?php if(!empty($p['oos_sizes'])): ?>
                <div title="Out of stock: <?=e($p['oos_sizes'])?>"
                     style="font-size:.62rem;color:#ef4444;font-weight:600;margin-top:4px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:default;">
                  OOS: <?=e($p['oos_sizes'])?>
                </div>
                <?php endif; ?>
                <?php if(!empty($p['low_sizes'])): ?>
                <div title="Low stock: <?=e($p['low_sizes'])?>"
                     style="font-size:.62rem;color:#f59e0b;font-weight:600;margin-top:2px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:default;">
                  Low: <?=e($p['low_sizes'])?>
                </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if($active): ?>
                  <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:rgba(42,155,90,.12);border:1px solid rgba(42,155,90,.3);border-radius:100px;font-size:.7rem;font-weight:700;letter-spacing:1px;color:var(--success);">● ACTIVE</span>
                <?php else: ?>
                  <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:rgba(214,64,64,.1);border:1px solid rgba(214,64,64,.25);border-radius:100px;font-size:.7rem;font-weight:700;letter-spacing:1px;color:var(--danger);">● INACTIVE</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:8px;align-items:center;">
                  <a href="admin_product_edit.php?id=<?=(int)$p['product_id']?>" class="btn btn-secondary btn-sm">Edit</a>
                  <?php if($active): ?>
                    <button type="button" class="btn btn-sm"
                            style="background:rgba(214,64,64,.12);border:1px solid rgba(214,64,64,.3);color:var(--danger);"
                            onclick="openDeactivateModal(<?=(int)$p['product_id']?>, '<?=e(addslashes($p['name']))?>')">
                      Deactivate
                    </button>
                  <?php else: ?>
                    <form method="POST" style="margin:0;">
                      <?=csrf_field()?>
                      <input type="hidden" name="toggle_status" value="1">
                      <input type="hidden" name="product_id" value="<?=(int)$p['product_id']?>">
                      <input type="hidden" name="new_status" value="1">
                      <button type="submit" class="btn btn-sm"
                              style="background:rgba(42,155,90,.12);border:1px solid rgba(42,155,90,.3);color:var(--success);">
                        Activate
                      </button>
                    </form>
                  <?php endif; ?>
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

<!-- ── Deactivate Confirmation Modal ── -->
<div class="del-modal-bg" id="deactivateModal">
  <div class="del-modal">
    <h3>DEACTIVATE PRODUCT</h3>
    <p>
      You are about to deactivate <strong id="deactProductName" style="color:var(--white);"></strong>.<br><br>
      <span style="color:var(--danger);font-size:.82rem;">Note: Orders in "Processing" with this product will be auto-cancelled.</span><br>
      <span style="color:var(--success);font-size:.82rem;">- Affected customers receive a full refund voucher + notification.</span><br>
      <span style="color:var(--success);font-size:.82rem;">- Delivered / Completed orders are NOT affected.</span><br>
      <span style="color:var(--success);font-size:.82rem;">- You can re-activate this product at any time.</span><br><br>
      Enter your admin password to confirm:
    </p>
    <form method="POST" id="deactivateForm">
      <?=csrf_field()?>
      <input type="hidden" name="toggle_status" value="1">
      <input type="hidden" name="product_id" id="deactProductId">
      <input type="hidden" name="new_status" value="0">
      <input type="password" name="admin_password" id="deactPasswordInput"
             placeholder="Enter your password" autocomplete="current-password" required>
      <div class="del-modal-btns">
        <button type="submit" class="btn btn-danger">CONFIRM DEACTIVATE</button>
        <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()">Cancel</button>
      </div>
    </form>
    <div style="font-size:.72rem;color:var(--muted);margin-top:12px;text-align:center;">
      Logged in as: <strong style="color:var(--white);"><?=e($_SESSION['admin_username']??'Admin')?></strong>
    </div>
  </div>
</div>

<script>
// ── Size/Stock grid ──────────────────────────────────────────
const SIZE_RANGES = {
    'Men':    ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12','13'],
    'Women':  ['3','3.5','4','4.5','5','5.5','6','6.5','7','7.5','8'],
    'Kids':   ['1','1.5','2','2.5','3','3.5','4','4.5','5','5.5','6'],
    'Unisex': ['6','6.5','7','7.5','8','8.5','9','9.5','10','10.5','11','11.5','12','13'],
};
function getSizes(gender){ return SIZE_RANGES[gender] || SIZE_RANGES['Men']; }
let colorIdx = 0;

function recalcTotal(){
    let t = 0;
    document.querySelectorAll('#colorGroups .sz-entry-input').forEach(inp => {
        t += Math.max(0, parseInt(inp.value) || 0);
    });
    document.getElementById('totalStockDisplay').textContent = t;
    document.getElementById('stockHidden').value = t;
}

// Rebuild ALL colour grids when gender changes
function updateAddSizeGrid(gender){
    const sizes = getSizes(gender);
    document.querySelectorAll('#colorGroups .color-group-block').forEach(block => {
        const idx  = block.dataset.idx;
        const grid = block.querySelector('.sz-stock-grid');
        if(!grid) return;
        // Preserve already-typed values
        const saved = {};
        grid.querySelectorAll('input[type=number]').forEach(inp => {
            const m = inp.name.match(/\[([^\]]+)\]$/);
            if(m && inp.value !== '') saved[m[1]] = inp.value;
        });
        grid.innerHTML = sizes.map(sz => `
            <div class="sz-entry-box">
                <div class="sz-entry-label">UK ${sz}</div>
                <input type="number" name="size_stock[${idx}][${sz}]" min="0"
                       value="${saved[sz]??''}" placeholder="0"
                       class="sz-entry-input" oninput="recalcTotal()">
            </div>`).join('');
        recalcTotal();
    });
}

function addColorGroup(){
    colorIdx++;
    const idx    = colorIdx;
    const gender = document.getElementById('genderSelect')?.value || 'Men';
    const sizes  = getSizes(gender);
    const sizesHtml = sizes.map(sz => `
        <div class="sz-entry-box">
            <div class="sz-entry-label">UK ${sz}</div>
            <input type="number" name="size_stock[${idx}][${sz}]" min="0" value="" placeholder="0"
                   class="sz-entry-input" oninput="recalcTotal()">
        </div>`).join('');

    const block = document.createElement('div');
    block.className = 'color-group-block';
    block.dataset.idx = idx;
    block.innerHTML = `
        <div class="color-name-row">
            <div class="color-name-field">
                <label>Colour Name</label>
                <input type="text" name="color_name[]" value="" placeholder="e.g. Black, White, Neon Green">
            </div>
            <button type="button" class="btn btn-sm remove-color-btn"
                    style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ef4444;"
                    onclick="removeColorGroup(this)">&times; Remove</button>
        </div>
        <div class="sz-stock-grid">${sizesHtml}</div>`;
    document.getElementById('colorGroups').appendChild(block);
    updateRemoveBtns();
    block.querySelector('input[type=text]').focus();
}

function removeColorGroup(btn){
    btn.closest('.color-group-block').remove();
    recalcTotal();
    updateRemoveBtns();
}

function updateRemoveBtns(){
    const groups = document.querySelectorAll('#colorGroups .color-group-block');
    groups.forEach(g => {
        const btn = g.querySelector('.remove-color-btn');
        if(btn) btn.style.display = groups.length > 1 ? 'inline-flex' : 'none';
    });
}

function previewImg(input){
    const preview = document.getElementById('imgPreview');
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => { preview.src=e.target.result; preview.style.display='block'; };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('imageUrlInput').value = '';
    }
}

function openDeactivateModal(id, name){
    document.getElementById('deactProductId').value = id;
    document.getElementById('deactProductName').textContent = name;
    document.getElementById('deactPasswordInput').value = '';
    document.getElementById('deactivateModal').classList.add('open');
    setTimeout(()=>document.getElementById('deactPasswordInput').focus(), 100);
}

function closeDeactivateModal(){
    document.getElementById('deactivateModal').classList.remove('open');
}

document.getElementById('deactivateModal').addEventListener('click', function(e){
    if(e.target === this) closeDeactivateModal();
});

// Block right-click context menu on delete buttons
document.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('contextmenu', e => e.preventDefault());
});
</script>
</body>
</html>
