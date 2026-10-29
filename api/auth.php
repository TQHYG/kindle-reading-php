<?php
/**
 * Kindle 设备授权接口（兼容旧客户端路径与字段）。
 *
 * GET /api/auth.php?action=get_url
 * GET /api/auth.php?action=check_status&device_code=...
 * GET /api/auth.php?action=logout   (Authorization: Bearer <token>)
 */

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_url') {
    $limit = max(1, (int)kykky_cfg('limits.device_pending_per_minute', 10));
    $recent = (int)db_val(
        'SELECT COUNT(*) FROM devices
         WHERE status = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
        [],
        0
    );
    if ($recent >= $limit) {
        legacy_response(['error' => 'Too many requests. Please try again later.'], 429);
    }

    $deviceCode = bin2hex(random_bytes(8));
    db_query('INSERT INTO devices (code, status) VALUES (?, 0)', [$deviceCode]);

    legacy_response([
        'device_code' => $deviceCode,
        'login_url' => kykky_base_url() . '/device.html?device_code=' . rawurlencode($deviceCode),
    ]);
}

if ($action === 'check_status') {
    $code = $_GET['device_code'] ?? '';
    if (!is_string($code) || $code === '') {
        legacy_response(['status' => 'error', 'msg' => 'missing_code'], 400);
    }

    $device = db_one('SELECT * FROM devices WHERE code = ? LIMIT 1', [$code]);
    if ($device === null) {
        legacy_response(['status' => 'expired'], 404);
    }

    if ((int)$device['status'] === 1) {
        $user = db_one('SELECT nickname, avatar FROM users WHERE id = ? LIMIT 1', [(int)$device['user_id']]);
        if ($user === null) {
            db_query('DELETE FROM devices WHERE code = ?', [$code]);
            legacy_response(['status' => 'expired'], 404);
        }

        legacy_response([
            'status' => 'success',
            'access_token' => issue_device_token((int)$device['user_id'], $code),
            'nickname' => (string)$user['nickname'],
            'avatar' => (string)$user['avatar'],
            'device_name' => (string)($device['device_name'] ?? ''),
        ]);
    }

    legacy_response(['status' => 'pending']);
}

if ($action === 'logout') {
    $auth = require_device_auth();
    db_query('DELETE FROM devices WHERE code = ? AND user_id = ?', [$auth['did'], $auth['uid']]);
    legacy_response(['status' => 'success', 'msg' => 'Device logged out and token revoked']);
}

legacy_response(['status' => 'error', 'msg' => 'invalid_action'], 400);
