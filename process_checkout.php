<?php
session_start();
require 'db.php';

if(!is_logged() || $_SERVER['REQUEST_METHOD']!=='POST'){ header("Location: index.php"); exit; }

$uid             = (int)$_SESSION['user_id'];
$shipping_address = trim($_POST['shipping_address'] ?? '');
$payment_method   = $_POST['payment_method'] ?? 'Online Banking';

// Calculate server-side (never trust form total)
$cs = $conn->prepare("SELECT c.product_id,c.quantity,c.size,p.price FROM cart_items c JOIN products p ON c.product_id=p.product_id WHERE c.user_id=?");
$cs->bind_param("i",$uid); $cs->execute();
$cart = $cs->get_result();
if($cart->num_rows===0){ header("Location: cart.php"); exit; }

$total=0; $items=[];
while($r=$cart->fetch_assoc()){ $total+=($r['price']*$r['quantity']); $items[]=$r; }

$shipping = $total>=300 ? 0 : 10;
$grand    = $total+$shipping;

// 1. Insert order
$os = $conn->prepare("INSERT INTO orders (user_id,total_amount,status,shipping_address) VALUES (?,?,'Processing',?)");
$os->bind_param("ids",$uid,$grand,$shipping_address);
$os->execute();
$oid = $conn->insert_id;

// 2. Insert order items (now includes size!)
$is = $conn->prepare("INSERT INTO order_items (order_id,product_id,size,quantity,price) VALUES (?,?,?,?,?)");
foreach($items as $item){
    $is->bind_param("iisd", $oid, $item['product_id'], $item['size'], $item['quantity']);
    // bind price separately
    $is2 = $conn->prepare("INSERT INTO order_items (order_id,product_id,size,quantity,price) VALUES (?,?,?,?,?)");
    $is2->bind_param("iisid",$oid,$item['product_id'],$item['size'],$item['quantity'],$item['price']);
    $is2->execute();
}

// 3. Status history
$conn->prepare("INSERT INTO order_status_history (order_id,status) VALUES (?,?)")->bind_param("is",$oid,'Processing') && false;
$hist = $conn->prepare("INSERT INTO order_status_history (order_id, status) VALUES (?, ?)");
$hist->bind_param("is",$oid,'Processing');
$hist->execute();

// 4. Update shipping address
$conn->prepare("UPDATE users SET address=? WHERE user_id=?")->bind_param("si",$shipping_address,$uid) && false;
$upd = $conn->prepare("UPDATE users SET address=? WHERE user_id=?");
$upd->bind_param("si",$shipping_address,$uid);
$upd->execute();

// 5. Clear cart
$conn->query("DELETE FROM cart_items WHERE user_id=$uid");

header("Location: order_success.php?order_id=$oid");
exit;
?>
