<?php
// search_ajax.php — Live search endpoint
// Returns JSON array of matching products
session_start();
require 'db.php';

header('Content-Type: application/json');

$raw = trim($_GET['q'] ?? '');
if (strlen($raw) < 2) { echo json_encode([]); exit; }

// Split into individual keywords — so "apex gen" becomes ["apex","gen"]
// Every keyword must appear somewhere in the product name or category
$keywords = preg_split('/\s+/', $raw);
$keywords = array_values(array_filter($keywords, fn($w) => strlen($w) >= 1));

// Guard: if no usable keywords remain (e.g. all-whitespace input), return empty
if(empty($keywords)){ echo json_encode([]); exit; }

// Build WHERE clause: each keyword is checked with LIKE
$conditions = [];
foreach ($keywords as $word) {
    $safe = $conn->real_escape_string($word);
    // Match keyword in name OR category — so "running apex" still works
    $conditions[] = "(p.name LIKE '%$safe%' OR c.category_name LIKE '%$safe%' OR p.description LIKE '%$safe%')";
}
$where = implode(' AND ', $conditions);

$sql = "
    SELECT p.product_id AS id, p.name, p.price, p.sale_percent, p.image_url AS image, c.category_name AS category
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.is_active = 1 AND $where
    ORDER BY p.name ASC
    LIMIT 6
";

$result = $conn->query($sql);
$out = [];
while ($row = $result->fetch_assoc()) {
    // Fix image path — local uploads need full URL, external URLs stay as-is
    if (empty($row['image'])) {
        $row['image'] = 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=80&q=60';
    } elseif (!str_starts_with($row['image'], 'http')) {
        $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'];
        // dirname('/apex_final/search_ajax.php') = '/apex_final'
        $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
        $row['image'] = $protocol . '://' . $host . $base_path . ltrim($row['image'], '/');
    }
    // Compute effective (discounted) price for display
    $sp_pct = max(0, min(99, (int)($row['sale_percent'] ?? 0)));
    $row['eff_price'] = $sp_pct > 0
        ? (float)round((float)$row['price'] * (1 - $sp_pct / 100))
        : (float)$row['price'];
    $out[] = $row;
}

echo json_encode($out);
