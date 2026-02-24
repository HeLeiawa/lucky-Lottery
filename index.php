<?php
if (!file_exists(__DIR__ . '/config/database.php')) {
    header('Location: install/index.php');
    exit;
}
session_start();
$config = require __DIR__ . '/config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);
$site = $pdo->query("SELECT * FROM config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
// 获取用户信息和余额
$user = null;
$user_balance = 0;
if (isset($_SESSION['user_id'])) {
    $user = $pdo->query("SELECT * FROM users WHERE id=" . $_SESSION['user_id'])->fetch(PDO::FETCH_ASSOC);
    $user_balance = $user ? $user['balance'] : 0;
}
// 获取活动奖池
$pools = $pdo->query("SELECT * FROM pools WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site['site_name']); ?></title>
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding-top: 60px;
            background: url('https://t.alcy.cc/ycy/') center/cover no-repeat;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.5) transparent;
        }

        /* 导航栏 */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
        }

        .navbar .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .navbar .logo img {
            max-height: 35px;
            max-width: 120px;
            object-fit: contain;
        }

        .navbar .logo .default-logo {
            color: #fff;
            font-size: 18px;
            font-weight: 600;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex: 1;
            margin: 0 30px;
        }

        .navbar .nav-links a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            padding: 5px 15px;
            border-radius: 8px;
            white-space: nowrap;
        }

        .navbar .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .navbar .nav-links a.active {
            background: rgba(102, 204, 255, 0.3);
            color: #66CCFF;
        }

        .navbar .nav-auth {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }

        .navbar .nav-auth a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            padding: 5px 15px;
            border-radius: 8px;
            white-space: nowrap;
        }

        .navbar .nav-auth a:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            #menuToggle {
                display: block !important;
            }

            .navbar {
                padding: 0 15px;
                height: auto;
                flex-direction: row;
                flex-wrap: wrap;
                padding-bottom: 10px;
            }

            .navbar .logo {
                flex: 1;
                justify-content: flex-start;
                padding: 10px 0;
            }

            .navbar .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                margin: 10px 0;
                gap: 5px;
                order: 3;
            }

            .navbar .nav-links.active {
                display: flex;
            }

            .navbar .nav-links a {
                width: 100%;
                text-align: center;
                padding: 12px 15px;
            }

            .navbar .nav-auth {
                display: none;
                flex-direction: column;
                width: 100%;
                margin: 10px 0;
                gap: 5px;
                order: 3;
            }

            .navbar .nav-auth.active {
                display: flex;
            }

            .navbar .nav-auth a,
            .navbar .nav-auth span {
                width: 100%;
                text-align: center;
                padding: 12px 15px;
            }

            .glass-card {
                width: 95%;
                padding: 25px 15px;
            }

            h1 {
                font-size: 24px;
            }

            .btn {
                padding: 12px;
                font-size: 15px;
            }

            .balance-bar {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .navbar .logo img {
                max-height: 30px;
                max-width: 100px;
            }

            .navbar .logo .default-logo {
                font-size: 16px;
            }
        }
        /* 适配Chrome/Edge/搜狗等浏览器滚动条 */
        body::-webkit-scrollbar {
            width: 6px;
        }
        body::-webkit-scrollbar-thumb {
            background-color: rgba(255,255,255,0.5);
            border-radius: 3px;
        }
        body::-webkit-scrollbar-track {
            background: transparent;
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
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37), inset 0 0 20px rgba(255, 255, 255, 0.1);
            padding: 40px 20px;
            width: 90%;
            max-width: 480px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
            text-align: center;
            margin-top: 20px;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 { color: #fff; font-size: 32px; font-weight: 700; margin-bottom: 10px; }
        p { color: rgba(255, 255, 255, 0.9); font-size: 15px; margin-bottom: 20px; }
        .balance-bar {
            background: rgba(255,255,255,0.15);
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
        }
        .balance-bar span { color: #fff; }
        .balance-bar .amount { color: #a8ff98; font-weight: bold; font-size: 20px; }
        input, select {
            width: 100%;
            padding: 14px 18px;
            font-size: 16px;
            color: #333;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            outline: none;
            margin-bottom: 15px;
        }
        .draw-type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .draw-type-tab {
            flex: 1;
            padding: 12px;
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 12px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s;
        }
        .draw-type-tab.active {
            background: linear-gradient(135deg, #66CCFF 0%, #00a8cc 100%);
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
            margin-bottom: 20px;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        #result { margin-top: 25px; padding: 20px; border-radius: 8px; background: rgba(255, 255, 255, 0.15); display: none; }
        #result h3 { color: #fff; margin-bottom: 10px; }
        #result p { margin: 0; color: rgba(255, 255, 255, 0.95); }
        #result img { max-width: 200px; border-radius: 8px; margin-top: 10px; }
        .nav-links { margin-top: 20px; margin-bottom: 20px; }
        .nav-links a { color: rgba(255,255,255,0.9); margin: 0 10px; text-decoration: none; }
        .loading { display: none; color: #fff; margin-top: 15px; margin-bottom: 15px; }
        .pool-info {
            background: rgba(255,255,255,0.1);
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: left;
        }
        .pool-info p { margin: 0; font-size: 13px; color: rgba(255,255,255,0.85); }
        .footer {
            margin-top: 30px;
            margin-bottom: 20px;
            text-align: center;
            z-index: 10;
            width: 100%;
            padding: 0 20px;
        }
        .footer a, .footer span {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            text-decoration: none;
            margin: 0 10px;
        }
        .footer a:hover {
            color: rgba(255,255,255,0.9);
        }
    </style>
</head>
<body>
    <script src="/js/click.js"></script>

    <div id="xf-MusicPlayer" data-cdnName="https://player.xfyun.club/js"  data-themeColor="xf-sky"></div>
    <script src="https://player.xfyun.club/js/xf-MusicPlayer/js/xf-MusicPlayer.min.js"></script>

    <!-- 导航栏 -->
    <nav class="navbar">
        <div class="logo">
            <?php if (!empty($site['logo'])): ?>
                <img src="<?php echo htmlspecialchars($site['logo']); ?>" alt="Logo">
            <?php else: ?>
                <div class="default-logo"><?php echo htmlspecialchars($site['site_name']); ?></div>
            <?php endif; ?>
        </div>
        <button id="menuToggle" style="display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 5px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="nav-links" id="navLinks">
            <a href="https://github.com/HeLeiawa/lucky-Lottery" target="_blank">开源地址</a>
            <a href="index.php" class="active">首页</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="my_records.php">抽奖记录</a>
            <?php endif; ?>
            <a href="#draw" onclick="document.getElementById('pool_id').focus(); return false;">开始抽奖</a>
        </div>
        <div class="nav-auth" id="navAuth">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span style="color: rgba(255,255,255,0.9); font-size: 14px; margin-right: 15px;">
                    <?php echo htmlspecialchars($_SESSION['user_nickname'] ?? $_SESSION['user_email']); ?>
                </span>
                <a href="logout.php" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 14px;">退出</a>
            <?php else: ?>
                <a href="login.php" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 14px; margin-right: 15px;">登录</a>
                <a href="register.php" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 14px;">注册</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="bubble"></div>
    <div class="glass-card">
        <h1><?php echo htmlspecialchars($site['site_name']); ?></h1>
        <p><?php echo htmlspecialchars($site['site_desc']); ?></p>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="balance-bar">
                <span>余额：</span>
                <span class="amount">¥<?php echo number_format($user_balance, 2); ?></span>
                <a href="recharge.php" style="margin-left: 15px; color: #66CCFF; text-decoration: none;">去充值 →</a>
            </div>

            <p style="color: #a8ff98;">欢迎，<?php echo htmlspecialchars($_SESSION['user_nickname'] ?? $_SESSION['user_email']); ?></p>
            
            <select id="pool_id" onchange="updatePoolInfo()">
                <option value="">请选择奖池</option>
                <?php foreach ($pools as $pool): ?>
                    <option value="<?php echo $pool['id']; ?>" 
                            data-price="<?php echo $pool['draw_price']; ?>"
                            data-guarantee="<?php echo $pool['guarantee_count']; ?>">
                        <?php echo htmlspecialchars($pool['name']); ?>
                        <?php echo $pool['draw_price'] > 0 ? '(¥' . $pool['draw_price'] . ')' : '(仅兑换码)'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="pool-info" id="pool_info" style="display: none;">
                <p id="pool_info_text"></p>
            </div>
            
            <div class="draw-type-tabs" id="draw_type_tabs" style="display: none;">
                <button type="button" class="draw-type-tab active" data-type="code" onclick="switchDrawType('code')">兑换码抽奖</button>
                <button type="button" class="draw-type-tab" data-type="balance" onclick="switchDrawType('balance')">余额抽奖</button>
            </div>
            
            <div id="code_input_group">
                <input type="text" id="code" placeholder="请输入兑换码">
            </div>
            
            <button class="btn" id="drawBtn" onclick="draw()">立即抽奖</button>
            
            <div class="loading" id="loading">抽奖中...</div>
            <div id="result"></div>
            
            <div class="nav-links">
                <a href="my_records.php">我的抽奖记录</a>
                <a href="my_balance.php">余额明细</a>
                <a href="logout.php">退出</a>
            </div>
        <?php else: ?>
            <p style="color: #ffb3b3;">请先登录后抽奖</p>
            <div class="nav-links">
                <a href="login.php">登录</a>
                <a href="register.php">注册</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- GitHub按钮 -->
    <a href="https://github.com/HeLeiawa/lucky-Lottery" target="_blank"
       style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;"
       title="项目开源地址">
        <svg width="40" height="40" viewBox="0 0 16 16" fill="white">
            <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
        </svg>
    </a>

    <div class="footer">
        <p style="margin-bottom: 8px;">Copyright © 2025 - 2026 北沓/醉梦/BlackEgg All Rights Reserved.</p>
        <?php if (!empty($site['icp'])): ?>
            <a href="https://beian.miit.gov.cn/" target="_blank" style="display: inline-flex; align-items: center; gap: 5px;">
                <img src="https://www.dengyuhang.cn/Style/img/icp.svg" alt="ICP" width="14" height="14">
                <span><?php echo htmlspecialchars($site['icp']); ?></span>
            </a>
        <?php endif; ?>
        <span>版本 V2.1</span>
    </div>
    <script>
        let currentDrawType = 'code';
        let isDrawing = false;
        let selectedPool = null;
        function updatePoolInfo() {
            const poolSelect = document.getElementById('pool_id');
            const poolInfo = document.getElementById('pool_info');
            const poolInfoText = document.getElementById('pool_info_text');
            const drawTypeTabs = document.getElementById('draw_type_tabs');
            const codeInputGroup = document.getElementById('code_input_group');
            
            if (poolSelect.value) {
                selectedPool = poolSelect.options[poolSelect.selectedIndex];
                const price = parseFloat(selectedPool.dataset.price);
                const guarantee = parseInt(selectedPool.dataset.guarantee);

                let info = '';
                if (guarantee > 0) {
                    info += '保底：连续' + guarantee + '次未中后必中保底奖品 ';
                }
                if (price > 0) {
                    info += '余额抽奖：¥' + price + '/次';
                } else {
                    info += '仅支持兑换码抽奖';
                }
                
                poolInfoText.textContent = info;
                poolInfo.style.display = 'block';
                
                if (price > 0) {
                    drawTypeTabs.style.display = 'flex';
                    switchDrawType(currentDrawType);
                } else {
                    drawTypeTabs.style.display = 'none';
                    codeInputGroup.style.display = 'block';
                    currentDrawType = 'code';
                }
            } else {
                poolInfo.style.display = 'none';
                drawTypeTabs.style.display = 'none';
                codeInputGroup.style.display = 'block';
            }
        }
        function switchDrawType(type) {
            currentDrawType = type;
            const tabs = document.querySelectorAll('.draw-type-tab');
            tabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.type === type);
            });
            
            const codeInputGroup = document.getElementById('code_input_group');
            const drawBtn = document.getElementById('drawBtn');
            
            if (type === 'code') {
                codeInputGroup.style.display = 'block';
                drawBtn.textContent = '立即抽奖';
            } else {
                codeInputGroup.style.display = 'none';
                const price = selectedPool ? parseFloat(selectedPool.dataset.price) : 0;
                drawBtn.textContent = '余额抽奖 (¥' + price + ')';
            }
        }
        function draw() {
            if (isDrawing) return;
            
            const poolId = document.getElementById('pool_id').value;
            if (!poolId) {
                alert('请先选择奖池');
                return;
            }
            let body = 'pool_id=' + encodeURIComponent(poolId) + '&draw_type=' + currentDrawType;
            
            if (currentDrawType === 'code') {
                const code = document.getElementById('code').value.trim();
                if (!code) {
                    alert('请输入兑换码');
                    return;
                }
                body += '&code=' + encodeURIComponent(code);
            }
            isDrawing = true;
            const btn = document.getElementById('drawBtn');
            const loading = document.getElementById('loading');
            const result = document.getElementById('result');
            
            btn.disabled = true;
            loading.style.display = 'block';
            result.style.display = 'none';
            fetch('api/draw.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(response => response.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('服务器返回格式错误');
                }
            })
            .then(data => {
                loading.style.display = 'none';
                result.style.display = 'block';
                
                if (data.code === 1) {
                    let html = `<h3>恭喜中奖！</h3>`;
                    html += `<p>奖品：${data.prize.name}</p>`;
                    if (data.prize.image) {
                        html += `<img src="${data.prize.image}">`;
                    }
                    result.innerHTML = html;
                    result.style.background = 'rgba(82, 196, 26, 0.2)';
                    
                    if (data.new_balance !== undefined) {
                        document.querySelector('.amount').textContent = '¥' + data.new_balance.toFixed(2);
                    }
                } else {
                    result.innerHTML = `<p style="color: #ff6b6b;">${data.msg || '抽奖失败'}</p>`;
                    result.style.background = 'rgba(255, 255, 255, 0.15)';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                result.style.display = 'block';
                result.innerHTML = `<p style="color: #ff6b6b;">发生错误：${error.message}</p>`;
                result.style.background = 'rgba(255, 77, 79, 0.2)';
            })
            .finally(() => {
                isDrawing = false;
                btn.disabled = false;
            });
        }
        document.getElementById('code')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                draw();
            }
        });

        // 移动端菜单切换
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        const navAuth = document.getElementById('navAuth');

        menuToggle?.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            navAuth.classList.toggle('active');
        });
    </script>
</body>
</html>
