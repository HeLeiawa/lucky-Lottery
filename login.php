<?php
session_start();
if (isset($_SESSION['user_id'])) header('Location: index.php');

$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_nickname'] = $user['nickname'];
        header('Location: index.php');
    } else {
        $error = '邮箱或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 抽奖系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('https://t.alcy.cc/ycy/') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            animation: float 20s infinite ease-in-out;
        }
        .bubble:nth-child(1) { width: 80px; height: 80px; top: 10%; left: 10%; animation-delay: 0s; }
        .bubble:nth-child(2) { width: 120px; height: 120px; top: 60%; right: 10%; animation-delay: 2s; }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 40px;
            width: 90%;
            max-width: 400px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 { color: #fff; font-size: 28px; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #fff; font-size: 14px; margin-bottom: 8px; }
        input {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            color: #333;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            outline: none;
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
        }
        .error { background: rgba(255,77,79,0.2); color: #ffb3b3; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: rgba(255,255,255,0.9); text-decoration: none; }
    </style>
</head>
<body>
     <script src="/js/click.js"></script>
    
    <div class="bubble"></div>
    <div class="bubble"></div>

    <div class="glass-card">
        <h1>用户登录</h1>

        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" required placeholder="请输入邮箱">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required placeholder="请输入密码">
            </div>
            <button type="submit" class="btn">登录</button>
        </form>

        <div class="links">
            <a href="register.php">没有账号？去注册 →</a>
        </div>
    </div>

    <!-- GitHub按钮 -->
    <a href="https://github.com/HeLeiawa/lucky-Lottery" target="_blank"
       style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;"
       title="项目开源地址">
        <svg width="40" height="40" viewBox="0 0 16 16" fill="white">
            <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
        </svg>
    </a>
</body>
</html>