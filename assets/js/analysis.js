(function () {
    'use strict';

    let globalData = {};
    let viewYear = new Date().getFullYear();
    let viewMonth = new Date().getMonth() + 1;
    let charts = { today: null, week: null };
    let currentSession = null;

    const urlParams = new URLSearchParams(window.location.search);
    const exportMode = urlParams.get('exporting') === '1';
    const isMobile = exportMode ? false : window.innerWidth < 768;
    const GOAL_MINUTES = 30;

    const pcChartConfig = {
        indexAxis: 'x',
        scales: {
            x: { ticks: { maxRotation: 60 }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { callback: function (v) { return v + 'm'; } } }
        }
    };
    const mobileChartConfig = {
        indexAxis: 'y',
        scales: {
            x: { beginAtZero: true, ticks: { callback: function (v) { return v + 'm'; } } },
            y: { reverse: false }
        }
    };
    const commonConfig = Object.assign(
        { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
        isMobile ? mobileChartConfig : pcChartConfig
    );

    function showView(name) {
        ['loading', 'no-data', 'dashboard'].forEach(function (view) {
            const element = document.getElementById(view + '-view');
            if (element) element.hidden = view !== name;
        });
    }

    function parseLogContent(text) {
        const regex = /metric_generic,(\d+),.*,com\.lab126\.booklet\.reader\.activeDuration,(\d+)/;
        text.split('\n').forEach(function (line) {
            const match = line.match(regex);
            if (!match) return;

            const endTimeTs = parseInt(match[1], 10);
            const durationSec = Math.floor(parseInt(match[2], 10) / 1000);
            if (!(durationSec > 0)) return;

            let currentTs = endTimeTs - durationSec;
            let remainingDuration = durationSec;

            while (remainingDuration > 0) {
                const dateObj = new Date(currentTs * 1000);
                const y = dateObj.getFullYear();
                const m = dateObj.getMonth() + 1;
                const d = dateObj.getDate();
                const h = dateObj.getHours();
                const key = y + '-' + m;

                if (!globalData[key]) globalData[key] = { days: {}, hours: {} };
                if (!globalData[key].days[d]) globalData[key].days[d] = 0;
                if (!globalData[key].hours[d]) globalData[key].hours[d] = new Array(12).fill(0);
                if (!globalData[key].days[d + '_sec']) globalData[key].days[d + '_sec'] = 0;

                const tomorrow = new Date(dateObj);
                tomorrow.setDate(d + 1);
                tomorrow.setHours(0, 0, 0, 0);
                const secondsUntilTomorrow = (tomorrow.getTime() / 1000) - currentTs;
                const timeInThisDay = Math.min(remainingDuration, secondsUntilTomorrow);

                globalData[key].days[d + '_sec'] += timeInThisDay;
                globalData[key].days[d] = Math.floor(globalData[key].days[d + '_sec'] / 60);

                const blockIndex = Math.floor(h / 2);
                globalData[key].hours[d][blockIndex] += Math.floor(timeInThisDay / 60);

                remainingDuration -= timeInThisDay;
                currentTs += timeInThisDay;
            }
        });
    }

    async function downloadFile(fileInfo) {
        const url = '/api/files.php?action=download&file=' + encodeURIComponent(fileInfo.name);
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) {
            throw new Error('下载日志失败: ' + fileInfo.name);
        }
        if (fileInfo.name === 'history.gz') {
            const buffer = await response.arrayBuffer();
            return pako.inflate(new Uint8Array(buffer), { to: 'string' });
        }
        return response.text();
    }

    async function loadServerData() {
        try {
            const payload = await Kykky.request('/api/files.php?action=list');
            const files = payload.files || [];
            if (!files.length) {
                showView('no-data');
                return;
            }

            globalData = {};
            for (const fileInfo of files) {
                try {
                    parseLogContent(await downloadFile(fileInfo));
                } catch (e) {
                    console.warn(e.message);
                }
            }

            if (Object.keys(globalData).length === 0) {
                showView('no-data');
                return;
            }

            const urlY = parseInt(urlParams.get('y'), 10);
            const urlM = parseInt(urlParams.get('m'), 10);
            if (urlY && urlM) {
                viewYear = urlY;
                viewMonth = urlM;
            } else {
                const keys = Object.keys(globalData).sort(function (a, b) {
                    const pa = a.split('-').map(Number);
                    const pb = b.split('-').map(Number);
                    return (pa[0] * 12 + pa[1]) - (pb[0] * 12 + pb[1]);
                });
                const lastKey = keys[keys.length - 1];
                const parts = lastKey.split('-').map(Number);
                viewYear = parts[0];
                viewMonth = parts[1];
            }

            if (exportMode) document.body.classList.add('export-mode');
            showView('dashboard');
            renderDashboard();

            if (exportMode) {
                setTimeout(function () { autoExport('阅读报告-' + viewYear + viewMonth + '-完整版'); }, 1000);
            }
        } catch (e) {
            console.error(e);
            showView('no-data');
            Kykky.toast(e.message, 'danger');
        }
    }

    function renderDashboard() {
        const key = viewYear + '-' + viewMonth;
        const monthData = globalData[key] || { days: {}, hours: {} };
        const goal = GOAL_MINUTES;
        const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();

        let totalSec = 0;
        let goalDays = 0;
        let maxStreak = 0;
        let currentStreak = 0;
        const activeDays = Object.keys(monthData.days)
            .filter(function (k) { return !k.endsWith('_sec'); })
            .map(Number)
            .sort(function (a, b) { return a - b; });
        const lastActiveDay = activeDays.length > 0 ? activeDays[activeDays.length - 1] : 1;

        for (let d = 1; d <= daysInMonth; d++) {
            const mins = monthData.days[d] || 0;
            totalSec += (monthData.days[d + '_sec'] || 0);
            if (mins >= goal) {
                goalDays++;
                currentStreak++;
            } else {
                maxStreak = Math.max(maxStreak, currentStreak);
                currentStreak = 0;
            }
        }
        maxStreak = Math.max(maxStreak, currentStreak);

        document.getElementById('val-last-day').textContent = monthData.days[lastActiveDay] || 0;
        document.getElementById('val-month').textContent = Math.floor(totalSec / 60);
        document.getElementById('val-goal').textContent = goalDays;
        document.getElementById('val-streak').textContent = maxStreak;

        document.getElementById('current-date-display').textContent = viewYear + '年' + viewMonth + '月';
        document.getElementById('report-badge').innerHTML =
            '<i class="fa-regular fa-calendar-check me-1"></i> ' + viewYear + '年' + viewMonth + '月';

        updateCharts(monthData, lastActiveDay, daysInMonth);
        renderCalendar(monthData, daysInMonth, goal);
        updateComments(monthData, lastActiveDay);
    }

    function updateCharts(monthData, targetDay, totalDays) {
        if (typeof Chart === 'undefined') return;
        const dayDist = (monthData.hours && monthData.hours[targetDay]) || new Array(12).fill(0);
        const labelsToday = ['0-2', '2-4', '4-6', '6-8', '8-10', '10-12',
            '12-14', '14-16', '16-18', '18-20', '20-22', '22-24'];

        const ctxToday = document.getElementById('todayChart').getContext('2d');
        if (charts.today) charts.today.destroy();
        charts.today = new Chart(ctxToday, {
            type: 'bar',
            data: {
                labels: labelsToday,
                datasets: [{ data: dayDist, backgroundColor: 'rgba(13,110,253,0.7)', borderRadius: 4 }]
            },
            options: commonConfig
        });

        let startDay = targetDay - 6;
        if (startDay < 1) startDay = 1;
        const endDay = Math.min(startDay + 6, totalDays);
        const weekLabels = [];
        const weekData = [];
        for (let i = startDay; i <= endDay; i++) {
            weekLabels.push(i + '日');
            weekData.push(monthData.days[i] || 0);
        }

        const ctxWeek = document.getElementById('weekChart').getContext('2d');
        if (charts.week) charts.week.destroy();
        charts.week = new Chart(ctxWeek, {
            type: 'line',
            data: {
                labels: weekLabels,
                datasets: [{
                    data: weekData,
                    fill: true,
                    backgroundColor: 'rgba(13,110,253,0.1)',
                    borderColor: '#0d6efd',
                    tension: 0.4
                }]
            },
            options: commonConfig
        });
    }

    function renderCalendar(monthData, daysInMonth, goal) {
        const container = document.getElementById('calendar-body');
        container.innerHTML = '';
        const firstDay = new Date(viewYear, viewMonth - 1, 1).getDay();
        for (let i = 0; i < firstDay; i++) container.innerHTML += '<div></div>';

        for (let d = 1; d <= daysInMonth; d++) {
            const min = monthData.days[d] || 0;
            let cls = '';
            if (min > 0) cls = min >= goal ? 'goal-reached' : 'active';
            const timeHtml = min > 0
                ? '<div class="day-time">' + min + 'm</div>'
                : '<div class="day-time text-muted" style="opacity:.3;font-size:.7rem">-</div>';
            container.innerHTML += '<div class="day-cell ' + cls + '"><div class="day-num">' + d + '</div>' + timeHtml + '</div>';
        }
    }

    function updateComments(monthData, targetDay) {
        const todayMins = monthData.days[targetDay] || 0;
        document.getElementById('comment-today').innerHTML =
            '<i class="fa-solid fa-lightbulb text-warning me-1"></i> ' +
            (todayMins > 0
                ? '该日阅读了 <strong>' + todayMins + '</strong> 分钟，请继续保持！'
                : '该日暂无阅读记录。');
        document.getElementById('comment-week').innerHTML =
            '<i class="fa-solid fa-chart-line text-primary me-1"></i> 保持阅读习惯，知识复利惊人。';
    }

    function changeMonth(offset) {
        const date = new Date(viewYear, viewMonth - 1 + offset, 1);
        viewYear = date.getFullYear();
        viewMonth = date.getMonth() + 1;
        const url = new URL(window.location.href);
        url.searchParams.set('y', viewYear);
        url.searchParams.set('m', viewMonth);
        window.history.pushState({}, '', url.href);
        renderDashboard();
    }

    async function sectionExport(element, filename) {
        if (typeof html2canvas === 'undefined') {
            Kykky.toast('截图组件未加载，请稍后重试', 'warning');
            return;
        }
        const canvas = await html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#f8f9fa' });
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = filename + '.png';
        link.click();
    }

    async function fullExport(element, filename) {
        if (window.innerWidth < 768 && !exportMode) {
            const currentUrl = window.location.href;
            const exportUrl = currentUrl + (currentUrl.includes('?') ? '&' : '?') + 'exporting=1';
            const exportWindow = window.open(exportUrl, '_blank');
            if (exportWindow) {
                window.addEventListener('message', function (event) {
                    if (event.data === 'export_complete') console.log('导出完成');
                });
                return;
            }
        }
        if (typeof html2canvas === 'undefined') {
            Kykky.toast('截图组件未加载，请稍后重试', 'warning');
            return;
        }
        const fab = document.querySelector('.fab-container');
        if (fab) fab.style.visibility = 'hidden';
        try {
            const canvas = await html2canvas(element, {
                scale: 2, useCORS: true, backgroundColor: '#f8f9fa', windowWidth: 1000
            });
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = filename + '.png';
            link.click();
        } catch (e) {
            console.error('生成失败:', e);
            Kykky.toast('生成图片失败，请重试', 'danger');
        }
        if (fab) fab.style.visibility = 'visible';
    }

    function autoExport(filename) {
        if (typeof html2canvas === 'undefined') return;
        const fab = document.querySelector('.fab-container');
        if (fab) fab.style.display = 'none';

        const notice = document.createElement('div');
        notice.id = 'export-notice';
        notice.innerHTML = '<div style="position:fixed;top:10px;left:10px;right:10px;background:#ffc107;padding:20px;border-radius:5px;text-align:left;z-index:10000;">正在生成截图，请稍候...</div>';
        document.body.appendChild(notice);

        setTimeout(async function () {
            try {
                const canvas = await html2canvas(document.getElementById('capture-area'), {
                    scale: 2, useCORS: true, backgroundColor: '#f8f9fa', windowWidth: 1200
                });
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = filename + '.png';
                link.click();
                notice.remove();
                if (window.opener) window.opener.postMessage('export_complete', '*');
            } catch (error) {
                console.error('导出失败:', error);
                notice.innerHTML = '<div style="position:fixed;top:10px;left:10px;right:10px;background:#dc3545;color:white;padding:10px;border-radius:5px;text-align:center;z-index:10000;">导出失败，请重试</div>';
            }
        }, 1000);
    }

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        try {
            currentSession = await Kykky.requireLogin('/analysis.html' + window.location.search);
        } catch (e) {
            return;
        }
        await Kykky.initNav('analysis');
        document.getElementById('report-nickname').textContent = currentSession.user.nickname || '我';

        document.getElementById('month-prev').addEventListener('click', function () { changeMonth(-1); });
        document.getElementById('month-next').addEventListener('click', function () { changeMonth(1); });
        document.getElementById('export-full').addEventListener('click', function () {
            fullExport(document.getElementById('capture-area'), '阅读报告-' + viewYear + viewMonth + '-完整版');
        });
        document.querySelectorAll('[data-export]').forEach(function (button) {
            button.addEventListener('click', function () {
                sectionExport(document.getElementById(button.dataset.export), '阅读统计-' + button.dataset.name);
            });
        });
        window.onpopstate = function () { window.location.reload(); };

        await loadServerData();
    });
})();
