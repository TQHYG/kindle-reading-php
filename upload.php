<?php
$exporting = isset($_GET['exporting']) && $_GET['exporting'] == 1;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kindle 阅读数据导入</title>
    
    <link href="style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root { --primary-color: #0d6efd; --bg-color: #f4f7f9; }
        body { background: var(--bg-color); min-height: 100vh; font-family: system-ui, -apple-system, sans-serif; }
        
        /* 上传区域样式 */
        #upload-view { 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            min-height: 80vh; 
            max-width: 520px !important;
        }
        .upload-box {
            border: 2px dashed #cbd5e0; 
            border-radius: 16px; 
            padding: 40px; 
            text-align: center;
            align-items: center;
            background: white; 
            transition: all 0.3s; 
            cursor: pointer; 
            max-width: 500px; 
            width: 100%;
        }
        .upload-box:hover, .upload-box.drag-over { border-color: var(--primary-color); background: #f8fbff; }
        .upload-icon { font-size: 4rem; color: #a0aec0; margin-bottom: 20px; transition: color 0.3s; }
        .upload-box:hover .upload-icon { color: var(--primary-color); }
        
        /* 导航栏 */
        .nav-header { background: #fff; padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        
        /* 隐藏原有的 FAB， */
        .fab-container { display: none; }
        
        /* 控制视图显示 */
        .view-section { display: none; }
        .view-section.active { display: block; }

        /* 加载动画 */
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; margin-right: 10px;}
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>

<body <?php echo $exporting ? 'class="export-mode"' : ''; ?>>

<div id="upload-view" class="container view-section">
    <div class="text-center mb-5">
        <h2 class="fw-bold text-dark bigTitle">导入 Kindle 阅读记录</h2>
        <p class="text-muted">数据仅在本地处理与存储，不会上传服务器</p>
    </div>

    <div class="upload-box" id="drop-zone" onclick="document.getElementById('dir-input').click()">
        <input type="file" id="dir-input" webkitdirectory directory multiple style="display: none">
        <div class="upload-icon">
            <i class="fa-solid fa-folder-open"></i>
        </div>
        <h4 class="fw-bold">点击选择文件夹</h4>
        <p class="text-secondary small mb-3">
            请拖入或选择 Kindle 磁盘中的 <code class="bg-light px-2 py-1 rounded">extensions/kykky/log</code> 目录
        </p>
        <p class="text-muted small">
            包含 <strong>metrics_reader_*</strong> 及 <strong>history.gz</strong> 文件
        </p>
        <div id="loading-msg" class="d-none text-primary mt-3">
            <div class="loader"></div> 正在解析日志，请稍候...
        </div>
    </div>
    
    <div class="mt-4 text-center">
        <a href="index.php" class="text-decoration-none text-muted"><i class="fa-solid fa-arrow-left"></i> 返回首页</a>
    </div>
</div>

<div id="dashboard-view" class="view-section">
    <div class="nav-header sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-3"><i class="fa-solid fa-home"></i></a>
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-sm" onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <span class="btn btn-outline-primary btn-sm disabled fw-bold" id="current-date-display" style="width: 120px; opacity: 1; color: var(--primary-color);">Loading...</span>
                    <button class="btn btn-outline-primary btn-sm" onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            
            <div>
                <button class="btn btn-outline-primary btn-sm me-2" onclick="document.getElementById('dir-input').click()">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> 更新数据
                </button>
                
                <button class="btn btn-danger btn-sm text-white" onclick="clearData()">
                    <i class="fa-solid fa-trash-can"></i> 清除
                </button>
            </div>
            </div>
    </div>

    <div class="container pb-5" id="export-container">
        <div id="capture-area">
            <div class="d-flex justify-content-between align-items-end mb-4 px-2">
                <div>
                    <h3 class="fw-bold mb-0 text-dark">阅读统计分析报告</h3>
                    <span class="badge bg-primary-subtle text-primary mt-2 px-3 py-2 rounded-pill" id="report-badge">
                        <i class="fa-regular fa-calendar-check me-1"></i> -- 年 -- 月
                    </span>
                </div>
                <div class="text-end d-none d-md-block">
                    <small class="text-muted">Data Source: Local Import</small>
                </div>
            </div>

            <div id="sec-overview" class="stat-card">
                <div class="row text-center g-3">
                    <div class="col-6 col-md-3">
                        <div class="highlight-num text-primary" id="val-today">0</div>
                        <div class="sub-label">最后阅读日(m)</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="highlight-num" id="val-month">0</div>
                        <div class="sub-label">本月累计(m)</div>
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

            <div class="row">
                <div class="col-md-6">
                    <div id="sec-today" class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0"><i class="fa-solid fa-chart-simple text-primary me-2"></i>时段分布 (最后活跃日)</h6>
                            <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="quickExport('sec-today', '时段分布')"><i class="fa-solid fa-camera"></i> 导出</button>
                        </div>
                        <div class="chart-container">
                            <canvas id="todayChart"></canvas>
                        </div>
                        <p class="small text-muted mt-3 mb-0 bg-light p-2 rounded" id="comment-today">...</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div id="sec-week" class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0"><i class="fa-solid fa-chart-line text-primary me-2"></i>本月每日趋势</h6>
                            <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="quickExport('sec-week', '月度趋势')"><i class="fa-solid fa-camera"></i> 导出</button>
                        </div>
                        <div class="chart-container">
                            <canvas id="weekChart"></canvas>
                        </div>
                        <p class="small text-muted mt-3 mb-0 bg-light p-2 rounded" id="comment-week">...</p>
                    </div>
                </div>
            </div>

            <div id="sec-month" class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold m-0"><i class="fa-solid fa-calendar-days text-primary me-2"></i>全月阅读日历</h6>
                    <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="quickExport('sec-month', '月日历')"><i class="fa-solid fa-camera"></i> 导出</button>
                </div>
                
                <div class="calendar-scroll-area">
                    <div class="calendar-grid-container">
                        <div class="calendar-grid mb-2">
                            <div class="calendar-day-header">SUN</div><div class="calendar-day-header">MON</div>
                            <div class="calendar-day-header">TUE</div><div class="calendar-day-header">WED</div>
                            <div class="calendar-day-header">THU</div><div class="calendar-day-header">FRI</div>
                            <div class="calendar-day-header">SAT</div>
                        </div>
                        <div class="calendar-grid" id="calendar-body">
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="fab-container" style="display:block">
        <button class="btn btn-primary btn-fab shadow" onclick="exportFullReport()">
            <i class="fa-solid fa-download me-2"></i> 保存长图报告
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/pako@2.1.0/dist/pako.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script>
/**
 * 全局状态管理
 */
const STORAGE_KEY = 'kykky_reading_data';
const urlParams = new URLSearchParams(window.location.search);
const exportMode = urlParams.get('exporting') === '1';
const isMobile = exportMode ? false : window.innerWidth < 768;
let globalData = {}; // 结构: { '2025-12': { days: {1: 30, ...}, hours: {1: [0,0...], ...} } }
let viewYear = new Date().getFullYear();
let viewMonth = new Date().getMonth() + 1;
let charts = { today: null, week: null };

// 1. 初始化检查
document.addEventListener('DOMContentLoaded', () => {
    // --- 尝试从 URL 获取日期参数 ---
    const urlParams = new URLSearchParams(window.location.search);
    const urlY = parseInt(urlParams.get('y'));
    const urlM = parseInt(urlParams.get('m'));

    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved && saved !== '{}') {
        try {
            globalData = JSON.parse(saved);
            if (urlY && urlM) {
                // 如果 URL 有参数，优先使用 URL 的日期
                viewYear = urlY;
                viewMonth = urlM;
            } else {
                const keys = Object.keys(globalData).sort();
                if (keys.length > 0) {
                    const lastKey = keys[keys.length - 1];
                    [viewYear, viewMonth] = lastKey.split('-').map(Number);
                }
            }
            switchView('dashboard'); // 有数据，跳到看板
            renderDashboard();
        } catch (e) {
            switchView('upload');
        }
    } else {
        switchView('upload');
    }
    window.onpopstate = () => location.reload();
});

function switchView(viewName) {
    // 隐藏所有视图
    document.getElementById('upload-view').classList.remove('active');
    document.getElementById('dashboard-view').classList.remove('active');
    document.getElementById('upload-view').style.display = 'none';
    document.getElementById('dashboard-view').style.display = 'none';

    // 显示目标视图
    const target = document.getElementById(viewName + '-view');
    if (target) {
        target.classList.add('active');
        target.style.display = 'block';
    }
}

function clearData() {
    if(confirm('确定要清除本地缓存的阅读数据吗？')) {
        localStorage.removeItem(STORAGE_KEY);
        globalData = {}; 
        switchView('upload'); // 清除后，立即显示大上传区域
    }
}

// 2. 文件处理逻辑
const dropZone = document.getElementById('drop-zone');
const dirInput = document.getElementById('dir-input');

dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    handleFiles(e.dataTransfer.items); // 使用 items 处理文件夹
});

