<?php
// func/cron.php
// 建议在 crontab 中这样设置：*/10 * * * * php /path/to/your/func/cron.php

// 确保只能从命令行执行，防止外部通过 URL 触发清理
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/db.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup...\n";

/**
 * 任务 1：清理过期的 Pending 设备码
 * 逻辑：status = 0 (未绑定) 且超过 10 分钟未处理
 */
$deleted_pending = db_query(
    "DELETE FROM devices WHERE status = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
                            []
);

/**
 * 任务 2：(可选) 清理极其陈旧的未登录 Session 或 临时文件
 * 你可以在这里扩展其他的清理逻辑
 */

echo "Cleanup finished.\n";
echo "- Expired pending devices removed.\n";
