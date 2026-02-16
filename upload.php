<?php
require_once __DIR__ . '/func/common.php';

// 1. 权限与频率限制
$user = get_current_user_info();
if (!$user) redirect('/login.php?from=/upload.php');

$uid = $user['id'];
$storage_path = __DIR__ . "/storage/{$uid}";

// 2. 处理后端数据提交 (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_stats') {
    // 频率限制：每 1 分钟允许上传一次
    if (isset($_SESSION['last_manual_sync']) && (time() - $_SESSION['last_manual_sync'] < 60)) {
        echo json_encode(['status' => 'error', 'msg' => '同步太频繁，请稍后再试']);
        exit;
    }

    $today_sec = (int)$_POST['today_sec'];
    $month_sec = (int)$_POST['month_sec'];

    // 物理文件同步：清理并保存文件
    if (!is_dir($storage_path)) mkdir($storage_path, 0755, true);
    array_map('unlink', glob("$storage_path/*")); // 清空旧文件

    if (isset($_FILES['logs']['name'])) {

        foreach ($_FILES['logs']['name'] as $key => $name) {
            $filename = basename($name);

            $is_metrics = preg_match('/^metrics_reader_\d+$/', $filename);
            $is_history = ($filename === 'history.gz');

            if (!$is_metrics && !$is_history) {
                echo json_encode([
                    'status' => 'error',
                    'msg' => '检测到非法文件上传: ' . $filename
                ]);
                exit;
            }
        }

        // 所有文件都合法后再执行保存（保证原子性）
        foreach ($_FILES['logs']['name'] as $key => $name) {
            $filename = basename($name);
            $tmp = $_FILES['logs']['tmp_name'][$key];
            move_uploaded_file($tmp, "$storage_path/$filename");
        }
    }



    // 更新数据库
    if (update_user_stats($uid, $today_sec, $month_sec)) {
        $_SESSION['last_manual_sync'] = time();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => '数据异常校验未通过']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>数据更新 - Kykky 阅读数据统计</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/style.css">
</head>
<body>
<?php include __DIR__ . '/func/header.php'; ?>

<div class="container" style="max-width: 600px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="mb-3"><i class="fa-solid fa-cloud-arrow-up"></i> 手动同步阅读日志</h3>
            
            <div class="alert alert-warning">
                <i class="fa-solid fa-triangle-exclamation"></i> 
                <strong>注意：</strong>上传将以本次数据为准更新排行榜，并覆盖旧的日志备份。
            </div>

            <div id="drop-zone" class="upload-zone">
                <input type="file" id="file-input" webkitdirectory directory multiple hidden>
                <div class="icon-box">
                    <i class="fa-solid fa-folder-tree"></i>
                </div>
                <h4>拖拽 log 文件夹或点击选择</h4>
                <p class="text-secondary mb-0">请选择包含 <code>history.gz</code> 的 Kindle 日志目录</p>
                <div id="file-list" class="mt-2 text-success fw-bold"></div>
            </div>

            <button id="upload-btn" class="btn btn-primary w-100 mt-3 py-2" disabled>
                开始解析并同步
            </button>
        </div>
    </div>
</div>

<script>
let selectedFiles = [];
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const uploadBtn = document.getElementById('upload-btn');

dropZone.onclick = () => fileInput.click();
fileInput.onchange = (e) => handleFiles(e.target.files);

dropZone.ondrop = async (e) => {
    e.preventDefault();
    dropZone.classList.remove('hover');
    
    const items = e.dataTransfer.items;
    const allFiles = [];

    // 递归读取文件夹中的所有文件
    const traverse = async (entry) => {
        if (entry.isFile) {
            const file = await new Promise(res => entry.file(res));
            allFiles.push(file);
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const entries = await new Promise(res => reader.readEntries(res));
            for (const e of entries) await traverse(e);
        }
    };

    for (const item of items) {
        const entry = item.webkitGetAsEntry();
        if (entry) await traverse(entry);
    }
    
    handleFiles(allFiles);
};

async function handleFiles(files) {
    const fileArray = Array.from(files);
    const validFiles = [];
    const invalidFiles = [];

    // 1. 严格校验：只允许特定文件名
    fileArray.forEach(file => {
        // 排除文件夹中的隐藏文件或无关文件
        const name = file.name;
        if (name.startsWith('metrics_reader_') || name === 'history.gz') {
            validFiles.push(file);
        } else if (!name.startsWith('.')) {
            invalidFiles.push(name);
        }
    });

    if (invalidFiles.length > 0) {
        alert("文件夹内包含非法文件: " + invalidFiles.slice(0, 3).join(', ') + "等。请确保只上传 log 目录下的内容。");
        selectedFiles = [];
        uploadBtn.disabled = true;
        return;
    }

    selectedFiles = validFiles;
    document.getElementById('file-list').innerHTML = `已识别 ${selectedFiles.length} 个合法日志文件`;
    uploadBtn.disabled = selectedFiles.length === 0;
}

uploadBtn.onclick = async () => {
    if (!confirm("确认上传并覆盖现有数据？")) return;

    uploadBtn.disabled = true;
    uploadBtn.innerText = "正在本地解析日志...";

    let todaySec = 0;
    let monthSec = 0;

    const now = new Date();
    const todayStr = now.toISOString().split('T')[0];
    const monthPrefix = `metrics_reader_${now.getFullYear().toString().slice(-2)}${(now.getMonth() + 1).toString().padStart(2, '0')}`;

    try {
        for (const file of selectedFiles) {
            if (!file.name.startsWith(monthPrefix)) continue;

            const text = await file.text();
            const lines = text.split('\n');
            const regex = /metric_generic,(\d+),.*,com\.lab126\.booklet\.reader\.activeDuration,(\d+)/;

            for (const line of lines) {
                const match = line.match(regex);
                if (!match) continue;

                const endTs = parseInt(match[1]) * 1000;
                const durationSec = Math.floor(parseInt(match[2]) / 1000);

                if (durationSec <= 0) continue;

                const date = new Date(endTs);
                const dateStr = date.toISOString().split('T')[0];

                if (dateStr === todayStr) todaySec += durationSec;
                monthSec += durationSec;
            }
        }

        const fd = new FormData();
        fd.append('action', 'sync_stats');
        fd.append('today_sec', todaySec);
        fd.append('month_sec', monthSec);
        selectedFiles.forEach(f => fd.append('logs[]', f));

        uploadBtn.innerText = "正在同步至服务器...";

        try {
            const res = await fetch('upload.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.status === 'success') {
                alert("同步成功！");
                location.href = 'user.php';
                return;
            }

            alert("同步失败：" + json.msg);
            uploadBtn.disabled = false;
            uploadBtn.innerText = "重新同步";

        } catch (e) {
            alert("网络或解析错误：" + e.message);
            uploadBtn.disabled = false;
            uploadBtn.innerText = "重新同步";
        }


    } catch (e) {
        alert("解析错误：" + e.message);
        uploadBtn.disabled = false;
        uploadBtn.innerText = "重新同步";
    }
};

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
