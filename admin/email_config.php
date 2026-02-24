<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取当前配置
$email_config = $pdo->query("SELECT * FROM email_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("REPLACE INTO email_config (id, smtp_host, smtp_port, smtp_user, smtp_pass, from_name, is_ssl, send_interval) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['smtp_host'],
        intval($_POST['smtp_port']),
        $_POST['smtp_user'],
        $_POST['smtp_pass'],
        $_POST['from_name'],
        isset($_POST['is_ssl']) ? 1 : 0,
        intval($_POST['send_interval'])
    ]);
    header('Location: email_config.php?success=1');
    exit;
}

$page_title = '邮件设置';
$current_page = 'email';
require 'header.php';
?>
        <h1 class="page-title">📧 邮件设置</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">保存成功！</div>
        <?php endif; ?>

        <div class="glass-card">
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP服务器</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($email_config['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="form-group">
                        <label>端口</label>
                        <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($email_config['smtp_port'] ?? '465'); ?>" placeholder="465">
                    </div>
                </div>
                <div class="form-row" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>SMTP用户名</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($email_config['smtp_user'] ?? ''); ?>" placeholder="noreply@example.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP密码</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($email_config['smtp_pass'] ?? ''); ?>" placeholder="密码">
                    </div>
                </div>
                <div class="form-row" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>发件人名称</label>
                        <input type="text" name="from_name" value="<?php echo htmlspecialchars($email_config['from_name'] ?? '抽奖系统'); ?>" placeholder="抽奖系统">
                    </div>
                    <div class="form-group">
                        <label>发送间隔(秒)</label>
                        <input type="number" name="send_interval" value="<?php echo htmlspecialchars($email_config['send_interval'] ?? '60'); ?>" placeholder="60" min="10" max="3600">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_ssl" <?php echo ($email_config['is_ssl'] ?? 1) ? 'checked' : ''; ?>>
                            使用SSL
                        </label>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">保存设置</button>
                </div>
            </form>
        </div>
        
        <div class="glass-card" style="margin-top: 20px;">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">💡 常用SMTP配置</h3>
            <table>
                <thead>
                    <tr>
                        <th>邮箱</th>
                        <th>SMTP服务器</th>
                        <th>端口(SSL)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>QQ邮箱</td>
                        <td>smtp.qq.com</td>
                        <td>465</td>
                    </tr>
                    <tr>
                        <td>163邮箱</td>
                        <td>smtp.163.com</td>
                        <td>465</td>
                    </tr>
                    <tr>
                        <td>Gmail</td>
                        <td>smtp.gmail.com</td>
                        <td>465</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>