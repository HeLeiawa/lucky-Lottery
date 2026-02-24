<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

$pool_id = intval($_GET['pool_id']);
$pool = $pdo->query("SELECT * FROM pools WHERE id=$pool_id")->fetch(PDO::FETCH_ASSOC);
if (!$pool) header('Location: pools.php');

$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$exclude_sql = $edit_id ? " AND id != $edit_id" : "";
$current_total = $pdo->query("SELECT COALESCE(SUM(probability), 0) as total FROM prizes WHERE pool_id=$pool_id $exclude_sql")->fetch(PDO::FETCH_ASSOC)['total'];
$guarantee_total = $pdo->query("SELECT COALESCE(SUM(guarantee_probability), 0) as total FROM prizes WHERE pool_id=$pool_id AND is_guarantee=1 $exclude_sql")->fetch(PDO::FETCH_ASSOC)['total'];

$error = '';
$editing_prize = null;

if ($edit_id) {
    $editing_prize = $pdo->query("SELECT * FROM prizes WHERE id=$edit_id")->fetch(PDO::FETCH_ASSOC);
    if (!$editing_prize) header('Location: prizes.php?pool_id=' . $pool_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_prize']) || isset($_POST['edit_prize'])) {
        $is_edit = isset($_POST['edit_prize']);
        $prize_id = $is_edit ? intval($_POST['prize_id']) : 0;
        $name = $_POST['name'];
        $stock = intval($_POST['stock']);
        $prob = floatval($_POST['probability']);
        $is_guarantee = isset($_POST['is_guarantee']) ? 1 : 0;
        $guarantee_prob = $is_guarantee ? floatval($_POST['guarantee_probability']) : 0;
        $is_card = isset($_POST['is_card']) ? 1 : 0;
        $image = $is_edit ? $editing_prize['image'] : '';

        if ($current_total + $prob > 100) {
            $error = ($is_edit ? "编辑" : "添加") . "失败！当前奖池总概率已达 {$current_total}%，再加 {$prob}% 会超过100%";
        } elseif ($is_guarantee && $guarantee_total + $guarantee_prob > 100) {
            $error = ($is_edit ? "编辑" : "添加") . "失败！当前保底总概率已达 {$guarantee_total}%，再加 {$guarantee_prob}% 会超过100%";
        } else {
            if (!empty($_FILES['image']['name'])) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../assets/uploads/' . $filename);
                $image = 'assets/uploads/' . $filename;
            }

            if ($is_edit) {
                $stmt = $pdo->prepare("UPDATE prizes SET name=?, image=?, stock=?, probability=?, is_guarantee=?, guarantee_probability=?, is_card=? WHERE id=?");
                $stmt->execute([$name, $image, $stock, $prob, $is_guarantee, $guarantee_prob, $is_card, $prize_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO prizes (pool_id, name, image, stock, probability, is_guarantee, guarantee_probability, is_card) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$pool_id, $name, $image, $stock, $prob, $is_guarantee, $guarantee_prob, $is_card]);
            }
            header('Location: prizes.php?pool_id=' . $pool_id . '&success=1');
            exit;
        }
    } elseif (isset($_POST['add_card_codes'])) {
        // 批量添加卡密
        $prize_id = intval($_POST['prize_id']);
        $card_codes = trim($_POST['card_codes']);
        $codes = explode("\n", $card_codes);
        $count = 0;
        
        foreach ($codes as $code) {
            $code = trim($code);
            if (!empty($code)) {
                $stmt = $pdo->prepare("INSERT INTO card_codes (prize_id, code) VALUES (?, ?)");
                $stmt->execute([$prize_id, $code]);
                $count++;
            }
        }
        
        // 更新奖品库存
        $card_count = $pdo->query("SELECT COUNT(*) FROM card_codes WHERE prize_id=$prize_id AND is_used=0")->fetchColumn();
        $pdo->exec("UPDATE prizes SET stock=$card_count WHERE id=$prize_id");
        
        header('Location: prizes.php?pool_id=' . $pool_id . '&success=1&card_added=' . $count);
        exit;
    }
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $pdo->exec("DELETE FROM prizes WHERE id=$delete_id");
    header('Location: prizes.php?pool_id=' . $pool_id);
    exit;
}

if (isset($_GET['view_cards'])) {
    $view_prize_id = intval($_GET['view_cards']);
    $view_prize = $pdo->query("SELECT * FROM prizes WHERE id=$view_prize_id")->fetch(PDO::FETCH_ASSOC);
    $card_page = isset($_GET['card_page']) ? max(1, intval($_GET['card_page'])) : 1;
    $card_per_page = 20;
    $card_offset = ($card_page - 1) * $card_per_page;
    $cards = $pdo->query("SELECT * FROM card_codes WHERE prize_id=$view_prize_id ORDER BY id DESC LIMIT $card_per_page OFFSET $card_offset")->fetchAll(PDO::FETCH_ASSOC);
    $card_total = $pdo->query("SELECT COUNT(*) FROM card_codes WHERE prize_id=$view_prize_id")->fetchColumn();
}

