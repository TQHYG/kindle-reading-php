<?php
require_once __DIR__ . '/func/common.php';
$user = get_current_user_info();
if (!$user) redirect('/login.php?from=/analysis.php');
$exporting = isset($_GET['exporting']) && $_GET['exporting'] == 1;
$nickname = htmlspecialchars($user['nickname']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据分析 - Kykky 阅读数据统计</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
    <style>
        /* 分析仪表板专用样式 */
        .analysis-panel {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.08);
        }
        .highlight-num {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1a1a1a;
        }
        .sub-label {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        /* 日历 */
        .calendar-scroll-area { overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 10px; }
        .calendar-grid-container { min-width: 500px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        .calendar-day-header { text-align: center; font-weight: 600; color: #adb5bd; font-size: 0.8rem; padding: 8px 0; }
        .day-cell {
            background: #f8f9fa; border: 1px solid #edf2f7; border-radius: 10px;
            aspect-ratio: 1/1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; min-height: 60px;
        }
        .day-cell.active { background-color: #e3f2fd; border-color: #90caf9; }
        .day-cell.goal-reached { background-color: #e8f5e9; border-color: #a5d6a7; }
        .day-num { font-size: 0.85rem; font-weight: 700; color: #4a5568; }
        .day-time { font-size: 0.8rem; color: var(--bs-primary, #0d6efd); font-weight: 600; }

        /* 图表容器 */
        .chart-container { position: relative; height: 280px; width: 100%; }

        /* 悬浮导出按钮 */
        .fab-container { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 999; }
        .btn-fab { border-radius: 50px; padding: 12px 30px; box-shadow: 0 10px 20px rgba(13,110,253,0.3); font-weight: bold; }

        /* 导出模式 */
        body.export-mode .fab-container { display: none !important; }

        /* 移动端适配 */
        @media (max-width: 768px) {
            .highlight-num { font-size: 1.6rem; }
            .analysis-panel { padding: 16px; }
            .chart-container { height: 350px; }
        }
    </style>
</head>

<body <?= $exporting ? 'class="export-mode"' : '' ?>>

<?php if (!$exporting): ?>
    <?php include __DIR__ . '/func/header.php'; ?>
<?php endif; ?>

<div class="container py-4" style="max-width: 960px;">

    <!-- 加载中 -->
    <div id="loading-view" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">加载中...</span>
        </div>
        <p class="mt-3 text-muted">正在加载阅读数据...</p>
    </div>

    <!-- 无数据 -->
    <div id="no-data-view" style="display:none;" class="text-center py-5">
        <div class="mb-3"><i class="fa-solid fa-inbox fa-4x text-muted"></i></div>
        <h4 class="text-muted">暂无阅读数据</h4>
        <p class="text-secondary">请先上传 Kindle 日志文件以生成分析报告</p>
        <a href="/upload.php" class="btn btn-primary mt-2">
            <i class="fa-solid fa-cloud-arrow-up me-1"></i> 前往上传
        </a>
    </div>

    <!-- 仪表板 -->
    <div id="dashboard-view" style="display:none;">

        <!-- 月份导航 -->
        <?php if (!$exporting): ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div class="btn-group">
                <button class="btn btn-outline-primary btn-sm" onclick="changeMonth(-1)">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span class="btn btn-outline-primary btn-sm disabled fw-bold" id="current-date-display"
                      style="min-width: 120px; opacity: 1; color: var(--bs-primary, #0d6efd);">
                    加载中...
                </span>
                <button class="btn btn-outline-primary btn-sm" onclick="changeMonth(1)">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
            <div>
                <a href="/upload.php" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> 更新数据
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div id="capture-area">

            <!-- 报告标题 -->
            <div class="d-flex justify-content-between align-items-end mb-4 px-1">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">
                        <?= $nickname ?> 的阅读分析报告
                    </h4>
                    <span class="badge bg-primary-subtle text-primary mt-1 px-3 py-2 rounded-pill" id="report-badge">
                        <i class="fa-regular fa-calendar-check me-1"></i> -- 年 -- 月
                    </span>
                </div>
                <div class="text-end d-none d-md-block">
                    <small class="text-muted">Data Source: Server Import</small>
                </div>
            </div>

            <!-- 概览数据 -->
            <div class="analysis-panel">
                <div class="row text-center g-3">
                    <div class="col-6 col-md-3">
                        <div class="highlight-num text-primary" id="val-today">0</div>
                        <div class="sub-label">最后阅读日(分钟)</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="highlight-num" id="val-month">0</div>
                        <div class="sub-label">本月累计(分钟)</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="highlight-num text-success" id="val-goal">0</div>
                        <div class="sub-label">达标天数</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="highlight-num text-warning" id="val-streak">0</div>
                        <div class="sub-label">最长连续(天)</div>
                    </div>
                </div>
            </div>

            <!-- 图表行 -->
            <div class="row">
                <div class="col-md-6">
                    <div id="sec-today" class="analysis-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0">
                                <i class="fa-solid fa-chart-simple text-primary me-2"></i>时段分布 (最后活跃日)
                            </h6>
                            <button class="btn btn-sm btn-light text-muted" onclick="quickExport('sec-today', '时段分布')">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="todayChart"></canvas>
                        </div>
                        <p class="small text-muted mt-3 mb-0 bg-light p-2 rounded" id="comment-today">
                            <i class="fa-solid fa-lightbulb text-warning me-1"></i> 加载中...
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div id="sec-week" class="analysis-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0">
                                <i class="fa-solid fa-chart-line text-primary me-2"></i>近期每日趋势
                            </h6>
                            <button class="btn btn-sm btn-light text-muted" onclick="quickExport('sec-week', '每日趋势')">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="weekChart"></canvas>
                        </div>
                        <p class="small text-muted mt-3 mb-0 bg-light p-2 rounded" id="comment-week">
                            <i class="fa-solid fa-chart-line text-primary me-1"></i> 加载中...
                        </p>
                    </div>
                </div>
            </div>

            <!-- 月度日历 -->
            <div id="sec-month" class="analysis-panel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold m-0">
                        <i class="fa-solid fa-calendar-days text-primary me-2"></i>全月阅读日历
                    </h6>
                    <button class="btn btn-sm btn-light text-muted" onclick="quickExport('sec-month', '月日历')">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                </div>
                <div class="calendar-scroll-area">
                    <div class="calendar-grid-container">
                        <div class="calendar-grid mb-2">
                            <div class="calendar-day-header">日</div>
                            <div class="calendar-day-header">一</div>
                            <div class="calendar-day-header">二</div>
                            <div class="calendar-day-header">三</div>
                            <div class="calendar-day-header">四</div>
                            <div class="calendar-day-header">五</div>
                            <div class="calendar-day-header">六</div>
                        </div>
                        <div class="calendar-grid" id="calendar-body"></div>
                    </div>
                </div>
            </div>

        </div><!-- #capture-area -->

        <!-- 悬浮导出按钮 -->
        <div class="fab-container">
            <button class="btn btn-primary btn-fab shadow" onclick="exportFullReport()">
                <i class="fa-solid fa-download me-2"></i> 保存长图报告
            </button>
        </div>

    </div><!-- #dashboard-view -->

</div><!-- .container -->

<script src="https://cdn.jsdelivr.net/npm/pako@2.1.0/dist/pako.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script>
/**
 * ===== 全局状态 =====
 */
let globalData = {};
let viewYear = new Date().getFullYear();
let viewMonth = new Date().getMonth() + 1;
let charts = { today: null, week: null };

const urlParams = new URLSearchParams(window.location.search);
const exportMode = urlParams.get('exporting') === '1';
const isMobile = exportMode ? false : window.innerWidth < 768;

/**
 * ===== 图表通用配置 =====
 */
const pcChartConfig = {
    indexAxis: 'x',
    scales: {
        x: { ticks: { maxRotation: 60 }, grid: { display: false } },
        y: { beginAtZero: true, ticks: { callback: v => v + 'm' } }
    }
};
const mobileChartConfig = {
    indexAxis: 'y',
    scales: {
        x: { beginAtZero: true, ticks: { callback: v => v + 'm' } },
        y: { reverse: false }
    }
};
const commonConfig = Object.assign(
    { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    isMobile ? mobileChartConfig : pcChartConfig
);

/**
 * ===== 视图切换 =====
 */
function showView(name) {
    ['loading', 'no-data', 'dashboard'].forEach(v => {
        const el = document.getElementById(v + '-view');
        if (el) el.style.display = (v === name) ? 'block' : 'none';
    });
}

/**
 * ===== 从服务器加载数据 =====
 */
async function loadServerData() {
    try {
        const listRes = await fetch('/api/files.php?action=list');
        const listData = await listRes.json();

        if (!listData.files || listData.files.length === 0) {
            showView('no-data');
            return;
        }

        globalData = {};

        for (const fileInfo of listData.files) {
            const url = '/api/files.php?action=download&file=' + encodeURIComponent(fileInfo.name);
            let content = '';

            if (fileInfo.name === 'history.gz') {
                const res = await fetch(url);
                const buf = await res.arrayBuffer();
                try {
                    content = pako.inflate(new Uint8Array(buf), { to: 'string' });
                } catch (e) {
                    console.warn('解压失败:', fileInfo.name);
                    continue;
                }
            } else {
                const res = await fetch(url);
                content = await res.text();
            }

            parseLogContent(content);
        }

        if (Object.keys(globalData).length === 0) {
            showView('no-data');
            return;
        }

        // URL 参数优先
        const urlY = parseInt(urlParams.get('y'));
        const urlM = parseInt(urlParams.get('m'));
        if (urlY && urlM) {
            viewYear = urlY;
            viewMonth = urlM;
        } else {
            const keys = Object.keys(globalData).sort();
            if (keys.length > 0) {
                const lastKey = keys[keys.length - 1];
                [viewYear, viewMonth] = lastKey.split('-').map(Number);
            }
        }

        showView('dashboard');
        renderDashboard();

        // 导出模式：渲染完成后自动截图
        if (exportMode) {
            setTimeout(() => autoExport('阅读报告-' + viewYear + viewMonth + '-完整版'), 1000);
        }

    } catch (e) {
        console.error('加载数据失败:', e);
        showView('no-data');
    }
}

/**
 * ===== 日志解析核心 =====
 */
function parseLogContent(text) {
    const lines = text.split('\n');
    const regex = /metric_generic,(\d+),.*,com\.lab126\.booklet\.reader\.activeDuration,(\d+)/;

    lines.forEach(line => {
        const match = line.match(regex);
        if (!match) return;

        const endTimeTs = parseInt(match[1]);
        const durationMs = parseInt(match[2]);
        const durationSec = Math.floor(durationMs / 1000);
        if (durationSec <= 0) return;

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

/**
 * ===== 月份切换 =====
 */
function changeMonth(offset) {
    const newDate = new Date(viewYear, viewMonth - 1 + offset, 1);
    viewYear = newDate.getFullYear();
    viewMonth = newDate.getMonth() + 1;

    const newUrl = new URL(window.location.href);
    newUrl.searchParams.set('y', viewYear);
    newUrl.searchParams.set('m', viewMonth);
    window.history.pushState({}, '', newUrl.href);

    renderDashboard();
}

/**
 * ===== 看板渲染主函数 =====
 */
function renderDashboard() {
    document.getElementById('current-date-display').textContent = viewYear + '年' + viewMonth + '月';
    document.getElementById('report-badge').innerHTML =
        '<i class="fa-regular fa-calendar-check me-1"></i> ' + viewYear + '年' + viewMonth + '月';

    const key = viewYear + '-' + viewMonth;
    const monthData = globalData[key] || { days: {}, hours: {} };
    const goal = 30;

    let totalSec = 0, goalDays = 0, maxStreak = 0, currentStreak = 0;
    const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();
    const activeDays = Object.keys(monthData.days)
        .filter(k => !k.endsWith('_sec')).map(Number).sort((a, b) => a - b);
    const lastActiveDay = activeDays.length > 0 ? activeDays[activeDays.length - 1] : 1;

    for (let d = 1; d <= daysInMonth; d++) {
        const mins = monthData.days[d] || 0;
        totalSec += (monthData.days[d + '_sec'] || 0);
        if (mins >= goal) { goalDays++; currentStreak++; }
        else { maxStreak = Math.max(maxStreak, currentStreak); currentStreak = 0; }
    }
    maxStreak = Math.max(maxStreak, currentStreak);

    document.getElementById('val-today').textContent = (monthData.days[lastActiveDay] || 0);
    document.getElementById('val-month').textContent = Math.floor(totalSec / 60);
    document.getElementById('val-goal').textContent = goalDays;
    document.getElementById('val-streak').textContent = maxStreak;

    updateCharts(monthData, lastActiveDay, daysInMonth);
    renderCalendar(monthData, daysInMonth, goal);
    updateComments(monthData, lastActiveDay);
}

/**
 * ===== 图表更新 =====
 */
function updateCharts(monthData, targetDay, totalDays) {
    // 时段分布图
    const dayDist = monthData.hours[targetDay] || new Array(12).fill(0);
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

    // 每日趋势图
    let startDay = targetDay - 6;
    if (startDay < 1) startDay = 1;
    const endDay = Math.min(startDay + 6, totalDays);
    const weekLabels = [], weekData = [];
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
                data: weekData, fill: true,
                backgroundColor: 'rgba(13,110,253,0.1)',
                borderColor: '#0d6efd', tension: 0.4
            }]
        },
        options: commonConfig
    });
}

/**
 * ===== 日历渲染 =====
 */
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
            : '<div class="day-time text-muted" style="opacity:0.3;font-size:0.7rem">-</div>';
        container.innerHTML +=
            '<div class="day-cell ' + cls + '"><div class="day-num">' + d + '</div>' + timeHtml + '</div>';
    }
}

/**
 * ===== 点评 =====
 */
function updateComments(monthData, targetDay) {
    const todayMins = monthData.days[targetDay] || 0;
    const txt = todayMins > 0
        ? '该日阅读了 <strong>' + todayMins + '</strong> 分钟，请继续保持！'
        : '该日暂无阅读记录。';
    document.getElementById('comment-today').innerHTML =
        '<i class="fa-solid fa-lightbulb text-warning me-1"></i> ' + txt;
    document.getElementById('comment-week').innerHTML =
        '<i class="fa-solid fa-chart-line text-primary me-1"></i> 保持阅读习惯，知识复利惊人。';
}

/**
 * ===== 导出功能 =====
 */
async function sectionExport(element, filename) {
    const canvas = await html2canvas(element, {
        scale: 2, useCORS: true, backgroundColor: '#f8f9fa'
    });
    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = filename + '.png';
    link.click();
}

async function fullExport(element, filename) {
    if (window.innerWidth < 768) {
        const currentUrl = window.location.href;
        const separator = currentUrl.includes('?') ? '&' : '?';
        const exportUrl = currentUrl + separator + 'exporting=1';
        const exportWindow = window.open(exportUrl, '_blank');
        if (exportWindow) {
            window.addEventListener('message', function(event) {
                if (event.data === 'export_complete') {
                    console.log('导出完成');
                }
            });
            return;
        }
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
    } catch (e) { console.error('生成失败:', e); }
    if (fab) fab.style.visibility = 'visible';
}

function quickExport(id, name) {
    sectionExport(document.getElementById(id), '阅读统计-' + name);
}

function exportFullReport() {
    fullExport(document.getElementById('capture-area'),
        '阅读报告-' + viewYear + viewMonth + '-完整版');
}

function autoExport(filename) {
    const fab = document.querySelector('.fab-container');
    if (fab) fab.style.display = 'none';

    const notice = document.createElement('div');
    notice.id = 'export-notice';
    notice.innerHTML = '<div style="position:fixed;top:10px;left:10px;right:10px;background:#ffc107;padding:20px;border-radius:5px;text-align:left;z-index:10000;">正在生成截图，请稍候...</div>';
    document.body.appendChild(notice);

    setTimeout(async () => {
        try {
            const canvas = await html2canvas(document.getElementById('capture-area'), {
                scale: 2, useCORS: true, backgroundColor: '#f8f9fa', windowWidth: 1200
            });
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = filename + '.png';
            link.click();
            document.getElementById('export-notice').remove();
            if (window.opener) window.opener.postMessage('export_complete', '*');
        } catch (error) {
            console.error('导出失败:', error);
            document.getElementById('export-notice').innerHTML =
                '<div style="position:fixed;top:10px;left:10px;right:10px;background:#dc3545;color:white;padding:10px;border-radius:5px;text-align:center;z-index:10000;">导出失败，请重试</div>';
        }
    }, 1000);
}

/**
 * ===== 初始化 =====
 */
document.addEventListener('DOMContentLoaded', () => {
    loadServerData();
    window.onpopstate = () => location.reload();
});
</script>

</body>
</html>
