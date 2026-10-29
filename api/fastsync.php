<?php
/**
 * 无网络时扫码快捷同步。
 *
 * GET  /api/fastsync.php?action=preview&data=<base64>
 * POST /api/fastsync.php?action=confirm   {data}  (X-CSRF-Token)
 *
 * data: {"did":"设备码","today":秒,"month":秒,"week":秒,"total":秒}
 * week/total 可选，兼容旧客户端。
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

    $result = [
        'did' => $data['did'],
        'today' => max(0, (int)$data['today']),
        'month' => max(0, (int)$data['month']),
        'week' => null,
        'total' => null,
    ];
    foreach (['week', 'total'] as $key) {
        if (array_key_exists($key, $data)) {
            if (!is_numeric($data[$key])) {
                api_error('invalid_data', '阅读数据格式不正确', 400);
            }
            $result[$key] = max(0, (int)$data[$key]);
        }
    }
    return $result;
}

function fastsync_fields(array $data): array
{
    $fields = [
        'today_seconds' => $data['today'],
        'month_seconds' => $data['month'],
    ];
    if ($data['week'] !== null) {
        $fields['week_seconds'] = $data['week'];
    }
    if ($data['total'] !== null) {
        $fields['total_seconds'] = $data['total'];
    }
    if (!stats_validate_fields($fields)) {
        api_error('invalid_stats', '阅读数据超出合理范围', 400);
    }
    return $fields;
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
    fastsync_fields($data);
    api_ok([
        'device_name' => (string)($device['device_name'] ?? ''),
        'device_code' => $data['did'],
        'today_seconds' => $data['today'],
        'week_seconds' => $data['week'],
        'month_seconds' => $data['month'],
        'total_seconds' => $data['total'],
    ]);
}

if ($action === 'confirm') {
    api_require_method('POST');
    require_csrf($session);
    $input = request_data();
    $data = decode_fastsync_data($input['data'] ?? null);
    assert_device_owner($data['did'], $userId);
    $fields = fastsync_fields($data);
    stats_upsert_fields($userId, $fields);
    $actual = stats_for_user($userId);
    api_ok([
        'today_seconds' => (int)$actual['today_seconds'],
        'week_seconds' => (int)$actual['week_seconds'],
        'month_seconds' => (int)$actual['month_seconds'],
        'total_seconds' => (int)$actual['total_seconds'],
        'device_name' => (string)(db_val(
            'SELECT device_name FROM devices WHERE code = ? AND user_id = ?',
            [$data['did'], $userId],
            ''
        )),
    ]);
}

api_error('invalid_action', '未知操作', 400);
