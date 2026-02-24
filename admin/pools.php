<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 添加/编辑奖池
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_pool'])) {
        $stmt = $pdo->prepare("INSERT INTO pools (name, guarantee_count, draw_price) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['name'], intval($_POST['guarantee_count']), floatval($_POST['draw_price'])]);
        header('Location: pools.php?success=1');
        exit;
    } elseif (isset($_POST['edit_pool'])) {
        $stmt = $pdo->prepare("UPDATE pools SET name=?, guarantee_count=?, draw_price=?, is_active=? WHERE id=?");
        $stmt->execute([$_POST['name'], intval($_POST['guarantee_count']), floatval($_POST['draw_price']), isset($_POST['is_active']) ? 1 : 0, intval($_POST['pool_id'])]);
        header('Location: pools.php?success=1');
        exit;
    }
}

// 删除奖池
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $pdo->exec("DELETE FROM pools WHERE id=$delete_id");
    header('Location: pools.php');
    exit;
}

// 获取编辑中的奖池
$editing_pool = null;
if (isset($_GET['edit'])) {
    $editing_pool = $pdo->query("SELECT * FROM pools WHERE id=" . intval($_GET['edit']))->fetch(PDO::FETCH_ASSOC);
}

// 获取列表
$pools = $pdo->query("SELECT p.*, COUNT(c.id) as code_count FROM pools p LEFT JOIN codes c ON p.id=c.pool_id GROUP BY p.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = '奖池管理';
$current_page = 'pools';
require 'header.php';
?>
        <h1 class="page-title">🎯 奖池管理</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">操作成功！</div>
        <?php endif; ?>

        <div class="glass-card">
            <?php if ($editing_pool): ?>
                <h3 style="color: var(--text-primary); margin-bottom: 15px;">✏️ 编辑奖池</h3>
                <form method="post" style="margin-bottom: 25px; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                    <input type="hidden" name="pool_id" value="<?php echo $editing_pool['id']; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>奖池名称</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($editing_pool['name']); ?>" required style="width: 200px;">
                        </div>
                        <div class="form-group">
                            <label>保底次数 (0关闭)</label>
                            <input type="number" name="guarantee_count" value="<?php echo $editing_pool['guarantee_count']; ?>" min="0" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>抽奖金额 (0仅兑换码)</label>
                            <input type="number" name="draw_price" value="<?php echo $editing_pool['draw_price']; ?>" step="0.01" min="0" style="width: 100px;">
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="is_active" <?php echo $editing_pool['is_active'] ? 'checked' : ''; ?>>
                                启用
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="edit_pool" class="btn">保存</button>
                            <a href="pools.php" class="btn btn-secondary" style="margin-left: 10px;">取消</a>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <form method="post" style="margin-bottom: 25px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>奖池名称</label>
                            <input type="text" name="name" placeholder="请输入奖池名称" required style="width: 200px;">
                        </div>
                        <div class="form-group">
                            <label>保底次数</label>
                            <input type="number" name="guarantee_count" value="0" min="0" placeholder="0" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>抽奖金额</label>
                            <input type="number" name="draw_price" value="0.00" step="0.01" min="0" placeholder="0.00" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_pool" class="btn">添加奖池</button>
                        </div>
                    </div>
                    <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin-top: 10px;">
                        💡 抽奖金额设为0表示仅支持兑换码抽奖；大于0表示支持余额抽奖
                    </p>
                </form>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>名称</th>
                        <th>保底次数</th>
                        <th>抽奖金额</th>
                        <th>兑换码数</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pools as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo $p['guarantee_count'] > 0 ? $p['guarantee_count'] . '次' : '<span style="opacity:0.6;">关闭</span>'; ?></td>
                        <td><?php echo $p['draw_price'] > 0 ? '¥' . $p['draw_price'] : '<span style="opacity:0.6;">仅兑换码</span>'; ?></td>
                        <td><?php echo $p['code_count']; ?></td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="tag tag-green">启用</span>
                            <?php else: ?>
                                <span class="tag tag-red">禁用</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="prizes.php?pool_id=<?php echo $p['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">奖品</a>
                            <a href="pools.php?edit=<?php echo $p['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px; margin-left: 5px;">编辑</a>
                            <a href="pools.php?delete=<?php echo $p['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 13px; margin-left: 5px;" onclick="return confirm('确定删除？');">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>