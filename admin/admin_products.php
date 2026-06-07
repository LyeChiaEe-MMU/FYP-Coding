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
            if($affected && $affected->num_rows > 0){
                while($ord = $affected->fetch_assoc()){
                    $oid       = (int)$ord['order_id'];
                    $uid_c     = (int)$ord['user_id'];
                    $amt       = (float)$ord['total_amount'];
                    $ord_label = str_pad($oid, 6, '0', STR_PAD_LEFT);

                    // Cancel the order
                    $conn->query("UPDATE orders SET status='Cancelled' WHERE order_id=$oid");

                    // Log into order_status_history (if table exists)
                    $osh = $conn->query("SHOW TABLES LIKE 'order_status_history'");
                    if($osh && $osh->num_rows > 0){
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
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price']   ?? 0);
    $stock       = (int)($_POST['stock']      ?? 0);
    $is_on_sale  = isset($_POST['is_on_sale']) ? 1 : 0;
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
            $stmt = $conn->prepare("INSERT INTO products (name,description,category_id,gender,price,stock,is_on_sale,image_url) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssisdiis",$name,$description,$category_id,$gender,$price,$stock,$is_on_sale,$image_url);
            $stmt->execute();
            header("Location: admin_products.php?msg=Product+added+successfully."); exit;
        }
    }
}

$msg   = $msg   ?: ($_GET['msg']   ?? '');
$mtype = $mtype ?: ($_GET['mtype'] ?? 'ok');

$products   = $conn->query("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id=c.category_id ORDER BY p.is_active DESC, p.created_at DESC");
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Products | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=4">
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
          <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-top:10px;font-size:.875rem;color:var(--muted);">
            <input type="checkbox" name="is_on_sale" value="1" style="width:16px;height:16px;accent-color:var(--danger);cursor:pointer;">
            Mark as <strong style="color:var(--danger);">ON SALE</strong>
          </label>
          <br>
          <button type="submit" name="add_product" class="btn btn-primary" style="margin-top:8px;">+ ADD PRODUCT</button>
        </form>
      </div>

      <!-- Products Table -->
      <div class="admin-table-wrap">
        <div class="admin-table-head">
          <h3>ALL PRODUCTS</h3>
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
              <td style="font-family:'Oswald',sans-serif;color:<?=$active?'var(--accent)':'var(--muted)'?>;">RM <?=number_format($p['price'],2)?></td>
              <td><span style="color:<?=$p['stock']>0?'var(--white)':'var(--danger)'?>;font-weight:600;"><?=(int)$p['stock']?></span></td>
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
    <div style="font-size:1.8rem;margin-bottom:12px;">🚫</div>
    <h3>DEACTIVATE PRODUCT</h3>
    <p>
      You are about to deactivate <strong id="deactProductName" style="color:var(--white);"></strong>.<br><br>
      <span style="color:var(--danger);font-size:.82rem;">⚠ Orders in "Processing" with this product will be auto-cancelled.</span><br>
      <span style="color:var(--success);font-size:.82rem;">✔ Affected customers receive a full refund voucher + notification.</span><br>
      <span style="color:var(--success);font-size:.82rem;">✔ Delivered / Completed orders are NOT affected.</span><br>
      <span style="color:var(--success);font-size:.82rem;">✔ You can re-activate this product at any time.</span><br><br>
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
