<?php
session_start();
require 'db.php';

// ── Preserve gender context so "Back to X" still works after cart redirect ──
$_bg_allowed = ['Men','Women','Kids'];
$_bg_raw     = trim($_POST['back_gender'] ?? '');
$back_gender_suffix = (in_array($_bg_raw, $_bg_allowed)) ? '&gender='.urlencode($_bg_raw) : '';

if(!isset($_SESSION['user_id'])){
    $_SESSION['cart_msg']      = "Please login to add items to your cart.";
    $_SESSION['cart_msg_type'] = "err";
    if(!empty($_POST['product_id']))
        header("Location: product_detail.php?id=".(int)$_POST['product_id'].$back_gender_suffix);
    else
        header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if(!empty($_SESSION['admin_id'])){
    $_SESSION['cart_msg']      = "Admin accounts cannot add items to cart.";
    $_SESSION['cart_msg_type'] = "err";
    header("Location: " . (!empty($_POST['product_id']) ? "product_detail.php?id=".(int)$_POST['product_id'].$back_gender_suffix : "index.php"));
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action = $_POST['action'] ?? '';

    if($action==='add'){
        $product_id = (int)$_POST['product_id'];
        $size       = trim($_POST['size']);
        $color      = trim($_POST['color'] ?? 'Default');
        if(!$color) $color = 'Default';
        $quantity   = max(1,(int)$_POST['quantity']);

        if(!$size){
            $_SESSION['cart_msg']      = "Please select a size before adding to cart.";
            $_SESSION['cart_msg_type'] = "err";
            header("Location: product_detail.php?id=$product_id$back_gender_suffix");
            exit;
        }

        // ── Check how many are already in cart for this size+colour ──
        $chk = $conn->prepare("SELECT cart_id, quantity FROM cart_items WHERE user_id=? AND product_id=? AND size=? AND color=?");
        $chk->bind_param("iiss",$user_id,$product_id,$size,$color);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $already_in_cart = $row ? (int)$row['quantity'] : 0;

        // ── Check stock — must have enough for what's already in cart + new qty ──
        $stk_chk = $conn->prepare("SELECT stock FROM product_stock WHERE product_id=? AND color_name=? AND size=?");
        if($stk_chk){
            $stk_chk->bind_param("iss",$product_id,$color,$size);
            $stk_chk->execute();
            $stk_row = $stk_chk->get_result()->fetch_assoc();
            if($stk_row !== null){
                $available = (int)$stk_row['stock'];
                if($available < 1){
                    $_SESSION['cart_msg']      = "Sorry, UK $size in $color is out of stock.";
                    $_SESSION['cart_msg_type'] = "err";
                    header("Location: product_detail.php?id=$product_id$back_gender_suffix");
                    exit;
                }
                if(($already_in_cart + $quantity) > $available){
                    $_SESSION['cart_msg']      = "Only $available unit(s) available for UK $size in $color (you already have $already_in_cart in cart).";
                    $_SESSION['cart_msg_type'] = "err";
                    header("Location: product_detail.php?id=$product_id$back_gender_suffix");
                    exit;
                }
            }
        }

        if($row){
            $nq = $row['quantity'] + $quantity;
            $upd = $conn->prepare("UPDATE cart_items SET quantity=? WHERE cart_id=?");
            $upd->bind_param("ii",$nq,$row['cart_id']);
            $upd->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO cart_items (user_id, product_id, size, color, quantity) VALUES (?,?,?,?,?)");
            $ins->bind_param("iissi",$user_id,$product_id,$size,$color,$quantity);
            $ins->execute();
        }

        $_SESSION['cart_msg']      = "Added to cart! UK $size" . ($color && $color!='Default' ? " — $color" : "");
        $_SESSION['cart_msg_type'] = "ok";
        header("Location: product_detail.php?id=$product_id$back_gender_suffix");
        exit;

    } elseif($action==='update'){
        $cart_id  = (int)$_POST['cart_id'];
        $quantity = max(1,(int)$_POST['quantity']);

        // Fetch cart item to get product/size/color for stock validation
        $ci = $conn->prepare("SELECT product_id, size, color FROM cart_items WHERE cart_id=? AND user_id=?");
        $ci->bind_param("ii", $cart_id, $user_id);
        $ci->execute();
        $ci_row = $ci->get_result()->fetch_assoc();

        if($ci_row){
            $stk = $conn->prepare("SELECT stock FROM product_stock WHERE product_id=? AND color_name=? AND size=?");
            $stk->bind_param("iss", $ci_row['product_id'], $ci_row['color'], $ci_row['size']);
            $stk->execute();
            $stk_row = $stk->get_result()->fetch_assoc();
            if($stk_row && $quantity > (int)$stk_row['stock']){
                $_SESSION['cart_msg']      = "Only ".(int)$stk_row['stock']." unit(s) available for that size/colour.";
                $_SESSION['cart_msg_type'] = "err";
                header("Location: cart.php"); exit;
            }
            $stmt = $conn->prepare("UPDATE cart_items SET quantity=? WHERE cart_id=? AND user_id=?");
            $stmt->bind_param("iii",$quantity,$cart_id,$user_id);
            $stmt->execute();
        }
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
