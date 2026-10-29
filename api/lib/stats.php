<?php
/**
 * 阅读统计值的校验与写入。
 *
 * 兼容策略：
 * - 旧客户端只提交 today/month 时，仅更新这两个字段，不覆盖 week/total。
 * - 新客户端或服务端从日志重算时，提交完整四个字段。
 */

function stats_limits(): array
{
    return [
        'today_seconds' => 200000,
        'week_seconds' => 1000000,
        'month_seconds' => 8000000,
        'total_seconds' => 2000000000,
    ];
}

/**
 * @param array<string,int> $values 允许的键：today_seconds/week_seconds/month_seconds/total_seconds
 */
function stats_validate_fields(array $values): bool
{
    if ($values === []) {
        return false;
    }
    $limits = stats_limits();
    foreach ($values as $key => $value) {
        if (!array_key_exists($key, $limits)) {
            return false;
        }
        if (!is_int($value) || $value < 0 || $value > $limits[$key]) {
            return false;
        }
    }
    return true;
}

/**
 * 按传入字段做部分更新，未传入的列保持不变。
 *
 * @param array<string,int> $values
 */
function stats_upsert_fields(int $userId, array $values): void
{
    $allowed = array_keys(stats_limits());
    $columns = [];
    $placeholders = [];
    $params = [$userId];

    foreach ($allowed as $column) {
        if (array_key_exists($column, $values)) {
            $columns[] = $column;
            $placeholders[] = '?';
            $params[] = (int)$values[$column];
        }
    }
    if ($columns === []) {
        throw new InvalidArgumentException('没有可更新的统计字段');
    }

    $updates = array_map(static fn(string $column): string => $column . ' = VALUES(' . $column . ')', $columns);
    $sql = 'INSERT INTO stats (user_id, ' . implode(', ', $columns) . ', last_update)
            VALUES (?, ' . implode(', ', $placeholders) . ', NOW())
            ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) . ', last_update = NOW()';
    db_query($sql, $params);
}

function stats_upsert(int $userId, int $todaySeconds, int $monthSeconds): void
{
    stats_upsert_fields($userId, [
        'today_seconds' => $todaySeconds,
        'month_seconds' => $monthSeconds,
    ]);
}

function stats_upsert_full(int $userId, int $todaySeconds, int $weekSeconds, int $monthSeconds, int $totalSeconds): void
{
    stats_upsert_fields($userId, [
        'today_seconds' => $todaySeconds,
        'week_seconds' => $weekSeconds,
        'month_seconds' => $monthSeconds,
        'total_seconds' => $totalSeconds,
    ]);
}

function stats_for_user(int $userId): array
{
    $row = db_one(
        'SELECT today_seconds, week_seconds, month_seconds, total_seconds, last_update
         FROM stats WHERE user_id = ?',
        [$userId]
    );
    return $row ?? [
        'today_seconds' => 0,
        'week_seconds' => 0,
        'month_seconds' => 0,
        'total_seconds' => 0,
        'last_update' => null,
    ];
}
