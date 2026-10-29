<?php
/**
 * Kykky 服务端配置示例
 *
 * 复制到 /etc/kykky/config.php（推荐，Web 根目录之外）。
 *
 * 可通过环境变量 KYKKY_CONFIG 指定配置文件路径。
 */
return [
    'app' => [
        'name' => 'Kykky 阅读数据统计',
        // 对外访问根地址，不要以 / 结尾
        'base_url' => 'https://reading.tqhyg.net',
        'timezone' => 'Asia/Shanghai',
        'debug' => false,
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'kykky_db',
        'user' => 'kykky',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],

    'oauth' => [
        'client_id' => 'GITHUB_CLIENT_ID',
        'client_secret' => 'GITHUB_CLIENT_SECRET',
        // 必须与 GitHub OAuth App 中登记的 callback URL 完全一致
        'redirect_uri' => 'https://reading.tqhyg.net/api/github.php?action=callback',
        'authorize_url' => 'https://github.com/login/oauth/authorize',
        'token_url' => 'https://github.com/login/oauth/access_token',
        'user_url' => 'https://api.github.com/user',
        'scope' => 'read:user',
    ],

    'security' => [
        // 用于设备 token 签名、OAuth state 签名。必须是随机长字符串。
        'app_secret' => 'replace-with-a-long-random-string',
        'session_cookie' => 'kykky_session',
        // 登录有效期：一年；每次访问临近过期时自动续期
        'session_ttl' => 31536000,
        // 剩余不足 180 天时续期
        'session_renew_threshold' => 15552000,
        // auto: HTTPS 时加 Secure；也可强制 true/false
        'cookie_secure' => 'auto',
        // 仅在明确由可信反向代理传递 X-Forwarded-For 时设为 true
        'trust_proxy' => false,
    ],

    'storage' => [
        // 建议放在 Web 根目录之外
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
