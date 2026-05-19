<?php
require_once 'auth_check.php';

$pid = (int)($_POST['product_id'] ?? 0);
if(!$pid){ header("Location: admin_products.php"); exit; }

// Handle delete image
if(isset($_GET['del_img'])){
    $iid = (int)$_GET['del_img'];
    $pid_get = (int)($_GET['id'] ?? 0);
    // Also delete file if local
    $row = $conn->query("SELECT image_url FROM product_images WHERE image_id=$iid")->fetch_assoc();
    if($row && !str_starts_with($row['image_url'],'http')){
        $filepath = dirname(__DIR__) . '/' . $row['image_url'];
        if(file_exists($filepath)) unlink($filepath);
    }
    $conn->query("DELETE FROM product_images WHERE image_id=$iid");
    header("Location: admin_product_edit.php?id=$pid_get&msg=Image+removed.");
    exit;
}

$color_name  = trim($_POST['color_name']  ?? '');
$variant_url = trim($_POST['variant_url'] ?? '');
$image_url   = $variant_url;

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS `product_images` (
    `image_id`   int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `image_url`  varchar(300) NOT NULL,
    `color_name` varchar(80)  DEFAULT NULL,
    `sort_order` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`image_id`),
    KEY `product_id` (`product_id`),
    CONSTRAINT `pi_fk1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle file upload
if(!empty($_FILES['variant_image']['name'])){
    $file    = $_FILES['variant_image'];
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(in_array($ext, $allowed) && $file['size'] <= 5*1024*1024){
        $filename  = 'variant_' . $pid . '_' . time() . '.' . $ext;
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if(move_uploaded_file($file['tmp_name'], $uploadDir . $filename)){
            $image_url = 'uploads/' . $filename;
        }
    }
}

if($image_url){
    // Get next sort order
    $max = $conn->query("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM product_images WHERE product_id=$pid")->fetch_assoc()['n'];
    $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, color_name, sort_order) VALUES (?,?,?,?)");
    $cn   = $conn->real_escape_string($color_name);
    $stmt->bind_param("issi", $pid, $image_url, $color_name, $max);
    $stmt->execute();
}

header("Location: admin_product_edit.php?id=$pid&msg=Image+added.");
exit;
