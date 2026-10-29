(function () {
    'use strict';

    let selectedFiles = [];
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const uploadButton = document.getElementById('upload-btn');

    function handleFiles(files) {
        const valid = [];
        const invalid = [];
        Array.from(files).forEach(function (file) {
            if (file.name === 'history.gz' || file.name.startsWith('metrics_reader_')) {
                valid.push(file);
            } else if (!file.name.startsWith('.')) {
                invalid.push(file.name);
            }
        });

        if (invalid.length) {
            Kykky.toast('包含非法文件: ' + invalid.slice(0, 3).join(', ') + '，请只选择 log 目录内容', 'danger');
            selectedFiles = [];
            uploadButton.disabled = true;
            document.getElementById('file-list').textContent = '';
            return;
        }

        selectedFiles = valid;
        document.getElementById('file-list').textContent = '已识别 ' + selectedFiles.length + ' 个合法日志文件';
        uploadButton.disabled = selectedFiles.length === 0;
    }

    async function upload() {
        if (!selectedFiles.length) return;
        if (!window.confirm('确认上传并覆盖服务器上的现有日志与排行数据？')) return;

        const resultBox = document.getElementById('upload-result');
        resultBox.hidden = true;
        Kykky.setBusy(uploadButton, true, '正在上传并解析...');

        const formData = new FormData();
        selectedFiles.forEach(function (file) {
            formData.append('logs[]', file, file.name);
        });

        try {
            const payload = await Kykky.request('/api/upload.php', {
                method: 'POST',
                csrf: true,
                body: formData
            });
            const today = Kykky.formatDuration(payload.today_seconds || 0);
            const month = Kykky.formatDuration(payload.month_seconds || 0);
            resultBox.hidden = false;
            resultBox.innerHTML =
                '<div class="alert alert-success">' +
                '<h5 class="alert-heading"><i class="fa-solid fa-circle-check me-1"></i> 同步成功</h5>' +
                '<div class="data-row"><span class="data-label">今日阅读</span><span class="data-value">' +
                Kykky.escapeHtml(today) + '</span></div>' +
                '<div class="data-row"><span class="data-label">本月累计</span><span class="data-value">' +
                Kykky.escapeHtml(month) + '</span></div>' +
                '<div class="data-row"><span class="data-label">文件数量</span><span class="data-value">' +
                Number(payload.files_count || 0) + '</span></div>' +
                '<div class="mt-3 d-flex gap-2 flex-wrap">' +
                '<a href="/user.html" class="btn btn-primary btn-sm">返回用户中心</a>' +
                '<a href="/analysis.html" class="btn btn-outline-primary btn-sm">查看数据分析</a>' +
                '</div></div>';
            uploadButton.textContent = '同步成功';
            uploadButton.disabled = true;
            Kykky.toast('同步成功', 'success');
        } catch (e) {
            Kykky.setBusy(uploadButton, false);
            resultBox.hidden = false;
            resultBox.innerHTML = '<div class="alert alert-danger">' +
                Kykky.escapeHtml(e.message) + '</div>';
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        try {
            await Kykky.requireLogin('/upload.html');
        } catch (e) {
            return;
        }
        await Kykky.initNav('upload');

        dropZone.addEventListener('click', function () { fileInput.click(); });
        fileInput.addEventListener('change', function (event) { handleFiles(event.target.files); });

        dropZone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropZone.classList.add('hover');
        });
        dropZone.addEventListener('dragleave', function () { dropZone.classList.remove('hover'); });
        dropZone.addEventListener('drop', async function (event) {
            event.preventDefault();
            dropZone.classList.remove('hover');
            const allFiles = [];
            const items = event.dataTransfer.items;

            async function traverse(entry) {
                if (entry.isFile) {
                    allFiles.push(await new Promise(function (resolve) { entry.file(resolve); }));
                } else if (entry.isDirectory) {
                    const reader = entry.createReader();
                    const entries = await new Promise(function (resolve) { reader.readEntries(resolve); });
                    for (const child of entries) await traverse(child);
                }
            }

            for (const item of items) {
                const entry = item.webkitGetAsEntry && item.webkitGetAsEntry();
                if (entry) await traverse(entry);
            }
            handleFiles(allFiles);
        });

        uploadButton.addEventListener('click', upload);
    });
})();