$prizes = $pdo->query("SELECT * FROM prizes WHERE pool_id=$pool_id ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$current_total = $pdo->query("SELECT COALESCE(SUM(probability), 0) as total FROM prizes WHERE pool_id=$pool_id")->fetch(PDO::FETCH_ASSOC)['total'];
$guarantee_total = $pdo->query("SELECT COALESCE(SUM(guarantee_probability), 0) as total FROM prizes WHERE pool_id=$pool_id AND is_guarantee=1")->fetch(PDO::FETCH_ASSOC)['total'];
$thank_you_prob = max(0, 100 - $current_total);

$page_title = '奖品管理';
$current_page = 'pools';
require 'header.php';
?>
        <h1 class="page-title">🎁 奖品管理 - <?php echo htmlspecialchars($pool['name']); ?></h1>

        <p style="margin-bottom: 20px;"><a href="pools.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">← 返回奖池列表</a></p>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                操作成功！
                <?php if (isset($_GET['card_added'])): ?>
                    已添加 <?php echo intval($_GET['card_added']); ?> 张卡密
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($view_prize)): ?>
            <div class="glass-card" style="margin-bottom: 20px;">
                <h3 style="color: var(--text-primary); margin-bottom: 15px;">📋 卡密列表 - <?php echo htmlspecialchars($view_prize['name']); ?></h3>
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 15px;">
                    共 <?php echo $card_total; ?> 张卡密，其中未使用 <?php echo $pdo->query("SELECT COUNT(*) FROM card_codes WHERE prize_id=$view_prize_id AND is_used=0")->fetchColumn(); ?> 张
                </p>
                
                <div style="margin-bottom: 20px;">
                    <form method="post" style="display: flex; gap: 10px; align-items: flex-end;">
                        <input type="hidden" name="prize_id" value="<?php echo $view_prize_id; ?>">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>批量添加卡密（每行一个）</label>
                            <textarea name="card_codes" rows="4" placeholder="请输入卡密，每行一个" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.9);"></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <button type="submit" name="add_card_codes" class="btn">批量添加</button>
                        </div>
                    </form>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>卡密内容</th>
                            <th>状态</th>
                            <th>使用时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards as $card): ?>
                        <tr>
                            <td><?php echo $card['id']; ?></td>
                            <td>
                                <code style="background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px; cursor: pointer;" onclick="copyText('<?php echo addslashes($card['code']); ?>')" title="点击复制">
                                    <?php echo htmlspecialchars(mb_substr($card['code'], 0, 20)); ?><?php echo mb_strlen($card['code']) > 20 ? '...' : ''; ?>
                                </code>
                            </td>
                            <td>
                                <?php if ($card['is_used']): ?>
                                    <span class="tag tag-red">已使用</span>
                                <?php else: ?>
                                    <span class="tag tag-green">未使用</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $card['used_at'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="pagination" style="margin-top: 15px;">
                    <?php for ($i = 1; $i <= ceil($card_total / $card_per_page); $i++): ?>
                        <a href="?pool_id=<?php echo $pool_id; ?>&view_cards=<?php echo $view_prize_id; ?>&card_page=<?php echo $i; ?>" <?php if ($i == $card_page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                
                <p style="margin-top: 15px;">
                    <a href="prizes.php?pool_id=<?php echo $pool_id; ?>" class="btn btn-secondary">返回奖品列表</a>
                </p>
            </div>
            
            <script>
                function copyText(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('已复制到剪贴板');
                    });
                }
            </script>
        <?php else: ?>
            <div class="glass-card">
                <div style="margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 12px;">
                    <p style="color: var(--text-primary); margin-bottom: 8px;">
                        <strong>📊 概率统计：</strong>
                        奖品总概率 <span style="color: <?php echo $current_total > 100 ? '#ff6b6b' : ($current_total == 100 ? '#a8ff98' : '#ffd93d'); ?>; font-weight: bold;"><?php echo $current_total; ?>%</span>
                        / 100%
                        <?php if ($thank_you_prob > 0): ?>
                            <span style="margin-left: 15px; color: rgba(255,255,255,0.8);">(谢谢参与: <strong><?php echo $thank_you_prob; ?>%</strong>)</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($pool['guarantee_count'] > 0): ?>
                        <p style="color: var(--text-primary); margin-top: 8px;">
                            <strong>🎯 保底设置：</strong>
                            保底总概率 <span style="color: <?php echo $guarantee_total > 100 ? '#ff6b6b' : ($guarantee_total == 100 ? '#a8ff98' : '#ffd93d'); ?>; font-weight: bold;"><?php echo $guarantee_total; ?>%</span>
                            / 100%
                            <span style="margin-left: 15px; color: rgba(255,255,255,0.8);">(保底触发: <?php echo $pool['guarantee_count']; ?>次未中后)</span>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($editing_prize): ?>
                    <h3 style="color: var(--text-primary); margin-bottom: 15px;">✏️ 编辑奖品</h3>
                    <form method="post" enctype="multipart/form-data" style="margin-bottom: 25px; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                        <input type="hidden" name="prize_id" value="<?php echo $editing_prize['id']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>奖品名称</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($editing_prize['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>库存</label>
                                <input type="number" name="stock" value="<?php echo $editing_prize['stock']; ?>" required style="width: 100px;">
                            </div>
                            <div class="form-group">
                                <label>普通概率(%)</label>
                                <input type="number" name="probability" value="<?php echo $editing_prize['probability']; ?>" step="0.01" min="0" max="100" required style="width: 100px;">
                            </div>
                            <div class="form-group">
                                <label>图片</label>
                                <input type="file" name="image" accept="image/*" style="width: 180px;">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="is_guarantee" id="edit_is_guarantee" <?php echo $editing_prize['is_guarantee'] ? 'checked' : ''; ?> onchange="toggleGuarantee()">
                                    设为保底奖品
                                </label>
                            </div>
                            <div class="form-group" id="edit_guarantee_prob_group" style="<?php echo !$editing_prize['is_guarantee'] ? 'display:none;' : ''; ?>">
                                <label>保底概率(%)</label>
                                <input type="number" name="guarantee_probability" value="<?php echo $editing_prize['guarantee_probability']; ?>" step="0.01" min="0" max="100" style="width: 120px;">
                            </div>
                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="is_card" id="edit_is_card" <?php echo $editing_prize['is_card'] ? 'checked' : ''; ?>>
                                    卡密奖品
                                </label>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="edit_prize" class="btn">保存</button>
                                <a href="prizes.php?pool_id=<?php echo $pool_id; ?>" class="btn btn-secondary" style="margin-left: 10px;">取消</a>
                            </div>
                        </div>
                        <?php if ($editing_prize['image']): ?>
                            <div style="margin-top: 10px;">
                                <img src="../<?php echo $editing_prize['image']; ?>" class="img-preview" style="max-width: 100px;">
                            </div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" style="margin-bottom: 25px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>奖品名称</label>
                                <input type="text" name="name" placeholder="奖品名称" required>
                            </div>
                            <div class="form-group">
                                <label>库存</label>
                                <input type="number" name="stock" placeholder="库存" required style="width: 100px;">
                            </div>
                            <div class="form-group">
                                <label>普通概率(%)</label>
                                <input type="number" name="probability" placeholder="概率" step="0.01" min="0" max="100" required style="width: 100px;">
                            </div>
                            <div class="form-group">
                                <label>图片</label>
                                <input type="file" name="image" accept="image/*" style="width: 180px;">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="is_guarantee" id="is_guarantee" onchange="toggleGuarantee()">
                                    设为保底奖品
                                </label>
                            </div>
                            <div class="form-group" id="guarantee_prob_group" style="display: none;">
                                <label>保底概率(%)</label>
                                <input type="number" name="guarantee_probability" placeholder="保底概率" step="0.01" min="0" max="100" style="width: 120px;">
                            </div>
                            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="is_card" id="is_card">
                                    卡密奖品
                                </label>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="add_prize" class="btn">添加</button>
                            </div>
                        </div>
                        <?php if ($pool['guarantee_count'] == 0): ?>
                            <p style="color: rgba(255,255,255,0.6); font-size: 12px; margin-top: 10px;">
                                💡 当前奖池未开启保底功能，可在奖池管理中设置保底次数
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>图片</th>
                            <th>名称</th>
                            <th>类型</th>
                            <th>库存</th>
                            <th>普通概率</th>
                            <th>保底</th>
                            <th>保底概率</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prizes as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php if ($p['image']) echo '<img src="../' . $p['image'] . '" class="img-preview">'; ?></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td>
                                <?php if ($p['is_card']): ?>
                                    <span class="tag tag-green">卡密</span>
                                <?php else: ?>
                                    <span class="tag tag-red">普通</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $p['stock']; ?></td>
                            <td><?php echo $p['probability']; ?>%</td>
                            <td>
                                <?php if ($p['is_guarantee']): ?>
                                    <span class="tag tag-green">是</span>
                                <?php else: ?>
                                    <span class="tag tag-red">否</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $p['is_guarantee'] ? $p['guarantee_probability'] . '%' : '-'; ?></td>
                            <td>
                                <a href="prizes.php?pool_id=<?php echo $pool_id; ?>&edit=<?php echo $p['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">编辑</a>
                                <?php if ($p['is_card']): ?>
                                    <a href="prizes.php?pool_id=<?php echo $pool_id; ?>&view_cards=<?php echo $p['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px; margin-left: 5px;">卡密</a>
                                <?php endif; ?>
                                <a href="prizes.php?pool_id=<?php echo $pool_id; ?>&delete=<?php echo $p['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 13px; margin-left: 5px;" onclick="return confirm('确定删除？');">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($prizes)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: rgba(255,255,255,0.6);">暂无奖品</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                function toggleGuarantee() {
                    const isGuarantee = document.getElementById('is_guarantee')?.checked || document.getElementById('edit_is_guarantee')?.checked;
                    const group = document.getElementById('guarantee_prob_group') || document.getElementById('edit_guarantee_prob_group');
                    if (group) {
                        group.style.display = isGuarantee ? 'block' : 'none';
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>