<?php
/**
 * 用户日志文件 API（Web 会话认证）。
 *
 * GET /api/files.php?action=list
 * GET /api/files.php?action=download&file=metrics_reader_2610
 * GET /api/files.php?action=download_backup
 */

require_once __DIR__ . '/bootstrap.php';

$session = current_session();
if ($session === null) {
    legacy_response(['status' => 'error', 'msg' => '未登录'], 401);
}
$userId = (int)$session['user']['id'];
$action = $_GET['action'] ?? '';

if ($action === 'list') {
    legacy_response([
        'status' => 'success',
        'files' => storage_list_files($userId),
    ]);
}

if ($action === 'download') {
    $name = $_GET['file'] ?? '';
    if (!is_string($name)) {
        legacy_response(['status' => 'error', 'msg' => '非法文件名'], 400);
    }
    try {
        $path = storage_file_path($userId, $name);
    } catch (InvalidArgumentException $e) {
        legacy_response(['status' => 'error', 'msg' => $e->getMessage()], 400);
    } catch (RuntimeException $e) {
        legacy_response(['status' => 'error', 'msg' => $e->getMessage()], 404);
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($action === 'download_backup') {
    $files = storage_list_files($userId);
    if ($files === []) {
        legacy_response(['status' => 'error', 'msg' => '暂无可备份的日志文件'], 404);
    }
    if (!class_exists('ZipArchive')) {
        legacy_response(['status' => 'error', 'msg' => '服务器未安装 PHP zip 扩展'], 500);
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'kykky_zip_');
    if ($zipPath === false) {
        legacy_response(['status' => 'error', 'msg' => '无法创建临时备份文件'], 500);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($zipPath);
        legacy_response(['status' => 'error', 'msg' => '无法创建备份压缩包'], 500);
    }

    foreach ($files as $file) {
        $path = storage_user_dir($userId) . '/' . $file['name'];
        if (is_file($path)) {
            $zip->addFile($path, $file['name']);
        }
    }
    $zip->close();

    $downloadName = 'kindle_backup_user_' . $userId . '_' . date('Ymd') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($zipPath));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Cache-Control: no-store');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

legacy_response(['status' => 'error', 'msg' => 'invalid_action'], 400);
