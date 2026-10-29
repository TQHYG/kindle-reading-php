/* Kykky 2.0 前端公共库 */
(function () {
    'use strict';

    let sessionCache = null;
    let sessionPromise = null;

    function fallbackSession() {
        return { authenticated: false, oauth_configured: false };
    }

    function errorMessage(payload, response) {
        if (payload && payload.error && payload.error.message) return payload.error.message;
        if (payload && payload.msg) return payload.msg;
        return '请求失败 (HTTP ' + response.status + ')';
    }

    async function request(path, options) {
        options = options || {};
        const method = (options.method || 'GET').toUpperCase();
        const headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
        let body = options.body;

        if (options.csrf) {
            const session = await getSession();
            if (!session.authenticated || !session.csrf) {
                throw new Error('登录状态已失效，请重新登录');
            }
            headers['X-CSRF-Token'] = session.csrf;
        }

        if (body && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        const response = await fetch(path, {
            method: method,
            headers: headers,
            body: body,
            credentials: 'same-origin',
            cache: 'no-store'
        });

        const text = await response.text();
        let payload = null;
        if (text) {
            try {
                payload = JSON.parse(text);
            } catch (e) {
                throw new Error('服务器返回了非 JSON 响应 (HTTP ' + response.status + ')');
            }
        }

        if (response.status === 401) {
            sessionCache = null;
        }
        if (!response.ok || (payload && payload.ok === false) || (payload && payload.status === 'error')) {
            const error = new Error(errorMessage(payload, response));
            error.status = response.status;
            error.payload = payload;
            throw error;
        }
        return payload;
    }

    async function getSession(force) {
        if (!force && sessionCache) return sessionCache;
        if (!force && sessionPromise) return sessionPromise;
        sessionPromise = request('/api/session.php?action=me')
            .then(function (payload) {
                sessionCache = payload.data || fallbackSession();
                return sessionCache;
            })
            .catch(function () {
                sessionCache = fallbackSession();
                return sessionCache;
            })
            .finally(function () {
                sessionPromise = null;
            });
        return sessionPromise;
    }

    function clearSessionCache() {
        sessionCache = null;
    }

    async function requireLogin(redirect) {
        const session = await getSession(true);
        if (!session.authenticated) {
            const target = redirect || (window.location.pathname + window.location.search);
            window.location.replace('/login.html?redirect=' + encodeURIComponent(target));
            throw new Error('需要登录');
        }
        return session;
    }

    async function logout() {
        await request('/api/github.php?action=logout', { method: 'POST', csrf: true });
        clearSessionCache();
        window.location.href = '/';
    }

    function loginUrl(redirect) {
        const target = redirect || (window.location.pathname + window.location.search);
        return '/api/github.php?action=login&redirect=' + encodeURIComponent(target);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDuration(seconds, withSeconds) {
        seconds = Math.max(0, Math.floor(Number(seconds) || 0));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (withSeconds) {
            return h + 'H ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
        }
        if (h > 0) return h + ' 小时 ' + m + ' 分钟';
        return m + ' 分钟';
    }

    function formatMinutes(seconds) {
        return Math.floor(Math.max(0, Number(seconds) || 0) / 60);
    }

    function navHtml(active, session) {
        const items = [
            ['/', 'fa-house', '首页', 'home'],
            ['/rank.html', 'fa-ranking-star', '排行榜', 'rank'],
            ['/analysis.html', 'fa-chart-pie', '数据分析', 'analysis'],
            ['/upload.html', 'fa-cloud-arrow-up', '导入数据', 'upload']
        ];
        let left = '';
        items.forEach(function (item) {
            const cls = 'nav-link' + (active === item[3] ? ' active fw-semibold' : '');
            left += '<li class="nav-item"><a class="' + cls + '" href="' + item[0] + '">' +
                '<i class="fa-solid ' + item[1] + ' me-1"></i>' + item[2] + '</a></li>';
        });

        let right = '';
        if (session && session.authenticated && session.user) {
            const avatar = session.user.avatar
                ? '<img src="' + escapeHtml(session.user.avatar) + '" class="nav-avatar me-2" alt="">'
                : '<i class="fa-solid fa-circle-user me-2"></i>';
            right =
                '<li class="nav-item d-flex align-items-center me-2">' + avatar +
                '<span class="navbar-text">' + escapeHtml(session.user.nickname || 'User') + '</span></li>' +
                '<li class="nav-item"><a class="nav-link" href="/user.html">用户中心</a></li>' +
                '<li class="nav-item"><button class="btn btn-link nav-link text-danger" id="nav-logout" type="button">退出</button></li>';
        } else {
            right = '<li class="nav-item"><a class="btn btn-primary btn-sm" href="/login.html">登录</a></li>';
        }

        return '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">' +
            '<div class="container">' +
            '<a class="navbar-brand" href="/"><i class="fa-solid fa-book-open me-2"></i>Kykky 阅读数据</a>' +
            '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="菜单">' +
            '<span class="navbar-toggler-icon"></span></button>' +
            '<div class="collapse navbar-collapse" id="mainNav">' +
            '<ul class="navbar-nav me-auto">' + left + '</ul>' +
            '<ul class="navbar-nav">' + right + '</ul>' +
            '</div></div></nav>';
    }

    async function initNav(active) {
        const mount = document.getElementById('site-nav');
        const session = await getSession(true);
        if (mount) {
            mount.innerHTML = navHtml(active, session);
            const logoutButton = document.getElementById('nav-logout');
            if (logoutButton) {
                logoutButton.addEventListener('click', function () {
                    logout().catch(function (e) { toast(e.message, 'danger'); });
                });
            }
        }
        return session;
    }

    function toast(message, type) {
        type = type || 'primary';
        let box = document.getElementById('kykky-toast');
        if (!box) {
            box = document.createElement('div');
            box.id = 'kykky-toast';
            box.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:20000;max-width:90vw;';
            document.body.appendChild(box);
        }
        box.innerHTML = '<div class="alert alert-' + type + ' shadow-sm mb-0 py-2 px-3">' +
            escapeHtml(message) + '</div>';
        clearTimeout(box._timer);
        box._timer = setTimeout(function () { box.innerHTML = ''; }, 4000);
    }

    function setBusy(button, busy, busyText) {
        if (!button) return;
        if (busy) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' +
                (busyText || '处理中...');
        } else {
            button.disabled = false;
            if (button.dataset.originalText) button.innerHTML = button.dataset.originalText;
        }
    }

    function queryParam(name, fallback) {
        const value = new URLSearchParams(window.location.search).get(name);
        return value === null || value === '' ? (fallback === undefined ? null : fallback) : value;
    }

    function appendQuery(path, key, value) {
        return path + (path.indexOf('?') >= 0 ? '&' : '?') + encodeURIComponent(key) + '=' + encodeURIComponent(value);
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (isNaN(date.getTime())) return String(value);
        return date.toLocaleString('zh-CN', { hour12: false });
    }

    window.Kykky = {
        request: request,
        getSession: getSession,
        requireLogin: requireLogin,
        logout: logout,
        loginUrl: loginUrl,
        escapeHtml: escapeHtml,
        formatDuration: formatDuration,
        formatMinutes: formatMinutes,
        initNav: initNav,
        toast: toast,
        setBusy: setBusy,
        queryParam: queryParam,
        appendQuery: appendQuery,
        formatDateTime: formatDateTime
    };
})();
