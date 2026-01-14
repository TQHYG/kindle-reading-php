<?php
// device.php
require_once __DIR__ . '/func/common.php';

$device_code = $_GET['device_code'] ?? '';
if (!$device_code) die("错误：未检测到设备码");

// 1. 检查是否登录
$user = get_current_user_info();
if (!$user) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('/login.php');
}

// 2. 检查设备码有效性
$d_rows = db_query("SELECT * FROM devices WHERE code = ?", [$device_code]);
if (!$d_rows) die("错误：二维码已过期，请在 Kindle 上刷新");
$device = $d_rows[0];

// 3. 处理命名提交 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['device_name']) ?: '我的 Kindle';
    db_query("UPDATE devices SET user_id = ?, device_name = ?, status = 1 WHERE code = ?", [$user['id'], $name, $device_code]);
    redirect("/device.php?device_code=$device_code");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>设备绑定 - Kykky 阅读数据统计</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <?php include __DIR__ . '/func/header.php'; ?>

    <div class="container" style="max-width: 500px;">
        <?php if ($device['status'] == 1): ?>
            <div class="card" style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 48px; color: var(--success); margin-bottom: 20px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h2 style="margin-bottom: 10px;">绑定成功！</h2>
                <p style="color: var(--text-muted); margin-bottom: 30px;">
                    设备 <strong><?= htmlspecialchars($device['device_name']) ?></strong> 已与您的账号关联。
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a href="/user.php" class="btn btn-primary">前往用户中心</a>
                    <a href="/rank.php" class="btn btn-outline">查看排行榜</a>
                </div>
            </div>

        <?php else: ?>
            <div class="card">
                <h3 style="margin-top: 0;"><i class="fa-solid fa-link"></i> 最后一步：绑定设备</h3>
                <p style="color: var(--text-muted); font-size: 14px;">
                    您好 <strong><?= htmlspecialchars($user['nickname']) ?></strong>，请为您的新 Kindle 起一个好听的名字：
                </p>
                
                <form method="POST" style="margin-top: 20px;">
                    <div style="margin-bottom: 20px;">
                        <input type="text" name="device_name" class="form-control" 
                               placeholder="例如：我的 KPW5 / 卧室的 Kindle" required 
                               style="width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                        确认并完成绑定
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>