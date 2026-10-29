# nginx 在 WAF / 反向代理后的重定向配置

## 现象

nginx 实际监听内部端口（例如 `10443`），外部通过 WAF 以 `https://your-domain` 访问。使用 `return 302 /share.html` 或 `rewrite ... redirect` 时，nginx 可能生成：

```http
Location: https://your-domain:10443/share.html
```

浏览器会跳转到错误的内部端口。

## 原因

nginx 默认 `absolute_redirect on`，会把自身监听的 `scheme`、`server_name`、`port` 拼进 `Location`。在反向代理/WAF 后面，这些值通常是内部地址。

## 推荐方案

在 nginx 的 `http` 块全局关闭绝对重定向：

```nginx
http {
    absolute_redirect off;
    port_in_redirect off;
    server_name_in_redirect off;

    # 其他配置...
}
```

或者在单个 `server` 块中配置：

```nginx
server {
    listen 10443;
    server_name _;

    absolute_redirect off;
    port_in_redirect off;
    server_name_in_redirect off;

    location = /share.php {
        return 302 /share.html$is_args$args;
    }
}
```

这样 nginx 生成：

```http
Location: /share.html
```

浏览器会保留当前公网域名和端口，不再出现 `:10443`。

## 注意事项

- `absolute_redirect off` 只影响 nginx 自己生成的相对路径跳转；显式写完整 URL 的 `return 301 https://example.com/...` 仍然会使用完整 URL。
- 如果必须在所有站点统一修复，放在 `http` 块最省事；如果担心影响个别站点，也可以只放到对应 `server` 块。
- PHP 应用自身输出的 `Location` 不受该指令影响。本项目的 OAuth 跳转使用配置 `app.base_url`，应设置为外部公网地址。
- 修改后执行：

```bash
nginx -t
systemctl reload nginx
```

用 `curl -D -` 检查 `Location` 是否已成为相对路径。
