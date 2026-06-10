<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if(!is_logged()){
    echo json_encode(['valid'=>false,'msg'=>'Please log in first.']); exit;
}
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['valid'=>false,'msg'=>'Bad request.']); exit;
}
csrf_check();

$uid  = (int)$_SESSION['user_id'];
$code = strtoupper(trim($_POST['code'] ?? ''));

if($code === ''){
    echo json_encode(['valid'=>false,'msg'=>'Please enter a voucher code.']); exit;
}

$stmt = $conn->prepare("
    SELECT voucher_id, amount, reason
    FROM vouchers
    WHERE code = ? AND user_id = ? AND is_used = 0
      AND (expires_at IS NULL OR expires_at >= CURDATE())
");
$stmt->bind_param("si", $code, $uid);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();

if($v){
    echo json_encode([
        'valid'  => true,
        'amount' => (float)$v['amount'],
        'reason' => $v['reason'],
        'msg'    => 'Voucher applied! You save RM '.number_format($v['amount'],2).'.',
    ]);
} else {
    echo json_encode(['valid'=>false,'msg'=>'Invalid, expired, or already used voucher code.']);
}
