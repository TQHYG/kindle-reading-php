<?php
/**
 * GitHub OAuth 登录 / 回调 / 注销。
 *
 * GET  /api/github.php?action=login&redirect=/user.html
 * GET  /api/github.php?action=callback&code=..&state=..
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

if ($action === 'logout') {
    api_require_method('POST');
    $session = require_session();
    require_csrf($session);
    destroy_current_session();
    api_ok(['logged_out' => true]);
}

api_error('invalid_action', '未知操作', 400);
