<?php
/**
 * 账号认证：
 *  - Web 使用数据库持久会话（HttpOnly Cookie），解决 PHP session 易掉登录的问题；
 *  - Kindle 设备继续使用兼容旧版的 HMAC Bearer Token。
 */

function session_cookie_name(): string
{
    $name = (string)kykky_cfg('security.session_cookie', 'kykky_session');
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
        return 'kykky_session';
    }
    return $name;
}

function session_cookie_secure(): bool
{
    $value = kykky_cfg('security.cookie_secure', 'auto');
    if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
        return true;
    }
    if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
        return false;
    }
    return kykky_is_https() || str_starts_with(kykky_base_url(), 'https://');
}

function set_auth_cookie(string $name, string $value, int $expires, bool $httpOnly = true): void
{
    if (headers_sent()) {
        return;
    }
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => session_cookie_secure(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ]);
}

function clear_auth_cookie(string $name): void
{
    if (headers_sent()) {
        return;
    }
    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => session_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * @return array{token:string, csrf:string, expires_at:string}
 */
function create_session(int $userId): array
{
    $ttl = (int)kykky_cfg('security.session_ttl', 31536000);
    $maxSessions = max(1, (int)kykky_cfg('limits.max_sessions_per_user', 20));
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $csrf = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

    // 清理该用户的过期/吊销会话，并限制会话数量。
    db_query(
        'DELETE FROM sessions WHERE user_id = ? AND (expires_at < NOW() OR revoked_at IS NOT NULL)',
        [$userId]
    );
    $count = (int)db_val('SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$userId], 0);
    if ($count >= $maxSessions) {
        $deleteCount = max(1, $count - $maxSessions + 1);
        db_query(
            'DELETE FROM sessions WHERE user_id = ? ORDER BY last_seen ASC LIMIT ' . $deleteCount,
            [$userId]
        );
    }

    db_query(
        'INSERT INTO sessions (user_id, token_hash, csrf_token, user_agent, ip, created_at, last_seen, expires_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)',
        [$userId, $hash, $csrf, user_agent(), client_ip(), $expiresAt]
    );

    set_auth_cookie(session_cookie_name(), $token, strtotime($expiresAt));

    return [
        'token' => $token,
        'csrf' => $csrf,
        'expires_at' => $expiresAt,
    ];
}

/**
 * 解析当前 Cookie 会话。
 *
 * @return array{user:array, session:array}|null
 */
function current_session(bool $touch = true): ?array
{
    static $resolved = false;
    static $cached = null;
    if ($resolved) {
        return $cached;
    }
    $resolved = true;

    $name = session_cookie_name();
    $token = $_COOKIE[$name] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $row = db_one(
        'SELECT s.id AS session_id, s.csrf_token, s.expires_at, s.last_seen,
                u.id AS user_id, u.nickname, u.avatar, u.oauth_provider
         FROM sessions s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = ? AND s.revoked_at IS NULL
         LIMIT 1',
        [hash('sha256', $token)]
    );

    if ($row === null) {
        clear_auth_cookie($name);
        return null;
    }

    $now = time();
    $expiresTs = strtotime((string)$row['expires_at']);
    if ($expiresTs === false || $expiresTs <= $now) {
        db_query('DELETE FROM sessions WHERE id = ?', [(int)$row['session_id']]);
        clear_auth_cookie($name);
        return null;
    }

    if ($touch) {
        $renewThreshold = (int)kykky_cfg('security.session_renew_threshold', 15552000);
        $ttl = (int)kykky_cfg('security.session_ttl', 31536000);
        $lastSeenTs = strtotime((string)$row['last_seen']) ?: 0;
        if ($renewThreshold > 0 && ($expiresTs - $now) < $renewThreshold) {
            $newExpiresAt = date('Y-m-d H:i:s', $now + $ttl);
            db_query(
                'UPDATE sessions SET last_seen = NOW(), expires_at = ? WHERE id = ?',
                [$newExpiresAt, (int)$row['session_id']]
            );
            set_auth_cookie($name, $token, strtotime($newExpiresAt));
            $row['expires_at'] = $newExpiresAt;
        } elseif ($now - $lastSeenTs > 3600) {
            db_query('UPDATE sessions SET last_seen = NOW() WHERE id = ?', [(int)$row['session_id']]);
        }
    }

    $cached = [
        'user' => [
            'id' => (int)$row['user_id'],
            'nickname' => (string)$row['nickname'],
            'avatar' => (string)$row['avatar'],
            'oauth_provider' => (string)$row['oauth_provider'],
        ],
        'session' => [
            'id' => (int)$row['session_id'],
            'csrf' => (string)$row['csrf_token'],
            'expires_at' => (string)$row['expires_at'],
        ],
    ];
    return $cached;
}

function current_user(): ?array
{
    $session = current_session();
    return $session['user'] ?? null;
}

function require_session(): array
{
    $session = current_session();
    if ($session === null) {
        api_error('unauthenticated', '请先登录', 401);
    }
    return $session;
}

function require_csrf(array $session): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || $provided === '' || !hash_equals($session['session']['csrf'], $provided)) {
        api_error('invalid_csrf', 'CSRF 校验失败，请刷新页面后重试', 403);
    }
}

