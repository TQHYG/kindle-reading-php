<?php
/**
 * GitHub OAuth 与用户落库。
 */

function oauth_state_cookie_name(): string
{
    return session_cookie_name() . '_oauth';
}

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string|false
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data, true);
}

function github_state_payload(?string $token): ?array
{
    if (!is_string($token) || $token === '') {
        return null;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$payloadB64, $sig] = $parts;
    $expected = hash_hmac('sha256', $payloadB64, token_secret());
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $raw = b64url_decode($payloadB64);
    if ($raw === false) {
        return null;
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty($payload['n']) || empty($payload['e'])) {
        return null;
    }
    if ((int)$payload['e'] < time()) {
        return null;
    }
    return $payload;
}

/**
 * 生成 state 并写入短期 Cookie，返回 GitHub 需要的 state 值。
 */
function create_oauth_state(string $redirectPath): string
{
    $nonce = bin2hex(random_bytes(16));
    $payload = [
        'n' => $nonce,
        'r' => safe_redirect_path($redirectPath),
        'e' => time() + 600,
    ];
    $payloadB64 = b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $token = $payloadB64 . '.' . hash_hmac('sha256', $payloadB64, token_secret());
    set_auth_cookie(oauth_state_cookie_name(), $token, time() + 600, true);
    return $nonce;
}

/**
 * 校验 GitHub 回调中的 state，返回登录后跳转路径。
 */
function consume_oauth_state(?string $state): ?string
{
    $cookie = $_COOKIE[oauth_state_cookie_name()] ?? '';
    clear_auth_cookie(oauth_state_cookie_name());

    $payload = github_state_payload($cookie);
    if ($payload === null) {
        return null;
    }
    if (!is_string($state) || !hash_equals((string)$payload['n'], $state)) {
        return null;
    }
    return safe_redirect_path((string)($payload['r'] ?? '/user.html'));
}

function github_http(string $url, ?array $postFields, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('无法初始化 GitHub 请求');
    }
    $defaultHeaders = [
        'Accept: application/json',
        'User-Agent: Kykky-Server/2.0',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
    ]);
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($body === false) {
        throw new RuntimeException('GitHub 请求失败: ' . $error);
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException('GitHub 返回了无法解析的响应 (HTTP ' . $status . ')');
    }
    if ($status >= 400) {
        $message = $json['error_description'] ?? $json['message'] ?? ('HTTP ' . $status);
        throw new RuntimeException('GitHub 请求失败: ' . $message);
    }
    return $json;
}

function github_exchange_code(string $code): string
{
    $json = github_http((string)kykky_cfg('oauth.token_url'), [
        'client_id' => (string)kykky_cfg('oauth.client_id'),
        'client_secret' => (string)kykky_cfg('oauth.client_secret'),
        'code' => $code,
        'redirect_uri' => (string)kykky_cfg('oauth.redirect_uri'),
    ]);
    if (empty($json['access_token']) || !is_string($json['access_token'])) {
        throw new RuntimeException('GitHub 授权失败：未获得 access_token');
    }
    return $json['access_token'];
}

function github_fetch_user(string $accessToken): array
{
    $json = github_http((string)kykky_cfg('oauth.user_url'), null, [
        'Authorization: Bearer ' . $accessToken,
    ]);
    if (empty($json['id'])) {
        throw new RuntimeException('GitHub 用户信息缺少 id');
    }
    return $json;
}

/**
 * 按 GitHub 用户 ID 创建或更新本地用户，返回本地 user_id。
 */
function github_upsert_user(array $gh): int
{
    $oauthId = (string)$gh['id'];
    $nickname = trim((string)($gh['login'] ?? 'GitHub User'));
    if (function_exists('mb_substr')) {
        $nickname = mb_substr($nickname, 0, 100);
    } else {
        $nickname = substr($nickname, 0, 100);
    }
    $avatar = (string)($gh['avatar_url'] ?? '');
    $avatar = substr($avatar, 0, 500);

    return db_transaction(function (PDO $pdo) use ($oauthId, $nickname, $avatar): int {
        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE oauth_provider = 'github' AND oauth_id = ? LIMIT 1"
        );
        $stmt->execute([$oauthId]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            $userId = (int)$existing['id'];
            $update = $pdo->prepare(
                'UPDATE users SET nickname = ?, avatar = ? WHERE id = ?'
            );
            $update->execute([$nickname, $avatar, $userId]);
            return $userId;
        }

        $insert = $pdo->prepare(
            "INSERT INTO users (oauth_id, oauth_provider, nickname, avatar)
             VALUES (?, 'github', ?, ?)"
        );
        $insert->execute([$oauthId, $nickname, $avatar]);
        $userId = (int)$pdo->lastInsertId();

        $stats = $pdo->prepare('INSERT IGNORE INTO stats (user_id) VALUES (?)');
        $stats->execute([$userId]);
        return $userId;
    });
}

function github_authorize_url(string $redirectPath): string
{
    $state = create_oauth_state($redirectPath);
    $query = http_build_query([
        'client_id' => (string)kykky_cfg('oauth.client_id'),
        'redirect_uri' => (string)kykky_cfg('oauth.redirect_uri'),
        'scope' => (string)kykky_cfg('oauth.scope', 'read:user'),
        'state' => $state,
    ]);
    return rtrim((string)kykky_cfg('oauth.authorize_url'), '?') . '?' . $query;
}
