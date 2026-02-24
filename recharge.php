<?php
session_start();
if (!isset($_SESSION['user_id'])) header('Location: login.php');

$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取用户信息
$user = $pdo->query("SELECT * FROM users WHERE id=" . $_SESSION['user_id'])->fetch(PDO::FETCH_ASSOC);

// 获取支付配置
$pay_config = $pdo->query("SELECT * FROM pay_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);

// 检查支付是否开启
if (!$pay_config['status']) {
    die('充值功能暂未开启');
}

// 处理充值请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $money = floatval($_POST['money']);
    $pay_type = trim($_POST['pay_type']);
    
    if ($money < 0.01) {
        $error = '充值金额不能小于0.01元';
    } else {
        // 生成订单号
        $out_trade_no = date('YmdHis') . rand(1000, 9999);
        
        // 插入订单
        $stmt = $pdo->prepare("INSERT INTO recharge_orders (user_id, out_trade_no, money, pay_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $out_trade_no, $money, $pay_type]);
        
        // 调用支付SDK
        require_once __DIR__ . '/includes/PaySDK.php';
        $paySDK = new PaySDK($pay_config['pay_url'], $pay_config['pid'], $pay_config['key']);
        
        // 构建支付参数
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . '://' . $host;
        
        $params = [
            'out_trade_no' => $out_trade_no,
            'name' => '余额充值 - ' . $money . '元',
            'money' => $money,
            'notify_url' => $base_url . '/notify.php',
            'return_url' => $base_url . '/return.php',
            'clientip' => $_SERVER['REMOTE_ADDR'],
            'device' => 'pc'
        ];
        
        if ($pay_type) {
            $params['type'] = $pay_type;
        }
        
        // 跳转到支付页面
        $pay_url = $paySDK->getPayUrl($params);
        header('Location: ' . $pay_url);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在线充值 - 抽奖系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('https://ts1.tc.mm.bing.net/th/id/R-C.d74c4354d4b3aa89043af15cc3d1168b?rik=BtGkkZv%2bctJG%2fA&riu=http%3a%2f%2fimg.netbian.com%2ffile%2f2023%2f0127%2fsmall113422oBswH1674790462.jpg&ehk=UFQxxjGqbe%2fIgx08B%2fOCAICdOOWGwR8J04DtkpmgJuc%3d&risl=&pid=ImgRaw&r=0') center/cover no-repeat;
            padding: 30px;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container { max-width: 500px; width: 100%; }
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 40px;
        }
        h1 { color: #fff; font-size: 28px; margin-bottom: 10px; text-align: center; }
        .balance-bar {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .balance-bar .label { color: rgba(255,255,255,0.85); font-size: 14px; }
        .balance-bar .amount { color: #a8ff98; font-size: 32px; font-weight: bold; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #fff; font-size: 14px; font-weight: 500; margin-bottom: 8px; }
        input, select {
            width: 100%;
            padding: 14px 18px;
            font-size: 16px;
            color: #333;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            outline: none;
        }
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-amount {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .quick-amount:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        .pay-types {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        .pay-type {
            flex: 1;
            background: rgba(255,255,255,0.15);
            border: 2px solid transparent;
            color: #fff;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .pay-type.active {
            border-color: #66CCFF;
            background: rgba(102, 204, 255, 0.2);
        }
        .pay-type:hover {
            background: rgba(255,255,255,0.25);
        }
        .btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #66CCFF 0%, #00a8cc 100%);
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 204, 255, 0.4);
        }
        .btn:hover { transform: translateY(-2px); }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; display: block; text-align: center; margin-top: 20px; }
        .error { background: rgba(255,77,79,0.2); color: #fff; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <h1>💰 在线充值</h1>
            
            <div class="balance-bar">
                <div class="label">当前余额</div>
                <div class="amount">¥<?php echo number_format($user['balance'], 2); ?></div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post" id="rechargeForm">
                <div class="form-group">
                    <label>快捷充值</label>
                    <div class="quick-amounts">
                        <div class="quick-amount" onclick="setAmount(1)">¥1</div>
                        <div class="quick-amount" onclick="setAmount(5)">¥5</div>
                        <div class="quick-amount" onclick="setAmount(10)">¥10</div>
                        <div class="quick-amount" onclick="setAmount(50)">¥50</div>
                        <div class="quick-amount" onclick="setAmount(100)">¥100</div>
                        <div class="quick-amount" onclick="setAmount(200)">¥200</div>
                        <div class="quick-amount" onclick="setAmount(500)">¥500</div>
                        <div class="quick-amount" onclick="setAmount(1000)">¥1000</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>充值金额</label>
                    <input type="number" name="money" id="money" step="0.01" min="0.01" placeholder="请输入充值金额" required>
                </div>
                
                <div class="form-group">
                    <label>支付方式</label>
                    <div class="pay-types">
                        <div class="pay-type active" data-type="" onclick="selectPayType(this)">
                            <div style="font-size: 20px; margin-bottom: 5px; font-weight: bold;">收银台</div>
                        </div>
                        <div class="pay-type" data-type="alipay" onclick="selectPayType(this)">
                            <div style="font-size: 20px; margin-bottom: 5px; font-weight: bold;">支付宝</div>
                        </div>
                        <div class="pay-type" data-type="wxpay" onclick="selectPayType(this)">
                            <div style="font-size: 20px; margin-bottom: 5px; font-weight: bold;">微信支付</div>
                        </div>
                    </div>
                    <input type="hidden" name="pay_type" id="pay_type" value="">
                </div>
                
                <button type="submit" class="btn">立即充值</button>
            </form>
            
            <a href="index.php" class="back-link">← 返回首页</a>
        </div>
    </div>

    <script>
        function setAmount(amount) {
            document.getElementById('money').value = amount;
        }

        function selectPayType(el) {
            document.querySelectorAll('.pay-type').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('pay_type').value = el.dataset.type;
        }
    </script>
</body>
</html>