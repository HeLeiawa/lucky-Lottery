<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 获取站点配置
$site = $pdo->query("SELECT * FROM config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? '管理后台'; ?> - 抽奖系统</title>
    <?php if (!empty($site['font']) && $site['font'] != 'default'): ?>
        <style>
            @font-face {
                font-family: 'CustomFont';
                src: url('/ttf/<?php echo htmlspecialchars($site['font']); ?>') format('truetype');
                font-weight: normal;
                font-style: normal;
                font-display: swap;
            }
            * {
                font-family: 'CustomFont', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            }
        </style>
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-sidebar: #2c3e50;
            --bg-sidebar-hover: #34495e;
            --bg-card: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --bg-sidebar: #0f0f23;
            --bg-sidebar-hover: #1a1a3e;
            --bg-card: #1e1e3f;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a4e;
            --shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: var(--bg-secondary);
            position: relative;
            transition: all 0.3s ease;
        }

        /* 侧边栏 */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            padding: 30px 20px;
            z-index: 100;
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .sidebar .logo {
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar .logo img {
            max-width: 100%;
            max-height: 50px;
            object-fit: contain;
        }

        .sidebar .logo .default-logo {
            color: #fff;
            font-size: 22px;
            font-weight: 600;
        }

        .nav-item {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item:hover {
            background: var(--bg-sidebar-hover);
            color: #fff;
        }

        .nav-item.active {
            background: #66CCFF;
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 204, 255, 0.3);
        }

        .nav-section {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 25px 0 10px;
            padding-left: 10px;
        }

        /* 主题切换按钮 */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            box-shadow: var(--shadow);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        /* 主内容区 */
        .main {
            margin-left: 260px;
            padding: 30px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }

        .glass-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 25px;
        }

        .page-title {
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        /* 表单元素 */
        input, select, textarea {
            padding: 10px 14px;
            font-size: 14px;
            color: var(--text-primary);
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #66CCFF;
            box-shadow: 0 0 0 3px rgba(102, 204, 255, 0.3);
        }

        .form-group label {
            display: block;
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .btn {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: #66CCFF;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 204, 255, 0.3);
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 204, 255, 0.4);
        }

        .btn-secondary {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            box-shadow: none;
        }

        .btn-danger {
            background: #ff6b6b;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        /* 表格 */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
        }

        td {
            color: var(--text-primary);
        }

        tr:hover {
            background: var(--bg-secondary);
        }

        /* 状态标签 */
        .tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .tag-green {
            background: rgba(82, 196, 26, 0.3);
            color: #a8ff98;
        }

        .tag-red {
            background: rgba(255, 77, 79, 0.3);
            color: #ffb3b3;
        }

        /* 分页 */
        .pagination {
            margin-top: 20px;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 14px;
            margin-right: 8px;
            background: var(--bg-card);
            color: var(--text-primary);
            text-decoration: none;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .pagination a:hover, .pagination a.active {
            background: #66CCFF;
            color: #fff;
            border-color: #66CCFF;
        }

        /* 消息提示 */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(82, 196, 26, 0.1);
            color: #52c41a;
            border: 1px solid rgba(82, 196, 26, 0.2);
        }

        .alert-error {
            background: rgba(255, 77, 79, 0.1);
            color: #f5222d;
            border: 1px solid rgba(255, 77, 79, 0.2);
        }

        /* 图片预览 */
        .img-preview {
            max-width: 80px;
            border-radius: 8px;
        }

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-card h3 {
            color: var(--text-primary);
            font-size: 36px;
            margin-bottom: 8px;
        }

        .stat-card p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* 表单行 */
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
        }

        .tag-green {
            background: rgba(82, 196, 26, 0.1);
            color: #52c41a;
            border: 1px solid rgba(82, 196, 26, 0.2);
        }

        .tag-red {
            background: rgba(255, 77, 79, 0.1);
            color: #f5222d;
            border: 1px solid rgba(255, 77, 79, 0.2);
        }

        /* 响应式设计 */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }
            .main {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            #sidebarToggle {
                display: block !important;
            }
            .sidebar {
                transform: translateX(-100%);
                z-index: 1001;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main {
                margin-left: 0;
                padding: 20px;
                padding-top: 70px;
            }
            .theme-toggle {
                top: 10px;
                right: 10px;
                padding: 8px 12px;
                font-size: 12px;
            }
            .glass-card {
                padding: 20px 15px;
            }
            .page-title {
                font-size: 22px;
                margin-bottom: 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            .stat-card {
                padding: 20px;
            }
            .stat-card h3 {
                font-size: 28px;
            }
            table {
                font-size: 13px;
            }
            th, td {
                padding: 8px 10px;
            }
            .form-row {
                flex-direction: column;
            }
            .form-group {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 15px;
            }
            .page-title {
                font-size: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            .form-row .btn {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body data-theme="light">
     <script src="/js/click.js"></script>

    <!-- 主题切换按钮 -->
    <button class="theme-toggle" onclick="toggleTheme()">
        <span id="theme-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </span>
        <span id="theme-text">深色模式</span>
    </button>

    <!-- 移动端菜单按钮 -->
    <button id="sidebarToggle" style="position: fixed; top: 20px; left: 20px; background: var(--bg-card); border: 1px solid var(--border-color); padding: 10px; border-radius: 8px; cursor: pointer; display: none; z-index: 1002;" onclick="toggleSidebar()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <?php if (!empty($site['logo'])): ?>
                <img src="<?php echo '/' . htmlspecialchars($site['logo']); ?>" alt="Logo">
            <?php else: ?>
                <div class="default-logo">抽奖后台</div>
            <?php endif; ?>
        </div>

        <div class="nav-section">核心功能</div>
        <a href="index.php" class="nav-item <?php echo ($current_page ?? '') == 'index' ? 'active' : ''; ?>">数据概览</a>
        <a href="config.php" class="nav-item <?php echo ($current_page ?? '') == 'config' ? 'active' : ''; ?>">站点配置</a>

        <div class="nav-section">内容管理</div>
        <a href="codes.php" class="nav-item <?php echo ($current_page ?? '') == 'codes' ? 'active' : ''; ?>">兑换码管理</a>
        <a href="pools.php" class="nav-item <?php echo ($current_page ?? '') == 'pools' ? 'active' : ''; ?>">奖池管理</a>
        <a href="winners.php" class="nav-item <?php echo ($current_page ?? '') == 'winners' ? 'active' : ''; ?>">中奖记录</a>

        <div class="nav-section">系统设置</div>
        <a href="email_config.php" class="nav-item <?php echo ($current_page ?? '') == 'email' ? 'active' : ''; ?>">邮件设置</a>
        <a href="pay_config.php" class="nav-item <?php echo ($current_page ?? '') == 'pay' ? 'active' : ''; ?>">支付设置</a>
        <a href="users.php" class="nav-item <?php echo ($current_page ?? '') == 'users' ? 'active' : ''; ?>">用户管理</a>
        <a href="about.php" class="nav-item <?php echo ($current_page ?? '') == 'about' ? 'active' : ''; ?>">关于系统</a>
        <a href="author.php" class="nav-item <?php echo ($current_page ?? '') == 'author' ? 'active' : ''; ?>">作者信息</a>
        <a href="change_password.php" class="nav-item <?php echo ($current_page ?? '') == 'password' ? 'active' : ''; ?>">修改密码</a>
        <a href="logout.php" class="nav-item" style="margin-top: 30px; opacity: 0.8;">退出登录</a>

    </div>

    <div class="main">
    <script>
        // 主题切换功能
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            const themeText = document.getElementById('theme-text');
            const currentTheme = body.getAttribute('data-theme');

            if (currentTheme === 'light') {
                body.setAttribute('data-theme', 'dark');
                themeIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
                themeText.textContent = '浅色模式';
                localStorage.setItem('theme', 'dark');
            } else {
                body.setAttribute('data-theme', 'light');
                themeIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
                themeText.textContent = '深色模式';
                localStorage.setItem('theme', 'light');
            }
        }

        // 页面加载时恢复主题设置
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            const themeText = document.getElementById('theme-text');

            body.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'dark') {
                themeIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
                themeText.textContent = '浅色模式';
            } else {
                themeIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
                themeText.textContent = '深色模式';
            }
        });

        // 侧边栏切换（移动端）
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // 点击主内容区域关闭侧边栏（移动端）
        document.querySelector('.main')?.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>