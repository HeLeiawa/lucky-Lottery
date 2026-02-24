<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

$pools = $pdo->query("SELECT * FROM pools ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 批量删除已使用
if (isset($_POST['delete_used'])) {
    $pdo->exec("DELETE FROM codes WHERE is_used=1");
    header('Location: codes.php?success=1');
    exit;
}

// 生成兑换码
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    $count = intval($_POST['count']);
    $pool_id = !empty($_POST['pool_id']) ? intval($_POST['pool_id']) : 'NULL';
    $values = [];
    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(substr(md5(uniqid() . rand(0, 9999)), 0, 8));
        $values[] = "($pool_id, '$code')";
    }
    $pdo->exec("INSERT INTO codes (pool_id, code) VALUES " . implode(',', $values));
    header('Location: codes.php?success=1');
    exit;
}

// 单个删除
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $pdo->exec("DELETE FROM codes WHERE id=$delete_id");
    header('Location: codes.php');
    exit;
}

// 分页获取
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_pool = isset($_GET['pool_id']) ? intval($_GET['pool_id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = ['1=1'];
if ($filter_pool) $where[] = "c.pool_id=$filter_pool";
if ($filter_status !== '') $where[] = "c.is_used=" . ($filter_status == '1' ? '1' : '0');
$where_sql = implode(' AND ', $where);

$codes = $pdo->query("SELECT c.*, p.name as pool_name, pr.name as prize_name 
                      FROM codes c 
                      LEFT JOIN pools p ON c.pool_id=p.id 
                      LEFT JOIN winners w ON c.id=w.code_id 
                      LEFT JOIN prizes pr ON w.prize_id=pr.id 
                      WHERE $where_sql 
                      ORDER BY c.id DESC LIMIT $per_page OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

$total = $pdo->query("SELECT COUNT(*) FROM codes c WHERE $where_sql")->fetchColumn();

// 获取未使用的兑换码用于复制
$unused_codes = $pdo->query("SELECT c.code, p.name as pool_name 
                             FROM codes c 
                             LEFT JOIN pools p ON c.pool_id=p.id 
                             WHERE c.is_used=0 
                             ORDER BY c.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$page_title = '兑换码管理';
$current_page = 'codes';
require 'header.php';
?>
        <h1 class="page-title">🎫 兑换码管理</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">操作成功！</div>
        <?php endif; ?>

        <div class="glass-card">
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <form method="post" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>绑定奖池</label>
                        <select name="pool_id" style="width: 180px;">
                            <option value="">通用（不绑定）</option>
                            <?php foreach ($pools as $pool): ?>
                                <option value="<?php echo $pool['id']; ?>"><?php echo htmlspecialchars($pool['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>生成数量</label>
                        <input type="number" name="count" value="10" min="1" max="500" style="width: 100px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" name="generate" class="btn">批量生成</button>
                    </div>
                </form>
                
                <form method="post" style="margin-bottom: 0;">
                    <button type="submit" name="delete_used" class="btn btn-danger" onclick="return confirm('确定删除所有已使用的兑换码？');">删除已使用</button>
                </form>
                
                <button type="button" class="btn btn-secondary" onclick="copyUnusedCodes()">复制未使用(前100)</button>
            </div>

            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <form method="get" style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="page" value="1">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>奖池筛选</label>
                        <select name="pool_id" onchange="this.form.submit()" style="width: 180px;">
                            <option value="">全部</option>
                            <?php foreach ($pools as $pool): ?>
                                <option value="<?php echo $pool['id']; ?>" <?php echo $filter_pool == $pool['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($pool['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>状态筛选</label>
                        <select name="status" onchange="this.form.submit()" style="width: 120px;">
                            <option value="">全部</option>
                            <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>未使用</option>
                            <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>已使用</option>
                        </select>
                    </div>
                    <?php if ($filter_pool || $filter_status !== ''): ?>
                        <a href="codes.php" class="btn btn-secondary" style="margin-bottom: 0;">重置筛选</a>
                    <?php endif; ?>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>兑换码</th>
                        <th>绑定奖池</th>
                        <th>中奖奖品</th>
                        <th>状态</th>
                        <th>使用时间</th>
                        <th>生成时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td>
                            <code style="background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; cursor: pointer;" onclick="copyText('<?php echo $c['code']; ?>')" title="点击复制">
                                <?php echo $c['code']; ?>
                            </code>
                        </td>
                        <td><?php echo $c['pool_name'] ? htmlspecialchars($c['pool_name']) : '<span style="opacity:0.6;">通用</span>'; ?></td>
                        <td><?php echo $c['prize_name'] ? htmlspecialchars($c['prize_name']) : '<span style="opacity:0.6;">-</span>'; ?></td>
                        <td>
                            <?php if ($c['is_used']): ?>
                                <span class="tag tag-red">已使用</span>
                            <?php else: ?>
                                <span class="tag tag-green">未使用</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $c['used_time'] ?: '-'; ?></td>
                        <td><?php echo $c['created_at']; ?></td>
                        <td>
                            <a href="codes.php?delete=<?php echo $c['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 13px;" onclick="return confirm('确定删除？');">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= ceil($total / $per_page); $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $filter_pool ? '&pool_id=' . $filter_pool : ''; ?><?php echo $filter_status !== '' ? '&status=' . $filter_status : ''; ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <textarea id="unusedCodesText" style="position: absolute; left: -9999px;"><?php 
            foreach ($unused_codes as $uc) {
                echo $uc['code'] . " - " . ($uc['pool_name'] ?: '通用') . "\n";
            }
        ?></textarea>

        <script>
            function copyText(text) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('已复制：' + text);
                });
            }

            function copyUnusedCodes() {
                const textarea = document.getElementById('unusedCodesText');
                textarea.select();
                document.execCommand('copy');
                alert('已复制前100个未使用的兑换码！');
            }
        </script>
    </div>
</body>
</html>