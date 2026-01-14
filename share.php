<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>跳转中...</title>
<script>
(function () {
    // 当前路径
    var path = window.location.pathname;

    // 只处理 /share.php 这个入口
    if (path === '/share.php') {
        // 保留原有的查询参数和 hash
        var search = window.location.search || '';
var hash = window.location.hash || '';

// 目标路径：/origin/share.php
var target = '/origin/share.php' + search + hash;

// 使用 replace 避免在历史记录中留下当前页面
window.location.replace(target);
    }
})();
</script>
</head>
<body>
正在跳转，请稍候……
</body>
</html>
