# Kykky Kindle 阅读数据统计（2.0）

Kykky 的服务端与 Web 前端，配合 Kindle 客户端 [TQHYG/kykky](https://github.com/TQHYG/kykky) 使用。

## 2.0 架构

- **静态前端**：Web 根目录只放 HTML/CSS/JS，所有数据通过 `fetch` 调用 `/api`。
- **API 后端**：所有 PHP 代码位于 `api/`，不再从静态页面直接渲染数据。
- **持久登录**：Web 登录使用数据库会话 + HttpOnly Cookie，默认有效期一年，临近过期自动续期，不再依赖易丢失的 PHP `session` 文件。
- **Kindle 兼容**：`/api/auth.php`、`/api/upload.php` 保持旧客户端字段兼容；设备 Token 仍使用 HMAC 签名。
- **数据库**：沿用原 `users` / `stats` / `devices` 结构，仅新增 `sessions` 表。

```
kindle-reading-php/
├── index.html, login.html, user.html, rank.html,
│   analysis.html, upload.html, device.html,
│   fastsync.html, share.html          # 静态页面
├── assets/css/app.css
├── assets/js/                         # 页面脚本 + api.js 公共库
├── assets/vendor/                     # Bootstrap / Font Awesome / Chart.js / pako / html2canvas
├── api/
│   ├── bootstrap.php
│   ├── lib/                           # 配置、DB、认证、日志解析、存储
│   ├── sql/                           # 全新安装与迁移 SQL
│   ├── auth.php                       # Kindle 设备授权（兼容旧版）
│   ├── upload.php                     # Kindle/Web 上传
│   ├── files.php                      # 日志列表/下载/备份
│   ├── github.php                     # GitHub OAuth / 注销
│   ├── session.php                    # Web 会话状态
│   ├── devices.php                    # 设备绑定与管理
│   ├── rank.php                       # 排行榜
│   ├── stats.php                      # 当前用户统计
│   ├── fastsync.php                   # 扫码快捷同步
│   ├── health.php                     # 健康检查
│   └── cron.php                       # CLI 定时任务
├── deploy/                            # nginx / php-fpm 示例
└── docs/                              # 迁移与 API 文档
```

## 安装

### 1. 依赖

- PHP 8.1+，扩展：`pdo_mysql`、`curl`、`json`、`mbstring`、`zip`
- MariaDB 10.5+ / MySQL 8+
- nginx + PHP-FPM

### 2. 数据库

```bash
mysql -uroot -p -e "CREATE DATABASE kykky_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -p -e "CREATE USER 'kykky'@'127.0.0.1' IDENTIFIED BY 'your-password';"
mysql -uroot -p -e "GRANT SELECT,INSERT,UPDATE,DELETE ON kykky_db.* TO 'kykky'@'127.0.0.1';"
mysql -uroot -p kykky_db < api/sql/schema.sql
```

已有旧库请参考 [docs/MIGRATION.md](docs/MIGRATION.md)，只新增 `sessions` 表。

### 3. 配置

配置文件放在 Web 根目录 `config.php`，由 nginx 明确拒绝外部访问。

```bash
cd /path/to/kindle-reading-php
cp config.sample.php config.php
sudo chown root:www-data config.php    # Fedora 使用 root:apache
sudo chmod 640 config.php
sudo mkdir -p /var/lib/kykky/storage
sudo chown -R www-data:www-data /var/lib/kykky   # Fedora 用 apache
```

也可以通过环境变量 `KYKKY_CONFIG` 指向其他配置文件。

重点配置：

- `app.base_url`：对外地址，如 `https://reading.example.com`
- `db`：数据库连接
- `oauth.redirect_uri`：必须与 GitHub OAuth App 登记的回调地址一致
- `security.app_secret`：随机长字符串，用于设备 Token 与 OAuth state 签名
- `storage.path`：日志保存目录，建议在 Web 根目录之外

### 4. nginx

参考 [deploy/nginx.conf.sample](deploy/nginx.conf.sample)。关键点：

- Web 根目录指向项目静态文件目录；
- 只有 `/api/*.php` 交给 PHP-FPM；
- 禁止访问 `config.php`、`config.sample.php`、`api/lib`、`api/sql`、`docs`、`deploy`；
- 旧入口 `/share.php`、`/device.php`、`/fastsync.php` 等 302 到静态页面；
- 传入 `HTTP_AUTHORIZATION`，Kindle 的 Bearer Token 才能生效；
- `client_max_body_size` 至少 32m。

前端依赖已全部本地化到 `assets/vendor/`，运行时不依赖公共 CDN。

### 5. 定时任务

```cron
* * * * * php /path/to/kindle-reading-php/api/cron.php >> /var/log/kykky-cron.log 2>&1
```

任务包括：清理过期待绑定设备、清理过期会话、每日/每月清零排行榜统计。

## Web 登录与会话

1. 浏览器访问 `/api/github.php?action=login&redirect=/user.html`。
2. 服务端写入短期签名 state Cookie，跳转 GitHub。
3. 回调 `/api/github.php?action=callback` 校验 state，换取 GitHub 用户信息。
4. 服务端创建 `sessions` 记录，设置 HttpOnly + SameSite=Lax Cookie。
5. 后续请求通过 Cookie 查库认证；剩余有效期不足 180 天时自动续到一年。
6. 写操作要求 `X-CSRF-Token`，令牌由 `/api/session.php?action=me` 返回。

Cookie 在 HTTPS 下自动加 `Secure`，本地 HTTP 调试时自动关闭。

## Kindle API 兼容

旧客户端仍可使用：

- `GET /api/auth.php?action=get_url`
- `GET /api/auth.php?action=check_status&device_code=...`
- `GET /api/auth.php?action=logout`（Bearer Token）
- `POST /api/upload.php?today_seconds=..&month_seconds=..`，multipart `logs[]`

服务端会重新解析上传的 `metrics_reader_*` / `history.gz` 计算今日和本月时长，优先使用计算结果，避免客户端统计偏差。

## API 文档

见 [docs/API.md](docs/API.md)。

## 安全说明

- 所有 SQL 使用 PDO 预处理；排序列、文件路径等动态部分均使用白名单。
- 上传文件先完整校验，再原子替换目录；单文件大小、数量均有限制。
- 日志目录使用每用户独立目录，禁止路径穿越与符号链接。
- GitHub OAuth state 使用 HMAC 签名 Cookie，防 CSRF。
- Web 写接口使用 CSRF Token；会话 Cookie HttpOnly。
- 设备 Token 继续使用 HMAC 校验，并在数据库中确认设备仍属于该用户且已启用。
