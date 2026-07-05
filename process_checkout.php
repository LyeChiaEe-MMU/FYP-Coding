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
    SELECT c.product_id, c.quantity, c.size, c.color, p.price, p.sale_percent, p.name
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
// First order ships FREE (as promised at registration); otherwise free above RM500
$foc = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE user_id=? AND status<>'Cancelled'");
$foc->bind_param("i", $uid);
$foc->execute();
$is_first_order = ((int)$foc->get_result()->fetch_assoc()['c'] === 0);

$shipping = ($is_first_order || $total >= 500) ? 0 : 10;
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

// 7. Notification — order placed (in-site only; the receipt email below replaces the generic email)
$order_num  = '#' . str_pad($oid, 6, '0', STR_PAD_LEFT);
$pay_short  = $pm_label . ($payment_detail ? ' — ' . $payment_detail : '');
add_notification(
    $conn, $uid,
    'Order Placed — ' . $order_num,
    'Your order ' . $order_num . ' has been placed successfully via ' . $pay_short . '. Total paid: RM ' . number_format($grand, 2) . '. We will process it shortly.',
    'order',
    false
);

// 7b. Email — invoice-style receipt
$ur = $conn->prepare("SELECT name, email FROM users WHERE user_id=?");
$ur->bind_param("i", $uid);
$ur->execute();
$buyer = $ur->get_result()->fetch_assoc();

if($buyer){
    $rows_html = '';
    foreach($items as $it){
        $line_total = $it['eff_price'] * $it['quantity'];
        $variant    = 'UK ' . htmlspecialchars($it['size'], ENT_QUOTES, 'UTF-8')
                    . (!empty($it['color']) && $it['color'] !== 'Default'
                        ? ' · ' . htmlspecialchars($it['color'], ENT_QUOTES, 'UTF-8') : '');
        $rows_html .= '<tr>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #eee;">'
            .   '<div style="font-weight:600;color:#2d2d2d;">' . htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') . '</div>'
            .   '<div style="font-size:.8rem;color:#888;">' . $variant . '</div>'
            . '</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #eee;text-align:center;">' . (int)$it['quantity'] . '</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #eee;text-align:right;">RM ' . number_format($it['eff_price'], 2) . '</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #eee;text-align:right;font-weight:600;">RM ' . number_format($line_total, 2) . '</td>'
            . '</tr>';
    }

    $totals_html = '<tr><td colspan="3" style="padding:8px 12px;text-align:right;color:#888;">Subtotal</td>'
        . '<td style="padding:8px 12px;text-align:right;">RM ' . number_format($total, 2) . '</td></tr>'
        . '<tr><td colspan="3" style="padding:8px 12px;text-align:right;color:#888;">Shipping</td>'
        . '<td style="padding:8px 12px;text-align:right;">' . ($shipping > 0 ? 'RM ' . number_format($shipping, 2) : 'FREE') . '</td></tr>';
    if($discount_amount > 0){
        $totals_html .= '<tr><td colspan="3" style="padding:8px 12px;text-align:right;color:#888;">Voucher (' . htmlspecialchars($voucher_code, ENT_QUOTES, 'UTF-8') . ')</td>'
            . '<td style="padding:8px 12px;text-align:right;color:#2a9b5a;">− RM ' . number_format($discount_amount, 2) . '</td></tr>';
    }
    $totals_html .= '<tr><td colspan="3" style="padding:12px;text-align:right;font-weight:700;color:#2d2d2d;border-top:2px solid #C8543C;">TOTAL PAID</td>'
        . '<td style="padding:12px;text-align:right;font-weight:700;font-size:1.1rem;color:#C8543C;border-top:2px solid #C8543C;">RM ' . number_format($grand, 2) . '</td></tr>';

    $buyer_name_safe = htmlspecialchars($buyer['name'], ENT_QUOTES, 'UTF-8');
    $addr_safe       = nl2br(htmlspecialchars($shipping_address, ENT_QUOTES, 'UTF-8'));
    $pay_safe        = htmlspecialchars($pay_short, ENT_QUOTES, 'UTF-8');

    $receipt = apex_mail_html_body(
        "<p>Hello {$buyer_name_safe},</p>"
        . "<p>Thank you for your order! Here is your receipt for order <strong>{$order_num}</strong> placed on <strong>" . date('d M Y, h:i A') . "</strong>.</p>"
        . '<table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:.9rem;">'
        . '<thead><tr style="background:#fff8f6;">'
        . '<th style="padding:10px 12px;text-align:left;color:#C8543C;font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Item</th>'
        . '<th style="padding:10px 12px;text-align:center;color:#C8543C;font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Qty</th>'
        . '<th style="padding:10px 12px;text-align:right;color:#C8543C;font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Price</th>'
        . '<th style="padding:10px 12px;text-align:right;color:#C8543C;font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Total</th>'
        . '</tr></thead>'
        . '<tbody>' . $rows_html . $totals_html . '</tbody></table>'
        . '<div style="background:#f9f7f4;border-radius:8px;padding:14px 18px;margin:16px 0;font-size:.85rem;color:#555;line-height:1.7;">'
        . '<strong style="color:#2d2d2d;">Payment:</strong> ' . $pay_safe . '<br>'
        . '<strong style="color:#2d2d2d;">Ship to:</strong> ' . $addr_safe
        . '</div>'
        . '<p style="color:#888;font-size:.85rem;">You can track this order anytime under <strong>My Orders</strong> in your Apex account.</p>'
    );
    apex_send_mail($buyer['email'], $buyer['name'], 'Order Receipt ' . $order_num . ' — Apex Store', $receipt);
}

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
