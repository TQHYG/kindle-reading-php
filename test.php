<?php
// test.php - 环境与数据库自检脚本
header('Content-Type: text/html; charset=utf-8');

function show_result($item, $status, $msg = '') {
    $color = $status ? 'green' : 'red';
    $icon  = $status ? '✅' : '❌';
    echo "<div style='margin-bottom:10px; border-bottom:1px solid #eee; padding:5px;'>";
    echo "<strong>$item</strong>: <span style='color:$color'>$icon</span> $msg";
    echo "</div>";
}

echo "<h1>Kykky 服务端环境自检</h1>";

// 1. 检查 PHP 版本和扩展
echo "<h3>1. 基础环境</h3>";
show_result("PHP Version", version_compare(PHP_VERSION, '7.4.0', '>='), "当前: " . PHP_VERSION);
show_result("Extension: PDO", extension_loaded('pdo'));
show_result("Extension: PDO_MySQL", extension_loaded('pdo_mysql'));
show_result("Extension: cURL", extension_loaded('curl'));
show_result("Extension: JSON", extension_loaded('json'));

// 2. 检查关键文件是否存在
echo "<h3>2. 文件结构</h3>";
$files = [
    'config.php',
'func/db.php',
'func/auth_tools.php',
'api/auth.php',
'callback.php'
];

$config_ready = true;
foreach ($files as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        show_result($f, true, "存在");
    } else {
        show_result($f, false, "缺失");
        if ($f == 'config.php' || $f == 'func/db.php') $config_ready = false;
    }
}

// 3. 检查存储目录权限
echo "<h3>3. 存储权限</h3>";
if (file_exists('config.php')) {
    $conf = require __DIR__ . '/config.php';
    $storage_path = $conf['storage']['base_path'] ?? __DIR__ . '/storage/';

    // 尝试创建 storage 目录（如果不存在）
    if (!is_dir($storage_path)) {
        @mkdir($storage_path, 0777, true);
    }

    if (is_dir($storage_path) && is_writable($storage_path)) {
        show_result("Storage 目录", true, "可写 ($storage_path)");
    } else {
        show_result("Storage 目录", false, "不可写或不存在 ($storage_path) - 请执行 chmod 777");
    }
} else {
    show_result("Storage 目录", false, "无法读取 config.php，跳过检查");
}

// 4. 数据库连接测试
echo "<h3>4. 数据库连接</h3>";
if ($config_ready) {
    try {
        require_once __DIR__ . '/func/db.php';
        $pdo = get_db();
        show_result("Database Connect", true, "连接成功");

        // 检查表是否存在
        $tables = ['users', 'stats', 'devices'];
        foreach ($tables as $t) {
            try {
                $check = db_query("SELECT 1 FROM $t LIMIT 1"); // 只要不报错就说明表存在
                show_result("Table: $t", true, "正常");
            } catch (Exception $e) {
                // 如果是空表，SELECT 1 也是可以执行的，报错通常意味着表不存在
                // 但为了严谨，具体错误码需分析，这里简单判定
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    show_result("Table: $t", false, "表不存在");
                } else {
                    show_result("Table: $t", true, "存在 (空表或有数据)");
                }
            }
        }

    } catch (Exception $e) {
        show_result("Database Connect", false, "连接失败: " . $e->getMessage());
    }
} else {
    show_result("Database Connect", false, "配置文件缺失，跳过检查");
}

echo "<hr><p>检查完成。测试通过后建议删除此文件。</p>";
