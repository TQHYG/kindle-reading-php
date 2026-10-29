(function () {
    'use strict';

    function safeRedirect(value) {
        if (typeof value !== 'string' || value === '') return '/user.html';
        if (value.charAt(0) !== '/' || value.charAt(1) === '/') return '/user.html';
        return value;
    }

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();

        // 兼容旧版 /login.php?action=logout
        if (Kykky.queryParam('action') === 'logout') {
            try {
                await Kykky.logout();
            } catch (e) {
                window.location.replace('/');
            }
            return;
        }

        const redirect = safeRedirect(Kykky.queryParam('redirect', Kykky.queryParam('from', '/user.html')));
        const errorBox = document.getElementById('login-error');
        const error = Kykky.queryParam('error');
        const detail = Kykky.queryParam('detail');

        document.getElementById('github-login').href = Kykky.loginUrl(redirect);

        if (error) {
            const messages = {
                oauth_failed: 'GitHub 登录失败，请重试。',
                oauth_not_configured: '当前服务端尚未配置 GitHub OAuth，请联系管理员。',
                oauth_state: '登录状态校验失败，请重新尝试。'
            };
            errorBox.hidden = false;
            errorBox.textContent = messages[error] || ('登录失败: ' + error) + (detail ? '（' + detail + '）' : '');
        }

        const session = await Kykky.getSession(true);
        if (session.authenticated) {
            window.location.replace(redirect);
            return;
        }

        if (session.dev_login) {
            const devButton = document.getElementById('dev-login');
            devButton.hidden = false;
            devButton.addEventListener('click', async function () {
                Kykky.setBusy(devButton, true, '正在登录...');
                try {
                    await Kykky.devLogin();
                } catch (e) {
                    Kykky.setBusy(devButton, false);
                    errorBox.hidden = false;
                    errorBox.textContent = e.message;
                }
            });
        }
    });
})();
