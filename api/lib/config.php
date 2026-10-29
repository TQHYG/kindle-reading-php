<?php
/**
 * 配置加载与访问。
 * 默认读取 Web 根目录的 config.php；Nginx 必须显式拒绝访问该文件。
 * 也支持通过 KYKKY_CONFIG 或 /etc/kykky/config.php 覆盖。
 */

function kykky_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $candidates = [];
    $envPath = getenv('KYKKY_CONFIG');
    if (is_string($envPath) && $envPath !== '') {
        $candidates[] = $envPath;
    }
    $candidates[] = dirname(__DIR__, 2) . '/config.php';
    $candidates[] = '/etc/kykky/config.php';

    $path = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }
    if ($path === null) {
        throw new RuntimeException('Kykky 配置文件不存在，请参考 config.sample.php');
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('Kykky 配置文件必须返回数组');
    }

    $defaults = [
        'app' => [
            'name' => 'Kykky 阅读数据统计',
            'base_url' => '',
            'timezone' => 'Asia/Shanghai',
            'debug' => false,
        ],
        'db' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'kykky_db',
            'user' => 'kykky',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'oauth' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',
            'authorize_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url' => 'https://api.github.com/user',
            'scope' => 'read:user',
        ],
        'security' => [
            'app_secret' => '',
            'session_cookie' => 'kykky_session',
            'session_ttl' => 31536000,
            'session_renew_threshold' => 15552000,
            'cookie_secure' => 'auto',
            'trust_proxy' => false,
        ],
        'storage' => [
            'path' => '/var/lib/kykky/storage',
        ],
        'limits' => [
            'device_pending_per_minute' => 10,
            'metrics_max_bytes' => 5 * 1024 * 1024,
            'history_max_bytes' => 20 * 1024 * 1024,
            'max_files_per_upload' => 100,
            'max_devices_per_user' => 20,
            'max_sessions_per_user' => 20,
        ],
    ];

    $config = array_replace_recursive($defaults, $loaded);

    // 兼容旧配置：旧版示例使用 security.token_secret / 顶层 app_secret。
    if (empty($config['security']['app_secret'])) {
        if (!empty($loaded['security']['token_secret'])) {
            $config['security']['app_secret'] = $loaded['security']['token_secret'];
        } elseif (!empty($loaded['app_secret'])) {
            $config['security']['app_secret'] = $loaded['app_secret'];
        }
    }

    $config['app']['base_url'] = rtrim((string)$config['app']['base_url'], '/');
    if ($config['app']['base_url'] === '') {
        // 允许在无法配置 base_url 的本地环境回退到当前请求。
        $scheme = kykky_request_scheme();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $config['app']['base_url'] = $scheme . '://' . $host;
    }
    if (!preg_match('#^https?://#i', $config['app']['base_url'])) {
        throw new RuntimeException('app.base_url 必须以 http:// 或 https:// 开头');
    }
    if (!in_array($config['app']['timezone'], timezone_identifiers_list(), true)) {
        throw new RuntimeException('app.timezone 不是有效的时区');
    }
    if (strlen((string)$config['security']['app_secret']) < 16) {
        throw new RuntimeException('security.app_secret 至少需要 16 个字符');
    }
    if (empty($config['oauth']['redirect_uri'])) {
        $config['oauth']['redirect_uri'] = $config['app']['base_url'] . '/api/github.php?action=callback';
    }

    $config['storage']['path'] = rtrim((string)$config['storage']['path'], '/');
    $config['security']['session_ttl'] = max(3600, (int)$config['security']['session_ttl']);
    $config['security']['session_renew_threshold'] = max(0, (int)$config['security']['session_renew_threshold']);

    return $config;
}

function kykky_cfg(string $key, mixed $default = null): mixed
{
    $value = kykky_config();
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function kykky_request_scheme(): string
{
    $trustProxy = (bool)kykky_cfg('security.trust_proxy', false);
    if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https' || $proto === 'http') {
            return $proto;
        }
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return 'https';
    }
    return 'http';
}

function kykky_base_url(): string
{
    return (string)kykky_cfg('app.base_url');
}

function kykky_is_https(): bool
{
    return kykky_request_scheme() === 'https';
}
