<?php
$page_title = '中奖记录';
$current_page = 'winners';
require 'header.php';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$winners = $pdo->query("SELECT w.*, cc.code as card_code, p.name as prize_name, p.image as prize_image, u.email as user_email FROM winners w LEFT JOIN card_codes cc ON w.card_code_id=cc.id LEFT JOIN prizes p ON w.prize_id=p.id LEFT JOIN users u ON w.user_id=u.id ORDER BY w.id DESC LIMIT $per_page OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$total = $pdo->query("SELECT COUNT(*) FROM winners")->fetchColumn();
?>
        <h1 class="page-title">🏆 中奖记录</h1>
        <div class="glass-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户邮箱</th>
                        <th>奖品卡密</th>
                        <th>奖品</th>
                        <th>中奖时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($winners as $w): ?>
                    <tr>
                        <td><?php echo $w['id']; ?></td>
                        <td><?php echo htmlspecialchars($w['user_email'] ?: '-'); ?></td>
                        <td><code style="background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px;"><?php echo $w['card_code'] ?: '-'; ?></code></td>
                        <td>
                            <?php if ($w['prize_image']) echo '<img src="../' . $w['prize_image'] . '" class="img-preview" style="margin-right: 10px; vertical-align: middle;">'; ?>
                            <?php echo htmlspecialchars($w['prize_name']); ?>
                        </td>
                        <td><?php echo $w['win_time']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination">
                <?php for ($i = 1; $i <= ceil($total / $per_page); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>
