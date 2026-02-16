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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <?php include __DIR__ . '/func/header.php'; ?>

    <div class="container" style="max-width: 500px;">
        <?php if ($device['status'] == 1): ?>
            <div class="card shadow-sm text-center">
                <div class="card-body py-5">
                    <div class="text-success mb-3" style="font-size: 48px;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2 class="mb-2">绑定成功！</h2>
                    <p class="text-secondary mb-4">
                        设备 <strong><?= htmlspecialchars($device['device_name']) ?></strong> 已与您的账号关联。
                    </p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="/user.php" class="btn btn-primary">前往用户中心</a>
                        <a href="/rank.php" class="btn btn-outline-primary">查看排行榜</a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="mb-2"><i class="fa-solid fa-link"></i> 最后一步：绑定设备</h3>
                    <p class="text-secondary small">
                        您好 <strong><?= htmlspecialchars($user['nickname']) ?></strong>，请为您的新 Kindle 起一个好听的名字：
                    </p>
                    
                    <form method="POST" class="mt-3">
                        <div class="mb-3">
                            <input type="text" name="device_name" class="form-control" 
                                   placeholder="例如：我的 KPW5 / 卧室的 Kindle" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            确认并完成绑定
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>