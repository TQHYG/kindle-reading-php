<?php
/**
 * 阅读统计值的校验与写入。
 */

function stats_validate_values(int $todaySeconds, int $monthSeconds): bool
{
    if ($todaySeconds < 0 || $monthSeconds < 0) {
        return false;
    }
    // 与旧版保持一致：单日不超过约 55.5 小时，单月不超过约 92 天。
    if ($todaySeconds > 200000 || $monthSeconds > 8000000) {
        return false;
    }
    return true;
}

function stats_upsert(int $userId, int $todaySeconds, int $monthSeconds): void
{
    db_query(
        'INSERT INTO stats (user_id, today_seconds, month_seconds, last_update)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             today_seconds = VALUES(today_seconds),
             month_seconds = VALUES(month_seconds),
             last_update = NOW()',
        [$userId, $todaySeconds, $monthSeconds]
    );
}

function stats_for_user(int $userId): array
{
    $row = db_one('SELECT today_seconds, month_seconds, last_update FROM stats WHERE user_id = ?', [$userId]);
    return $row ?? [
        'today_seconds' => 0,
        'month_seconds' => 0,
        'last_update' => null,
    ];
}
