<?php
/**
 * API 统一引导文件。
 */

require_once __DIR__ . '/lib/config.php';

date_default_timezone_set((string)kykky_cfg('app.timezone', 'Asia/Shanghai'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/stats.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/logs.php';
require_once __DIR__ . '/lib/storage.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    api_exception_handler($e);
});
