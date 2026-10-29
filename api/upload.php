<?php
/**
 * Kindle / Web 日志上传与统计同步。
 *
 * POST /api/upload.php?today_seconds=..&month_seconds=..
 * multipart: logs[]（metrics_reader_* 与 history.gz）
 * 认证：Kindle Bearer Token 或 Web 会话（Web 写操作需要 X-CSRF-Token）。
 */

require_once __DIR__ . '/bootstrap.php';

api_require_method('POST');

try {
    $auth = require_token_or_session();
    $userId = (int)$auth['uid'];

    $files = normalize_uploaded_files('logs');
    $maxFiles = max(1, (int)kykky_cfg('limits.max_files_per_upload', 100));
    if (count($files) > $maxFiles) {
        legacy_response(['status' => 'error', 'msg' => '一次上传的文件数量过多'], 413);
    }

    $validated = [];
    foreach ($files as $file) {
        validate_uploaded_log($file);
        $validated[] = $file;
    }

    if ($validated !== []) {
        $tmpPaths = array_map(static fn(array $f): string => (string)$f['tmp_name'], $validated);
        $computed = metrics_compute_stats($tmpPaths);
        $todaySeconds = (int)$computed['today_seconds'];
        $monthSeconds = (int)$computed['month_seconds'];
        if (!stats_validate_values($todaySeconds, $monthSeconds)) {
            legacy_response(['status' => 'error', 'msg' => '阅读数据校验未通过'], 400);
        }
        $fileCount = commit_uploaded_files($userId, $validated);
        $source = 'files';
    } else {
        $todayRaw = $_GET['today_seconds'] ?? ($_POST['today_seconds'] ?? null);
        $monthRaw = $_GET['month_seconds'] ?? ($_POST['month_seconds'] ?? null);
        if ($todayRaw === null || $monthRaw === null) {
            legacy_response(['status' => 'error', 'msg' => '缺少日志文件或统计数据'], 400);
        }
        if (!is_numeric($todayRaw) || !is_numeric($monthRaw)) {
            legacy_response(['status' => 'error', 'msg' => '统计数据格式不正确'], 400);
        }
        $todaySeconds = max(0, (int)$todayRaw);
        $monthSeconds = max(0, (int)$monthRaw);
        $fileCount = 0;
        $source = 'reported';
    }

    if (!stats_validate_values($todaySeconds, $monthSeconds)) {
        legacy_response(['status' => 'error', 'msg' => '阅读数据校验未通过'], 400);
    }

    stats_upsert($userId, $todaySeconds, $monthSeconds);

    legacy_response([
        'status' => 'success',
        'msg' => 'Sync completed',
        'db_synced' => true,
        'files_count' => $fileCount,
        'today_seconds' => $todaySeconds,
        'month_seconds' => $monthSeconds,
        'source' => $source,
    ]);
} catch (InvalidArgumentException $e) {
    legacy_response(['status' => 'error', 'msg' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    error_log('[kykky upload] ' . $e->getMessage());
    legacy_response(['status' => 'error', 'msg' => '服务器保存失败，请稍后重试'], 500);
}
