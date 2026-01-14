<?php
// user.php
require_once __DIR__ . '/func/common.php';

$user = get_current_user_info();
if (!$user) {
    redirect('/login.php?from=/user.php');
}

// --- 处理设备操作 (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $device_code = $_POST['device_code'] ?? ''; 

    // 权限校验：确保该 code 确实属于当前用户
    $check = db_query("SELECT status FROM devices WHERE code = ? AND user_id = ?", [$device_code, $user['id']]);
    
    if ($check) {
        if ($action === 'rename_device') {
            $new_name = trim($_POST['new_name']);
            if (!empty($new_name)) {
                db_query("UPDATE devices SET device_name = ? WHERE code = ?", [$new_name, $device_code]);
            }
        } elseif ($action === 'delete_device') {
            db_query("DELETE FROM devices WHERE code = ?", [$device_code]);
        }
    }
    redirect('/user.php');
}

// 获取统计数据
$stats_row = db_query("SELECT * FROM stats WHERE user_id = ?", [$user['id']]);
$stats = $stats_row[0] ?? ['today_seconds' => 0, 'month_seconds' => 0];

// 获取设备列表
$my_devices = db_query("SELECT * FROM devices WHERE user_id = ? AND status = 1 ORDER BY created_at DESC", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户中心 - Kykky Kindle</title>
    <style>
        /* 样式复用之前的，仅针对识别码做微调 */
        body { font-family: -apple-system, sans-serif; background: #f6f8fa; margin: 0; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .card { background: #fff; border: 1px solid #d0d7de; border-radius: 6px; padding: 24px; margin-bottom: 24px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border: 1px solid #d0d7de; border-radius: 6px; padding: 16px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: 600; color: #0969da; }
        .device-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d0d7de; border-radius: 6px; }
        .device-table th, .device-table td { padding: 12px 16px; border-bottom: 1px solid #d0d7de; font-size: 14px; text-align: left; }
        /* 识别码样式：类似代码块 */
        .code-badge { font-family: "SFMono-Regular", Consolas, monospace; background: #eff1f3; padding: 3px 6px; border-radius: 4px; color: #444; font-size: 13px; border: 1px solid #e1e4e8; }
        .btn { cursor: pointer; border: 1px solid #d0d7de; background: #f6f8fa; padding: 5px 12px; border-radius: 6px; font-size: 12px; }
        .btn-danger { color: #cf222e; }
    </style>
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<div class="container">
    <div class="card">
        <div style="display:flex; align-items:center; gap:15px;">
            <img src="<?= htmlspecialchars($user['avatar']) ?>" width="60" style="border-radius:50%;">
            <div>
                <h2 style="margin:0;"><?= htmlspecialchars($user['nickname']) ?></h2>
                <p style="margin:5px 0 0; color:#57606a;">阅读数据概览</p>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div style="color:#57606a; font-size:14px;">今日阅读</div>
            <div class="stat-value"><?= format_duration($stats['today_seconds']) ?></div>
        </div>
        <div class="stat-card">
            <div style="color:#57606a; font-size:14px;">本月累计</div>
            <div class="stat-value"><?= format_duration($stats['month_seconds']) ?></div>
        </div>
    </div>

    <h3>已关联设备</h3>
    <table class="device-table">
        <thead>
            <tr>
                <th>设备识别码</th>
                <th>设备名称</th>
                <th>有效期至</th>
                <th style="text-align:right;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($my_devices)): ?>
                <tr><td colspan="4" style="text-align:center; padding:40px; color:#666;">暂无绑定设备</td></tr>
            <?php else: ?>
                <?php foreach ($my_devices as $dev): 
                    $expiry_date = date('Y-m-d', strtotime($dev['created_at'] . ' +365 days'));
                ?>
                    <tr>
                        <td><span class="code-badge"><?= htmlspecialchars($dev['code']) ?></span></td>
                        <td><strong><?= htmlspecialchars($dev['device_name']) ?></strong></td>
                        <td><?= $expiry_date ?></td>
                        <td style="text-align:right;">
                            <button class="btn" onclick="renameDevice('<?= $dev['code'] ?>', '<?= addslashes($dev['device_name']) ?>')">重命名</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('确定要删除此设备吗？')">
                                <input type="hidden" name="action" value="delete_device">
                                <input type="hidden" name="device_code" value="<?= $dev['code'] ?>">
                                <button type="submit" class="btn btn-danger">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function renameDevice(code, oldName) {
    const newName = prompt("请输入新的设备名称：", oldName);
    if (newName && newName.trim() !== "" && newName !== oldName) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="rename_device">
            <input type="hidden" name="device_code" value="${code}">
            <input type="hidden" name="new_name" value="${newName.trim()}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>