dirInput.addEventListener('change', (e) => handleFiles(e.target.files));

async function handleFiles(fileList) {
    const files = [];
    // 兼容 input files 和 dataTransfer items
    if (fileList instanceof DataTransferItemList) {
        const entries = [];
        for (let i = 0; i < fileList.length; i++) {
            const entry = fileList[i].webkitGetAsEntry();
            if (entry) entries.push(entry);
        }
        await scanEntries(entries, files);
    } else {
        for (let i = 0; i < fileList.length; i++) {
            files.push(fileList[i]);
        }
    }

    // 筛选目标文件
    const targetFiles = files.filter(f => 
        f.name.startsWith('metrics_reader_') || f.name === 'history.gz'
    );

    if (targetFiles.length === 0) {
        alert('未找到 metrics_reader_* 或 history.gz 文件，请确认目录正确。');
        return;
    }

    document.getElementById('loading-msg').classList.remove('d-none');
    
    // 开始处理
    globalData = {}; // 重置数据
    
    try {
        for (const file of targetFiles) {
            let content = '';
            if (file.name.endsWith('.gz')) {
                const arrayBuffer = await readFileAsArrayBuffer(file);
                try {
                    content = pako.inflate(new Uint8Array(arrayBuffer), { to: 'string' });
                } catch(e) { console.warn('解压失败', file.name); continue; }
            } else {
                content = await readFileAsText(file);
            }
            parseLogContent(content);
        }

        // 保存并渲染
        localStorage.setItem(STORAGE_KEY, JSON.stringify(globalData));
        
        // 自动跳转到最新数据的月份
        const keys = Object.keys(globalData).sort();
        if (keys.length > 0) {
            const lastKey = keys[keys.length - 1];
            [viewYear, viewMonth] = lastKey.split('-').map(Number);
        }

        switchView('dashboard');
        renderDashboard();

    } catch (err) {
        console.error(err);
        alert('处理文件时出错: ' + err.message);
    } finally {
        document.getElementById('loading-msg').classList.add('d-none');
    }
}

