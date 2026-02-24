<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 获取当前管理员信息
    $admin = $pdo->query("SELECT * FROM admin WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($old_password, $admin['password'])) {
        $error = '原密码错误';
    } elseif (strlen($new_password) < 6) {
        $error = '新密码至少6位';
    } elseif ($new_password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admin SET password=? WHERE id=1");
        $stmt->execute([$hash]);
        $success = '密码修改成功！';
    }
}

$page_title = '修改密码';
$current_page = 'index';
require 'header.php';
?>
        <h1 class="page-title">🔐 修改管理员密码</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="glass-card">
            <form method="post" style="max-width: 450px;">
                <div class="form-group">
                    <label>原密码</label>
                    <input type="password" name="old_password" required placeholder="请输入原密码">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>新密码</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="请输入新密码（至少6位）">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>确认新密码</label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="请再次输入新密码">
                </div>
                <div style="margin-top: 25px;">
                    <button type="submit" class="btn">修改密码</button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">返回</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>