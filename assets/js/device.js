(function () {
    'use strict';

    let deviceCode = '';
    let session = null;

    function showError(message) {
        document.getElementById('device-loading').hidden = true;
        document.getElementById('device-form-wrap').hidden = true;
        const box = document.getElementById('device-error');
        box.hidden = false;
        box.innerHTML = '<div class="alert alert-danger">' + Kykky.escapeHtml(message) + '</div>' +
            '<a href="/user.html" class="btn btn-outline-primary w-100">返回用户中心</a>';
    }

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        deviceCode = Kykky.queryParam('device_code', '');
        const redirect = '/device.html?device_code=' + encodeURIComponent(deviceCode);

        try {
            session = await Kykky.requireLogin(redirect);
        } catch (e) {
            return;
        }
        await Kykky.initNav('user');

        if (!deviceCode) {
            showError('未检测到设备码，请重新在 Kindle 上生成二维码。');
            return;
        }

        document.getElementById('device-code').value = deviceCode;

        try {
            const payload = await Kykky.request('/api/devices.php?action=info&code=' + encodeURIComponent(deviceCode));
            const device = payload.data;
            document.getElementById('device-loading').hidden = true;

            if (!device.can_bind && device.status === 1) {
                document.getElementById('device-success').hidden = false;
                document.getElementById('device-success-text').textContent =
                    '设备「' + (device.device_name || '我的 Kindle') + '」已与您的账号关联。';
                return;
            }

            document.getElementById('device-form-wrap').hidden = false;
            const nameInput = document.getElementById('device-name');
            nameInput.value = device.device_name || '';
            nameInput.focus();
        } catch (e) {
            showError(e.message);
        }

        document.getElementById('device-form').addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = document.getElementById('bind-button');
            const name = document.getElementById('device-name').value.trim();
            if (!name) {
                Kykky.toast('请输入设备名称', 'warning');
                return;
            }

            Kykky.setBusy(button, true, '绑定中...');
            try {
                const payload = await Kykky.request('/api/devices.php?action=bind', {
                    method: 'POST',
                    csrf: true,
                    body: { code: deviceCode, device_name: name }
                });
                document.getElementById('device-form-wrap').hidden = true;
                document.getElementById('device-success').hidden = false;
                document.getElementById('device-success-text').textContent =
                    '设备「' + (payload.data.device_name || name) + '」已与您的账号关联。请回到 Kindle 继续。';
            } catch (e) {
                Kykky.setBusy(button, false);
                Kykky.toast(e.message, 'danger');
            }
        });
    });
})();
