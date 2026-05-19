<?php
session_start();
require 'db.php';

if(!isset($_SESSION['user_id'])){
    $_SESSION['cart_msg']      = "Please login to add items to your cart.";
    $_SESSION['cart_msg_type'] = "err";
    if(!empty($_POST['product_id']))
        header("Location: product_detail.php?id=".(int)$_POST['product_id']);
    else
        header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';

    if($action==='add'){
        $product_id = (int)$_POST['product_id'];
        $size       = trim($_POST['size']);
        $color      = trim($_POST['color'] ?? '');
        $quantity   = max(1,(int)$_POST['quantity']);

        if(!$size){
            $_SESSION['cart_msg']      = "Please select a size before adding to cart.";
            $_SESSION['cart_msg_type'] = "err";
            header("Location: product_detail.php?id=$product_id");
            exit;
        }

        $chk = $conn->prepare("SELECT cart_id, quantity FROM cart_items WHERE user_id=? AND product_id=? AND size=?");
        $chk->bind_param("iis",$user_id,$product_id,$size);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();

        if($row){
            $nq = $row['quantity'] + $quantity;
            $conn->prepare("UPDATE cart_items SET quantity=? WHERE cart_id=?")->bind_param("ii",$nq,$row['cart_id']);
            // simpler:
            $conn->query("UPDATE cart_items SET quantity=$nq WHERE cart_id=".$row['cart_id']);
        } else {
            $ins = $conn->prepare("INSERT INTO cart_items (user_id, product_id, size, quantity) VALUES (?,?,?,?)");
            $ins->bind_param("iisi",$user_id,$product_id,$size,$quantity);
            $ins->execute();
        }
        $_SESSION['cart_msg']      = "Added to cart successfully! 🛒";
        $_SESSION['cart_msg_type'] = "ok";
        header("Location: product_detail.php?id=$product_id");
        exit;

    } elseif($action==='update'){
        $cart_id  = (int)$_POST['cart_id'];
        $quantity = max(1,(int)$_POST['quantity']);
        $stmt = $conn->prepare("UPDATE cart_items SET quantity=? WHERE cart_id=? AND user_id=?");
        $stmt->bind_param("iii",$quantity,$cart_id,$user_id);
        $stmt->execute();
        header("Location: cart.php"); exit;

    } elseif($action==='remove'){
        $cart_id = (int)$_POST['cart_id'];
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id=? AND user_id=?");
        $stmt->bind_param("ii",$cart_id,$user_id);
        $stmt->execute();
        header("Location: cart.php"); exit;
    }
}
header("Location: cart.php");
?>
