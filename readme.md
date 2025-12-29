# Kindle 阅读统计系统

这是一个基于 PHP 开发的 Kindle 阅读统计网页，主要目的是与Kindle阅读统计插件 [TQHYG/kykky](https://github.com/TQHYG/kykky) 配合使用，方便用户从插件导出数据并分享。

## 主要功能

### 1. 数据收集方式
- **插件扫码**: 通过 [TQHYG/kykky](https://github.com/TQHYG/kykky) 插件生成二维码，快速导出当月数据。
- **文件上传**: 支持上传 Kindle 插件生成的 log 文件夹，用于进行完整的数据分析和图片导出。

### 2. 统计展示
- **时段分布**: 展示每天 24 小时内的阅读时间分布（按 2 小时一个区间）
- **月度趋势**: 显示当月每日阅读量的变化趋势
- **阅读日历**: 以日历形式展示整月阅读情况，直观显示达标天数
- **数据概览**: 包括今日时长、本月累计、达标天数、连续达标天数等核心指标

### 3. 特色功能
- **报告导出**: 支持将统计报告导出为图片，便于分享
- **响应式设计**: 支持 PC 和移动端访问，适配不同屏幕尺寸

## 使用方法

1. **首页入口**
   - 访问 `index.php` 进入主界面
   - 可选择扫码获取数据或上传文件

2. **扫码同步**
   - 在 Kindle 设备上安装 [TQHYG/kykky](https://github.com/TQHYG/kykky) 插件
   - 通过插件生成二维码并扫描同步数据

3. **文件上传**
   - 上传 Kindle 中 `extensions/kykky/log` 目录下的数据文件
   - 系统自动解析 `metrics_reader_*` 和 `history.gz` 文件

4. **数据查看**
   - 上传或同步成功后进入统计看板
   - 可切换不同月份查看历史数据
   - 支持导出完整报告或单独导出某个统计模块

## 技术架构

- **前端**: HTML5 + CSS3 + JavaScript + Bootstrap 5 + Chart.js
- **后端**: PHP 7+
- **数据存储**: 浏览器 localStorage
- **图表库**: Chart.js
- **截图导出**: html2canvas

## 文件结构

```
├── index.php        # 首页入口
├── upload.php       # 文件上传和数据展示页面
├── share.php        # 数据展示和分享页面
├── functions.php    # 核心数据处理函数
├── style.css        # 样式文件
└── readme.md        # 项目说明文档
```

## 自托管服务

Kindle上的阅读统计插件生成二维码时默认使用我托管的页面（ https://reading.tqhyg.net ），你可以修改插件的配置文件使用自托管服务：

在插件目录内的 etc/config.ini 文件中修改第二行的 share_domain 为自托管服务的域名即可。