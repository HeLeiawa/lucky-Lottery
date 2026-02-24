<?php
// 最顶部绝对不能有任何空格、换行、HTML内容，否则会破坏JSON输出
ob_start(); // 开启输出缓冲，彻底杜绝多余内容破坏JSON
session_start();
if (isset($_SESSION['user_id'])) header('Location: index.php');
$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);
try {
    $email_config = $pdo->query("SELECT * FROM email_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $send_interval = $email_config['send_interval'] ?? 60;
} catch (PDOException $e) {
    $send_interval = 60; // 如果email_config表不存在，使用默认值
}
$error = '';
$success = '';

// 处理Ajax发送验证码请求（核心修复：严格控制输出）
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_code') {
    ob_clean(); // 清空之前的所有输出，绝对保证JSON纯净
    header('Content-Type: application/json; charset=utf-8');
    $email = trim($_POST['email']);
    
    // 邮箱格式校验
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['code' => 0, 'msg' => '邮箱格式不正确']);
        exit;
    }
    
    // 检查发送频率
    $stmt = $pdo->prepare("SELECT * FROM email_codes WHERE email=? AND type='register' AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([$email, $send_interval]);
    if ($stmt->fetch()) {
        echo json_encode(['code' => 0, 'msg' => "发送太频繁，请{$send_interval}秒后再试"]);
        exit;
    }
    
    // 生成6位验证码
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expire = date('Y-m-d H:i:s', time() + 300);
    
    // 写入验证码到数据库
    $stmt = $pdo->prepare("REPLACE INTO email_codes (email, code, type, expire_time) VALUES (?, ?, 'register', ?)");
    $stmt->execute([$email, $code, $expire]);
    
    // 发送邮件（美化模板，无平台表述）
    require_once __DIR__ . '/includes/Email.php';
    $mailer = new Email($pdo);
    $email_title = '【账号注册】你的注册验证码';
    $email_content = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册验证码</title>
</head>
<body style="margin: 0; padding: 20px 10px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #66CCFF 50%, #764ba2 100%);">
    <div style="max-width: 600px; margin: 0 auto; background: rgba(255, 255, 255, 0.95); border-radius: 24px; box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15); padding: 40px 30px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #00a8cc; font-size: 24px; margin: 0 0 10px 0;">账号注册验证码</h1>
            <p style="color: #666; font-size: 14px; margin: 0;">你的验证码已送达</p>
        </div>
        <p style="color: #333; font-size: 15px; line-height: 1.8; margin: 0 0 15px 0;">嗨，你好呀！</p>
        <p style="color: #333; font-size: 15px; line-height: 1.8; margin: 0 0 30px 0;">你正在进行账号注册操作，本次注册的验证码在这里</p>
        <div style="background: #66CCFF; border-radius: 12px; padding: 30px 20px; text-align: center; margin: 0 0 30px 0;">
            <div style="font-size: 36px; font-weight: bold; color: #fff; letter-spacing: 12px; font-family: monospace;">$code</div>
        </div>
        <p style="color: #333; font-size: 15px; line-height: 1.8; margin: 0 0 10px 0;">这个验证码5分钟内有效，过期就无法使用了</p>
        <div style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; padding: 10px 15px; border-radius: 4px; margin: 0 0 20px 0;">
            <p style="margin: 0; color: #333; font-size: 14px; line-height: 1.6;"><strong>特别提醒：</strong><br>千万不要把这个验证码告诉任何人！我们的工作人员绝对不会向你索要验证码，谁要都别给！</p>
        </div>
        <p style="color: #333; font-size: 15px; line-height: 1.8; margin: 0;">如果不是你本人操作的注册，不用管这封邮件就行，不会有任何影响。</p>
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 12px;">
            <p style="margin: 0;">这是系统自动发送的邮件，无需回复</p>
        </div>
    </div>
</body>
</html>
HTML;

    // 核心修复：兼容邮件类的返回值，无论返回true/1都算成功
    $sendResult = $mailer->send($email, $email_title, $email_content);
    if ($sendResult || $sendResult == 1) {
        echo json_encode(['code' => 1, 'msg' => '验证码已发送，请查收邮件']);
    } else {
        echo json_encode(['code' => 0, 'msg' => '验证码发送失败，请稍后重试']);
    }
    exit; // 发送完JSON立即终止，绝对不输出后面的HTML内容
}