function require_csrf_for_mutation(array $session): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        require_csrf($session);
    }
}

function destroy_current_session(): void
{
    $session = current_session(false);
    if ($session !== null) {
        db_query('UPDATE sessions SET revoked_at = NOW() WHERE id = ?', [$session['session']['id']]);
    }
    clear_auth_cookie(session_cookie_name());
}

function session_public_payload(array $session): array
{
    return [
        'authenticated' => true,
        'user' => $session['user'],
        'csrf' => $session['session']['csrf'],
        'expires_at' => $session['session']['expires_at'],
    ];
}

// ---------------------------------------------------------------------------
// Kindle 设备 Token
// ---------------------------------------------------------------------------

function token_secret(): string
{
    return (string)kykky_cfg('security.app_secret', '');
}

function issue_device_token(int $userId, string $deviceCode): string
{
    $payload = [
        'uid' => $userId,
        'did' => $deviceCode,
        'exp' => time() + (86400 * 365),
    ];
    $data = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sign = hash_hmac('sha256', $data, token_secret());
    return $data . '.' . $sign;
}

/**
 * @return array{uid:int, did:string, exp:int}|null
 */
function validate_device_token(?string $token): ?array
{
    if (!is_string($token) || $token === '') {
        return null;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$data, $sign] = $parts;
    $expected = hash_hmac('sha256', $data, token_secret());
    if (!hash_equals($expected, $sign)) {
        return null;
    }
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
        return null;
    }
    $payload = json_decode($decoded, true);
    if (!is_array($payload) || !isset($payload['uid'], $payload['did'], $payload['exp'])) {
        return null;
    }
    if ((int)$payload['exp'] < time()) {
        return null;
    }
    $uid = (int)$payload['uid'];
    $did = (string)$payload['did'];

    $exists = db_val(
        'SELECT COUNT(*) FROM devices WHERE code = ? AND user_id = ? AND status = 1',
        [$did, $uid],
        0
    );
    if ((int)$exists === 0) {
        return null;
    }

    return ['uid' => $uid, 'did' => $did, 'exp' => (int)$payload['exp']];
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (is_string($header) && preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $m)) {
        return trim($m[1]);
    }
    if (!empty($_GET['token']) && is_string($_GET['token'])) {
        return $_GET['token'];
    }
    if (!empty($_POST['token']) && is_string($_POST['token'])) {
        return $_POST['token'];
    }
    return null;
}

function require_device_auth(): array
{
    $payload = validate_device_token(bearer_token());
    if ($payload === null) {
        legacy_response(['status' => 'error', 'msg' => 'Unauthorized or invalid token'], 401);
    }
    return $payload;
}

/**
 * 上传接口同时接受 Kindle Token 和 Web 会话。
 * Web 会话的写操作必须通过 CSRF 校验。
 *
 * @return array{uid:int, auth:string, did:?string, session:?array}
 */
function require_token_or_session(): array
{
    $payload = validate_device_token(bearer_token());
    if ($payload !== null) {
        return [
            'uid' => $payload['uid'],
            'auth' => 'device',
            'did' => $payload['did'],
            'session' => null,
        ];
    }

    $session = current_session();
    if ($session !== null) {
        require_csrf_for_mutation($session);
        return [
            'uid' => $session['user']['id'],
            'auth' => 'session',
            'did' => null,
            'session' => $session,
        ];
    }

    legacy_response(['status' => 'error', 'msg' => 'Unauthorized or invalid token'], 401);
}
