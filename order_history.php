<?php
session_start();
require 'db.php';
if(!is_logged()){ header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];

// ── Mark as Received (Delivered → Completed) ─────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mark_received'])){
    csrf_check();
    $oid = (int)$_POST['order_id'];
    // Verify order belongs to user and is currently Delivered
    $chk = $conn->prepare("SELECT order_id, total_amount FROM orders WHERE order_id=? AND user_id=? AND status='Delivered'");
    $chk->bind_param("ii",$oid,$uid); $chk->execute();
    $ord_row = $chk->get_result()->fetch_assoc();
    if($ord_row){
        $conn->query("UPDATE orders SET status='Completed' WHERE order_id=$oid");
        $conn->query("INSERT INTO order_status_history (order_id,status) VALUES ($oid,'Completed')");
        // Notification — received confirmed
        $order_num = '#' . str_pad($oid, 6, '0', STR_PAD_LEFT);
        add_notification(
            $conn, $uid,
            'Order ' . $order_num . ' Received — Thank You!',
            'You have confirmed receipt of order ' . $order_num . '. We hope you love your new shoes! You can now write a review for any item in this order.',
            'success'
        );

        // ── Reward voucher: 5% of the paid total back (RM 5 min, RM 30 cap) ──
        $reward = (float)max(5, min(30, round((float)$ord_row['total_amount'] * 0.05)));
        grant_voucher(
            $conn, $uid, $reward,
            'Thank-you reward for completing order ' . $order_num . '. Enjoy RM ' . number_format($reward,2) . ' off your next pair!',
            30, 'Order Reward — RM ' . number_format($reward,2) . ' Voucher'
        );

        // ── Loyalty bonus: every 5th completed order earns an extra RM 15 ──
        $cc = (int)$conn->query("SELECT COUNT(*) AS c FROM orders WHERE user_id=$uid AND status='Completed'")->fetch_assoc()['c'];
        if($cc > 0 && $cc % 5 === 0){
            grant_voucher(
                $conn, $uid, 15.00,
                "Loyalty reward — you've completed {$cc} orders with Apex. Thank you for sticking with us!",
                60, 'Loyalty Reward — RM 15.00 Voucher'
            );
        }
    }
    header("Location: order_history.php?msg=Order+marked+as+received.+A+reward+voucher+has+been+added+to+your+account!"); exit;
}

// ── Submit Review ─────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_review'])){
    csrf_check();
    $oid    = (int)$_POST['order_id'];
    $pid    = (int)$_POST['product_id'];
    $rating = max(1,min(5,(int)$_POST['rating']));
    $comment= trim($_POST['comment'] ?? '');
    // Verify order is Completed and belongs to user and contains this product
    $chk = $conn->prepare("
        SELECT oi.order_item_id FROM orders o
        JOIN order_items oi ON oi.order_id=o.order_id
        WHERE o.order_id=? AND o.user_id=? AND o.status='Completed' AND oi.product_id=?
    ");
    $chk->bind_param("iii",$oid,$uid,$pid); $chk->execute();
    if($chk->get_result()->num_rows > 0){
        $ins = $conn->prepare("INSERT IGNORE INTO reviews (user_id,product_id,order_id,rating,comment) VALUES (?,?,?,?,?)");
        $ins->bind_param("iiiis",$uid,$pid,$oid,$rating,$comment);
        $ins->execute();
        if($ins->affected_rows > 0){
            // Notification — review submitted
            $pname_r = $conn->query("SELECT name FROM products WHERE product_id=$pid");
            $pname   = $pname_r ? ($pname_r->fetch_assoc()['name'] ?? 'the product') : 'the product';
            $stars   = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
            add_notification(
                $conn, $uid,
                'Review Submitted — Thank You!',
                'Your ' . $stars . ' review for "' . $pname . '" has been published. Your feedback helps other shoppers make better choices!',
                'review'
            );
        }
    }
    header("Location: order_history.php?msg=Review+submitted.+Thank+you!"); exit;
}

