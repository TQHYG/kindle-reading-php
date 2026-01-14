<?php

function parse_reading_data($params) {
    $year = isset($params['year']) ? intval($params['year']) : date('Y');
    $month = isset($params['month']) ? intval($params['month']) : date('m');
    $goal = isset($params['goal']) ? intval($params['goal']) : 30;

    // 1. 提取每日数据 (key 为 1-31 的数字)
    $daily_data = [];
    $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));

    for ($i = 1; $i <= $days_in_month; $i++) {
        $daily_data[$i] = isset($params[$i]) ? intval($params[$i]) : 0;
    }

    // 2. 提取今日每2小时分布 (d1 - d12)
    $today_dist = [];
    for ($i = 1; $i <= 12; $i++) {
        $key = 'd' . $i;
        $today_dist[] = isset($params[$key]) ? intval($params[$key]) : 0;
    }

    return [
        'year' => $year,
        'month' => $month,
        'goal' => $goal,
        'daily_data' => $daily_data,
        'today_dist' => $today_dist,
    ];
}

/**
 * 计算概览统计数据
 */
function calculate_stats($data) {
    $daily = $data['daily_data'];
    $goal = $data['goal'];

    $total_month_time = array_sum($daily);
    $goal_reached_days = 0;
    $current_streak = 0;

    foreach ($daily as $minutes) {
        if ($minutes >= $goal) {
            $goal_reached_days++;
        }
    }

    // 简化的连续计算逻辑（倒序查找）
    $reversed_daily = array_reverse($daily, true);
    foreach ($reversed_daily as $day => $minutes) {
        if ($minutes >= $goal) {
            $current_streak++;
        } else {
            // 如果遇到0或者未达标，且不是未来的日子(简单判断：只要有数据且不达标就断)
            if ($minutes < $goal && $minutes > 0) break;
            // 如果单纯是0且在月末，可能还没发生，这里为演示简单处理为断开
            if($minutes < $goal) break;
        }
    }

    $today_total = array_sum($data['today_dist']);

    return [
        'total_month' => $total_month_time,
        'today_total' => $today_total,
        'goal_days' => $goal_reached_days,
        'streak' => $current_streak
    ];
}

/**
 * 生成点评
 */
function generate_comment($type, $data_array, $labels = []) {
    if (empty($data_array)) return "暂无数据。";
    $max_val = max($data_array);
    if ($max_val == 0) return "这段时间没有阅读记录，继续加油！";

    $max_keys = array_keys($data_array, $max_val);
    $best_idx = $max_keys[0];

    if ($type === 'today') {
        $period = isset($labels[$best_idx]) ? $labels[$best_idx] : '某时段';
        return "太棒了！你在 <strong>{$period}</strong> 精力最集中，阅读了 {$max_val} 分钟。";
    } elseif ($type === 'week') {
        return "本周 <strong>{$best_idx}号</strong> 是你的阅读巅峰，达到了 {$max_val} 分钟！";
    }
    return "阅读是最好的投资。";
}

$data = parse_reading_data($_GET);
$stats = calculate_stats($data);

$exporting = isset($_GET['exporting']) && $_GET['exporting'] == 1;

// JS 数据准备
$js_today_data = json_encode($data['today_dist']);
$js_today_labels = json_encode(["0-2点", "2-4点", "4-6点", "6-8点", "8-10点", "10-12点", "12-14点", "14-16点", "16-18点", "18-20点", "20-22点", "22-24点"]);

$daily_values = array_values($data['daily_data']);
$daily_keys = array_keys($data['daily_data']);

// 周视图数据计算 - 处理跨月和月初情况
$days_in_month = date('t', mktime(0, 0, 0, $data['month'], 1, $data['year']));
$current_year = date('Y');
$current_month = date('m');

// 确定结束日期
if ($data['year'] == $current_year && $data['month'] == $current_month) {
    // 当前月份，使用今天或最后一天（取较小值）
    $today_day = date('d');
    $end_day = min($today_day, $days_in_month);
} else {
    // 历史月份，使用最后一天
    $end_day = $days_in_month;
}

// 计算起始日期
$start_day = $end_day - 6;

$week_data = [];
$week_labels = [];

// 处理跨月情况
for ($i = 0; $i < 7; $i++) {
    $current_day = $start_day + $i;
    
    if ($current_day <= 0) {
        // 上个月的日子，显示为0
        $week_data[] = 0;
        $prev_month_days = date('t', mktime(0, 0, 0, $data['month'] - 1, 1, $data['year']));
        $day_in_prev_month = $prev_month_days + $current_day;
        $week_labels[] = $day_in_prev_month . "日*";
    } elseif ($current_day > $days_in_month) {
        // 下个月的日子（通常不会出现，除非结束日期是最后一天）
        $week_data[] = 0;
        $week_labels[] = ($current_day - $days_in_month) . "日*";
    } else {
        // 当前月的日子
        $week_data[] = $data['daily_data'][$current_day] ?? 0;
        $week_labels[] = $current_day . "日";
    }
}

$js_week_data = json_encode($week_data);
$js_week_labels = json_encode($week_labels);

// 周视图点评 - 只使用当前月实际有数据的日子
$week_assoc = [];
for ($day = max(1, $start_day); $day <= min($end_day, $days_in_month); $day++) {
    $week_assoc[$day] = $data['daily_data'][$day] ?? 0;
}

