<?php
session_start();
if (!isset($_SESSION['admin_id'])) header('Location: login.php');

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

// 检查并升级logo字段
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM config LIKE 'logo'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `config` ADD COLUMN `logo` varchar(255) DEFAULT NULL AFTER `icp`");
    }
} catch (PDOException $e) {
    // 忽略字段已存在的错误
}

// 检查并升级font字段
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM config LIKE 'font'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `config` ADD COLUMN `font` varchar(255) DEFAULT 'default' AFTER `logo`");
    }
} catch (PDOException $e) {
    // 忽略字段已存在的错误
}

// 获取可用字体
$font_dir = __DIR__ . '/../ttf/';
$available_fonts = ['default' => '默认字体'];
if (is_dir($font_dir)) {
    foreach (glob($font_dir . '*.ttf') as $font_file) {
        $font_name = basename($font_file);
        $available_fonts[$font_name] = $font_name;
    }
}

$site = $pdo->query("SELECT * FROM config WHERE id=1")->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $site_name = $_POST['site_name'];
    $site_desc = $_POST['site_desc'];
    $bg_type = $_POST['bg_type'];
    $bg_color = $_POST['bg_color'] ?? '';
    $bg_url = $_POST['bg_url'] ?? '';
    $icp = $_POST['icp'] ?? '';
    $font = $_POST['font'] ?? 'default';

    // 确保上传目录存在
    $upload_dir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $logo = $site['logo'];
    if (!empty($_FILES['logo']['name'])) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            die('仅支持jpg、png、gif、webp格式的图片');
        }
        $filename = uniqid() . '.' . $ext;
        $upload_path = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
            $logo = 'assets/uploads/' . $filename;
        } else {
            die('Logo上传失败，请检查目录权限');
        }
    }

    $bg_image = $site['bg_image'];
    if (!empty($_FILES['bg_image']['name'])) {
        $ext = pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $upload_path = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $upload_path)) {
            $bg_image = 'assets/uploads/' . $filename;
        } else {
            die('背景图上传失败，请检查目录权限');
        }
    }

    $stmt = $pdo->prepare("UPDATE config SET site_name=?, site_desc=?, bg_type=?, bg_color=?, bg_url=?, bg_image=?, icp=?, logo=?, font=? WHERE id=1");
    $stmt->execute([$site_name, $site_desc, $bg_type, $bg_color, $bg_url, $bg_image, $icp, $logo, $font]);
    
    header('Location: config.php?success=1');
    exit;
}

$page_title = '站点设置';
$current_page = 'config';
require 'header.php';
?>
        <h1 class="page-title">站点设置</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">保存成功！</div>
        <?php endif; ?>

        <div class="glass-card">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>网站名称</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($site['site_name']); ?>" required>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>网站描述</label>
                    <input type="text" name="site_desc" value="<?php echo htmlspecialchars($site['site_desc']); ?>">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>备案号（留空不显示）</label>
                    <input type="text" name="icp" value="<?php echo htmlspecialchars($site['icp'] ?? ''); ?>" placeholder="如：京ICP备12345678号">
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label>网站LOGO（用于导航栏显示）</label>
                    <input type="file" name="logo" accept="image/*">
                    <?php if (!empty($site['logo'])): ?>
                        <div style="margin-top: 10px; padding: 15px; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border-color);">
                            <p style="color: var(--text-secondary); font-size: 12px; margin-bottom: 8px;">当前LOGO预览（导航栏显示）：</p>
                            <img src="<?php echo htmlspecialchars($site['logo']); ?>" style="max-width: 150px; max-height: 50px; border-radius: 8px; border: 1px solid var(--border-color);">
                        </div>
                    <?php endif; ?>
                    <p style="color: var(--text-secondary); font-size: 12px; margin-top: 5px;">建议尺寸：200x60px，支持jpg、png、gif、webp格式，用于首页和后台导航栏左上角显示</p>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label>字体选择（全站字体）</label>
                    <select name="font">
                        <?php foreach ($available_fonts as $font_value => $font_label): ?>
                            <option value="<?php echo $font_value; ?>" <?php echo ($site['font'] ?? 'default') == $font_value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($font_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="color: var(--text-secondary); font-size: 12px; margin-top: 5px;">选择后会应用到整个网站，字体文件位于/ttf目录下</p>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label>背景类型</label>
                    <select name="bg_type" id="bg_type" onchange="toggleBgOptions()">
                        <option value="image" <?php echo $site['bg_type'] == 'image' ? 'selected' : ''; ?>>默认渐变</option>
                        <option value="color" <?php echo $site['bg_type'] == 'color' ? 'selected' : ''; ?>>纯色背景</option>
                        <option value="url" <?php echo $site['bg_type'] == 'url' ? 'selected' : ''; ?>>图片链接</option>
                    </select>
                </div>
                
                <div id="color_option" class="form-group" style="margin-top: 15px; display: <?php echo $site['bg_type'] == 'color' ? 'block' : 'none'; ?>;">
                    <label>背景颜色</label>
                    <input type="color" name="bg_color" value="<?php echo htmlspecialchars($site['bg_color'] ?? '#667eea'); ?>">
                </div>
                
                <div id="url_option" class="form-group" style="margin-top: 15px; display: <?php echo $site['bg_type'] == 'url' ? 'block' : 'none'; ?>;">
                    <label>图片链接</label>
                    <input type="text" name="bg_url" value="<?php echo htmlspecialchars($site['bg_url'] ?? ''); ?>" placeholder="https://...">
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label>上传首页背景图片（用于抽奖页面的背景）</label>
                    <input type="file" name="bg_image" accept="image/*">
                    <p style="color: var(--text-secondary); font-size: 12px; margin-top: 5px;">上传后会覆盖其他背景设置，作为首页抽奖卡片的背景显示</p>
                </div>
                
                <div style="margin-top: 25px;">
                    <button type="submit" class="btn">保存设置</button>
                </div>
            </form>
        </div>

        <script>
            function toggleBgOptions() {
                const type = document.getElementById('bg_type').value;
                document.getElementById('color_option').style.display = type === 'color' ? 'block' : 'none';
                document.getElementById('url_option').style.display = type === 'url' ? 'block' : 'none';
            }
        </script>
    </div>
</body>
</html>