<?php
// 数据库升级脚本 - 添加logo字段
require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);

    // 检查logo字段是否存在
    $stmt = $pdo->query("SHOW COLUMNS FROM config LIKE 'logo'");
    if ($stmt->rowCount() === 0) {
        // 添加logo字段
        $pdo->exec("ALTER TABLE `config` ADD COLUMN `logo` varchar(255) DEFAULT NULL AFTER `icp`");
        echo "Logo字段添加成功<br>";
    } else {
        echo "Logo字段已存在<br>";
    }

    echo "数据库升级完成！";
} catch (PDOException $e) {
    echo "数据库升级失败：" . $e->getMessage();
}
?>
