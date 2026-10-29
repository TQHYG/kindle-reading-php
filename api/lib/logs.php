<?php
/**
 * Kindle metrics_reader 日志解析。
 * 服务端只从上传文件计算今日/本月秒数，避免完全信任客户端上报值。
 */

function is_gzip_file(string $path): bool
{
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return false;
    }
    $magic = fread($fh, 2);
    fclose($fh);
    return $magic === "\x1f\x8b";
}

/**
 * @param string[] $paths 已通过校验的日志文件路径
 * @return array{today_seconds:int, week_seconds:int, month_seconds:int, total_seconds:int, records:int}
 */
function metrics_compute_stats(array $paths): array
{
    $tz = new DateTimeZone((string)kykky_cfg('app.timezone', 'Asia/Shanghai'));
    $now = new DateTimeImmutable('now', $tz);
    $midnight = $now->setTime(0, 0, 0);
    $todayStart = $midnight->getTimestamp();
    $todayEnd = $midnight->modify('+1 day')->getTimestamp();

    // 周一为一周起点。N: 1=周一 ... 7=周日
    $weekday = (int)$now->format('N');
    $weekStart = $midnight->modify('-' . ($weekday - 1) . ' days')->getTimestamp();
    $weekEnd = $midnight->modify('+' . (8 - $weekday) . ' days')->getTimestamp();

    $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0)->getTimestamp();
    $monthEnd = $now->modify('first day of next month')->setTime(0, 0, 0)->getTimestamp();

    $todaySeconds = 0;
    $weekSeconds = 0;
    $monthSeconds = 0;
    $totalSeconds = 0;
    $records = 0;
    $pattern = '/metric_generic,(\d+),.*,com\.lab126\.booklet\.reader\.activeDuration,(\d+)/';

    foreach ($paths as $path) {
        $gzip = is_gzip_file($path);
        $fh = $gzip ? @gzopen($path, 'rb') : @fopen($path, 'rb');
        if ($fh === false) {
            continue;
        }

        while (true) {
            $line = $gzip ? gzgets($fh, 65536) : fgets($fh, 65536);
            if ($line === false) {
                break;
            }
            if (!preg_match($pattern, $line, $m)) {
                continue;
            }

            $end = (int)$m[1];
            $duration = intdiv((int)$m[2], 1000);
            if ($duration <= 0) {
                continue;
            }
            $start = $end - $duration;

            $totalSeconds += $duration;

            $todayOverlap = min($end, $todayEnd) - max($start, $todayStart);
            if ($todayOverlap > 0) {
                $todaySeconds += $todayOverlap;
            }
            $weekOverlap = min($end, $weekEnd) - max($start, $weekStart);
            if ($weekOverlap > 0) {
                $weekSeconds += $weekOverlap;
            }
            $monthOverlap = min($end, $monthEnd) - max($start, $monthStart);
            if ($monthOverlap > 0) {
                $monthSeconds += $monthOverlap;
            }
            $records++;
        }

        if ($gzip) {
            gzclose($fh);
        } else {
            fclose($fh);
        }
    }

    return [
        'today_seconds' => $todaySeconds,
        'week_seconds' => $weekSeconds,
        'month_seconds' => $monthSeconds,
        'total_seconds' => $totalSeconds,
        'records' => $records,
    ];
}
