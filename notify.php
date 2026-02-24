<?php
/**
 * 支付异步回调处理
 */
$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取支付配置
$pay_config = $pdo->query("SELECT * FROM pay_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/PaySDK.php';
$paySDK = new PaySDK($pay_config['pay_url'], $pay_config['pid'], $pay_config['key']);

// 获取回调参数
$params = $_GET;

// 验证签名
if (!$paySDK->verifySign($params)) {
    die('fail');
}

// 验证支付状态
if ($params['trade_status'] != 'TRADE_SUCCESS') {
    die('success');
}

$out_trade_no = $params['out_trade_no'];
$trade_no = $params['trade_no'];
$money = floatval($params['money']);

// 检查订单
$order = $pdo->prepare("SELECT * FROM recharge_orders WHERE out_trade_no=?");
$order->execute([$out_trade_no]);
$order = $order->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('success');
}

if ($order['status'] == 1) {
    die('success');
}

// 开始事务
$pdo->beginTransaction();
try {
    // 更新订单状态
    $stmt = $pdo->prepare("UPDATE recharge_orders SET status=1, trade_no=?, paid_at=NOW() WHERE id=?");
    $stmt->execute([$trade_no, $order['id']]);
    
    // 增加用户余额
    $user = $pdo->query("SELECT * FROM users WHERE id=" . $order['user_id'])->fetch(PDO::FETCH_ASSOC);
    $balance_before = $user['balance'];
    $balance_after = $balance_before + $money;
    
    $stmt = $pdo->prepare("UPDATE users SET balance=? WHERE id=?");
    $stmt->execute([$balance_after, $order['user_id']]);
    
    // 记录余额变动
    $stmt = $pdo->prepare("INSERT INTO balance_logs (user_id, type, amount, balance_before, balance_after, remark) VALUES (?, 1, ?, ?, ?, ?)");
    $stmt->execute([$order['user_id'], $money, $balance_before, $balance_after, '在线充值 - 订单号:' . $out_trade_no]);
    
    $pdo->commit();
    echo 'success';
} catch (Exception $e) {
    $pdo->rollBack();
    echo 'fail';
}