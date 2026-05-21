<?php
// get_size_stock.php — AJAX endpoint
// Returns available sizes and their stock for a given product + colour
session_start();
require 'db.php';

header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);
$color_name = trim($_GET['color'] ?? 'Default');

if (!$product_id) { echo json_encode([]); exit; }

// Ensure product_stock table exists
$conn->query("CREATE TABLE IF NOT EXISTS `product_stock` (
    `stock_id`   int(11)     NOT NULL AUTO_INCREMENT,
    `product_id` int(11)     NOT NULL,
    `color_name` varchar(80) NOT NULL DEFAULT 'Default',
    `size`       varchar(10) NOT NULL,
    `stock`      int(11)     NOT NULL DEFAULT 0,
    PRIMARY KEY (`stock_id`),
    UNIQUE KEY `uq_pcs` (`product_id`,`color_name`,`size`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("
    SELECT size, stock
    FROM product_stock
    WHERE product_id = ? AND color_name = ?
    ORDER BY CAST(size AS DECIMAL) ASC
");
$stmt->bind_param("is", $product_id, $color_name);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($rows);
