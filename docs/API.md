# API 文档

所有 API 位于 `/api/`。

两种响应格式：

- 新接口：`{"ok":true,"data":...}` / `{"ok":false,"error":{"code":"...","message":"..."}}`
- 旧 Kindle 兼容接口（`auth.php`、`upload.php`、`files.php`）：保留 `status` / `msg` / `files` 等旧字段。

## 认证方式

### Web 会话

- Cookie：`kykky_session`，HttpOnly、SameSite=Lax，默认一年有效。
- 写操作需要请求头 `X-CSRF-Token`，值由 `GET /api/session.php?action=me` 返回。
- 会话存储在数据库 `sessions` 表，每次请求查库校验。

### Kindle 设备 Token

- 设备绑定成功后，`check_status` 返回 `access_token`。
- 请求头：`Authorization: Bearer <access_token>`。
- Token 为 HMAC 签名，有效期一年；每次 `check_status` 可获取新 Token。
- 设备在后台被删除后，Token 立即失效。

## 设备授权（旧客户端兼容）

### `GET /api/auth.php?action=get_url`

创建待绑定设备。

```json
{
  "device_code": "f69a97cf6abe99f2",
  "login_url": "https://example.com/device.html?device_code=f69a97cf6abe99f2"
}
```

限流：全局每分钟最多创建 10 个待绑定设备。

### `GET /api/auth.php?action=check_status&device_code=...`

- 等待绑定：`{"status":"pending"}`
- 已绑定：

```json
{
  "status": "success",
  "access_token": "...",
  "nickname": "User",
  "avatar": "https://...",
  "device_name": "我的 Kindle"
}
```

- 不存在/过期：HTTP 404 `{"status":"expired"}`

### `GET /api/auth.php?action=logout`

需要 Bearer Token。删除设备记录并吊销 Token。

```json
{"status":"success","msg":"Device logged out and token revoked"}
```

## 会话

### `GET /api/session.php?action=me`

未登录：

```json
{"ok":true,"data":{"authenticated":false,"oauth_configured":true}}
```

已登录：

```json
{
  "ok": true,
  "data": {
    "authenticated": true,
    "user": {"id":1,"nickname":"User","avatar":"...","oauth_provider":"github"},
    "csrf": "...",
    "expires_at": "2027-10-29 17:00:00",
    "stats": {"today_seconds":0,"month_seconds":0,"last_update":"..."},
    "oauth_configured": true
  }
}
```

### `POST /api/session.php?action=logout`

需要会话和 CSRF。

## GitHub OAuth

### `GET /api/github.php?action=login&redirect=/user.html`

设置短期 state Cookie，302 到 GitHub 授权页。

### `GET /api/github.php?action=callback&code=...&state=...`

校验 state，换取 GitHub 用户信息，创建/更新本地用户与会话，302 回前端。

### `POST /api/github.php?action=logout`

需要会话和 CSRF，吊销当前会话。

## 设备管理（Web 会话）

### `GET /api/devices.php?action=list`

```json
{"ok":true,"data":{"devices":[{"code":"...","device_name":"...","status":1,"expires_at":"2027-10-29"}]}}
```

### `GET /api/devices.php?action=info&code=...`

返回设备信息与 `can_bind`。设备属于其他账号时 403。

### `POST /api/devices.php?action=bind`

```json
{"code":"f69a97cf6abe99f2","device_name":"我的 Kindle"}
```

需要 CSRF。设备已被其他账号绑定返回 403；达到设备上限返回 409。

### `POST /api/devices.php?action=rename`

```json
{"code":"...","device_name":"新名称"}
```

### `POST /api/devices.php?action=delete`

```json
{"code":"..."}
```

删除后该设备 Token 立即失效。

## 上传

### `POST /api/upload.php`

认证：Kindle Bearer Token 或 Web 会话（Web 需要 CSRF）。

multipart：

- `logs[]`：`metrics_reader_*`、`history.gz`
- 可选 query：`today_seconds`、`month_seconds`

规则：

- 文件名白名单；
- `metrics` 最大 5MB，`history.gz` 最大 20MB；
- 每次最多 100 个文件；
- 先完整校验，再原子替换用户日志目录；
- 服务端重新解析日志计算今日/本月秒数，优先使用计算结果；
- 没有文件时才使用上报的 `today_seconds` / `month_seconds`。

响应（兼容旧版）：

```json
{
  "status": "success",
  "msg": "Sync completed",
  "db_synced": true,
  "files_count": 2,
  "today_seconds": 0,
  "month_seconds": 154285,
  "source": "files"
}
```

## 文件

### `GET /api/files.php?action=list`

```json
{"status":"success","files":[{"name":"history.gz","size":12484,"mtime":1793264860}]}
```

### `GET /api/files.php?action=download&file=history.gz`

返回二进制文件。

### `GET /api/files.php?action=download_backup`

返回 ZIP 备份。

## 统计与排行榜

### `GET /api/stats.php`

需要会话。

```json
{"ok":true,"data":{"today_seconds":1234,"month_seconds":56789,"last_update":"...","devices_count":1}}
```

### `GET /api/rank.php?type=today|month&page=1&limit=20`

公开接口。`limit` 最大 50。

```json
{
  "ok": true,
  "data": {
    "type": "month",
    "page": 1,
    "limit": 20,
    "has_more": false,
    "list": [{"user_id":1,"seconds":154285,"nickname":"User","avatar":""}],
    "my_rank": {"rank":1,"seconds":154285}
  }
}
```

`my_rank` 仅在已登录且第一页时返回。

## 快捷同步

### `GET /api/fastsync.php?action=preview&data=<base64>`

需要会话。`data` 为 `{"did":"设备码","today":秒,"month":秒}` 的 Base64。

返回设备名、今日/本月秒数，并校验设备属于当前账号。

### `POST /api/fastsync.php?action=confirm`

```json
{"data":"<base64>"}
```

需要 CSRF。确认覆盖排行榜统计。

## 健康检查

### `GET /api/health.php`

```json
{"ok":true,"data":{"service":"kykky","version":"2.0","db":true,"storage":true}}
```

数据库或存储不可用时返回 503。
