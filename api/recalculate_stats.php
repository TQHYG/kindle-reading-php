<?php
/**
 * 根据已上传日志重新计算每个用户的今日/本周/本月/总计阅读时长。
 *
 * 建议每日凌晨执行（在 api/cron.php 的清零窗口之后）：
 *   30 0 * * * www-data php /var/www/kindle-reading-php/api/recalculate_stats.php
 *
 * 可选只重算单个用户：
 *   php api/recalculate_stats.php --user=123
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$onlyUser = null;
foreach ($argv as $arg) {
    if (preg_match('/^--user=(\d+)$/', (string)$arg, $m)) {
        $onlyUser = (int)$m[1];
    }
}

storage_ensure_layout();
$lockPath = storage_root() . '/.recalculate.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false) {
    fwrite(STDERR, "无法创建重算锁文件: {$lockPath}\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "已有重算任务在运行，本次跳过。\n");
    fclose($lock);
    exit(1);
}

$start = microtime(true);
$userIds = [];

if ($onlyUser !== null) {
    $userIds[] = $onlyUser;
} else {
    foreach (db_all('SELECT user_id FROM stats') as $row) {
        $userIds[] = (int)$row['user_id'];
    }
    foreach (glob(storage_root() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if (ctype_digit($name)) {
            $userIds[] = (int)$name;
        }
    }
}
$userIds = array_values(array_unique(array_filter($userIds, static fn(int $id): bool => $id > 0)));
sort($userIds);

echo '[' . date('Y-m-d H:i:s') . "] Recalculating stats for " . count($userIds) . " user(s)...\n";

$updated = 0;
$skipped = 0;
$failed = 0;

foreach ($userIds as $userId) {
    try {
        $files = storage_list_files($userId);
        if ($files === []) {
            $skipped++;
            continue;
        }

        $paths = [];
        foreach ($files as $file) {
            try {
                $paths[] = storage_file_path($userId, (string)$file['name']);
            } catch (Throwable $e) {
                error_log("[kykky recalc] user={$userId} file={$file['name']}: " . $e->getMessage());
            }
        }
        if ($paths === []) {
            $skipped++;
            continue;
        }

        $computed = metrics_compute_stats($paths);
        $fields = [
            'today_seconds' => (int)$computed['today_seconds'],
            'week_seconds' => (int)$computed['week_seconds'],
            'month_seconds' => (int)$computed['month_seconds'],
            'total_seconds' => (int)$computed['total_seconds'],
        ];

        $names = array_column($files, 'name');
        if (!in_array('history.gz', $names, true)) {
            $existing = stats_for_user($userId);
            $fields['total_seconds'] = max(
                $fields['total_seconds'],
                (int)$existing['total_seconds'],
                $fields['month_seconds']
            );
        }

        if (!stats_validate_fields($fields)) {
            throw new RuntimeException('计算结果超出允许范围');
        }

        stats_upsert_fields($userId, $fields);
        $updated++;
        echo sprintf(
            "- user=%d today=%d week=%d month=%d total=%d records=%d\n",
            $userId,
            $fields['today_seconds'],
            $fields['week_seconds'],
            $fields['month_seconds'],
            $fields['total_seconds'],
            (int)$computed['records']
        );
    } catch (Throwable $e) {
        $failed++;
        error_log("[kykky recalc] user={$userId}: " . $e->getMessage());
        echo "- user={$userId} FAILED: {$e->getMessage()}\n";
    }
}

flock($lock, LOCK_UN);
fclose($lock);

$elapsed = microtime(true) - $start;
echo sprintf(
    "[%s] Done. updated=%d skipped=%d failed=%d elapsed=%.2fs\n",
    date('Y-m-d H:i:s'),
    $updated,
    $skipped,
    $failed,
    $elapsed
);

exit($failed > 0 ? 1 : 0);
