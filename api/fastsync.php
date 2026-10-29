<?php
/**
 * 无网络时扫码快捷同步。
 *
 * GET  /api/fastsync.php?data=<base64>
 * POST /api/fastsync.php?action=confirm   {data}  (X-CSRF-Token)
 */

require_once __DIR__ . '/bootstrap.php';

function decode_fastsync_data(mixed $raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        api_error('missing_data', '未检测到同步数据，请重新从 Kindle 扫描二维码', 400);
    }
    $raw = str_replace(' ', '+', trim($raw));
    $decoded = base64_decode($raw, true);
    if ($decoded === false) {
        $decoded = b64url_decode($raw);
    }
    if ($decoded === false) {
        api_error('invalid_data', '二维码数据已损坏', 400);
    }
    $data = json_decode($decoded, true);
    if (!is_array($data) || !isset($data['did'], $data['today'], $data['month'])) {
        api_error('invalid_data', '二维码格式不正确或已损坏', 400);
    }
    if (!is_string($data['did']) || !preg_match('/^[a-f0-9]{8,64}$/', $data['did'])) {
        api_error('invalid_data', '设备标识不正确', 400);
    }
    if (!is_numeric($data['today']) || !is_numeric($data['month'])) {
        api_error('invalid_data', '阅读数据格式不正确', 400);
    }
    return [
        'did' => $data['did'],
        'today' => max(0, (int)$data['today']),
        'month' => max(0, (int)$data['month']),
    ];
}

function assert_device_owner(string $deviceCode, int $userId): array
{
    $device = db_one(
        'SELECT code, device_name FROM devices WHERE code = ? AND user_id = ? AND status = 1 LIMIT 1',
        [$deviceCode, $userId]
    );
    if ($device === null) {
        api_error('forbidden', '安全校验失败：该设备不属于您的账号', 403);
    }
    return $device;
}

$session = require_session();
$userId = (int)$session['user']['id'];
$action = $_GET['action'] ?? 'preview';

if ($action === 'preview') {
    api_require_method('GET');
    $data = decode_fastsync_data($_GET['data'] ?? null);
    $device = assert_device_owner($data['did'], $userId);
    if (!stats_validate_values($data['today'], $data['month'])) {
        api_error('invalid_stats', '阅读数据超出合理范围', 400);
    }
    api_ok([
        'device_name' => (string)($device['device_name'] ?? ''),
        'device_code' => $data['did'],
        'today_seconds' => $data['today'],
        'month_seconds' => $data['month'],
    ]);
}

if ($action === 'confirm') {
    api_require_method('POST');
    require_csrf($session);
    $input = request_data();
    $data = decode_fastsync_data($input['data'] ?? null);
    assert_device_owner($data['did'], $userId);
    if (!stats_validate_values($data['today'], $data['month'])) {
        api_error('invalid_stats', '阅读数据超出合理范围', 400);
    }
    stats_upsert($userId, $data['today'], $data['month']);
    api_ok([
        'today_seconds' => $data['today'],
        'month_seconds' => $data['month'],
        'device_name' => (string)(db_val(
            'SELECT device_name FROM devices WHERE code = ? AND user_id = ?',
            [$data['did'], $userId],
            ''
        )),
    ]);
}

api_error('invalid_action', '未知操作', 400);
