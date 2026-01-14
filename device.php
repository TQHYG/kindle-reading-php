<?php
// device.php
require_once __DIR__ . '/func/common.php';

$device_code = $_GET['device_code'] ?? '';
if (!$device_code) die("错误：未检测到设备码");

// 1. 检查是否登录
$user = get_current_user_info();
if (!$user) {
    // 没登录，记录当前 URL 并跳转登录
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('/login.php');
}

// 2. 检查设备码有效性
$d_rows = db_query("SELECT * FROM devices WHERE code = ?", [$device_code]);
if (!$d_rows) die("错误：二维码已过期，请在 Kindle 上刷新");
$device = $d_rows[0];

// 如果设备已经绑定完成，直接显示成功
if ($device['status'] == 1) {
    include __DIR__ . '/func/header.php';
    echo "<div style='text-align:center; padding:50px;'>✅ 设备 <b>{$device['device_name']}</b> 已经绑定成功！</div>";
    exit;
}

// 3. 处理命名提交 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['device_name']) ?: '我的 Kindle';
    db_query("UPDATE devices SET user_id = ?, device_name = ?, status = 1 WHERE code = ?", [$user['id'], $name, $device_code]);
    redirect("/device.php?device_code=$device_code");
}

// 4. 显示起名表单
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>绑定新设备</title></head>
<body>
    <?php include __DIR__ . '/func/header.php'; ?>
    <div style="max-width:400px; margin: 50px auto; padding:20px;">
        <h3>最后一步：绑定设备</h3>
        <p>您好 <?= htmlspecialchars($user['nickname']) ?>，正在为您绑定新 Kindle：</p>
        <form method="POST">
            <input type="text" name="device_name" placeholder="为设备起名，如: KPW5" required 
                   style="width:100%; padding:10px; margin:15px 0; border:1px solid #ddd; border-radius:5px;">
            <button type="submit" style="width:100%; padding:10px; background:#2ea44f; color:white; border:none; border-radius:5px; cursor:pointer;">
                确认绑定
            </button>
        </form>
    </div>
</body>
</html>