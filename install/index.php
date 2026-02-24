<?php
// ==========================================
// 抽奖系统 - 最终完整版单文件安装程序
// 经过完整检查，包含所有表、所有字段
// ==========================================
// 强制关闭缓存
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
// 初始化变量（强制初始值，避免未定义错误）
$is_installed = false;
$error = '';
$success = false;
$manual_config = false;
$db_config = [
    'host' => 'localhost',
    'user' => '',
    'pass' => '',
    'dbname' => ''
];
// 定义路径
$config_dir = __DIR__ . '/../config';
$config_file = $config_dir . '/database.php';
// 检查是否已安装（更严格的判断）
if (file_exists($config_file)) {
    $config_content = @file_get_contents($config_file);
    if ($config_content !== false) {
        $config = @include $config_file;
        if (
            is_array($config)
            && !empty($config['host'])
            && !empty($config['user'])
            && !empty($config['dbname'])
        ) {
            $is_installed = true;
        }
    }
}
if ($is_installed) {
    header('Location: ../index.php');
    exit;
}
// ==========================================
// 完整的SQL语句（经过完整检查，包含所有表、所有字段）
// ==========================================
function get_sql() {
    return [
        "SET FOREIGN_KEY_CHECKS = 0",
        
        // 1. 管理员表
        "CREATE TABLE IF NOT EXISTS `admin` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `password` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 2. 站点配置表
        "CREATE TABLE IF NOT EXISTS `config` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `site_name` varchar(255) NOT NULL DEFAULT '幸运抽奖',
          `site_desc` text,
          `bg_type` varchar(20) NOT NULL DEFAULT 'image',
          `bg_color` varchar(20) DEFAULT NULL,
          `bg_url` varchar(255) DEFAULT NULL,
          `bg_image` varchar(255) DEFAULT NULL,
          `icp` varchar(50) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 3. 用户表
        "CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(100) NOT NULL,
          `password` varchar(255) NOT NULL,
          `nickname` varchar(50) DEFAULT NULL,
          `is_verified` tinyint(1) NOT NULL DEFAULT '0',
          `status` tinyint(1) NOT NULL DEFAULT '1',
          `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 4. 邮件验证码表
        "CREATE TABLE IF NOT EXISTS `email_codes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(100) NOT NULL,
          `code` varchar(10) NOT NULL,
          `type` varchar(20) NOT NULL,
          `expire_time` datetime NOT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_email_type` (`email`, `type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 5. 邮件配置表（完整版）
        "CREATE TABLE IF NOT EXISTS `email_config` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `smtp_host` varchar(255) DEFAULT NULL,
          `smtp_port` int(11) DEFAULT NULL,
          `smtp_user` varchar(255) DEFAULT NULL,
          `smtp_pass` varchar(255) DEFAULT NULL,
          `from_name` varchar(100) DEFAULT NULL,
          `from_email` varchar(255) DEFAULT NULL,
          `is_ssl` tinyint(1) NOT NULL DEFAULT '0',
          `send_interval` int(11) NOT NULL DEFAULT '60',
          `status` tinyint(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 6. 奖池表
        "CREATE TABLE IF NOT EXISTS `pools` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT '1',
          `guarantee_count` int(11) NOT NULL DEFAULT '0',
          `draw_price` decimal(10,2) NOT NULL DEFAULT '0.00',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 7. 奖品表（完整版）
        "CREATE TABLE IF NOT EXISTS `prizes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `pool_id` int(11) NOT NULL,
          `name` varchar(100) NOT NULL,
          `image` varchar(255) DEFAULT NULL,
          `probability` decimal(10,4) NOT NULL,
          `stock` int(11) NOT NULL DEFAULT '0',
          `sort` int(11) NOT NULL DEFAULT '0',
          `is_guarantee` tinyint(1) NOT NULL DEFAULT '0',
          `guarantee_probability` decimal(10,4) NOT NULL DEFAULT '0.0000',
          `is_card` tinyint(1) NOT NULL DEFAULT '0',
          `card_content` text,
          PRIMARY KEY (`id`),
          KEY `idx_pool` (`pool_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 8. 卡密表（完整版，包含所有可能字段）
        "CREATE TABLE IF NOT EXISTS `card_codes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `prize_id` int(11) NOT NULL,
          `code` varchar(255) NOT NULL,
          `is_used` tinyint(1) NOT NULL DEFAULT '0',
          `used_time` datetime DEFAULT NULL,
          `used_at` datetime DEFAULT NULL,
          `winner_id` int(11) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `code` (`code`),
          KEY `idx_prize` (`prize_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 9. 兑换码表（完整版，包含所有可能字段）
        "CREATE TABLE IF NOT EXISTS `codes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `pool_id` int(11) DEFAULT NULL,
          `code` varchar(50) NOT NULL,
          `is_used` tinyint(1) NOT NULL DEFAULT '0',
          `used_time` datetime DEFAULT NULL,
          `used_at` datetime DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `code` (`code`),
          KEY `idx_pool` (`pool_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 10. 中奖记录表（完整版，包含所有可能字段）
        "CREATE TABLE IF NOT EXISTS `winners` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `code_id` int(11) DEFAULT NULL,
          `prize_id` int(11) DEFAULT NULL,
          `card_code_id` int(11) DEFAULT NULL,
          `draw_type` tinyint(1) NOT NULL DEFAULT '1',
          `win_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 11. 用户抽奖日志表
        "CREATE TABLE IF NOT EXISTS `user_draw_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `pool_id` int(11) NOT NULL,
          `is_win` tinyint(1) NOT NULL DEFAULT '0',
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_pool` (`user_id`, `pool_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 12. 余额变动记录表
        "CREATE TABLE IF NOT EXISTS `balance_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `type` tinyint(1) NOT NULL,
          `amount` decimal(10,2) NOT NULL,
          `balance_before` decimal(10,2) NOT NULL,
          `balance_after` decimal(10,2) NOT NULL,
          `remark` varchar(255) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 13. 支付配置表
        "CREATE TABLE IF NOT EXISTS `pay_config` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `pay_url` varchar(255) NOT NULL DEFAULT 'https://b.cvcidc.com',
          `pid` int(11) NOT NULL DEFAULT '0',
          `key` varchar(32) NOT NULL DEFAULT '',
          `status` tinyint(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // 14. 充值订单表
        "CREATE TABLE IF NOT EXISTS `recharge_orders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `out_trade_no` varchar(32) NOT NULL,
          `trade_no` varchar(64) DEFAULT NULL,
          `money` decimal(10,2) NOT NULL,
          `status` tinyint(1) NOT NULL DEFAULT '0',
          `pay_type` varchar(20) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `paid_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `out_trade_no` (`out_trade_no`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "SET FOREIGN_KEY_CHECKS = 1",
        
        // 插入初始数据
        "INSERT IGNORE INTO `config` (`id`, `site_name`, `site_desc`, `bg_type`) VALUES (1, '幸运抽奖', '欢迎使用幸运抽奖系统', 'image')",
        "INSERT IGNORE INTO `email_config` (`id`, `is_ssl`, `send_interval`, `status`) VALUES (1, 0, 60, 0)",
        "INSERT IGNORE INTO `pay_config` (`id`, `pay_url`, `pid`, `key`, `status`) VALUES (1, 'https://b.cvcidc.com', 0, '', 0)"
    ];
}
// 只有POST请求才处理安装
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 接收并过滤参数（使用isset避免未定义错误）
    $db_config = [
        'host' => isset($_POST['host']) ? trim($_POST['host']) : 'localhost',
        'user' => isset($_POST['user']) ? trim($_POST['user']) : '',
        'pass' => isset($_POST['pass']) ? $_POST['pass'] : '',
        'dbname' => isset($_POST['dbname']) ? trim($_POST['dbname']) : ''
    ];
    $admin_user = isset($_POST['admin_user']) ? trim($_POST['admin_user']) : 'admin';
    $admin_pass = isset($_POST['admin_pass']) ? $_POST['admin_pass'] : '';
    // 验证输入
    if (empty($db_config['user']) || empty($db_config['dbname'])) {
        $error = '请填写完整的数据库信息';
    } elseif (strlen($admin_pass) < 6) {
        $error = '管理员密码至少6位';
    } else {
        try {
            // 连接数据库
            $pdo = new PDO(
                "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
                $db_config['user'],
                $db_config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            // 获取SQL数组
            $sql_array = get_sql();
            
            // 逐条执行SQL（更好的错误处理）
            $count = 0;
            foreach ($sql_array as $sql) {
                $sql = trim($sql);
                if (empty($sql)) continue;
                
                try {
                    $pdo->exec($sql);
                    $count++;
                } catch (PDOException $e) {
                    // 忽略表已存在和重复插入的错误
                    $err_msg = $e->getMessage();
                    if (
                        strpos($err_msg, 'already exists') === false
                        && strpos($err_msg, 'Duplicate entry') === false
                    ) {
                        throw new Exception("SQL执行失败: " . $err_msg . " | SQL: " . substr($sql, 0, 100));
                    }
                }
            }
            // 创建管理员账号（所有表创建完成后才执行）
            $password_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("REPLACE INTO `admin` (`id`, `username`, `password`) VALUES (1, ?, ?)");
            $stmt->execute([$admin_user, $password_hash]);
            // 确保config目录存在
            if (!is_dir($config_dir)) {
                if (!mkdir($config_dir, 0755, true)) {
                    throw new Exception("无法创建 config 目录，请手动创建该目录并设置权限为755");
                }
            }
            // 生成配置文件
            $config_content = "<?php
// 抽奖系统数据库配置文件
return [
    'host' => '" . addslashes($db_config['host']) . "',
    'user' => '" . addslashes($db_config['user']) . "',
    'pass' => '" . addslashes($db_config['pass']) . "',
    'dbname' => '" . addslashes($db_config['dbname']) . "'
];
";
            // 尝试写入配置文件
            if (@file_put_contents($config_file, $config_content) === false) {
                $manual_config = true;
            } else {
                @chmod($config_file, 0644);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = "数据库连接失败：" . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>抽奖系统 - 安装向导</title>
    <style>
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            padding: 20px;
        }
        .glass-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 40px;
            width: 90%;
            max-width: 520px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #2c3e50; font-size: 28px; font-weight: 600; margin-bottom: 8px; }
        .header p { color: #6c757d; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .section-title {
            color: #6c757d;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 200px; }
        label { display: block; color: #2c3e50; font-size: 14px; font-weight: 500; margin-bottom: 8px; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            color: #2c3e50;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            outline: none;
        }
        input:focus {
            background: #ffffff;
            border-color: #66CCFF;
            box-shadow: 0 0 0 3px rgba(102, 204, 255, 0.3);
        }
        .section-title {
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: #66CCFF;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .error { background: #fee2e2; color: #f5222d; border: 1px solid #fecaca; }
        .success { background: #d1fae5; color: #059669; border: 1px solid #badbc7; }
        .success a { color: #059669; font-weight: 600; }
        .manual-config {
            background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;
            font-family: 'Courier New', monospace; font-size: 12px; color: #2c3e50; overflow-x: auto; line-height: 1.8;
            border: 1px solid #e5e7eb;
        }
        .hint { background: #fff7ed; padding: 12px; border-radius: 8px; margin-top: 15px; font-size: 12px; color: #66403b; line-height: 1.6; border: 1px solid #fee2e2; }
    </style>
</head>
<body>
    <script src="/js/click.js"></script>
 
    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="glass-card">
        <?php if ($manual_config): ?>
            <div class="header">
                <h1>部分完成</h1>
                <p>数据库安装成功，但无法自动写入配置文件</p>
            </div>
            <div class="hint">
                请手动创建文件 <strong>config/database.php</strong>，并将以下内容复制进去：
            </div>
            <div class="manual-config">
&lt;?php<br>
// 抽奖系统数据库配置文件<br>
return [<br>
&nbsp;&nbsp;&nbsp;&nbsp;'host' => '<?php echo htmlspecialchars($db_config['host']); ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'user' => '<?php echo htmlspecialchars($db_config['user']); ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'pass' => '<?php echo htmlspecialchars($db_config['pass']); ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'dbname' => '<?php echo htmlspecialchars($db_config['dbname']); ?>'<br>
];
            </div>
            <div style="margin-top: 20px;">
                <a href="../index.php" class="btn">配置完成后前往首页</a>
            </div>
        <?php elseif ($success): ?>
            <div class="header">
                <h1>安装成功</h1>
                <p>Lottery System Installed Successfully</p>
            </div>
            <div class="message success">
                <p>系统已成功安装！</p>
                <p style="margin-top: 10px; font-size: 13px;">请立即删除 install 目录以确保安全</p>
            </div>
            <a href="../index.php" class="btn">前往首页</a>
        <?php else: ?>
            <div class="header">
                <h1>抽奖系统</h1>
                <p>最终完整版单文件安装向导</p>
            </div>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="hint">
                安装前请确认：<br>
                1. 已手动创建空数据库<br>
                2. 数据库用户有该库的读写权限<br>
                3. PHP版本 ≥ 8.0，已开启PDO扩展
            </div>
            <form method="post">
                <div class="section-title">数据库连接</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>主机地址</label>
                        <input type="text" name="host" value="localhost" placeholder="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>数据库名</label>
                        <input type="text" name="dbname" placeholder="已创建的空数据库名" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数据库用户</label>
                        <input type="text" name="user" placeholder="数据库登录账号" required>
                    </div>
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="pass" placeholder="数据库登录密码">
                    </div>
                </div>
                <div class="section-title">后台管理员</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>管理员账号</label>
                        <input type="text" name="admin_user" value="admin" placeholder="admin" required>
                    </div>
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_pass" placeholder="至少6位" required minlength="6">
                    </div>
                </div>
                <button type="submit" class="btn">开始全自动安装</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