// 处理注册提交
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);
    $password = $_POST['password'];
    $nickname = trim($_POST['nickname']);
    
    $stmt = $pdo->prepare("SELECT * FROM email_codes WHERE email=? AND code=? AND type='register' AND expire_time>NOW()");
    $stmt->execute([$email, $code]);
    if (!$stmt->fetch()) {
        echo json_encode(['code' => 0, 'msg' => '验证码错误或已过期']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, nickname, is_verified) VALUES (?, ?, ?, 1)");
        $stmt->execute([$email, $hash, $nickname]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_email'] = $email;
        $_SESSION['user_nickname'] = $nickname;
        echo json_encode(['code' => 1, 'msg' => '注册成功，正在跳转...']);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['code' => 0, 'msg' => '该邮箱已被注册']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 账号系统</title>
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
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 40px;
            width: 90%;
            max-width: 420px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 { color: #fff; font-size: 28px; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; color: #fff; font-size: 14px; margin-bottom: 6px; }
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
        .code-row { display: flex; gap: 10px; }
        .code-row input { flex: 1; }
        .btn {
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            background: #66CCFF;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-full { width: 100%; margin-top: 10px; }
        .error { background: rgba(255,77,79,0.2); color: #ffb3b3; padding: 12px; border-radius: 10px; margin-bottom: 15px; text-align: center; }
        .success { background: rgba(82,196,26,0.2); color: #a8ff98; padding: 12px; border-radius: 10px; margin-bottom: 15px; text-align: center; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: rgba(255,255,255,0.9); text-decoration: none; }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .glass-card {
                width: 95%;
                padding: 30px 20px;
                margin: 0 10px;
            }
            h1 { font-size: 24px; }
            .code-row { flex-direction: column; }
            .code-row button { width: 100%; margin-top: 10px; }
            .bubble { display: none; }
        }
        @media (max-width: 480px) {
            .glass-card { width: 95%; padding: 25px 15px; }
            h1 { font-size: 20px; margin-bottom: 20px; }
            input { font-size: 14px; padding: 10px 14px; }
            .btn { font-size: 14px; padding: 10px 16px; }
        }
    </style>
</head>
<body>
    <script src="/js/click.js"></script>
    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="glass-card">
        <h1>用户注册</h1>
        <div id="msgBox"></div>
        <div class="form-wrap">
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" id="email" placeholder="请输入邮箱">
            </div>
            <div class="form-group">
                <label>验证码</label>
                <div class="code-row">
                    <input type="text" id="code" placeholder="请输入验证码">
                    <button type="button" class="btn" id="sendCodeBtn">发送验证码</button>
                </div>
            </div>
            <div class="form-group">
                <label>昵称</label>
                <input type="text" id="nickname" placeholder="请输入昵称">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" id="password" minlength="6" placeholder="请输入密码（至少6位）">
            </div>
            <button type="button" class="btn btn-full" id="registerBtn">注册</button>
        </div>
        <div class="links">
            <a href="login.php">已有账号？去登录 →</a>
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

    <script>
        const sendInterval = <?php echo $send_interval; ?>;
        let countdown = 0;
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        const registerBtn = document.getElementById('registerBtn');
        const msgBox = document.getElementById('msgBox');

        // 消息提示
        function showMsg(text, isError = false) {
            msgBox.innerHTML = `<div class="${isError ? 'error' : 'success'}">${text}</div>`;
        }

        // 发送验证码（修复错误处理逻辑）
        sendCodeBtn.addEventListener('click', async function() {
            const email = document.getElementById('email').value.trim();
            if (!email) {
                showMsg('请先输入邮箱', true);
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showMsg('邮箱格式不正确', true);
                return;
            }
            if (countdown > 0) return;

            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = '发送中...';
            msgBox.innerHTML = '';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=send_code&email=${encodeURIComponent(email)}`
                });

                // 核心修复：先判断响应状态，再解析JSON
                if (!response.ok) {
                    throw new Error('服务器响应异常');
                }
                const result = await response.json();

                if (result.code === 1) {
                    showMsg(result.msg);
                    startCountdown();
                } else {
                    showMsg(result.msg, true);
                    resetSendBtn();
                }
            } catch (e) {
                // 详细错误提示，方便排查
                console.error('发送请求错误：', e);
                showMsg('请求异常，请刷新页面后重试', true);
                resetSendBtn();
            }
        });

        // 注册逻辑
        registerBtn.addEventListener('click', async function() {
            const email = document.getElementById('email').value.trim();
            const code = document.getElementById('code').value.trim();
            const password = document.getElementById('password').value;
            const nickname = document.getElementById('nickname').value.trim();

            if (!email || !code || !nickname || !password) {
                showMsg('请填写完整的注册信息', true);
                return;
            }
            if (password.length < 6) {
                showMsg('密码至少6位', true);
                return;
            }

            registerBtn.disabled = true;
            registerBtn.textContent = '注册中...';
            msgBox.innerHTML = '';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=register&email=${encodeURIComponent(email)}&code=${encodeURIComponent(code)}&nickname=${encodeURIComponent(nickname)}&password=${encodeURIComponent(password)}`
                });
                if (!response.ok) throw new Error('服务器响应异常');
                const result = await response.json();

                if (result.code === 1) {
                    showMsg(result.msg);
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    showMsg(result.msg, true);
                    registerBtn.disabled = false;
                    registerBtn.textContent = '注册';
                }
            } catch (e) {
                console.error('注册请求错误：', e);
                showMsg('请求异常，请刷新页面后重试', true);
                registerBtn.disabled = false;
                registerBtn.textContent = '注册';
            }
        });

        // 倒计时逻辑
        function startCountdown() {
            countdown = sendInterval;
            sendCodeBtn.disabled = true;
            const timer = setInterval(() => {
                countdown--;
                sendCodeBtn.textContent = `${countdown}s后重发`;
                if (countdown <= 0) {
                    clearInterval(timer);
                    resetSendBtn();
                }
            }, 1000);
        }

        // 重置发送按钮
        function resetSendBtn() {
            sendCodeBtn.disabled = false;
            sendCodeBtn.textContent = '发送验证码';
        }

        // 禁止回车触发刷新
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
