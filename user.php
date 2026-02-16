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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户中心 - Kykky 阅读数据统计</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<div class="container py-4">
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex align-items-center gap-3">
            <img src="<?= htmlspecialchars($user['avatar']) ?>" class="rounded-circle border" width="80" height="80">
            <div>
                <h4 class="mb-0"><?= htmlspecialchars($user['nickname']) ?></h4>
                <p class="mb-0 text-secondary"><i class="fa-regular fa-id-card me-1"></i> 阅读数据概览</p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <div class="text-secondary text-uppercase small fw-semibold mb-1">今日阅读</div>
                    <div class="stat-value"><?= format_duration($stats['today_seconds']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <div class="text-secondary text-uppercase small fw-semibold mb-1">本月累计</div>
                    <div class="stat-value"><?= format_duration($stats['month_seconds']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-box-archive me-1"></i> 数据备份</h5>
            <p class="text-secondary small">您可以下载备份在服务器上的原始日志文件（history.gz 及 metrics 日志）。</p>
            <a href="/api/files.php?action=download_backup" class="btn btn-primary">
                <i class="fa-solid fa-download me-1"></i> 打包下载备份 (.zip)
            </a>
        </div>
    </div>

    <h5 class="mb-3"><i class="fa-solid fa-tablet-screen-button me-1"></i> 已关联设备</h5>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>设备识别码</th>
                        <th>设备名称</th>
                        <th>有效期至</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_devices)): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-5">暂无绑定设备</td></tr>
                    <?php else: ?>
                        <?php foreach ($my_devices as $dev): 
                            $expiry_date = date('Y-m-d', strtotime($dev['created_at'] . ' +365 days'));
                        ?>
                            <tr>
                                <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($dev['code']) ?></code></td>
                                <td><strong><?= htmlspecialchars($dev['device_name']) ?></strong></td>
                                <td><?= $expiry_date ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-outline-secondary btn-sm" onclick="renameDevice('<?= $dev['code'] ?>', '<?= addslashes($dev['device_name']) ?>')">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> 重命名
                                        </button>
                                        <form method="POST" onsubmit="return confirm('确定要删除此设备吗？')">
                                            <input type="hidden" name="action" value="delete_device">
                                            <input type="hidden" name="device_code" value="<?= $dev['code'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fa-solid fa-trash-can me-1"></i> 删除
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>