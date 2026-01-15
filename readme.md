# Kykky Kindle 阅读统计系统

这是一个基于 PHP 开发的 Kindle 阅读数据同步与展示平台。配合 Kindle 插件 [TQHYG/kykky](https://github.com/TQHYG/kykky) 使用，可以实现阅读数据的自动云同步、全网排行榜、以及详细的本地数据分析。

## 🚀 主要功能

* **多维度数据同步**：
* **自动扫码绑定**：通过插件生成二维码，快速将 Kindle 设备与账号关联。
* **快捷同步**：支持通过特殊编码的 URL 参数快速同步今日与本月阅读时长。
* **手动上传**：支持上传 Kindle 内的 `log` 文件夹，解析 `metrics_reader` 历史记录。


* **社交与排行**：
* **全网排行榜**：支持“日榜”与“月榜”，实时查看你在所有用户中的阅读排名。
* **GitHub 登录**：集成 GitHub OAuth，快速创建账号并同步头像与昵称。


* **数据可视化与分析**：
* **实时统计**：展示今日时长、本月累计、连续达标天数等核心指标。
* **旧版/本地模式**：保留了原有的 `/origin/` 路径，用于纯本地、不上传服务器的数据分析。



## 🛠️ 安装与配置

### 1. 配置文件

仓库中提供了 `config.php.sample.txt` 作为模板，你需要将其重命名为 `config.php` 并修改相关配置：

```bash
cp config.php.sample.txt config.php

```

**配置要点：**

* **db**: 填写你的 MySQL 数据库连接信息。
* **oauth**:
* 前往 [GitHub Developer Settings](https://github.com/settings/developers) 创建 OAuth App。
* `redirect_uri` 必须与 GitHub 后台设置的的回调地址一致。


* **security**: `token_secret` 请修改为一段随机的强字符串，用于签名安全。

### 2. 旧版入口说明

如果你希望使用原有的本地分析工具而不经过账号系统：

* 直接访问 `/origin/` 路径。
* 根目录下的 `share.php` 会自动重定向旧版分享链接至 `/origin/share.php`，确保历史链接不失效。

## 🌐 Nginx 示例配置

为了确保路由正常工作（尤其是处理无后缀请求和静态资源），建议使用以下配置：

```nginx

server {
    listen 80;
    listen 443 ssl;        # OAuth大多要求配置https

    server_name reading.tqhyg.net;

    ssl_certificate /path/to/your/certs.pem;
    ssl_certificate_key /path/to/your/privkey.pem;

    root /var/www/html;
    index index.php index.html index.htm;

    location ^~ /func/ {
        deny all;          # 禁止直接访问函数功能目录
        return 403;
    }
    location ^~ /storage/ {
        deny all;          # 禁止直接访问用户数据目录
        return 403;
    }

    location / {
        try_files $uri $uri/ $uri/index.php =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;    # 根据你的 PHP 版本修改
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}


```

## 📂 文件结构

* `index.php`: 门户首页，展示功能特性。
* `user.php`: 个人中心，管理设备与查看个人数据。
* `rank.php`: 阅读排行榜（支持 API 调用与分页加载）。
* `device.php`: 设备绑定页面。
* `fastsync.php`: 处理来自 Kindle 插件的快捷同步请求。
* `login.php`: GitHub OAuth 登录逻辑。
* `style.css`: 统一的视觉样式表。
* `/func/`: 存放数据库连接、用户校验等核心函数。
* `/origin/`: 存放旧版纯前端数据分析工具。

---

**想要参与开发？**
欢迎提交 Pull Request 或通过 GitHub Issue 反馈建议。
