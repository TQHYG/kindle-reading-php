<?php
/**
 * Web 会话状态。
 *
 * GET  /api/session.php?action=me
 * POST /api/session.php?action=logout   (X-CSRF-Token)
 */

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? 'me';

if ($action === 'me') {
    $session = current_session();
    if ($session === null) {
        api_ok([
            'authenticated' => false,
            'oauth_configured' => (string)kykky_cfg('oauth.client_id', '') !== '',
        ]);
    }

    $payload = session_public_payload($session);
    $payload['stats'] = stats_for_user((int)$session['user']['id']);
    $payload['oauth_configured'] = (string)kykky_cfg('oauth.client_id', '') !== '';
    api_ok($payload);
}

if ($action === 'logout') {
    api_require_method('POST');
    $session = require_session();
    require_csrf($session);
    destroy_current_session();
    api_ok(['logged_out' => true]);
}

api_error('invalid_action', '未知操作', 400);
