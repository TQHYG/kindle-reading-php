<?php
/**
 * HTTP/JSON 响应与请求辅助。
 */

function api_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function json_response(array $data, int $status = 200): never
{
    api_headers();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 旧版 Kindle 客户端与旧接口使用的响应格式。
 */
function legacy_response(array $data, int $status = 200): never
{
    json_response($data, $status);
}

function api_ok(mixed $data = [], int $status = 200): never
{
    json_response(['ok' => true, 'data' => $data], $status);
}

function api_error(string $code, string $message, int $status = 400, array $extra = []): never
{
    json_response(array_merge([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], $extra), $status);
}

function api_require_method(string|array $methods): void
{
    $allowed = array_map('strtoupper', (array)$methods);
    $actual = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($actual === 'HEAD') {
        $actual = 'GET';
    }
    if (!in_array($actual, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_error('method_not_allowed', '请求方法不允许', 405);
    }
}

function read_json_body(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $cached = [];
    }
    if (strlen($raw) > 2 * 1024 * 1024) {
        api_error('payload_too_large', '请求体过大', 413);
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        api_error('invalid_json', '请求体不是合法 JSON', 400);
    }
    if (!is_array($data)) {
        api_error('invalid_json', '请求体必须是 JSON 对象', 400);
    }
    return $cached = $data;
}

/**
 * 读取 JSON 或表单参数（POST/PUT/PATCH）。
 */
function request_data(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    return read_json_body();
}

function client_ip(): string
{
    if ((bool)kykky_cfg('security.trust_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
}

function user_agent(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return is_string($ua) ? substr($ua, 0, 255) : '';
}

/**
 * 只允许跳回本站路径，避免开放重定向。
 */
function safe_redirect_path(?string $path, string $default = '/user.html'): string
{
    $path = is_string($path) ? trim($path) : '';
    if ($path === '' || preg_match('/[\r\n]/', $path)) {
        return $default;
    }

    $base = kykky_base_url();
    if (str_starts_with($path, $base . '/')) {
        $path = substr($path, strlen($base));
    } elseif (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $default;
    }

    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return $default;
    }
    return $path;
}

function is_loopback_request(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return $ip === '127.0.0.1' || $ip === '::1';
}

function api_exception_handler(Throwable $e): void
{
    error_log('[kykky] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (headers_sent()) {
        exit;
    }
    if ((bool)kykky_cfg('app.debug', false)) {
        api_error('server_error', $e->getMessage(), 500, [
            'trace' => explode("\n", $e->getTraceAsString()),
        ]);
    }
    api_error('server_error', '服务器内部错误', 500);
}
