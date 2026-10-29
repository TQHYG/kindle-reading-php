# 从旧版迁移到 2.0

## 数据库变化

**`users` / `devices` 表结构不变；`stats` 表新增两个统计列。**

### v2：持久登录会话

新增一张 `sessions` 表：

```sql
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
```

可直接执行：

```bash
mysql -uroot -p kykky_db < api/sql/migration_v2_sessions.sql
```

### v3：周榜 / 总榜字段

`stats` 表新增：

```sql
ALTER TABLE stats
    ADD COLUMN week_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER today_seconds,
    ADD COLUMN total_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER month_seconds;

CREATE INDEX idx_stats_week ON stats (week_seconds);
CREATE INDEX idx_stats_total ON stats (total_seconds);
```

推荐直接执行幂等迁移脚本：

```bash
mysql -uroot -p kykky_db < api/sql/migration_v3_week_total.sql
```

迁移后新列默认值为 0。执行一次重算即可根据已上传日志回填：

```bash
sudo -u www-data php /var/www/kindle-reading-php/api/recalculate_stats.php
# Fedora: sudo -u apache ...
```

### 兼容性

- 旧客户端上传日志时，服务端会重新解析日志并计算 `week/total`，不会因为旧客户端没传字段而丢失。
- 如果旧客户端只上传当前月日志、没有 `history.gz`，服务端不会把 `total_seconds` 计算得比数据库原值更低。
- 旧客户端只上报今日/本月、且没有上传文件时，只更新 `today_seconds/month_seconds`，不会覆盖已有的 `week_seconds/total_seconds`。
- 旧客户端生成的快捷同步二维码不带 `week/total`，同样只更新今日/本月。
- 每日重算任务会持续修正旧客户端缺失的周/总数据。

可选地为旧 `devices` 表补索引（已存在则忽略 duplicate key 错误）：

```sql
CREATE INDEX idx_devices_user ON devices (user_id, status);
CREATE INDEX idx_devices_pending ON devices (status, created_at);
```

删除 `sessions` 表即可回滚本次迁移，代价是所有 Web 用户需要重新登录；设备 Token 不受影响。

## 配置迁移

旧版示例使用：

```php
'security' => ['token_secret' => '...']
```

2.0 推荐：

```php
'security' => [
    'app_secret' => '...',   // 保持与旧 token_secret 相同可继续兼容旧设备 Token
    'session_cookie' => 'kykky_session',
    'session_ttl' => 31536000,
    'session_renew_threshold' => 15552000,
    'cookie_secure' => 'auto',
    'trust_proxy' => false,
],
```

代码仍兼容读取 `security.token_secret` 和顶层 `app_secret`，但建议迁移到 `security.app_secret`。

2.0 默认读取 Web 根目录的 `config.php`。该文件必须由 nginx 拒绝直接访问，示例配置已包含：

```nginx
location = /config.php        { deny all; }
location = /config.sample.php { deny all; }
```

新增配置：

```php
'app' => [
    'base_url' => 'https://your-domain.example',
    'timezone' => 'Asia/Shanghai',
    'debug' => false,
],
'storage' => [
    'path' => '/var/lib/kykky/storage',
],
```

## 文件存储迁移

旧版默认将日志放在项目内 `storage/{user_id}`。2.0 建议移到 Web 根目录之外：

```bash
sudo mkdir -p /var/lib/kykky/storage
sudo cp -a /old/path/storage/* /var/lib/kykky/storage/ 2>/dev/null || true
sudo chown -R www-data:www-data /var/lib/kykky     # Fedora 使用 apache:apache
sudo chmod -R 750 /var/lib/kykky
```

然后设置 `storage.path` 指向新目录。确认新站可以查看/下载备份后，再删除旧目录。

## GitHub OAuth 回调

旧版实际回调在 `login.php` 或 `callback.php`，2.0 回调为：

```
https://your-domain.example/api/github.php?action=callback
```

请到 GitHub OAuth App 中：

1. 把 **Authorization callback URL** 改为上面的新地址；
2. 将相同地址写入配置 `oauth.redirect_uri`。

nginx 已保留 `/callback.php` 和 `/login.php` 到新地址/静态页的兼容跳转，但 GitHub 发起授权时使用的是配置里的新回调地址。

## nginx 迁移

旧版允许整站执行 PHP。2.0 必须：

- 只有 `/api/*.php` 交给 PHP-FPM；
- 静态文件目录不执行 PHP；
- 禁止访问 `/config.php`、`/config.sample.php`、`/api/lib`、`/api/sql`、`/docs`、`/deploy`；
- 传递 `HTTP_AUTHORIZATION`；
- 为旧链接配置 302：
  - `/share.php` → `/share.html`
  - `/device.php` → `/device.html`
  - `/fastsync.php` → `/fastsync.html`
  - `/user.php` → `/user.html`
  - `/rank.php` → `/rank.html`
  - `/analysis.php` → `/analysis.html`
  - `/upload.php` → `/upload.html`
  - `/login.php` → `/login.html`
  - `/index.php` → `/`

参考 [deploy/nginx.conf.sample](../deploy/nginx.conf.sample)。

## Kindle 客户端

旧客户端无需改动即可继续使用：

- 设备授权仍走 `/api/auth.php?action=...`；
- 上传仍走 `/api/upload.php`；
- 快捷同步二维码仍指向 `/fastsync.php`，服务器会 302 到静态页；
- 分享二维码仍指向 `/share.php`，服务器会 302 到静态分享页。

客户端配置中的自定义域名仍通过 `etc/config.ini` 的 `share_domain` 设置。2.0 客户端额外支持显式协议：

```ini
share_domain=reading.tqhyg.net
share_scheme=https
```

## 上线检查

- [ ] 数据库已备份
- [ ] `sessions` 表已创建
- [ ] `stats.week_seconds` / `stats.total_seconds` 已添加
- [ ] 已执行一次 `api/recalculate_stats.php` 回填历史数据
- [ ] `app_secret` 已设置为随机长字符串
- [ ] `app.debug` 已关闭
- [ ] `app.base_url` 与 OAuth 回调一致
- [ ] `storage.path` 可写且不在 Web 根目录内
- [ ] nginx 只执行 `/api/*.php`
- [ ] `/api/health.php` 返回 `ok: true`
- [ ] GitHub 登录、设备绑定、上传、排行榜、分享链接均已验证
- [ ] cron 已配置（`cron.php` 与每日 `recalculate_stats.php`）
