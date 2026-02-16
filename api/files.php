<?php
// api/files.php - 用户文件服务 API
require_once __DIR__ . '/../func/common.php';

$user = get_current_user_info();
if (!$user) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => '未登录']);
    exit;
}

$uid = $user['id'];
$storage_path = __DIR__ . "/../storage/{$uid}";
$action = $_GET['action'] ?? '';

// ========== 1. 列出用户的日志文件 ==========
if ($action === 'list') {
    $files = [];
    if (is_dir($storage_path)) {
        foreach (glob("$storage_path/*") as $file) {
            $name = basename($file);
            // 白名单校验文件名
            if (preg_match('/^metrics_reader_\d+$/', $name) || $name === 'history.gz') {
                $files[] = ['name' => $name, 'size' => filesize($file)];
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'files' => $files]);
    exit;
}

// ========== 2. 下载单个日志文件 ==========
if ($action === 'download') {
    $filename = basename($_GET['file'] ?? '');

    // 白名单校验文件名，防止路径穿越
    if (!preg_match('/^metrics_reader_\d+$/', $filename) && $filename !== 'history.gz') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'msg' => '非法文件名']);
        exit;
    }

    $filepath = "$storage_path/$filename";
    if (!file_exists($filepath)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'msg' => '文件不存在']);
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($filepath));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filepath);
    exit;
}

// ========== 3. 打包下载备份 (ZIP) ==========
if ($action === 'download_backup') {
    if (!is_dir($storage_path)) {
        http_response_code(404);
        die("暂无备份数据。");
    }

    $zip_name = "kindle_backup_user_{$uid}_" . date('Ymd') . ".zip";
    $zip_file = tempnam(sys_get_temp_dir(), 'zip');

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = glob($storage_path . "/*");
        $has_files = false;
        foreach ($files as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
                $has_files = true;
            }
        }
        $zip->close();

        if (!$has_files) {
            unlink($zip_file);
            die("文件夹内没有可备份的文件。");
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_file));
        readfile($zip_file);
        unlink($zip_file);
        exit;
    } else {
        die("备份生成失败，请联系管理员。");
    }
}

// 无效 action
header('Content-Type: application/json; charset=utf-8');
http_response_code(400);
echo json_encode(['status' => 'error', 'msg' => 'invalid_action']);
