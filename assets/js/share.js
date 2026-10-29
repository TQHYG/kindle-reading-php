(function () {
    'use strict';

    const NICKNAME_KEY = 'kykky_nickname';
    const params = new URLSearchParams(window.location.search);
    const now = new Date();
    const exportMode = params.get('exporting') === '1';
    const isMobile = exportMode ? false : window.innerWidth < 768;

    function intParam(name, fallback, min, max) {
        const value = parseInt(params.get(name), 10);
        if (!Number.isFinite(value)) return fallback;
        return Math.min(max, Math.max(min, value));
    }

    const year = intParam('year', now.getFullYear(), 2000, 2100);
    const month = intParam('month', now.getMonth() + 1, 1, 12);
    const goal = intParam('goal', 30, 1, 600);
    const daysInMonth = new Date(year, month, 0).getDate();

    const daily = {};
    for (let d = 1; d <= daysInMonth; d++) {
        daily[d] = intParam(String(d), 0, 0, 100000);
    }
    const todayDist = [];
    for (let i = 1; i <= 12; i++) {
        todayDist.push(intParam('d' + i, 0, 0, 100000));
    }

    const labelsToday = ['0-2点', '2-4点', '4-6点', '6-8点', '8-10点', '10-12点',
        '12-14点', '14-16点', '16-18点', '18-20点', '20-22点', '22-24点'];

    const chartConfig = Object.assign({
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }, isMobile ? {
        indexAxis: 'y',
        scales: {
            x: { beginAtZero: true, ticks: { callback: function (v) { return v + 'm'; } } },
            y: { reverse: false }
        }
    } : {
        indexAxis: 'x',
        scales: {
            x: { ticks: { maxRotation: 60 }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { callback: function (v) { return v + 'm'; } } }
        }
    });

    function calculateStats() {
        let totalMonth = 0;
        let goalDays = 0;
        for (let d = 1; d <= daysInMonth; d++) {
            totalMonth += daily[d] || 0;
            if ((daily[d] || 0) >= goal) goalDays++;
        }

        let lastActive = 0;
        for (let d = daysInMonth; d >= 1; d--) {
            if ((daily[d] || 0) > 0) {
                lastActive = d;
                break;
            }
        }
        let streak = 0;
        if (lastActive > 0) {
            for (let d = lastActive; d >= 1; d--) {
                if ((daily[d] || 0) >= goal) streak++;
                else break;
            }
        }

        return {
            totalMonth: totalMonth,
            todayTotal: todayDist.reduce(function (a, b) { return a + b; }, 0),
            goalDays: goalDays,
            streak: streak,
            lastActive: lastActive
        };
    }

    function weekData() {
        let endDay;
        if (year === now.getFullYear() && month === now.getMonth() + 1) {
            endDay = Math.min(now.getDate(), daysInMonth);
        } else {
            endDay = daysInMonth;
        }
        const startDay = endDay - 6;
        const data = [];
        const labels = [];
        for (let i = 0; i < 7; i++) {
            const day = startDay + i;
            if (day <= 0) {
                const previousMonthDays = new Date(year, month - 1, 0).getDate();
                data.push(0);
                labels.push((previousMonthDays + day) + '日*');
            } else if (day > daysInMonth) {
                data.push(0);
                labels.push((day - daysInMonth) + '日*');
            } else {
                data.push(daily[day] || 0);
                labels.push(day + '日');
            }
        }
        return { data: data, labels: labels };
    }

    function commentToday() {
        let max = 0;
        let index = -1;
        todayDist.forEach(function (value, i) {
            if (value > max) {
                max = value;
                index = i;
            }
        });
        return max === 0 ? '这段时间没有阅读记录，继续加油！'
            : '太棒了！你在 ' + labelsToday[index] + ' 精力最集中，阅读了 ' + max + ' 分钟。';
    }

    function commentWeek() {
        const week = weekData();
        let max = 0;
        let index = -1;
        week.data.forEach(function (value, i) {
            if (value > max) {
                max = value;
                index = i;
            }
        });
        return max === 0 ? '这段时间没有阅读记录，继续加油！'
            : '本周 ' + week.labels[index] + ' 是你的阅读巅峰，达到了 ' + max + ' 分钟！';
    }

    function renderStats(stats) {
        document.getElementById('share-today').textContent = stats.todayTotal;
        document.getElementById('share-month').textContent = stats.totalMonth;
        document.getElementById('share-goal-days').textContent = stats.goalDays;
        document.getElementById('share-streak').textContent = stats.streak;
    }

    function renderCalendar() {
        const container = document.getElementById('share-calendar');
        container.innerHTML = '';
        const firstDay = new Date(year, month - 1, 1).getDay();
        for (let i = 0; i < firstDay; i++) container.innerHTML += '<div></div>';
        for (let d = 1; d <= daysInMonth; d++) {
            const min = daily[d] || 0;
            const cls = min >= goal ? 'goal-reached' : (min > 0 ? 'active' : '');
            container.innerHTML += '<div class="day-cell ' + cls + '">' +
                '<span class="day-num">' + d + '</span>' +
                (min > 0 ? '<span class="day-time">' + min + 'm</span>' : '') +
                '</div>';
        }
    }

    function renderCharts() {
        if (typeof Chart === 'undefined') return;
        const week = weekData();
        new Chart(document.getElementById('todayChart'), {
            type: 'bar',
            data: {
                labels: labelsToday,
                datasets: [{ label: '分钟', data: todayDist, backgroundColor: 'rgba(13,110,253,0.7)', borderRadius: 6 }]
            },
            options: chartConfig
        });
        new Chart(document.getElementById('weekChart'), {
            type: 'bar',
            data: {
                labels: week.labels,
                datasets: [{ label: '分钟', data: week.data, backgroundColor: 'rgba(13,110,253,0.7)', borderRadius: 6 }]
            },
            options: chartConfig
        });
    }

    function nickname() {
        return localStorage.getItem(NICKNAME_KEY) || '我';
    }

    function renderNickname() {
        document.getElementById('nickname-display').textContent = nickname();
    }

    function openNicknameModal() {
        document.getElementById('nickname-input').value =
            localStorage.getItem(NICKNAME_KEY) || '';
        document.getElementById('nickname-modal').classList.add('active');
    }

    function closeNicknameModal() {
        document.getElementById('nickname-modal').classList.remove('active');
    }

    function saveNickname() {
        const value = document.getElementById('nickname-input').value.trim();
        if (!value) {
            Kykky.toast('请输入一个昵称', 'warning');
            return;
        }
        localStorage.setItem(NICKNAME_KEY, value);
        renderNickname();
        closeNicknameModal();
    }

    async function sectionExport(element, filename) {
        if (typeof html2canvas === 'undefined') return;
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
        const fab = document.querySelector('.fab-container');
        if (fab) fab.style.visibility = 'hidden';
        try {
            if (typeof html2canvas !== 'undefined') {
                const canvas = await html2canvas(element, {
                    scale: 2, useCORS: true, backgroundColor: '#f8f9fa', windowWidth: 1000
                });
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = filename + '.png';
                link.click();
            }
        } catch (e) {
            console.error('生成失败:', e);
        }
        if (fab) fab.style.visibility = 'visible';
    }

    function autoExport(filename) {
        if (typeof html2canvas === 'undefined') return;
        document.querySelector('.fab-container').style.display = 'none';
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

    document.addEventListener('DOMContentLoaded', function () {
        if (exportMode) document.body.classList.add('export-mode');
        const stats = calculateStats();

        document.title = '阅读报告 - ' + year + '/' + month;
        document.getElementById('share-badge').innerHTML =
            '<i class="fa-regular fa-calendar-check me-1"></i> ' + year + '年' + month + '月';
        document.getElementById('comment-today').innerHTML =
            '<i class="fa-solid fa-quote-left me-1 opacity-50"></i> ' + commentToday();
        document.getElementById('comment-week').innerHTML =
            '<i class="fa-solid fa-quote-left me-1 opacity-50"></i> ' + commentWeek();

        renderNickname();
        renderStats(stats);
        renderCalendar();
        renderCharts();

        document.querySelectorAll('[data-export]').forEach(function (button) {
            button.addEventListener('click', function () {
                sectionExport(document.getElementById(button.dataset.export), '阅读统计-' + button.dataset.name);
            });
        });
        document.getElementById('share-export').addEventListener('click', function () {
            fullExport(document.getElementById('capture-area'), '阅读报告-全程回顾');
        });

        document.getElementById('nickname-display').addEventListener('click', openNicknameModal);
        document.getElementById('nickname-save').addEventListener('click', saveNickname);
        document.getElementById('nickname-cancel').addEventListener('click', closeNicknameModal);

        if (!localStorage.getItem(NICKNAME_KEY)) openNicknameModal();
        if (exportMode) autoExport('阅读报告全程回顾');
    });
})();