// 递归扫描目录 (用于拖拽文件夹)
async function scanEntries(entries, fileArray) {
    for (const entry of entries) {
        if (entry.isFile) {
            const file = await new Promise(resolve => entry.file(resolve));
            fileArray.push(file);
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const subEntries = await new Promise(resolve => {
                reader.readEntries(resolve);
            });
            await scanEntries(subEntries, fileArray);
        }
    }
}

function readFileAsText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsText(file);
    });
}

function readFileAsArrayBuffer(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

// 3. 核心解析逻辑 (复刻 Shell 脚本逻辑)
function parseLogContent(text) {
    const lines = text.split('\n');
    // Regex: 匹配 timestamp(索引1) 和 activeDuration(索引5的key, 索引6的value)
    // 示例: ...generic,1766635235,...activeDuration,3695
    const regex = /metric_generic,(\d+),.*,com\.lab126\.booklet\.reader\.activeDuration,(\d+)/;

    lines.forEach(line => {
        const match = line.match(regex);
        if (!match) return;

        const endTimeTs = parseInt(match[1]); // 秒
        const durationMs = parseInt(match[2]); // 毫秒
        const durationSec = Math.floor(durationMs / 1000);
        
        if (durationSec <= 0) return;

        const startTimeTs = endTimeTs - durationSec;

        // 这里的难点是把时长分配到具体的 [年-月][日][小时段]
        // 简单起见，我们按秒遍历太慢，我们按逻辑拆分
        
        let currentTs = startTimeTs;
        let remainingDuration = durationSec;

        while (remainingDuration > 0) {
            const dateObj = new Date(currentTs * 1000);
            const y = dateObj.getFullYear();
            const m = dateObj.getMonth() + 1;
            const d = dateObj.getDate();
            const h = dateObj.getHours();

            const key = `${y}-${m}`;
            
            // 初始化结构
            if (!globalData[key]) globalData[key] = { days: {}, hours: {} };
            if (!globalData[key].days[d]) globalData[key].days[d] = 0;
            if (!globalData[key].hours[d]) globalData[key].hours[d] = new Array(12).fill(0);

            // 计算当前小时还剩多少秒 (或者是当前分钟，为了d1-d12的2小时分布)
            // d1=0-2, d2=2-4 ... dN = floor(h / 2)
            
            // 只要在同一天内，我们就不需要按小时循环切分来计算总时长
            // 但是为了 hours 分布，我们需要知道在这个 2小时 block 里占了多少
            
            // 简化算法：本次循环处理这一天的剩余时长
            // 算出今天还剩多少秒直到明天 00:00
            const tomorrow = new Date(dateObj);
            tomorrow.setDate(d + 1);
            tomorrow.setHours(0, 0, 0, 0);
            const secondsUntilTomorrow = (tomorrow.getTime() / 1000) - currentTs;

            const timeInThisDay = Math.min(remainingDuration, secondsUntilTomorrow);
            
            // 累加日时长 (单位：分钟，与 share.php 保持一致，这里先存秒，最后渲染转分? 不，存分会有精度损失，存秒吧)
            // 为了简单，直接存秒，渲染时 / 60
            if (!globalData[key].days[d + '_sec']) globalData[key].days[d + '_sec'] = 0;
            globalData[key].days[d + '_sec'] += timeInThisDay;
            globalData[key].days[d] = Math.floor(globalData[key].days[d + '_sec'] / 60);

            // 处理小时分布 (近似处理：取中点，或者简单归入开始时段)
            // 精确处理太繁琐，这里采用 Shell 脚本的逻辑：
            // Shell 脚本其实只是累加了 TODAY, WEEK, MONTH。
            // 对于 hourly distribution (d1...d12)，我们把这 timeInThisDay 分配给 startHour 所在的 block
            const blockIndex = Math.floor(h / 2); // 0-11
            globalData[key].hours[d][blockIndex] += Math.floor(timeInThisDay / 60);

            // 步进
            remainingDuration -= timeInThisDay;
            currentTs += timeInThisDay;
        }
    });
}

// 4. 看板渲染逻辑
function changeMonth(offset) {
    let newDate = new Date(viewYear, viewMonth - 1 + offset, 1);
    viewYear = newDate.getFullYear();
    viewMonth = newDate.getMonth() + 1;

    // 同步到 URL 参数
    const newUrl = new URL(window.location.href);
    newUrl.searchParams.set('y', viewYear);
    newUrl.searchParams.set('m', viewMonth);
    window.history.pushState({ path: newUrl.href }, '', newUrl.href);

    renderDashboard();
}

function renderDashboard() {
    // 1. 设置 UI 文本
    document.getElementById('current-date-display').textContent = `${viewYear}年${viewMonth}月`;
    document.getElementById('report-badge').innerHTML = `<i class="fa-regular fa-calendar-check me-1"></i> ${viewYear}年${viewMonth}月`;

    const key = `${viewYear}-${viewMonth}`;
    const monthData = globalData[key] || { days: {}, hours: {} };
    const goal = 30; // 默认目标

    // 2. 计算统计数据
    let totalSec = 0;
    let goalDays = 0;
    let maxStreak = 0;
    let currentStreak = 0;
    const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();
    
    // 找出最后有阅读记录的一天 (作为 "Today" 展示)
    const activeDays = Object.keys(monthData.days).filter(k => !k.endsWith('_sec')).map(Number).sort((a,b) => a-b);
    const lastActiveDay = activeDays.length > 0 ? activeDays[activeDays.length - 1] : 1;

    // 遍历当月每一天计算 Stats
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

    // 填充概览数据
    document.getElementById('val-today').textContent = (monthData.days[lastActiveDay] || 0); // 实际上显示的是"最后活跃日"的时长
    document.getElementById('val-month').textContent = Math.floor(totalSec / 60);
    document.getElementById('val-goal').textContent = goalDays;
    document.getElementById('val-streak').textContent = maxStreak;

    // 3. 更新图表
    updateCharts(monthData, lastActiveDay, daysInMonth);

    // 4. 生成日历
    renderCalendar(monthData, daysInMonth, goal);

    // 5. 更新点评
    updateComments(monthData, lastActiveDay);
}

function updateCharts(monthData, targetDay, totalDays) {

    // 坐标轴设置
    const pcConfig = {
        indexAxis: 'x',
        scales: {
            x: {
                ticks: { maxRotation: 60 },
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) { return value + 'm'; }
                }
            }
        }
    };

    const mobileConfig = {
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) { return value + 'm'; }
                }
            },
            y: {
                reverse: false
            }
        }
    };

    // 根据设备类型合并配置
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    };

    const commonConfig = isMobile 
        ? Object.assign({}, baseOptions, mobileConfig)
        : Object.assign({}, baseOptions, pcConfig);

    // --- Chart 1: Today Dist (Target Day Dist) ---
    // 获取该日数据，默认为0数组
    const dayDist = monthData.hours[targetDay] || new Array(12).fill(0);
    const labelsToday = ["0-2点", "2-4点", "4-6点", "6-8点", "8-10点", "10-12点", "12-14点", "14-16点", "16-18点", "18-20点", "20-22点", "22-24点"];

    const ctxToday = document.getElementById('todayChart').getContext('2d');
    if (charts.today) charts.today.destroy();
    
    charts.today = new Chart(ctxToday, {
        type: isMobile ? 'bar' : 'bar',
        data: {
            labels: labelsToday,
            datasets: [{
                data: dayDist,
                backgroundColor: '#0d6efd',
                borderRadius: 4
            }]
        },
        options: commonConfig
    });

    // --- Chart 2: Month Trend (Daily) ---
    
    let startDay = targetDay - 6;
    if (startDay < 1) startDay = 1;
    // 确保显示7天
    let endDay = startDay + 6;
    if (endDay > totalDays) endDay = totalDays;

    const weekLabels = [];
    const weekData = [];
    for (let i = startDay; i <= endDay; i++) {
        weekLabels.push(i + '日');
        weekData.push(monthData.days[i] || 0);
    }

    const ctxWeek = document.getElementById('weekChart').getContext('2d');
    if (charts.week) charts.week.destroy();

    charts.week = new Chart(ctxWeek, {
        type: 'line', // share.php 用 line 或 bar? 原版是 line
        data: {
            labels: weekLabels,
            datasets: [{
                data: weekData,
                fill: true,
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
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

    const firstDayTs = new Date(viewYear, viewMonth - 1, 1);
    const startWeek = firstDayTs.getDay(); // 0 is Sunday

    // Padding
    for(let i=0; i<startWeek; i++) {
        container.innerHTML += '<div></div>';
    }

    for(let d=1; d<=daysInMonth; d++) {
        const min = monthData.days[d] || 0;
        let className = '';
        if (min > 0) className = min >= goal ? 'goal-reached' : 'active';
        
        const timeHtml = min > 0 ? `<div class="day-time">${min}m</div>` : `<div class="day-time text-muted opacity-25" style="font-size:0.7rem">-</div>`;

        const html = `
            <div class="day-cell ${className}">
                <div class="day-num">${d}</div>
                ${timeHtml}
            </div>
        `;
        container.innerHTML += html;
    }
}

function updateComments(monthData, targetDay) {
    // 简单点评逻辑
    const todayMins = monthData.days[targetDay] || 0;
    const txt1 = todayMins > 0 ? `该日阅读了 <strong>${todayMins}</strong> 分钟，请继续保持！` : "该日暂无阅读记录。";
    document.getElementById('comment-today').innerHTML = `<i class="fa-solid fa-lightbulb text-warning me-1"></i> ${txt1}`;
    
    document.getElementById('comment-week').innerHTML = `<i class="fa-solid fa-chart-line text-primary me-1"></i> 保持阅读习惯，知识复利惊人。`;
}

// 5. 导出相关
    async function sectionExport(element, filename) {
        // 临时增加 padding 防止截图边缘切断阴影
        const originalPadding = element.style.padding;

        const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: "#d1ecfeff",
            });

        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = filename + '.png';
        link.click();
    }

    async function fullExport(element, filename) {
        if (window.innerWidth < 768) {
            // 构建新URL，添加参数
            const currentUrl = window.location.href;
            const separator = currentUrl.includes('?') ? '&' : '?';
            const exportUrl = `${currentUrl}${separator}exporting=1`;
            
            // 打开新窗口
            const exportWindow = window.open(exportUrl, '_blank');
            
            // 等待新窗口加载完成后，监听导出事件
            if (exportWindow) {
                // 设置一个标记，防止重复导出
                window.exportInProgress = true;
                
                // 监听新窗口的消息（新窗口导出完成后会发送消息）
                window.addEventListener('message', function(event) {
                    if (event.data === 'export_complete') {
                        // 新窗口可以关闭了
                        try {
                            console.log('新窗口导出结束');
                            //exportWindow.close();
                        } catch (e) {
                            console.log('新窗口已关闭或无法关闭');
                        }
                        window.exportInProgress = false;
                    }
                });
                
                return; // 手机端直接返回，后续在新窗口处理
            }
        }
        const fab = document.querySelector('.fab-container');

        // 1. 锁定宽度并隐藏UI
        fab.style.visibility = 'hidden';

        try {
            const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: "#d1ecfeff",
                windowWidth: 1000 // 强制渲染器按此宽度解析
            });

            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = filename + '.png';
            link.click();
        } catch (e) {
            console.log('生成失败');
        }
        fab.style.visibility = 'visible';
    }

