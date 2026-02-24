<?php
session_start();
if (!isset($_SESSION['user_id'])) header('Location: login.php');

$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;
$user_id = $_SESSION['user_id'];

// ==========================================
// 简化版：分别查询中奖和未中奖记录
// ==========================================

// 1. 查询中奖记录
$winners = $pdo->query("SELECT w.*, c.code, p.name as prize_name, p.image as prize_image, po.name as pool_name, w.win_time as time
                       FROM winners w
                       LEFT JOIN codes c ON w.code_id = c.id
                       LEFT JOIN prizes p ON w.prize_id = p.id
                       LEFT JOIN pools po ON c.pool_id = po.id
                       WHERE w.user_id = $user_id
                       ORDER BY w.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 2. 查询未中奖记录（从抽奖日志）
$draw_logs = $pdo->query("SELECT l.*, po.name as pool_name, l.created_at as time
                          FROM user_draw_logs l
                          LEFT JOIN pools po ON l.pool_id = po.id
                          WHERE l.user_id = $user_id AND l.is_win = 0
                          ORDER BY l.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 3. 合并记录
$all_records = [];

// 添加中奖记录
foreach ($winners as $w) {
    $all_records[] = [
        'time' => $w['time'],
        'pool_name' => $w['pool_name'] ?? '-',
        'is_win' => 1,
        'prize_name' => $w['prize_name'],
        'prize_image' => $w['prize_image'],
        'code' => $w['code']
    ];
}

// 添加未中奖记录
foreach ($draw_logs as $d) {
    $all_records[] = [
        'time' => $d['time'],
        'pool_name' => $d['pool_name'] ?? '-',
        'is_win' => 0,
        'prize_name' => null,
        'prize_image' => null,
        'code' => null
    ];
}

// 4. 按时间倒序排序
usort($all_records, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// 5. 分页
$total = count($all_records);
$records = array_slice($all_records, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的抽奖记录 - 抽奖系统</title>
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
        .container { max-width: 900px; margin: 0 auto; }
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            padding: 30px;
        }
        h1 { color: #fff; font-size: 28px; margin-bottom: 20px; }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; margin-bottom: 20px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(255,255,255,0.15); color: #fff; }
        td { color: rgba(255,255,255,0.95); }
        .img-preview { max-width: 60px; border-radius: 8px; }
        .tag { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .tag-green { background: rgba(82, 196, 26, 0.3); color: #a8ff98; }
        .tag-red { background: rgba(255, 77, 79, 0.3); color: #ffb3b3; }
        .pagination { margin-top: 20px; }
        .pagination a { display: inline-block; padding: 8px 14px; margin-right: 8px; background: rgba(255,255,255,0.2); color: #fff; text-decoration: none; border-radius: 8px; }
        .pagination a:hover, .pagination a.active { background: #66CCFF; }
        .code-cell { font-family: 'Courier New', monospace; background: rgba(255,255,255,0.15); padding: 3px 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <a href="index.php" class="back-link">← 返回首页</a>
            <h1>📋 我的抽奖记录</h1>
            
            <?php if (empty($records)): ?>
                <p style="color: rgba(255,255,255,0.8);">暂无抽奖记录</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>奖池</th>
                            <th>结果</th>
                            <th>奖品</th>
                            <th>兑换码</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['time']); ?></td>
                            <td><?php echo htmlspecialchars($r['pool_name']); ?></td>
                            <td>
                                <?php if ($r['is_win']): ?>
                                    <span class="tag tag-green">中奖</span>
                                <?php else: ?>
                                    <span class="tag tag-red">未中奖</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['is_win'] && $r['prize_image']): ?>
                                    <img src="<?php echo htmlspecialchars($r['prize_image']); ?>" class="img-preview" style="vertical-align: middle; margin-right: 10px;">
                                <?php endif; ?>
                                <?php echo $r['is_win'] ? htmlspecialchars($r['prize_name']) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($r['is_win'] && !empty($r['code'])): ?>
                                    <span class="code-cell"><?php echo htmlspecialchars($r['code']); ?></span>
                                <?php else: ?>
                                    <span style="opacity: 0.6;">-</span>
                                <?php endif; ?>
                            </td>
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