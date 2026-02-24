<?php
$page_title = '数据概览';
$current_page = 'index';
require 'header.php';

// 统计数据
$stats = [
    'codes' => $pdo->query("SELECT COUNT(*) FROM codes")->fetchColumn(),
    'used' => $pdo->query("SELECT COUNT(*) FROM codes WHERE is_used=1")->fetchColumn(),
    'winners' => $pdo->query("SELECT COUNT(*) FROM winners")->fetchColumn(),
    'prizes' => $pdo->query("SELECT COUNT(*) FROM prizes")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'recharge_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM balance_logs WHERE type=1")->fetchColumn()
];

// 获取统计数据（按日、月、年）
$period = $_GET['period'] ?? 'day';

if ($period == 'day') {
    // 最近7天的数据
    $user_stats = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $recharge_stats = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM balance_logs WHERE type=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $recharge_amount_stats = $pdo->query("SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as amount FROM balance_logs WHERE type=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($period == 'month') {
    // 最近6个月的数据
    $user_stats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $recharge_stats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count FROM balance_logs WHERE type=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $recharge_amount_stats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COALESCE(SUM(amount), 0) as amount FROM balance_logs WHERE type=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // 最近1年的数据（按月）
    $user_stats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $recharge_stats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count FROM balance_logs WHERE type=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $recharge_amount_stats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COALESCE(SUM(amount), 0) as amount FROM balance_logs WHERE type=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
}

// 确保数据是数组且不为空
$user_stats = is_array($user_stats) ? $user_stats : [];
$recharge_stats = is_array($recharge_stats) ? $recharge_stats : [];
$recharge_amount_stats = is_array($recharge_amount_stats) ? $recharge_amount_stats : [];
?>
        <h1 class="page-title">数据概览</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['codes']; ?></h3>
                <p>总兑换码</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['used']; ?></h3>
                <p>已使用</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['winners']; ?></h3>
                <p>中奖次数</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['prizes']; ?></h3>
                <p>奖品总数</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['users']; ?></h3>
                <p>注册用户</p>
            </div>
            <div class="stat-card">
                <h3>¥<?php echo number_format($stats['recharge_amount'], 2); ?></h3>
                <p>充值总额</p>
            </div>
        </div>

        <div class="glass-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: var(--text-primary);">用户注册趋势</h3>
                <div style="display: flex; gap: 10px;">
                    <a href="?period=day" class="btn <?php echo $period == 'day' ? '' : 'btn-secondary'; ?>" style="padding: 8px 16px; font-size: 13px;">日</a>
                    <a href="?period=month" class="btn <?php echo $period == 'month' ? '' : 'btn-secondary'; ?>" style="padding: 8px 16px; font-size: 13px;">月</a>
                    <a href="?period=year" class="btn <?php echo $period == 'year' ? '' : 'btn-secondary'; ?>" style="padding: 8px 16px; font-size: 13px;">年</a>
                </div>
            </div>
            <div style="height: 300px;">
                <canvas id="userChart"></canvas>
            </div>
        </div>

        <div class="glass-card">
            <h3 style="color: var(--text-primary); margin-bottom: 20px;">充值统计</h3>
            <div style="height: 300px;">
                <canvas id="rechargeChart"></canvas>
            </div>
        </div>

        <div class="glass-card">
            <h3 style="color: var(--text-primary); margin-bottom: 15px;">快速操作</h3>
            <a href="codes.php" class="btn">生成兑换码</a>
            <a href="pools.php" class="btn" style="margin-left: 10px;">管理奖池</a>
            <a href="config.php" class="btn btn-secondary" style="margin-left: 10px;">站点设置</a>
            <a href="change_password.php" class="btn btn-secondary" style="margin-left: 10px;">修改密码</a>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            const userStats = <?php echo json_encode($user_stats, JSON_UNESCAPED_UNICODE); ?>;
            const rechargeStats = <?php echo json_encode($recharge_stats, JSON_UNESCAPED_UNICODE); ?>;
            const rechargeAmountStats = <?php echo json_encode($recharge_amount_stats, JSON_UNESCAPED_UNICODE); ?>;

            console.log('用户注册数据:', userStats);
            console.log('充值次数数据:', rechargeStats);
            console.log('充值金额数据:', rechargeAmountStats);

            // 确保数据是数组
            const safeUserStats = Array.isArray(userStats) ? userStats : [];
            const safeRechargeStats = Array.isArray(rechargeStats) ? rechargeStats : [];
            const safeRechargeAmountStats = Array.isArray(rechargeAmountStats) ? rechargeAmountStats : [];

            const labels = safeUserStats.map(s => s.date);
            const userData = safeUserStats.map(s => s.count || 0);
            const rechargeCountData = safeRechargeStats.map(s => s.count || 0);
            const rechargeAmountData = safeRechargeAmountStats.map(s => s.amount || 0);

            console.log('处理后的labels:', labels);
            console.log('处理后的userData:', userData);

            // 如果没有数据，显示提示
            if (userData.length === 0) {
                document.getElementById('userChart').parentElement.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding-top: 100px;">暂无用户注册数据</p>';
            }

            // 用户注册图表
            if (userData.length > 0) {
                new Chart(document.getElementById('userChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '注册用户数',
                        data: userData,
                        borderColor: '#66CCFF',
                        backgroundColor: 'rgba(102, 204, 255, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#e0e0e0'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#a0a0a0' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        y: {
                            ticks: { color: '#a0a0a0' },
                            grid: { color: 'rgba(255,255,255,0.1)' },
                            beginAtZero: true
                        }
                    }
                }
                });
            }

            // 充值统计图表
            if (rechargeCountData.length > 0) {
                new Chart(document.getElementById('rechargeChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '充值次数',
                        data: rechargeCountData,
                        borderColor: '#a8ff98',
                        backgroundColor: 'rgba(168, 255, 152, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: '充值金额(元)',
                        data: rechargeAmountData,
                        borderColor: '#ffb3b3',
                        backgroundColor: 'rgba(255, 179, 179, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#e0e0e0'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#a0a0a0' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        y: {
                            ticks: { color: '#a0a0a0' },
                            grid: { color: 'rgba(255,255,255,0.1)' },
                            beginAtZero: true
                        }
                    }
                }
                });
            }

            // 如果没有充值数据，显示提示
            if (rechargeCountData.length === 0) {
                document.getElementById('rechargeChart').parentElement.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding-top: 100px;">暂无充值数据</p>';
            }
        </script>
    </div>
</body>
</html>