function quickExport(id, name) {
    sectionExport(document.getElementById(id), '阅读统计-' + name);
}

function exportFullReport() {
    fullExport(document.getElementById('capture-area'), `阅读报告-${viewYear}${viewMonth}-完整版`);
}

    if (exportMode) {
        // 隐藏悬浮按钮
        document.querySelector('.fab-container').style.display = 'none';
            
        // 添加一个提示信息
        const notice = document.createElement('div');
        notice.id = 'export-notice';
        notice.innerHTML = '<div style="position: fixed; top: 10px; left: 10px; right: 10px; background: #ffc107; padding: 20px; border-radius: 5px; text-align: left; z-index: 10000;">正在生成截图，请稍候...</div>';
        document.body.appendChild(notice);

        // 延迟执行导出，确保页面完全渲染
        setTimeout(async () => {
            try {
                const canvas = await html2canvas(document.getElementById('capture-area'), {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: "#daf0ffff",
                    windowWidth: 1200
                });

                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = '阅读报告-${viewYear}${viewMonth}-完整版.png';
                link.click();
                    
                // 移除提示
                document.getElementById('export-notice').remove();
                    
                // 通知父窗口导出完成
                if (window.opener) {
                    window.opener.postMessage('export_complete', '*');
                }
                    
            } catch (error) {
                console.error('导出失败:', error);
                document.getElementById('export-notice').innerHTML = 
                    '<div style="position: fixed; top: 10px; left: 10px; right: 10px; background: #dc3545; color: white; padding: 10px; border-radius: 5px; text-align: center; z-index: 10000;">导出失败，请重试</div>';
            }
        }, 1000);
    }

</script>

</body>
</html>