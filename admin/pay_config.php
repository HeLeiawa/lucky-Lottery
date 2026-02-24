<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取当前配置
$pay_config = $pdo->query("SELECT * FROM pay_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);

// 保存配置
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pay_url = trim($_POST['pay_url']);
    $pid = intval($_POST['pid']);
    $key = trim($_POST['key']);
    $status = isset($_POST['status']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE pay_config SET pay_url=?, pid=?, `key`=?, status=? WHERE id=1");
    $stmt->execute([$pay_url, $pid, $key, $status]);
    
    header('Location: pay_config.php?success=1');
    exit;
}

$page_title = '支付设置';
$current_page = 'pay';
require 'header.php';
?>
        <h1 class="page-title">💳 支付设置</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">保存成功！</div>
        <?php endif; ?>

        <div class="glass-card">
            <form method="post" style="max-width: 600px;">
                <div class="form-group">
                    <label>支付接口地址</label>
                    <input type="text" name="pay_url" value="<?php echo htmlspecialchars($pay_config['pay_url']); ?>" placeholder="https://b.cvcidc.com" required>
                    <p style="color: rgba(255,255,255,0.6); font-size: 12px; margin-top: 5px;">彩虹易支付接口地址，不要带末尾的 /</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>商户ID (PID)</label>
                        <input type="number" name="pid" value="<?php echo $pay_config['pid']; ?>" placeholder="1001" required>
                    </div>
                    <div class="form-group">
                        <label>商户密钥 (Key)</label>
                        <input type="text" name="key" value="<?php echo htmlspecialchars($pay_config['key']); ?>" placeholder="32位密钥" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="status" <?php echo $pay_config['status'] ? 'checked' : ''; ?>>
                        开启在线充值功能
                    </label>
                </div>
                
                <div style="margin-top: 25px;">
                    <button type="submit" class="btn">保存配置</button>
                </div>
            </form>
            
            <hr style="border-color: rgba(255,255,255,0.2); margin: 30px 0;">

            <h3 style="color: var(--text-primary); margin-bottom: 15px;">📌 回调地址配置</h3>
            <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px;">
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 10px;">
                    <strong>异步通知地址 (notify_url)：</strong><br>
                    <code style="background: rgba(0,0,0,0.3); padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <?php 
                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        echo $protocol . '://' . $host . '/notify.php';
                        ?>
                    </code>
                </p>
                <p style="color: rgba(255,255,255,0.9);">
                    <strong>同步跳转地址 (return_url)：</strong><br>
                    <code style="background: rgba(0,0,0,0.3); padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <?php echo $protocol . '://' . $host . '/return.php'; ?>
                    </code>
                </p>
            </div>
        </div>
    </div>
</body>
</html>