$comment_today = generate_comment('today', $data['today_dist'], ["0-2点", "2-4点", "4-6点", "6-8点", "8-10点", "10-12点", "12-14点", "14-16点", "16-18点", "18-20点", "20-22点", "22-24点"]);
$week_assoc = array_combine(array_slice($daily_keys, -7), $week_data);
$comment_week = generate_comment('week', $week_assoc);
$comment_month = "本月目标 {$data['goal']} 分钟/天，已达标 {$stats['goal_days']} 天。";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>阅读报告 - <?php echo $data['year']; ?>/<?php echo $data['month']; ?></title>

    <link href="style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root { --primary-color: #0d6efd; --bg-color: #f4f7f9; }
        body { background: var(--bg-color); padding-bottom: 100px; font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>

<body <?php echo $exporting ? 'class="export-mode"' : ''; ?>>

<div class="container py-4" id="export-container">
    <div id="capture-area">
        <div class="d-flex justify-content-between align-items-end mb-4 px-2">
            <div>
                <h3 class="fw-bold mb-0 text-dark">
                    <span id="nickname-display" class="nickname-trigger" onclick="openNicknameModal()" title="点击修改昵称">我</span>
                    的阅读分析报告
                </h3>
                <span class="badge bg-primary-subtle text-primary mt-2 px-3 py-2 rounded-pill">
                    <i class="fa-regular fa-calendar-check me-1"></i> <?php echo $data['year']; ?>年<?php echo $data['month']; ?>月
                </span>
            </div>
            <div class="text-end d-none d-md-block">
                <small class="text-muted">Generated by Kindle Plugin</small>
            </div>
        </div>

        <div id="sec-overview" class="stat-card">
            <div class="row text-center g-3">
                <div class="col-6 col-md-3">
                    <div class="highlight-num text-primary"><?php echo $stats['today_total']; ?></div>
                    <div class="sub-label">今日时长(m)</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="highlight-num"><?php echo $stats['total_month']; ?></div>
                    <div class="sub-label">本月累计(m)</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="highlight-num text-success"><?php echo $stats['goal_days']; ?></div>
                    <div class="sub-label">达标天数</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="highlight-num text-warning"><?php echo $stats['streak']; ?></div>
                    <div class="sub-label">连续达标(天)</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div id="sec-today" class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0"><i class="fa-solid fa-chart-simple text-primary me-2"></i>今日时段分布</h6>
                        <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="quickExport('sec-today', '今日分布')"><i class="fa-solid fa-camera"></i> 导出</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="todayChart"></canvas>
                    </div>
                    <p class="small text-muted mt-3 mb-0 bg-light p-2 rounded">
                        <i class="fa-solid fa-quote-left me-1 opacity-50"></i> <?php echo strip_tags($comment_today); ?>
                    </p>
                </div>
            </div>

            <div class="col-md-6">
                <div id="sec-week" class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold m-0"><i class="fa-solid fa-chart-line text-primary me-2"></i>近七天趋势</h6>
                        <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="quickExport('sec-week', '周趋势')"><i class="fa-solid fa-camera"></i> 导出</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="weekChart"></canvas>
                    </div>
                    <p class="small text-muted mt-3 mb-0 bg-light p-2 rounded">
                        <i class="fa-solid fa-quote-left me-1 opacity-50"></i> <?php echo strip_tags($comment_week); ?>
                    </p>
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
                    <div class="calendar-grid">
                        <?php
                        $first_day_ts = mktime(0, 0, 0, $data['month'], 1, $data['year']);
                        $first_day_of_week = date('w', $first_day_ts);
                        $days_in_month = date('t', $first_day_ts);

                        for ($k = 0; $k < $first_day_of_week; $k++) echo "<div></div>";

                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $min = $data['daily_data'][$day] ?? 0;
                            $style = $min >= $data['goal'] ? 'goal-reached' : ($min > 0 ? 'active' : '');
                            echo "<div class='day-cell {$style}'>";
                            echo "<span class='day-num'>{$day}</span>";
                            if($min > 0) echo "<span class='day-time'>{$min}m</span>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fab-container">
    <button class="btn btn-primary btn-fab shadow" onclick="exportFullReport()">
        <i class="fa-solid fa-download me-2"></i> 保存完整报告
    </button>
</div>

<div id="nickname-modal" class="custom-modal">
    <div class="modal-content-box">
        <h4 class="fw-bold mb-3">设置你的昵称</h4>
        <p class="text-muted small mb-4">昵称将显示在统计报告的标题中</p>
        <input type="text" id="nickname-input" class="form-control mb-4 text-center" placeholder="例如: 书虫小王" maxlength="10">
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-primary px-4" onclick="saveNickname()">保存</button>
            <button class="btn btn-outline-secondary px-4" onclick="closeNicknameModal()">取消</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<script src="common.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        initNicknameSystem();
    });
    
    // 今日分布
    new Chart(document.getElementById('todayChart'), {
        type: 'bar', 
        data: {
            labels: <?php echo $js_today_labels; ?>,
            datasets: [{
                label: '分钟',
                data: <?php echo $js_today_data; ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderRadius: 6
            }]
        },
        options: finalOptions
    });


    // 本周分布
    new Chart(document.getElementById('weekChart'), {
        type: 'bar', 
        data: {
            labels: <?php echo $js_week_labels; ?>,
            datasets: [{
                label: '分钟',
                data: <?php echo $js_week_data; ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderRadius: 6
            }]
        },
        options: finalOptions 
    });

    function exportFullReport() {
        // 原有逻辑
        fullExport(document.getElementById('capture-area'), '阅读报告全程回顾');
    }

    if (exportMode) {
            autoExport('阅读报告全程回顾');
    }
</script>

</body>
</html>
