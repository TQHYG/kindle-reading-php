<?php
/**
 * GitHub OAuth 登录 / 回调 / 本地开发登录 / 注销。
 *
 * GET  /api/github.php?action=login&redirect=/user.html
 * GET  /api/github.php?action=callback&code=..&state=..
 * POST /api/github.php?action=dev_login        (仅 app.dev_login + 回环地址)
 * POST /api/github.php?action=logout           (X-CSRF-Token)
 */

require_once __DIR__ . '/bootstrap.php';

function auth_redirect(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

function append_query(string $path, string $key, string $value): string
{
    $separator = str_contains($path, '?') ? '&' : '?';
    return $path . $separator . rawurlencode($key) . '=' . rawurlencode($value);
}

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $redirect = safe_redirect_path($_GET['redirect'] ?? '/user.html');
    if (current_session() !== null) {
        auth_redirect($redirect);
    }
    if ((string)kykky_cfg('oauth.client_id', '') === '' || (string)kykky_cfg('oauth.client_secret', '') === '') {
        auth_redirect(append_query('/login.html', 'error', 'oauth_not_configured'));
    }
    auth_redirect(github_authorize_url($redirect));
}

if ($action === 'callback') {
    $redirect = '/user.html';
    try {
        if (!empty($_GET['error'])) {
            throw new RuntimeException('GitHub 返回错误: ' . (string)$_GET['error']);
        }
        $state = $_GET['state'] ?? null;
        $code = $_GET['code'] ?? null;
        if (!is_string($state) || !is_string($code) || $code === '') {
            throw new RuntimeException('OAuth 回调参数不完整');
        }

        $redirectPath = consume_oauth_state($state);
        if ($redirectPath !== null) {
            $redirect = $redirectPath;
        } else {
            throw new RuntimeException('OAuth state 校验失败或已过期');
        }

        $accessToken = github_exchange_code($code);
        $ghUser = github_fetch_user($accessToken);
        $userId = github_upsert_user($ghUser);
        create_session($userId);
        auth_redirect(append_query($redirect, 'login', 'ok'));
    } catch (Throwable $e) {
        error_log('[kykky oauth] ' . $e->getMessage());
        $target = append_query('/login.html', 'error', 'oauth_failed');
        if ((bool)kykky_cfg('app.debug', false)) {
            $target = append_query($target, 'detail', $e->getMessage());
        }
        auth_redirect($target);
    }
}

if ($action === 'dev_login') {
    api_require_method('POST');
    if (!(bool)kykky_cfg('app.dev_login', false) || !is_loopback_request()) {
        api_error('forbidden', '本地开发登录未启用', 403);
    }

    $userId = db_transaction(function (PDO $pdo): int {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE oauth_provider = 'dev' AND oauth_id = 0 LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row !== false) {
            return (int)$row['id'];
        }
        $insert = $pdo->prepare(
            "INSERT INTO users (oauth_id, oauth_provider, nickname, avatar)
             VALUES (0, 'dev', '本地测试用户', '')"
        );
        $insert->execute();
        $id = (int)$pdo->lastInsertId();
        $stats = $pdo->prepare('INSERT IGNORE INTO stats (user_id) VALUES (?)');
        $stats->execute([$id]);
        return $id;
    });

    create_session($userId);
    api_ok(['redirect' => '/user.html']);
}

if ($action === 'logout') {
    api_require_method('POST');
    $session = require_session();
    require_csrf($session);
    destroy_current_session();
    api_ok(['logged_out' => true]);
}

api_error('invalid_action', '未知操作', 400);
