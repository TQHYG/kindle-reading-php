<?php
// fastsync.php
require_once __DIR__ . '/func/common.php';

// 1. 必须登录才能扫码授权
$user = get_current_user_info();
if (!$user) {
    // 没登录则跳转登录，带上当前 URL 以便登录后跳回
    header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$uid = $user['id'];
$error = '';
$data = null;

// 2. 获取并解析二维码数据 (参数名为 data)
$raw_b64 = $_GET['data'] ?? '';
if ($raw_b64) {
    try {
        $decoded = json_decode(base64_decode($raw_b64), true);
        if (!$decoded || !isset($decoded['did'], $decoded['today'], $decoded['month'])) {
            throw new Exception("二维码格式不正确或已损坏");
        }

        // 3. 安全校验：检查该设备是否属于当前登录用户
        $device_code = $decoded['did'];
        $check = db_query("SELECT code FROM devices WHERE code = ? AND user_id = ?", [$device_code, $uid]);
        
        if (empty($check)) {
            throw new Exception("安全校验失败：该设备不属于您的账号，无法同步。");
        }

        $data = $decoded;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $error = "未检测到同步数据，请重新从 Kindle 扫描二维码。";
}

// 4. 处理同步提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_sync']) && $data) {
    // 调用 common.php 中已有的更新函数
    if (update_user_stats($uid, (int)$data['today'], (int)$data['month'])) {
        // 同步成功，跳转到用户中心
        header("Location: /user.php?msg=sync_ok");
        exit;
    } else {
        $error = "数据库更新失败，请稍后重试。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>快捷同步确认 - Kykky 阅读数据统计</title>
    <link href="style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<div class="container">
    <div class="card sync-card">
        <h3 class="text-center"><i class="fa-solid fa-qrcode"></i> 快捷同步</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" style="color:var(--danger); background:#ffebe9; padding:15px; border-radius:6px; margin-top:20px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
            <a href="/user.php" class="btn btn-outline w-100 mt-3" style="display:block; text-align:center;">返回用户中心</a>
        <?php elseif ($data): ?>
            <p class="text-muted text-center">检测到来自您的 Kindle 的阅读数据：</p>
            
            <div class="data-preview">
                <div class="data-row">
                    <span class="data-label">今日阅读</span>
                    <span class="data-value"><?= format_duration($data['today']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">本月阅读</span>
                    <span class="data-value"><?= format_duration($data['month']) ?></span>
                </div>
                <div class="data-row" style="font-size: 12px; border-top: 1px solid #ddd; pt-2; margin-top: 10px;">
                    <span class="data-label">设备标识</span>
                    <span class="data-value text-muted" style="font-weight:normal;"><?= htmlspecialchars(substr($data['did'], 0, 8)) ?>...</span>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="confirm_sync" value="1">
                <button type="submit" class="btn btn-primary w-100 py-3" style="font-size: 16px;">
                    <i class="fa-solid fa-check-double"></i> 确认覆盖并同步
                </button>
            </form>
            <p class="text-center mt-3 small text-muted">确认后将覆盖您当前的排行榜数据</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>