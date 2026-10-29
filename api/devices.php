<?php
/**
 * 设备绑定与管理（Web 会话认证）。
 *
 * GET  /api/devices.php?action=list
 * GET  /api/devices.php?action=info&code=...
 * POST /api/devices.php?action=bind      {code, device_name}
 * POST /api/devices.php?action=rename    {code, device_name}
 * POST /api/devices.php?action=delete    {code}
 */

require_once __DIR__ . '/bootstrap.php';

function device_public(array $device): array
{
    $createdTs = strtotime((string)$device['created_at']) ?: time();
    return [
        'code' => (string)$device['code'],
        'device_name' => (string)($device['device_name'] ?? ''),
        'status' => (int)$device['status'],
        'created_at' => (string)$device['created_at'],
        'expires_at' => date('Y-m-d', $createdTs + 86400 * 365),
    ];
}

function normalize_device_name(mixed $value): string
{
    $name = trim((string)$value);
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 50);
    } else {
        $name = substr($name, 0, 50);
    }
    return $name === '' ? '我的 Kindle' : $name;
}

function valid_device_code(mixed $code): bool
{
    return is_string($code) && preg_match('/^[a-f0-9]{8,64}$/', $code) === 1;
}

$session = require_session();
$userId = (int)$session['user']['id'];
$action = $_GET['action'] ?? '';
$data = request_data();

if ($action === 'list') {
    api_require_method('GET');
    $rows = db_all(
        'SELECT code, device_name, status, created_at
         FROM devices WHERE user_id = ? AND status = 1
         ORDER BY created_at DESC',
        [$userId]
    );
    api_ok(['devices' => array_map('device_public', $rows)]);
}

if ($action === 'info') {
    api_require_method('GET');
    $code = $_GET['code'] ?? '';
    if (!valid_device_code($code)) {
        api_error('invalid_code', '设备码格式不正确', 400);
    }
    $device = db_one('SELECT * FROM devices WHERE code = ? LIMIT 1', [$code]);
    if ($device === null) {
        api_error('not_found', '二维码已过期，请在 Kindle 上刷新', 404);
    }
    if ($device['user_id'] !== null && (int)$device['user_id'] !== $userId) {
        api_error('forbidden', '该设备已绑定到其他账号', 403);
    }
    $public = device_public($device);
    $public['can_bind'] = (int)$device['status'] === 0;
    api_ok($public);
}

if ($action === 'bind') {
    api_require_method('POST');
    require_csrf($session);
    $code = $data['code'] ?? '';
    $name = normalize_device_name($data['device_name'] ?? '');
    if (!valid_device_code($code)) {
        api_error('invalid_code', '设备码格式不正确', 400);
    }

    $result = db_transaction(function (PDO $pdo) use ($code, $name, $userId): array {
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE code = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$code]);
        $device = $stmt->fetch();
        if ($device === false) {
            return ['error' => 'not_found'];
        }
        if ($device['user_id'] !== null && (int)$device['user_id'] !== $userId) {
            return ['error' => 'forbidden'];
        }
        if ((int)$device['status'] === 1) {
            // 已属于当前用户：允许更新名称。
            $update = $pdo->prepare('UPDATE devices SET device_name = ? WHERE code = ?');
            $update->execute([$name, $code]);
            return ['device' => array_merge($device, ['device_name' => $name])];
        }

        $maxDevices = max(1, (int)kykky_cfg('limits.max_devices_per_user', 20));
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM devices WHERE user_id = ? AND status = 1');
        $countStmt->execute([$userId]);
        if ((int)$countStmt->fetchColumn() >= $maxDevices) {
            return ['error' => 'limit'];
        }

        $update = $pdo->prepare(
            'UPDATE devices SET user_id = ?, device_name = ?, status = 1 WHERE code = ?'
        );
        $update->execute([$userId, $name, $code]);
        return ['device' => array_merge($device, [
            'user_id' => $userId,
            'device_name' => $name,
            'status' => 1,
        ])];
    });

    if (($result['error'] ?? '') === 'not_found') {
        api_error('not_found', '二维码已过期，请在 Kindle 上刷新', 404);
    }
    if (($result['error'] ?? '') === 'forbidden') {
        api_error('forbidden', '该设备已绑定到其他账号', 403);
    }
    if (($result['error'] ?? '') === 'limit') {
        api_error('device_limit', '已达到设备数量上限，请先删除旧设备', 409);
    }
    api_ok(device_public($result['device']));
}

if ($action === 'rename') {
    api_require_method('POST');
    require_csrf($session);
    $code = $data['code'] ?? '';
    $name = normalize_device_name($data['device_name'] ?? '');
    if (!valid_device_code($code)) {
        api_error('invalid_code', '设备码格式不正确', 400);
    }
    $device = db_one(
        'SELECT code FROM devices WHERE code = ? AND user_id = ? LIMIT 1',
        [$code, $userId]
    );
    if ($device === null) {
        api_error('not_found', '设备不存在或不属于当前账号', 404);
    }
    db_query(
        'UPDATE devices SET device_name = ? WHERE code = ? AND user_id = ?',
        [$name, $code, $userId]
    );
    api_ok(['code' => $code, 'device_name' => $name]);
}

if ($action === 'delete') {
    api_require_method('POST');
    require_csrf($session);
    $code = $data['code'] ?? '';
    if (!valid_device_code($code)) {
        api_error('invalid_code', '设备码格式不正确', 400);
    }
    $deleted = db_exec('DELETE FROM devices WHERE code = ? AND user_id = ?', [$code, $userId]);
    if ($deleted === 0) {
        api_error('not_found', '设备不存在或不属于当前账号', 404);
    }
    api_ok(['deleted' => true]);
}

api_error('invalid_action', '未知操作', 400);