$msg = $_GET['msg'] ?? '';

// Section for fetching the user's orders
$orders = $conn->prepare("
    SELECT o.order_id, o.total_amount,
           COALESCE(o.discount_amount,0) AS discount_amount,
           COALESCE(o.voucher_code,'')   AS voucher_code,
           o.status, o.order_date, o.shipping_address,
           COUNT(oi.order_item_id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");
$orders->bind_param("i",$uid);
$orders->execute();
$result = $orders->get_result();

// Pre-load reviewed product+order combos for this user
$reviewed = [];
$rv = $conn->query("SELECT product_id, order_id FROM reviews WHERE user_id=$uid");
if($rv) while($r=$rv->fetch_assoc()) $reviewed[$r['order_id'].'_'.$r['product_id']] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Orders | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Review modal */
.review-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;}
.review-modal-bg.open{display:flex;}
.review-modal{background:var(--navy2);border:1px solid var(--border);border-radius:14px;padding:32px;max-width:460px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,.5);}
.review-modal h3{font-family:'Oswald',sans-serif;font-size:1.15rem;letter-spacing:2px;color:var(--white);margin-bottom:4px;}
.star-row{display:flex;gap:6px;margin:18px 0 6px;}
.star-btn{font-size:1.8rem;cursor:pointer;color:var(--border);transition:color .15s;background:none;border:none;padding:0;line-height:1;}
.star-btn.lit{color:#f59e0b;}
.review-textarea{width:100%;background:var(--navy);border:1px solid var(--border);border-radius:var(--radius);padding:11px 14px;color:var(--text);font-size:.875rem;font-family:'Inter',sans-serif;resize:vertical;min-height:90px;outline:none;transition:border-color .2s;box-sizing:border-box;margin-top:10px;}
.review-textarea:focus{border-color:var(--accent);}
.btn-received{background:rgba(34,197,94,.12);border:1.5px solid rgba(34,197,94,.4);color:#22c55e;border-radius:var(--radius);padding:8px 18px;font-size:.8rem;font-weight:700;letter-spacing:.5px;cursor:pointer;transition:all .2s;}
.btn-received:hover{background:rgba(34,197,94,.22);border-color:#22c55e;}
.btn-review{background:rgba(200,84,60,.1);border:1.5px solid rgba(200,84,60,.35);color:var(--accent);border-radius:var(--radius);padding:6px 14px;font-size:.75rem;font-weight:700;letter-spacing:.5px;cursor:pointer;transition:all .2s;white-space:nowrap;}
.btn-review:hover{background:rgba(200,84,60,.2);}
.reviewed-badge{font-size:.7rem;color:#22c55e;font-weight:600;display:flex;align-items:center;gap:4px;}

/* Received confirmation modal */
.confirm-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9998;align-items:center;justify-content:center;}
.confirm-modal-bg.open{display:flex;}
.confirm-modal{background:var(--navy2);border:1px solid var(--border);border-radius:14px;padding:32px;max-width:440px;width:92%;box-shadow:0 24px 60px rgba(0,0,0,.6);}
.confirm-modal h3{font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:2px;color:var(--white);margin-bottom:12px;}
.confirm-modal p{font-size:.875rem;color:var(--text);line-height:1.75;margin-bottom:8px;}
.confirm-modal .disclaimer{font-size:.78rem;color:#f59e0b;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:8px;padding:12px 14px;margin:16px 0;line-height:1.7;}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>My Orders</span></div>
    <h1>MY <span style="color:var(--accent)">ORDERS</span></h1>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap" style="max-width:860px;">

<?php if($msg): ?>
<div class="flash flash-ok" style="margin-bottom:18px;"><?=e($msg)?></div>
<?php endif; ?>

<?php if($result->num_rows===0): ?>
<div class="empty-cart" style="padding:80px 0;">
  <div class="ec-icon" style="font-family:'Oswald',sans-serif;font-size:1.5rem;letter-spacing:2px;opacity:.3;">ORDERS</div>
  <h3>No Orders Yet</h3>
  <p>You haven't placed any orders yet.</p>
  <a href="products.php" class="btn btn-primary" style="margin-top:8px;">Start Shopping</a>
</div>

<?php else: while($o=$result->fetch_assoc()):
  $oid    = (int)$o['order_id'];
  $status = $o['status'];
  $steps  = ['Processing','Delivered','Completed'];
  $cur    = array_search($status, $steps);
  if($cur===false) $cur = -1;

  // Fetch items for this order (include product_id for review links)
  $oit = $conn->query("
      SELECT oi.order_item_id, oi.product_id, oi.size, oi.color, oi.quantity, oi.price,
             COALESCE(oi.original_price, oi.price) AS original_price,
             p.name, p.image_url,
             (SELECT pi.image_url FROM product_images pi
              WHERE pi.product_id=oi.product_id AND pi.color_name=oi.color
              ORDER BY pi.sort_order ASC LIMIT 1) AS color_image
      FROM order_items oi JOIN products p ON oi.product_id=p.product_id
      WHERE oi.order_id=$oid
  ");
  $items_arr = $oit->fetch_all(MYSQLI_ASSOC);

  // Status history → first date each status was reached (for timeline dates)
  $hist_map = [];
  $hq = $conn->query("SELECT status, changed_at FROM order_status_history WHERE order_id=$oid ORDER BY changed_at ASC");
  if($hq) while($h = $hq->fetch_assoc()){
      if(!isset($hist_map[$h['status']])) $hist_map[$h['status']] = $h['changed_at'];
  }
  // Older orders may predate history logging — the order date covers Processing
  if(!isset($hist_map['Processing'])) $hist_map['Processing'] = $o['order_date'];
?>
<div class="card" style="margin-bottom:18px;overflow:hidden;">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px;">
    <div>
      <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);">
        ORDER #<?=str_pad($oid,6,'0',STR_PAD_LEFT)?>
      </div>
      <div style="font-size:.75rem;color:var(--muted);margin-top:3px;">
        <?=date('d M Y, h:i A',strtotime($o['order_date']))?>
        &nbsp;·&nbsp; <?=(int)$o['item_count']?> item<?=$o['item_count']!=1?'s':''?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="text-align:right;">
        <?php
          $disc      = (float)$o['discount_amount'];
          $vc        = $o['voucher_code'];
          $original  = $o['total_amount'] + $disc;   // restore original before discount
        ?>
        <?php if($disc > 0): ?>
        <div style="font-size:.72rem;color:var(--muted);text-decoration:line-through;">
          RM <?=number_format($original,2)?>
        </div>
        <?php endif; ?>
        <div style="font-family:'Oswald',sans-serif;font-size:1.2rem;color:var(--accent);">
          RM <?=number_format($o['total_amount'],2)?>
        </div>
        <?php if($disc > 0 && $vc !== ''): ?>
        <div style="font-size:.68rem;color:#22c55e;font-weight:600;white-space:nowrap;">
          <?=e($vc)?> −RM <?=number_format($disc,2)?>
        </div>
        <?php endif; ?>
      </div>
      <?=status_badge($status)?>
    </div>
  </div>

  <!-- Items -->
  <div style="padding:16px 22px;border-bottom:1px solid var(--border);">
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php foreach($items_arr as $it):
        $img        = !empty($it['color_image']) ? e($it['color_image']) : (!empty($it['image_url']) ? e($it['image_url']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60');
        $alreadyRev = isset($reviewed[$oid.'_'.$it['product_id']]);
      ?>
      <div style="display:flex;align-items:center;gap:10px;background:var(--navy2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;flex:1;min-width:200px;">
        <img src="<?=$img?>" style="width:44px;height:44px;border-radius:6px;object-fit:cover;flex-shrink:0;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:.82rem;font-weight:600;color:var(--white);"><?=e($it['name'])?></div>
          <div style="font-size:.72rem;color:var(--muted);">UK <?=e($it['size'])?>
            <?php if(!empty($it['color']) && $it['color'] !== 'Default'): ?> · <?=e($it['color'])?><?php endif; ?>
            × <?=(int)$it['quantity']?> —
            <?php if((float)$it['original_price'] > (float)$it['price']): ?>
            <span style="text-decoration:line-through;opacity:.6;">RM <?=number_format($it['original_price'],2)?></span>
            <span style="color:var(--danger);font-weight:600;"> RM <?=number_format($it['price'],2)?>/pc</span>
            <?php else: ?>
            RM <?=number_format($it['price'],2)?>/pc
            <?php endif; ?>
          </div>
        </div>
        <?php if($status === 'Completed'): ?>
          <?php if($alreadyRev): ?>
          <div class="reviewed-badge"><i class="fa-solid fa-check-circle"></i> Reviewed</div>
          <?php else: ?>
          <button type="button" class="btn-review"
                  onclick="openReview(<?=$oid?>,<?=(int)$it['product_id']?>,'<?=e(addslashes($it['name']))?>')">
            Review
          </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Timeline + actions -->
  <div style="padding:18px 22px;">
    <?php if($status === 'Cancelled'): ?>
    <div style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:100px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);">
      <span style="color:#ef4444;font-size:.72rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;">✗ Order Cancelled</span>
      <?php if(isset($hist_map['Cancelled'])): ?>
      <span style="color:var(--muted);font-size:.68rem;"><?=date('d M Y, h:i A', strtotime($hist_map['Cancelled']))?></span>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="display:flex;align-items:center;">
      <?php foreach($steps as $i=>$step):
        $done   = $i <= $cur;
        $isLast = $i === count($steps)-1;
      ?>
      <div style="flex:1;text-align:center;position:relative;">
        <?php if(!$isLast): ?>
        <div style="position:absolute;top:13px;left:50%;width:100%;height:2px;background:<?=$done&&$i<$cur?'var(--accent)':'var(--border)'?>;z-index:0;"></div>
        <?php endif; ?>
        <div style="width:27px;height:27px;border-radius:50%;background:<?=$done?'var(--accent)':'var(--navy3)'?>;border:2px solid <?=$done?'var(--accent)':'var(--border)'?>;margin:0 auto 7px;position:relative;z-index:1;display:flex;align-items:center;justify-content:center;font-size:.65rem;color:<?=$done?'var(--navy)':'var(--muted)'?>;font-weight:700;">
          <?=$done?'✓':($i+1)?>
        </div>
        <div style="font-size:.65rem;letter-spacing:.5px;color:<?=$done?'var(--white)':'var(--muted)'?>;text-transform:uppercase;"><?=e($step)?></div>
        <?php if($done && isset($hist_map[$step])): ?>
        <div style="font-size:.6rem;color:var(--muted);margin-top:3px;"><?=date('d M, h:i A', strtotime($hist_map[$step]))?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Mark as Received button -->
    <?php if($status === 'Delivered'): ?>
    <div style="margin-top:16px;">
      <button type="button" class="btn-received" onclick="openReceiveConfirm(<?=$oid?>)">
        Mark as Received
      </button>
    </div>
    <?php endif; ?>

    <div style="margin-top:12px;font-size:.78rem;color:var(--muted);">
      <?=e($o['shipping_address'])?>
    </div>
  </div>

</div>
<?php endwhile; endif; ?>

</div>
</section>

<!-- ── Mark as Received Confirmation Modal ── -->
<div class="confirm-modal-bg" id="receiveModal">
  <div class="confirm-modal">
    <h3>CONFIRM ORDER RECEIVED</h3>
    <p>Please confirm that you have <strong style="color:var(--white);">physically received</strong> your order and the items are in good condition.</p>

    <div class="disclaimer">
      <strong>Important Notice:</strong> By clicking <em>"Yes, I Received It"</em>, you confirm that your order has been delivered to you. This action <strong>cannot be undone</strong>.<br><br>
      Once confirmed, Apex Sport will not be held responsible for any claims of non-delivery. If you have not received your package yet, please click <strong>"Not Yet"</strong> and contact our support team.
    </div>

    <form method="POST" id="receiveForm">
      <?=csrf_field()?>
      <input type="hidden" name="mark_received" value="1">
      <input type="hidden" name="order_id" id="receive_order_id" value="">
      <div style="display:flex;gap:10px;margin-top:6px;">
        <button type="submit" class="btn btn-primary btn-sm" style="background:#22c55e;border-color:#22c55e;flex:1;">
          ✓ Yes, I Received It
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeReceiveConfirm()" style="flex:1;">
          ✗ Not Yet
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Review Modal ── -->
<div class="review-modal-bg" id="reviewModal">
  <div class="review-modal">
    <h3 id="reviewModalTitle">WRITE A REVIEW</h3>
    <p id="reviewProductName" style="color:var(--muted);font-size:.82rem;margin-top:4px;"></p>

    <form method="POST" id="reviewForm">
      <?=csrf_field()?>
      <input type="hidden" name="submit_review" value="1">
      <input type="hidden" name="order_id"   id="rv_order_id">
      <input type="hidden" name="product_id" id="rv_product_id">
      <input type="hidden" name="rating"     id="rv_rating" value="0">

      <div>
        <div style="font-size:.72rem;letter-spacing:1.5px;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Your Rating *</div>
        <div class="star-row" id="starRow">
          <?php for($s=1;$s<=5;$s++): ?>
          <button type="button" class="star-btn" data-val="<?=$s?>" onclick="setStar(<?=$s?>)">★</button>
          <?php endfor; ?>
        </div>
        <div id="ratingLabel" style="font-size:.75rem;color:var(--muted);min-height:16px;"></div>
      </div>

      <textarea name="comment" class="review-textarea" placeholder="Share your experience with this shoe... (optional)"></textarea>

      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="btn btn-primary btn-sm" id="submitReviewBtn" disabled>Submit Review</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeReview()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// ── Mark as Received modal ──
function openReceiveConfirm(orderId){
    document.getElementById('receive_order_id').value = orderId;
    document.getElementById('receiveModal').classList.add('open');
}
function closeReceiveConfirm(){
    document.getElementById('receiveModal').classList.remove('open');
}
document.getElementById('receiveModal').addEventListener('click', function(e){
    if(e.target === this) closeReceiveConfirm();
});

const ratingLabels = {1:'Poor',2:'Fair',3:'Good',4:'Very Good',5:'Excellent'};
function setStar(val){
    document.getElementById('rv_rating').value = val;
    document.getElementById('ratingLabel').textContent = ratingLabels[val] || '';
    document.querySelectorAll('.star-btn').forEach(b => {
        b.classList.toggle('lit', parseInt(b.dataset.val) <= val);
    });
    document.getElementById('submitReviewBtn').disabled = false;
}
function openReview(orderId, productId, productName){
    document.getElementById('rv_order_id').value   = orderId;
    document.getElementById('rv_product_id').value = productId;
    document.getElementById('rv_rating').value     = 0;
    document.getElementById('reviewProductName').textContent = productName;
    document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('lit'));
    document.getElementById('ratingLabel').textContent = '';
    document.getElementById('submitReviewBtn').disabled = true;
    document.querySelector('#reviewForm textarea').value = '';
    document.getElementById('reviewModal').classList.add('open');
}
function closeReview(){
    document.getElementById('reviewModal').classList.remove('open');
}
document.getElementById('reviewModal').addEventListener('click', function(e){
    if(e.target === this) closeReview();
});
</script>
</body>
</html>
