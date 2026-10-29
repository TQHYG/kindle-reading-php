<?php
/**
 * 定时任务（仅 CLI）。
 *
 * php /path/to/api/cron.php
 * php /path/to/api/cron.php --force-daily --force-monthly  # 本地测试
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$forceDaily = in_array('--force-daily', $argv, true);
$forceMonthly = in_array('--force-monthly', $argv, true);
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
if ($forceDaily || $isResetWindow) {
    db_query('UPDATE stats SET today_seconds = 0');
    echo "- Today seconds reset to zero.\n";
}

if ($forceMonthly || ($isResetWindow && $now->format('d') === '01')) {
    db_query('UPDATE stats SET month_seconds = 0');
    echo "- Monthly seconds reset to zero.\n";
}

echo "All tasks finished.\n";
