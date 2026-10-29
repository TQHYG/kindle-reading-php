-- 从旧版升级：仅新增持久登录会话表。
-- 不修改 users / stats / devices 的任何列。
-- 执行前请先备份数据库。

CREATE TABLE IF NOT EXISTS sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    csrf_token CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sessions_token (token_hash),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 可选：为旧 devices 表补索引，提升设备列表与限流查询性能。
-- 若索引已存在会报 duplicate key name，可忽略。
-- CREATE INDEX idx_devices_user ON devices (user_id, status);
-- CREATE INDEX idx_devices_pending ON devices (status, created_at);
