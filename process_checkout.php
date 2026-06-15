<?php
session_start();
require 'db.php';

if (!is_logged() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php"); exit;
}
csrf_check();

$uid              = (int)$_SESSION['user_id'];
$shipping_address = trim($_POST['shipping_address'] ?? '');
$payment_method   = trim($_POST['payment_method']  ?? '');
$payment_detail   = trim($_POST['payment_detail']  ?? '');
$voucher_code     = strtoupper(trim($_POST['voucher_code'] ?? ''));
$discount_amount  = max(0, (float)($_POST['discount_amount'] ?? 0));

// Server-side validation
if(empty($shipping_address)){
    $_SESSION['checkout_error'] = "Please enter your shipping address.";
    header("Location: checkout.php"); exit;
}

// Allowed payment methods
$allowed_pm = ['online_banking','credit_card','ewallet','cod'];
if(!in_array($payment_method, $allowed_pm)){
    $_SESSION['checkout_error'] = "Please select a valid payment method.";
    header("Location: checkout.php"); exit;
}

// Human-readable payment method label
$pm_labels = [
    'online_banking' => 'Online Banking',
    'credit_card'    => 'Credit / Debit Card',
    'ewallet'        => 'E-Wallet',
    'cod'            => 'Cash on Delivery',
];
$pm_label = $pm_labels[$payment_method] ?? $payment_method;

// Get cart items
$cs = $conn->prepare("
    SELECT c.product_id, c.quantity, c.size, c.color, p.price, p.sale_percent
    FROM cart_items c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = ?
");
$cs->bind_param("i", $uid);
$cs->execute();
$cart = $cs->get_result();

if ($cart->num_rows === 0) { header("Location: cart.php"); exit; }

$total = 0; $items = [];
while ($r = $cart->fetch_assoc()) {
    $ep = effective_price($r['price'], (int)($r['sale_percent'] ?? 0));
    $r['original_price'] = (float)$r['price'];
    $r['eff_price']      = $ep;
    $total += ($ep * $r['quantity']);
    $items[] = $r;
}
$shipping = $total >= 300 ? 0 : 10;
$grand    = $total + $shipping;

// ── Pre-validate voucher (read-only check before transaction) ───
$vrow = null;
if($voucher_code !== ''){
    $vs = $conn->prepare("
        SELECT voucher_id, amount FROM vouchers
        WHERE code=? AND user_id=? AND is_used=0
          AND (expires_at IS NULL OR expires_at >= CURDATE())
    ");
    $vs->bind_param("si", $voucher_code, $uid);
    $vs->execute();
    $vrow = $vs->get_result()->fetch_assoc();
    if($vrow){
        $discount_amount = min((float)$vrow['amount'], $grand);
        $grand = max(0, $grand - $discount_amount);
    } else {
        $voucher_code = ''; $discount_amount = 0;
    }
} else {
    $discount_amount = 0;
}

// ── BEGIN TRANSACTION — all steps succeed or all roll back ───────
$conn->begin_transaction();
try {

    // 1. Insert order
    $os = $conn->prepare("
        INSERT INTO orders (user_id, total_amount, discount_amount, voucher_code, status, shipping_address, payment_method, payment_detail)
        VALUES (?, ?, ?, ?, 'Processing', ?, ?, ?)
    ");
    $vc_val = $voucher_code ?: '';
    $os->bind_param("iddssss", $uid, $grand, $discount_amount, $vc_val, $shipping_address, $pm_label, $payment_detail);
    $os->execute();
    $oid = $conn->insert_id;
    if(!$oid) throw new Exception("Order insert failed.");

    // 2. Insert order items (price = effective/discounted, original_price = retail before discount)
    $ins = $conn->prepare("INSERT INTO order_items (order_id, product_id, size, color, quantity, price, original_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $ep   = $item['eff_price'];
        $orig = $item['original_price'];
        $ins->bind_param("iissidd", $oid, $item['product_id'], $item['size'], $item['color'], $item['quantity'], $ep, $orig);
        $ins->execute();
    }

    // 3. Status history
    $hist = $conn->prepare("INSERT INTO order_status_history (order_id, status) VALUES (?, ?)");
    $st   = 'Processing';
    $hist->bind_param("is", $oid, $st);
    $hist->execute();

    // 4. Update user shipping address
    $upd = $conn->prepare("UPDATE users SET address = ? WHERE user_id = ?");
    $upd->bind_param("si", $shipping_address, $uid);
    $upd->execute();

    // 5a. Decrement per-colour-size stock — requires actual stock available (race-safe)
    $deduct_sz = $conn->prepare("
        UPDATE product_stock
        SET stock = stock - ?
        WHERE product_id = ? AND color_name = ? AND size = ? AND stock >= ?
    ");
    // 5b. Decrement total stock in products
    $deduct_tot = $conn->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE product_id = ?");

    foreach ($items as $item) {
        $qty = $item['quantity'];
        $deduct_sz->bind_param("iissi", $qty, $item['product_id'], $item['color'], $item['size'], $qty);
        $deduct_sz->execute();
        if($deduct_sz->affected_rows === 0){
            // Stock ran out between cart-view and checkout — abort and refund the customer nothing was charged
            throw new Exception("Sorry, UK {$item['size']} — {$item['color']} for one of your items ran out of stock just now. Please update your cart and try again.");
        }
        $deduct_tot->bind_param("ii", $qty, $item['product_id']);
        $deduct_tot->execute();
    }

    // 6. Mark voucher used — atomic: WHERE is_used=0 guards against double-spend
    if($vrow){
        $vu = $conn->prepare("UPDATE vouchers SET is_used=1 WHERE voucher_id=? AND is_used=0");
        $vu->bind_param("i", $vrow['voucher_id']);
        $vu->execute();
        if($vu->affected_rows === 0){
            // Another concurrent request already consumed this voucher
            throw new Exception("Voucher already used.");
        }
    }

    // 7. Clear cart
    $del = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $del->bind_param("i", $uid);
    $del->execute();

    $conn->commit();

} catch(Exception $e){
    $conn->rollback();
    $_SESSION['checkout_error'] = "Checkout failed: " . $e->getMessage() . " Please try again.";
    header("Location: checkout.php"); exit;
}

// 7. Notification — order placed
$order_num  = '#' . str_pad($oid, 6, '0', STR_PAD_LEFT);
$pay_short  = $pm_label . ($payment_detail ? ' — ' . $payment_detail : '');
add_notification(
    $conn, $uid,
    'Order Placed — ' . $order_num,
    'Your order ' . $order_num . ' has been placed successfully via ' . $pay_short . '. Total paid: RM ' . number_format($grand, 2) . '. We will process it shortly.',
    'order'
);

// 8. Notification — voucher redeemed (if used)
if($discount_amount > 0 && $voucher_code !== ''){
    add_notification(
        $conn, $uid,
        'Voucher Redeemed — ' . $voucher_code,
        'Your voucher code "' . $voucher_code . '" was successfully applied to order ' . $order_num . ', saving you RM ' . number_format($discount_amount, 2) . '. The voucher has been marked as used.',
        'info'
    );
}

header("Location: order_success.php?order_id=$oid");
exit;
