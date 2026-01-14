<?php
// func/cron.php
// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

// 确保只能从命令行执行
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/db.php';

$now = time();
$currentH = date('H', $now);
$currentM = date('i', $now);
$currentD = date('d', $now);

echo "[" . date('Y-m-d H:i:s') . "] Starting Cron Tasks...\n";

/**
 * 任务 1：清理过期的 Pending 设备码
 */
db_query(
    "DELETE FROM devices WHERE status = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
    []
);
echo "- Expired pending devices removed.\n";

/**
 * 任务 2：重置今日阅读时间 (每天 00:00 - 00:10 触发)
 */
if ($currentH == '00' && $currentM < 10) {
    db_query("UPDATE stats SET today_seconds = 0", []);
    echo "- Today seconds reset to zero.\n";
    
    /**
     * 任务 3：重置每月阅读时间 (每月 1 号 00:00 - 00:10 触发)
     */
    if ($currentD == '01') {
        db_query("UPDATE stats SET month_seconds = 0", []);
        echo "- Monthly seconds reset to zero.\n";
    }
}

echo "All tasks finished.\n";