(function () {
    'use strict';

    let syncData = '';

    function showError(message) {
        document.getElementById('sync-loading').hidden = true;
        document.getElementById('sync-preview').hidden = true;
        const box = document.getElementById('sync-error');
        box.hidden = false;
        box.innerHTML = '<div class="alert alert-danger">' + Kykky.escapeHtml(message) + '</div>' +
            '<a href="/user.html" class="btn btn-outline-primary w-100">返回用户中心</a>';
    }

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        syncData = Kykky.queryParam('data', '');

        try {
            await Kykky.requireLogin(window.location.pathname + window.location.search);
        } catch (e) {
            return;
        }
        await Kykky.initNav('user');

        if (!syncData) {
            showError('未检测到同步数据，请重新从 Kindle 扫描二维码。');
            return;
        }

        try {
            const payload = await Kykky.request('/api/fastsync.php?action=preview&data=' + encodeURIComponent(syncData));
            const data = payload.data;
            document.getElementById('sync-loading').hidden = true;
            document.getElementById('sync-preview').hidden = false;
            document.getElementById('sync-today').textContent = Kykky.formatDuration(data.today_seconds);
            document.getElementById('sync-month').textContent = Kykky.formatDuration(data.month_seconds);
            document.getElementById('sync-device').textContent = data.device_name || data.device_code;

            const confirmButton = document.getElementById('sync-confirm');
            confirmButton.addEventListener('click', async function () {
                Kykky.setBusy(confirmButton, true, '正在同步...');
                try {
                    const result = await Kykky.request('/api/fastsync.php?action=confirm', {
                        method: 'POST',
                        csrf: true,
                        body: { data: syncData }
                    });
                    document.getElementById('sync-preview').hidden = true;
                    document.getElementById('sync-success').hidden = false;
                    document.getElementById('sync-success-text').textContent =
                        '今日 ' + Kykky.formatDuration(result.data.today_seconds) +
                        '，本月 ' + Kykky.formatDuration(result.data.month_seconds) + '。';
                } catch (e) {
                    Kykky.setBusy(confirmButton, false);
                    Kykky.toast(e.message, 'danger');
                }
            });
        } catch (e) {
            showError(e.message);
        }
    });
})();
