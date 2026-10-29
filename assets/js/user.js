(function () {
    'use strict';

    let currentSession = null;

    function showAlert(message, type) {
        const box = document.getElementById('page-alert');
        box.hidden = false;
        box.innerHTML = '<div class="alert alert-' + (type || 'danger') + '">' +
            Kykky.escapeHtml(message) + '</div>';
    }

    function renderProfile(session) {
        const user = session.user;
        const avatar = document.getElementById('profile-avatar');
        if (user.avatar) {
            avatar.innerHTML = '<img src="' + Kykky.escapeHtml(user.avatar) +
                '" class="rounded-circle border" width="80" height="80" alt="">';
        } else {
            avatar.innerHTML = '<div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" ' +
                'style="width:80px;height:80px;"><i class="fa-solid fa-user fa-2x text-secondary"></i></div>';
        }
        document.getElementById('profile-name').textContent = user.nickname || 'User';
    }

    function renderStats(stats) {
        document.getElementById('stat-today').textContent = Kykky.formatDuration(stats.today_seconds);
        document.getElementById('stat-month').textContent = Kykky.formatDuration(stats.month_seconds);
    }

    function renderDevices(devices) {
        const tbody = document.getElementById('device-table');
        document.getElementById('device-count').textContent = devices.length + ' 台设备';
        if (!devices.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-5">暂无绑定设备</td></tr>';
            return;
        }
        tbody.innerHTML = devices.map(function (device) {
            return '<tr>' +
                '<td><code class="bg-light px-2 py-1 rounded">' + Kykky.escapeHtml(device.code) + '</code></td>' +
                '<td><strong>' + Kykky.escapeHtml(device.device_name || '未命名设备') + '</strong></td>' +
                '<td>' + Kykky.escapeHtml(device.expires_at) + '</td>' +
                '<td class="text-end"><div class="d-flex justify-content-end gap-2">' +
                '<button class="btn btn-outline-secondary btn-sm" data-action="rename" data-code="' +
                Kykky.escapeHtml(device.code) + '" data-name="' + Kykky.escapeHtml(device.device_name || '') + '">' +
                '<i class="fa-solid fa-pen-to-square me-1"></i>重命名</button>' +
                '<button class="btn btn-outline-danger btn-sm" data-action="delete" data-code="' +
                Kykky.escapeHtml(device.code) + '"><i class="fa-solid fa-trash-can me-1"></i>删除</button>' +
                '</div></td></tr>';
        }).join('');
    }

    async function loadDevices() {
        const payload = await Kykky.request('/api/devices.php?action=list');
        renderDevices(payload.data.devices || []);
    }

    async function loadPage() {
        try {
            currentSession = await Kykky.requireLogin('/user.html');
        } catch (e) {
            return;
        }
        renderProfile(currentSession);

        try {
            const results = await Promise.all([
                Kykky.request('/api/stats.php'),
                Kykky.request('/api/devices.php?action=list')
            ]);
            renderStats(results[0].data);
            renderDevices(results[1].data.devices || []);
        } catch (e) {
            showAlert(e.message);
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        await Kykky.initNav('user');
        await loadPage();

        document.getElementById('device-table').addEventListener('click', async function (event) {
            const button = event.target.closest('button[data-action]');
            if (!button) return;
            const action = button.dataset.action;
            const code = button.dataset.code;

            if (action === 'rename') {
                const oldName = button.dataset.name || '';
                const newName = window.prompt('请输入新的设备名称：', oldName);
                if (newName === null) return;
                const name = newName.trim();
                if (!name) {
                    Kykky.toast('设备名称不能为空', 'warning');
                    return;
                }
                Kykky.setBusy(button, true, '保存中');
                try {
                    await Kykky.request('/api/devices.php?action=rename', {
                        method: 'POST',
                        csrf: true,
                        body: { code: code, device_name: name }
                    });
                    Kykky.toast('设备名称已更新', 'success');
                    await loadDevices();
                } catch (e) {
                    Kykky.toast(e.message, 'danger');
                    Kykky.setBusy(button, false);
                }
            }

            if (action === 'delete') {
                if (!window.confirm('确定要删除此设备吗？删除后该设备上的 Token 将立即失效。')) return;
                Kykky.setBusy(button, true, '删除中');
                try {
                    await Kykky.request('/api/devices.php?action=delete', {
                        method: 'POST',
                        csrf: true,
                        body: { code: code }
                    });
                    Kykky.toast('设备已删除', 'success');
                    await loadDevices();
                } catch (e) {
                    Kykky.toast(e.message, 'danger');
                    Kykky.setBusy(button, false);
                }
            }
        });
    });
})();
