<?php
/**
 * 健康检查。
 *
 * GET /api/health.php
 */

require_once __DIR__ . '/bootstrap.php';

try {
    $dbOk = (int)db_val('SELECT 1', [], 0) === 1;
    $storage = storage_root();
    api_ok([
        'service' => 'kykky',
        'version' => '2.0',
        'time' => date('c'),
        'timezone' => date_default_timezone_get(),
        'db' => $dbOk,
        'storage' => is_dir($storage) && is_writable($storage),
    ]);
} catch (Throwable $e) {
    api_error('unhealthy', '服务未就绪', 503);
}
