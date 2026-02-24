<?php
// 安装检测
if (!file_exists(__DIR__ . '/../config/database.php')) {
    header('Location: ../install/index.php');
    exit;
}

session_start();
if (isset($_SESSION['admin_id'])) header('Location: index.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($_POST['password'], $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: index.php');
    } else {
        $error = '账号或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - 抽奖系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('https://ts1.tc.mm.bing.net/th/id/R-C.d74c4354d4b3aa89043af15cc3d1168b?rik=BtGkkZv%2bctJG%2fA&riu=http%3a%2f%2fimg.netbian.com%2ffile%2f2023%2f0127%2fsmall113422oBswH1674790462.jpg&ehk=UFQxxjGqbe%2fIgx08B%2fOCAICdOOWGwR8J04DtkpmgJuc%3d&risl=&pid=ImgRaw&r=0') center/cover no-repeat;
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
        .bubble:nth-child(3) { width: 60px; height: 60px; bottom: 15%; left: 30%; animation-delay: 4s; }
        .bubble:nth-child(4) { width: 100px; height: 100px; top: 20%; right: 30%; animation-delay: 6s; }

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
            box-shadow:
                0 8px 32px 0 rgba(31, 38, 135, 0.37),
                inset 0 0 20px rgba(255, 255, 255, 0.1);
            padding: 40px;
            width: 90%;
            max-width: 400px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 8px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            color: #333;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            outline: none;
            transition: all 0.3s ease;
        }

        input:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: #66CCFF;
            box-shadow: 0 0 0 3px rgba(102, 204, 255, 0.3);
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
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 204, 255, 0.4);
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 204, 255, 0.5);
        }

        .error {
            background: rgba(255, 77, 79, 0.2);
            color: #fff;
            border: 1px solid rgba(255, 77, 79, 0.3);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
   
 
    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="bubble"></div>

    <div class="glass-card">
        <div class="header">
            <h1>管理后台</h1>
            <p>抽奖系统 · Admin Login</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>账号</label>
                <input type="text" name="username" required placeholder="请输入账号">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required placeholder="请输入密码">
            </div>
            <button type="submit" class="btn">登录</button>
        </form>
    </div>
 
    <script src="/js/click.js"></script>
</body>
</html>