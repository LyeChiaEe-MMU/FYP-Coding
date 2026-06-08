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

// Allowed payment methods
$allowed_pm = ['online_banking','credit_card','ewallet','cod'];
if(!in_array($payment_method, $allowed_pm)) $payment_method = 'online_banking';

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
    SELECT c.product_id, c.quantity, c.size, c.color, p.price
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
    $total += ($r['price'] * $r['quantity']);
    $items[] = $r;
}
$shipping = $total >= 300 ? 0 : 10;
$grand    = $total + $shipping;

// ── Validate + apply voucher server-side ────────────────────────
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
        $discount_amount = min((float)$vrow['amount'], $grand); // can't discount more than total
        $grand = max(0, $grand - $discount_amount);
        // Mark voucher as used
        $conn->query("UPDATE vouchers SET is_used=1 WHERE voucher_id={$vrow['voucher_id']}");
    } else {
        $voucher_code    = '';
        $discount_amount = 0;
    }
} else {
    $discount_amount = 0;
}

// 1. Insert order
$os = $conn->prepare("
    INSERT INTO orders (user_id, total_amount, discount_amount, voucher_code, status, shipping_address, payment_method, payment_detail)
    VALUES (?, ?, ?, ?, 'Processing', ?, ?, ?)
");
$vc_val = $voucher_code ?: '';
$os->bind_param("iddssss", $uid, $grand, $discount_amount, $vc_val, $shipping_address, $pm_label, $payment_detail);
$os->execute();
$oid = $conn->insert_id;

// 2. Insert order items
$ins = $conn->prepare("INSERT INTO order_items (order_id, product_id, size, color, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($items as $item) {
    $ins->bind_param("iissid", $oid, $item['product_id'], $item['size'], $item['color'], $item['quantity'], $item['price']);
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

// 5a. Decrement per-colour-size stock in product_stock
$deduct_sz = $conn->prepare("
    UPDATE product_stock
    SET stock = GREATEST(0, stock - ?)
    WHERE product_id = ? AND color_name = ? AND size = ?
");
// 5b. Decrement total stock in products
$deduct_tot = $conn->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE product_id = ?");

foreach ($items as $item) {
    $deduct_sz->bind_param("iiss", $item['quantity'], $item['product_id'], $item['color'], $item['size']);
    $deduct_sz->execute();
    $deduct_tot->bind_param("ii", $item['quantity'], $item['product_id']);
    $deduct_tot->execute();
}

// 6. Clear cart
$del = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
$del->bind_param("i", $uid);
$del->execute();

header("Location: order_success.php?order_id=$oid");
exit;
