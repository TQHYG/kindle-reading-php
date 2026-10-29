<?php
/**
 * Kindle / Web 日志上传与统计同步。
 *
 * POST /api/upload.php?today_seconds=..&week_seconds=..&month_seconds=..&total_seconds=..
 * multipart: logs[]（metrics_reader_* 与 history.gz）
 * 认证：Kindle Bearer Token 或 Web 会话（Web 写操作需要 X-CSRF-Token）。
 *
 * 兼容：旧客户端只传 today_seconds/month_seconds 时，不会覆盖 week/total。
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
    $hasHistory = false;
    foreach ($files as $file) {
        validate_uploaded_log($file);
        if ($file['name'] === 'history.gz') {
            $hasHistory = true;
        }
        $validated[] = $file;
    }

    if ($validated !== []) {
        // 服务端重新解析日志，计算今日/本周/本月/总计。
        $tmpPaths = array_map(static fn(array $f): string => (string)$f['tmp_name'], $validated);
        $computed = metrics_compute_stats($tmpPaths);
        $fields = [
            'today_seconds' => (int)$computed['today_seconds'],
            'week_seconds' => (int)$computed['week_seconds'],
            'month_seconds' => (int)$computed['month_seconds'],
            'total_seconds' => (int)$computed['total_seconds'],
        ];
        // 旧客户端可能只上传当前月日志而没有 history.gz。
        // 为避免把历史总计算低，在这种情况下总计至少保持原值。
        if (!$hasHistory) {
            $existing = stats_for_user($userId);
            $fields['total_seconds'] = max(
                $fields['total_seconds'],
                (int)$existing['total_seconds'],
                $fields['month_seconds']
            );
        }
        if (!stats_validate_fields($fields)) {
            legacy_response(['status' => 'error', 'msg' => '阅读数据校验未通过'], 400);
        }
        $fileCount = commit_uploaded_files($userId, $validated);
        stats_upsert_fields($userId, $fields);
        $source = 'files';
    } else {
        // 无文件：兼容旧客户端只上报今日/本月；新客户端可附带本周/总计。
        $fields = [];
        foreach (['today_seconds', 'week_seconds', 'month_seconds', 'total_seconds'] as $key) {
            $raw = $_GET[$key] ?? ($_POST[$key] ?? null);
            if ($raw !== null) {
                if (!is_numeric($raw)) {
                    legacy_response(['status' => 'error', 'msg' => '统计数据格式不正确'], 400);
                }
                $fields[$key] = max(0, (int)$raw);
            }
        }
        if (!isset($fields['today_seconds'], $fields['month_seconds'])) {
            legacy_response(['status' => 'error', 'msg' => '缺少日志文件或统计数据'], 400);
        }
        if (!stats_validate_fields($fields)) {
            legacy_response(['status' => 'error', 'msg' => '阅读数据校验未通过'], 400);
        }
        stats_upsert_fields($userId, $fields);
        $fileCount = 0;
        $source = 'reported';
    }

    $actual = stats_for_user($userId);
    legacy_response([
        'status' => 'success',
        'msg' => 'Sync completed',
        'db_synced' => true,
        'files_count' => $fileCount,
        'today_seconds' => (int)$actual['today_seconds'],
        'week_seconds' => (int)$actual['week_seconds'],
        'month_seconds' => (int)$actual['month_seconds'],
        'total_seconds' => (int)$actual['total_seconds'],
        'source' => $source,
    ]);
} catch (InvalidArgumentException $e) {
    legacy_response(['status' => 'error', 'msg' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    error_log('[kykky upload] ' . $e->getMessage());
    legacy_response(['status' => 'error', 'msg' => '服务器保存失败，请稍后重试'], 500);
}
