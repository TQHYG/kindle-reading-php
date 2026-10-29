<?php
/**
 * 当前用户统计概览。
 *
 * GET /api/stats.php
 */

require_once __DIR__ . '/bootstrap.php';

$session = require_session();
$userId = (int)$session['user']['id'];
$stats = stats_for_user($userId);
$stats['devices_count'] = (int)db_val(
    'SELECT COUNT(*) FROM devices WHERE user_id = ? AND status = 1',
    [$userId],
    0
);
api_ok($stats);
