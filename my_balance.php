<?php
session_start();
if (!isset($_SESSION['user_id'])) header('Location: login.php');

$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取用户信息
$user = $pdo->query("SELECT * FROM users WHERE id=" . $_SESSION['user_id'])->fetch(PDO::FETCH_ASSOC);
if (!$user) header('Location: logout.php');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$user_id = $_SESSION['user_id'];

// 获取余额记录
$logs = $pdo->query("SELECT * FROM balance_logs WHERE user_id=$user_id ORDER BY id DESC LIMIT $per_page OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$total = $pdo->query("SELECT COUNT(*) FROM balance_logs WHERE user_id=$user_id")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>余额明细 - 抽奖系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: url('https://ts1.tc.mm.bing.net/th/id/R-C.d74c4354d4b3aa89043af15cc3d1168b?rik=BtGkkZv%2bctJG%2fA&riu=http%3a%2f%2fimg.netbian.com%2ffile%2f2023%2f0127%2fsmall113422oBswH1674790462.jpg&ehk=UFQxxjGqbe%2fIgx08B%2fOCAICdOOWGwR8J04DtkpmgJuc%3d&risl=&pid=ImgRaw&r=0') center/cover no-repeat;
            padding: 30px;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container { max-width: 800px; margin: 0 auto; }
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            padding: 30px;
        }
        h1 { color: #fff; font-size: 28px; margin-bottom: 10px; }
        .balance-display {
            background: rgba(255,255,255,0.15);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        .balance-display .label { color: rgba(255,255,255,0.85); font-size: 14px; }
        .balance-display .amount { color: #a8ff98; font-size: 36px; font-weight: bold; margin-top: 5px; }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; margin-bottom: 20px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(255,255,255,0.15); color: #fff; }
        td { color: rgba(255,255,255,0.95); }
        .amount-plus { color: #a8ff98; font-weight: bold; }
        .amount-minus { color: #ff6b6b; font-weight: bold; }
        .pagination { margin-top: 20px; }
        .pagination a { display: inline-block; padding: 8px 14px; margin-right: 8px; background: rgba(255,255,255,0.2); color: #fff; text-decoration: none; border-radius: 8px; }
        .pagination a:hover, .pagination a.active { background: #66CCFF; }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <a href="index.php" class="back-link">← 返回首页</a>
            <h1>余额明细</h1>
            
            <div class="balance-display">
                <div class="label">当前余额</div>
                <div class="amount">¥<?php echo number_format($user['balance'], 2); ?></div>
            </div>
            
            <?php if (empty($logs)): ?>
                <p style="color: rgba(255,255,255,0.8);">暂无余额变动记录</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>类型</th>
                            <th>变动金额</th>
                            <th>变动后余额</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td>
                                <?php 
                                if ($log['type'] == 1) echo '<span style="color: #a8ff98;">充值</span>';
                                elseif ($log['type'] == 2) echo '<span style="color: #ff6b6b;">消费</span>';
                                elseif ($log['type'] == 3) echo '<span style="color: #ffd93d;">退款</span>';
                                else echo '其他';
                                ?>
                            </td>
                            <td class="<?php echo $log['type'] == 1 ? 'amount-plus' : 'amount-minus'; ?>">
                                <?php echo $log['type'] == 1 ? '+' : '-'; ?>¥<?php echo number_format($log['amount'], 2); ?>
                            </td>
                            <td>¥<?php echo number_format($log['balance_after'], 2); ?></td>
                            <td><?php echo htmlspecialchars($log['remark'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <?php for ($i = 1; $i <= ceil($total / $per_page); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>