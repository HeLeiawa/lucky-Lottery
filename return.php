<?php
/**
 * 支付同步跳转
 */
session_start();
$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取支付配置
$pay_config = $pdo->query("SELECT * FROM pay_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/PaySDK.php';
$paySDK = new PaySDK($pay_config['pay_url'], $pay_config['pid'], $pay_config['key']);

// 获取回调参数
$params = $_GET;

$out_trade_no = $params['out_trade_no'] ?? '';
$is_success = false;

// 验证签名
if ($paySDK->verifySign($params) && $params['trade_status'] == 'TRADE_SUCCESS') {
    $is_success = true;
} else {
    // 主动查询订单状态
    if ($out_trade_no) {
        $result = $paySDK->queryOrder($out_trade_no);
        if ($result && isset($result['code']) && $result['code'] == 1 && isset($result['status']) && $result['status'] == 1) {
            $is_success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值结果 - 抽奖系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #66CCFF 50%, #764ba2 100%);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            padding: 30px;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container { max-width: 450px; width: 100%; }
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 50px 40px;
            text-align: center;
        }
        .icon { font-size: 80px; margin-bottom: 20px; }
        h1 { color: #fff; font-size: 28px; margin-bottom: 10px; }
        p { color: rgba(255,255,255,0.9); font-size: 16px; margin-bottom: 30px; }
        .btn {
            display: inline-block;
            padding: 14px 40px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #66CCFF 0%, #00a8cc 100%);
            border: none;
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 204, 255, 0.4);
        }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <?php if ($is_success): ?>
                <div class="icon">✅</div>
                <h1>充值成功！</h1>
                <p>订单号：<?php echo htmlspecialchars($out_trade_no); ?></p>
            <?php else: ?>
                <div class="icon">⏳</div>
                <h1>充值处理中</h1>
                <p>如未到账请稍后查看余额，或联系客服</p>
            <?php endif; ?>
            <a href="index.php" class="btn">返回首页</a>
        </div>
    </div>
</body>
</html>