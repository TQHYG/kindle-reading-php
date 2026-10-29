<?php
/**
 * 阅读排行榜。
 *
 * GET /api/rank.php?type=today|week|month|total&page=1
 */

require_once __DIR__ . '/bootstrap.php';

$typeMap = [
    'today' => 'today_seconds',
    'week' => 'week_seconds',
    'month' => 'month_seconds',
    'total' => 'total_seconds',
];
$requestedType = $_GET['type'] ?? 'today';
$type = is_string($requestedType) && array_key_exists($requestedType, $typeMap) ? $requestedType : 'today';
$page = max(1, min(1000, (int)($_GET['page'] ?? 1)));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$field = $typeMap[$type];

$rows = db_all(
    "SELECT s.user_id, s.{$field} AS seconds, u.nickname, u.avatar
     FROM stats s
     LEFT JOIN users u ON u.id = s.user_id
     WHERE s.{$field} > 0
     ORDER BY s.{$field} DESC, s.user_id ASC
     LIMIT " . ($limit + 1) . ' OFFSET ' . $offset
);

$hasMore = count($rows) > $limit;
if ($hasMore) {
    array_pop($rows);
}
foreach ($rows as &$row) {
    $row['user_id'] = (int)$row['user_id'];
    $row['seconds'] = (int)$row['seconds'];
    $row['nickname'] = (string)($row['nickname'] ?? 'User');
    $row['avatar'] = (string)($row['avatar'] ?? '');
}
unset($row);

$myRank = null;
$session = current_session();
if ($session !== null && $page === 1) {
    $userId = (int)$session['user']['id'];
    $mySeconds = (int)db_val(
        "SELECT {$field} FROM stats WHERE user_id = ?",
        [$userId],
        0
    );
    if ($mySeconds > 0) {
        $greater = (int)db_val(
            "SELECT COUNT(*) FROM stats WHERE {$field} > ?",
            [$mySeconds],
            0
        );
        $myRank = ['rank' => $greater + 1, 'seconds' => $mySeconds];
    }
}

api_ok([
    'type' => $type,
    'page' => $page,
    'limit' => $limit,
    'has_more' => $hasMore,
    'list' => $rows,
    'my_rank' => $myRank,
]);
