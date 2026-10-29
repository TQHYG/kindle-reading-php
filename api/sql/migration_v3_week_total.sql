-- 从 2.0 升级：排行榜新增周榜/总榜所需字段。
-- 不修改 users / devices；只扩展 stats 表。

SET @db = DATABASE();

-- week_seconds
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE stats ADD COLUMN week_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER today_seconds',
        'DO 0'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'stats' AND COLUMN_NAME = 'week_seconds'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- total_seconds
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE stats ADD COLUMN total_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER month_seconds',
        'DO 0'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'stats' AND COLUMN_NAME = 'total_seconds'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 索引：周榜
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_stats_week ON stats (week_seconds)',
        'DO 0'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'stats' AND INDEX_NAME = 'idx_stats_week'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 索引：总榜
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX idx_stats_total ON stats (total_seconds)',
        'DO 0'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'stats' AND INDEX_NAME = 'idx_stats_total'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 迁移完成后可执行 api/cron.php --recalc，根据已上传日志回填周/月/总数据。
