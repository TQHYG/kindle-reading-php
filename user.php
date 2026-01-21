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

// 处理数据下载
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $uid = $user['id'];
    $user_storage = __DIR__ . "/storage/{$uid}";

    if (!is_dir($user_storage)) {
        die("暂无备份数据。");
    }

    $zip_name = "kindle_backup_user_{$uid}_" . date('Ymd') . ".zip";
    $zip_file = tempnam(sys_get_temp_dir(), 'zip'); // 创建临时文件

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = glob($user_storage . "/*");
        $has_files = false;
        foreach ($files as $file) {
            if (is_file($file)) {
                // 仅添加文件，不带绝对路径层级
                $zip->addFile($file, basename($file));
                $has_files = true;
            }
        }
        $zip->close();

        if (!$has_files) {
            die("文件夹内没有可备份的文件。");
        }

        // 发送 Headers 触发浏览器下载
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_file));
        readfile($zip_file);
        unlink($zip_file); // 下载完删除临时 zip
        exit;
    } else {
        die("备份生成失败，请联系管理员。");
    }
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
    <title>用户中心 - Kykky 阅读数据统计</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<div class="container">
    <div class="card">
        <div class="user-profile">
            <img src="<?= htmlspecialchars($user['avatar']) ?>" class="profile-avatar">
            <div class="profile-info">
                <h2 style="margin:0; font-size:1.5rem;"><?= htmlspecialchars($user['nickname']) ?></h2>
                <p style="margin:5px 0 0; color:var(--text-muted);"><i class="fa-regular fa-id-card"></i> 阅读数据概览</p>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">今日阅读</span>
            <div class="stat-value"><?= format_duration($stats['today_seconds']) ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-label">本月累计</span>
            <div class="stat-value"><?= format_duration($stats['month_seconds']) ?></div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-box-archive"></i> 数据备份</h3>
        <p class="text-muted" style="font-size: 14px;">您可以下载备份在服务器上的原始日志文件（history.gz 及 metrics 日志）。</p>
        <a href="user.php?action=download_backup" class="btn btn-primary">
            <i class="fa-solid fa-download"></i> 打包下载备份 (.zip)
        </a>
    </div>

    <h3 style="margin: 30px 0 15px;"><i class="fa-solid fa-tablet-screen-button"></i> 已关联设备</h3>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
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
                        <tr><td colspan="4" style="text-align:center; padding:60px; color:var(--text-muted);">暂无绑定设备</td></tr>
                    <?php else: ?>
                        <?php foreach ($my_devices as $dev): 
                            $expiry_date = date('Y-m-d', strtotime($dev['created_at'] . ' +365 days'));
                        ?>
                            <tr>
                                <td><span class="code-badge"><?= htmlspecialchars($dev['code']) ?></span></td>
                                <td><strong><?= htmlspecialchars($dev['device_name']) ?></strong></td>
                                <td><?= $expiry_date ?></td>
                                <td style="text-align:right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                        <button class="btn btn-outline" onclick="renameDevice('<?= $dev['code'] ?>', '<?= addslashes($dev['device_name']) ?>')">
                                            <i class="fa-solid fa-pen-to-square"></i> 重命名
                                        </button>
                                        <form method="POST" onsubmit="return confirm('确定要删除此设备吗？')">
                                            <input type="hidden" name="action" value="delete_device">
                                            <input type="hidden" name="device_code" value="<?= $dev['code'] ?>">
                                            <button type="submit" class="btn btn-outline">
                                                <i class="fa-solid fa-trash-can"></i> 删除
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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