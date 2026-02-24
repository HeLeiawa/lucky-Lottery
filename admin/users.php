<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 新增用户
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $nickname = trim($_POST['nickname']);
    $balance = floatval($_POST['balance']);

    // 检查邮箱是否已存在
    $exists = $pdo->query("SELECT id FROM users WHERE email='$email'")->fetch();
    if ($exists) {
        header('Location: users.php?error=exists');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password, nickname, balance, is_verified, status, created_at) VALUES (?, ?, ?, ?, 1, 1, NOW())");
    if ($stmt->execute([$email, $hash, $nickname, $balance])) {
        header('Location: users.php?success=add');
        exit;
    } else {
        header('Location: users.php?error=1');
        exit;
    }
}

// 编辑用户
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $nickname = $_POST['nickname'];
        $status = isset($_POST['status']) ? 1 : 0;
        $password = $_POST['password'];
        $created_at = $_POST['created_at'] ?? null;

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!empty($created_at)) {
                $stmt = $pdo->prepare("UPDATE users SET nickname=?, status=?, password=?, created_at=? WHERE id=?");
                $stmt->execute([$nickname, $status, $hash, $created_at, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nickname=?, status=?, password=? WHERE id=?");
                $stmt->execute([$nickname, $status, $hash, $user_id]);
            }
        } else {
            if (!empty($created_at)) {
                $stmt = $pdo->prepare("UPDATE users SET nickname=?, status=?, created_at=? WHERE id=?");
                $stmt->execute([$nickname, $status, $created_at, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nickname=?, status=? WHERE id=?");
                $stmt->execute([$nickname, $status, $user_id]);
            }
        }
        header('Location: users.php?success=1');
        exit;
    } elseif (isset($_POST['adjust_balance'])) {
        // 余额调整
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $type = intval($_POST['type']);
        $remark = $_POST['remark'];

        $user = $pdo->query("SELECT * FROM users WHERE id=$user_id")->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $balance_before = $user['balance'];
            if ($type == 2) {
                // 扣款
                if ($user['balance'] < $amount) {
                    header('Location: users.php?error=1');
                    exit;
                }
                $balance_after = $balance_before - $amount;
            } else {
                // 充值
                $balance_after = $balance_before + $amount;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE users SET balance=? WHERE id=?");
                $stmt->execute([$balance_after, $user_id]);

                $stmt = $pdo->prepare("INSERT INTO balance_logs (user_id, type, amount, balance_before, balance_after, remark) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $type, $amount, $balance_before, $balance_after, $remark]);

                $pdo->commit();
                header('Location: users.php?success=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                header('Location: users.php?error=1');
                exit;
            }
        }
    }
}

// 删除用户
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $pdo->exec("DELETE FROM users WHERE id=$delete_id");
    header('Location: users.php');
    exit;
}

// 获取编辑中的用户
$editing_user = null;
if (isset($_GET['edit'])) {
    $editing_user = $pdo->query("SELECT * FROM users WHERE id=" . intval($_GET['edit']))->fetch(PDO::FETCH_ASSOC);
}

// 分页获取
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$where = ['1=1'];
if ($filter_status !== '') $where[] = "status=" . ($filter_status == '1' ? '1' : '0');
$where_sql = implode(' AND ', $where);

$users = $pdo->query("SELECT u.*, COUNT(w.id) as win_count 
                      FROM users u 
                      LEFT JOIN winners w ON u.id=w.user_id 
                      WHERE $where_sql 
                      GROUP BY u.id 
                      ORDER BY u.id DESC LIMIT $per_page OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$total = $pdo->query("SELECT COUNT(*) FROM users WHERE $where_sql")->fetchColumn();

$page_title = '用户管理';
$current_page = 'users';
require 'header.php';
?>
        <h1 class="page-title">用户管理</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">操作成功！</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">操作失败！</div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] == 'exists'): ?>
            <div class="alert alert-error">邮箱已存在！</div>
        <?php endif; ?>

        <div class="glass-card">
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                <form method="get" style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="page" value="1">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>状态筛选</label>
                        <select name="status" onchange="this.form.submit()" style="width: 150px;">
                            <option value="">全部</option>
                            <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>正常</option>
                            <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>封禁</option>
                        </select>
                    </div>
                    <?php if ($filter_status !== ''): ?>
                        <a href="users.php" class="btn btn-secondary" style="margin-bottom: 0;">重置筛选</a>
                    <?php endif; ?>
                </form>
                <button type="button" class="btn" onclick="showAddUserModal()">新增用户</button>
            </div>

            <?php if ($editing_user): ?>
                <div style="margin-bottom: 25px; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                    <h3 style="color: var(--text-primary); margin-bottom: 15px;">编辑用户 - <?php echo htmlspecialchars($editing_user['email']); ?></h3>
                    <form method="post">
                        <input type="hidden" name="user_id" value="<?php echo $editing_user['id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>昵称</label>
                                <input type="text" name="nickname" value="<?php echo htmlspecialchars($editing_user['nickname'] ?? ''); ?>" style="width: 200px;">
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="status" <?php echo $editing_user['status'] ? 'checked' : ''; ?>>
                                    正常状态
                                </label>
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group">
                                <label>注册时间</label>
                                <input type="datetime-local" name="created_at" value="<?php echo date('Y-m-d\TH:i', strtotime($editing_user['created_at'])); ?>" style="width: 250px;">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group">
                                <label>新密码（留空不修改）</label>
                                <input type="password" name="password" placeholder="留空不修改" style="width: 250px;">
                            </div>
                            <div class="form-group">
                                <button type="submit" name="edit_user" class="btn">保存</button>
                                <a href="users.php" class="btn btn-secondary" style="margin-left: 10px;">取消</a>
                            </div>
                        </div>
                    </form>

                    <hr style="border-color: rgba(255,255,255,0.2); margin: 20px 0;">

                    <h4 style="color: var(--text-primary); margin-bottom: 15px;">余额调整 (当前余额: ¥<?php echo $editing_user['balance']; ?>)</h4>
                    <form method="post">
                        <input type="hidden" name="user_id" value="<?php echo $editing_user['id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>类型</label>
                                <select name="type" style="width: 120px;">
                                    <option value="1">充值</option>
                                    <option value="2">扣款</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>金额</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required style="width: 120px;">
                            </div>
                            <div class="form-group">
                                <label>备注</label>
                                <input type="text" name="remark" placeholder="备注" style="width: 200px;">
                            </div>
                            <div class="form-group">
                                <button type="submit" name="adjust_balance" class="btn">确认调整</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>邮箱</th>
                        <th>昵称</th>
                        <th>余额</th>
                        <th>中奖次数</th>
                        <th>验证状态</th>
                        <th>账号状态</th>
                        <th>注册时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['nickname'] ?? '-'); ?></td>
                        <td style="color: #a8ff98; font-weight: bold;">¥<?php echo $u['balance']; ?></td>
                        <td><?php echo $u['win_count']; ?></td>
                        <td>
                            <?php if ($u['is_verified']): ?>
                                <span class="tag tag-green">已验证</span>
                            <?php else: ?>
                                <span class="tag tag-red">未验证</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['status']): ?>
                                <span class="tag tag-green">正常</span>
                            <?php else: ?>
                                <span class="tag tag-red">封禁</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $u['created_at']; ?></td>
                        <td>
                            <a href="users.php?edit=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">编辑</a>
                            <a href="users.php?delete=<?php echo $u['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 13px; margin-left: 5px;" onclick="return confirm('确定删除？该用户的所有数据也会被删除！');">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= ceil($total / $per_page); $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $filter_status !== '' ? '&status=' . $filter_status : ''; ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- 新增用户模态框 -->
        <div id="addUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
            <div style="background: var(--bg-card); border-radius: 12px; padding: 30px; width: 90%; max-width: 450px; border: 1px solid var(--border-color); box-shadow: var(--shadow);">
                <h3 style="color: var(--text-primary); margin-bottom: 20px; font-size: 18px;">新增用户</h3>
                <form method="post">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>邮箱</label>
                        <input type="email" name="email" required placeholder="请输入邮箱">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>密码</label>
                        <input type="password" name="password" required placeholder="请输入密码" minlength="6">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>昵称</label>
                        <input type="text" name="nickname" required placeholder="请输入昵称">
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>初始余额</label>
                        <input type="number" name="balance" step="0.01" min="0" value="0" placeholder="0.00">
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="hideAddUserModal()">取消</button>
                        <button type="submit" name="add_user" class="btn">确认添加</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function showAddUserModal() {
                document.getElementById('addUserModal').style.display = 'flex';
            }

            function hideAddUserModal() {
                document.getElementById('addUserModal').style.display = 'none';
            }

            // 点击模态框外部关闭
            document.getElementById('addUserModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    hideAddUserModal();
                }
            });
        </script>
    </div>
</body>
</html>