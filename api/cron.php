<?php
/**
 * 定时任务（仅 CLI）。
 *
 * 每分钟执行：
 *   php /path/to/api/cron.php
 *
 * 手动立即全量重算：
 *   php /path/to/api/cron.php --recalc
 *
 * 只重算单个用户：
 *   php /path/to/api/cron.php --user=123
 *
 * 默认每天凌晨 00:20 - 04:59 之间自动执行一次全量统计重算，
 * 使用完成标记避免每分钟重复执行。
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

/**
 * 根据已上传日志重算用户统计。
 *
 * @return array{updated:int,skipped:int,failed:int,locked:bool}
 */
function cron_recalculate_stats(?int $onlyUser): array
{
    storage_ensure_layout();
    $lockPath = storage_root() . '/.recalculate.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('无法创建重算锁文件: ' . $lockPath);
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return ['updated' => 0, 'skipped' => 0, 'failed' => 0, 'locked' => true];
    }

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

    $result = ['updated' => 0, 'skipped' => 0, 'failed' => 0, 'locked' => false];
    echo '  Recalculating stats for ' . count($userIds) . " user(s)...\n";

    try {
        foreach ($userIds as $userId) {
            try {
                $files = storage_list_files($userId);
                if ($files === []) {
                    $result['skipped']++;
                    continue;
                }

                $paths = [];
                foreach ($files as $file) {
                    try {
                        $paths[] = storage_file_path($userId, (string)$file['name']);
                    } catch (Throwable $e) {
                        error_log("[kykky cron] user={$userId} file={$file['name']}: " . $e->getMessage());
                    }
                }
                if ($paths === []) {
                    $result['skipped']++;
                    continue;
                }

                $computed = metrics_compute_stats($paths);
                $fields = [
                    'today_seconds' => (int)$computed['today_seconds'],
                    'week_seconds' => (int)$computed['week_seconds'],
                    'month_seconds' => (int)$computed['month_seconds'],
                    'total_seconds' => (int)$computed['total_seconds'],
                ];

                // 旧客户端可能只上传当前月日志而没有 history.gz。
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
                $result['updated']++;
                echo sprintf(
                    "  - user=%d today=%d week=%d month=%d total=%d records=%d\n",
                    $userId,
                    $fields['today_seconds'],
                    $fields['week_seconds'],
                    $fields['month_seconds'],
                    $fields['total_seconds'],
                    (int)$computed['records']
                );
            } catch (Throwable $e) {
                $result['failed']++;
                error_log("[kykky cron] recalc user={$userId}: " . $e->getMessage());
                echo "  - user={$userId} FAILED: {$e->getMessage()}\n";
            }
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    echo sprintf(
        "  Recalc done. updated=%d skipped=%d failed=%d\n",
        $result['updated'],
        $result['skipped'],
        $result['failed']
    );
    return $result;
}

$onlyUser = null;
foreach ($argv as $arg) {
    if (preg_match('/^--user=(\d+)$/', (string)$arg, $m)) {
        $onlyUser = (int)$m[1];
    }
}
$forceRecalc = in_array('--recalc', $argv, true);

$now = new DateTimeImmutable('now', new DateTimeZone((string)kykky_cfg('app.timezone', 'Asia/Shanghai')));
echo '[' . $now->format('Y-m-d H:i:s') . "] Starting Cron Tasks...\n";

$pending = db_exec(
    'DELETE FROM devices WHERE status = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
);
echo "- Expired pending devices removed: {$pending}\n";

$sessions = db_exec(
    'DELETE FROM sessions WHERE expires_at < NOW()
     OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))'
);
echo "- Expired sessions removed: {$sessions}\n";

$isResetWindow = $now->format('H') === '00' && (int)$now->format('i') < 10;
if ($isResetWindow) {
    db_query('UPDATE stats SET today_seconds = 0');
    echo "- Today seconds reset to zero.\n";

    if ($now->format('d') === '01') {
        db_query('UPDATE stats SET month_seconds = 0');
        echo "- Monthly seconds reset to zero.\n";
    }
}

// 每日 00:20 - 04:59 自动重算一次；--recalc 立即全量重算；--user=ID 只重算单个用户。
$minutesOfDay = ((int)$now->format('G')) * 60 + (int)$now->format('i');
$isRecalcWindow = $minutesOfDay >= 20 && $minutesOfDay < 300;
$markerPath = storage_root() . '/.recalc-complete';
$today = $now->format('Y-m-d');
$lastRecalcDate = is_file($markerPath) ? trim((string)file_get_contents($markerPath)) : '';
$shouldRecalculate = $forceRecalc || $onlyUser !== null || ($isRecalcWindow && $lastRecalcDate !== $today);

$exitCode = 0;
if ($shouldRecalculate) {
    echo "- Stats recalculation requested.\n";
    try {
        $result = cron_recalculate_stats($onlyUser);
        if ($result['locked']) {
            echo "- Stats recalculation skipped: another task is running.\n";
            if ($onlyUser !== null || $forceRecalc) {
                $exitCode = 1;
            }
        } else {
            if ($onlyUser === null) {
                @file_put_contents($markerPath, $today . "\n", LOCK_EX);
            }
            if (($onlyUser !== null || $forceRecalc) && $result['failed'] > 0) {
                $exitCode = 1;
            }
        }
    } catch (Throwable $e) {
        $exitCode = 1;
        error_log('[kykky cron] recalc: ' . $e->getMessage());
        echo "- Stats recalculation FAILED: {$e->getMessage()}\n";
    }
} else {
    echo "- Stats recalculation not due.\n";
}

echo "All tasks finished.\n";
exit($exitCode);
