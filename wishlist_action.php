<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!is_logged()) {
    echo json_encode(['status'=>'error','msg'=>'Please login first.']);
    exit;
}

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS `wishlists` (
    `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id`     int(11) NOT NULL,
    `product_id`  int(11) NOT NULL,
    `added_at`    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wishlist_id`),
    UNIQUE KEY `uq_up` (`user_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `wishlist_notifications` (
    `notif_id`   int(11) NOT NULL AUTO_INCREMENT,
    `user_id`    int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `message`    varchar(300) NOT NULL,
    `is_read`    tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notif_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','msg'=>'Invalid request.']);
    exit;
}

csrf_check();

$uid = (int)$_SESSION['user_id'];
$pid = (int)($_POST['product_id'] ?? 0);
if (!$pid) {
    echo json_encode(['status'=>'error','msg'=>'Invalid product.']);
    exit;
}

// Toggle
$chk = $conn->prepare("SELECT wishlist_id FROM wishlists WHERE user_id=? AND product_id=?");
$chk->bind_param("ii", $uid, $pid);
$chk->execute();

if ($chk->get_result()->num_rows > 0) {
    $del = $conn->prepare("DELETE FROM wishlists WHERE user_id=? AND product_id=?");
    $del->bind_param("ii", $uid, $pid);
    $del->execute();
    echo json_encode(['status'=>'ok','wishlisted'=>false,'msg'=>'Removed from wishlist.']);
} else {
    $ins = $conn->prepare("INSERT IGNORE INTO wishlists (user_id,product_id) VALUES (?,?)");
    $ins->bind_param("ii", $uid, $pid);
    $ins->execute();
    echo json_encode(['status'=>'ok','wishlisted'=>true,'msg'=>'Added to wishlist! ♥']);
}
