<?php
require_once __DIR__ . '/func/common.php';
$user = get_current_user_info();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kykky Kindle - 你的 Kindle 阅读数据中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<section class="hero">
    <div class="container">
        <h1>阅读，不再孤单。</h1>
        <p>自动同步 Kindle 阅读记录，与同好一起发现阅读的乐趣。</p>
        
        <div class="main-actions">
            <?php if (!$user): ?>
                <a href="/login.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">使用 GitHub 登录</a>
            <?php else: ?>
                <a href="/user.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">进入个人中心</a>
                <a href="/rank.php" class="btn btn-outline" style="padding: 12px 30px; font-size: 16px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: white;">查看排行榜</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container">
    <div class="feature-grid">
        <div class="card feature-item">
            <i class="fa-solid fa-bolt"></i> <h3>实时数据统计</h3>
            <p>通过 Kindle 自动云备份与数据处理，无需插线即可实时同步阅读时长，并在全网排行中展示你的进度。</p>
            <a href="/rank.php" class="btn btn-outline">
                <i class="fa-solid fa-ranking-star"></i> 立即查看排行榜
            </a>
        </div>
        
        <div class="card feature-item">
            <i class="fa-solid fa-chart-pie"></i> 
            <h3>传统本地数据分析</h3>
            <p>想要更详细的图表？不希望数据被上传？我们保留了经典的本地化分析工具，支持查看历史趋势与阅读分布。</p>
            <a href="/origin/" class="btn btn-outline">
                <i class="fa-solid fa-flask-vial"></i> 进入本地分析模式
            </a>
        </div>
    </div>

    <?php if ($user): ?>
    <div class="card">
        <h3><i class="fa-solid fa-rocket"></i> 快速操作</h3>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="/upload.php" class="btn btn-outline">
                <i class="fa-solid fa-cloud-arrow-up"></i> 手动更新日志
            </a>
            <a href="user.php?action=download_backup" class="btn btn-primary">
                <i class="fa-solid fa-file-zipper"></i> 打包下载备份 (.zip)
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="background: transparent; border-style: dashed;">
        <h4 style="margin-top: 0;">如何开始？</h4>
        <ol style="color: var(--text-muted); padding-left: 20px;">
            <li>登录并前往个人中心。</li>
            <li>在Kindle上安装插件并注册你的设备以进行同步。</li>
            <li>在 Kindle 插件内启动同步，或手动在此处上传 <code>log</code> 文件夹。</li>
            <li>从此你的每一分钟阅读都将被铭记。</li>
        </ol>
    </div>
</div>

<footer style="text-align: center; padding: 40px; color: var(--text-muted); font-size: 12px;">
    &copy; <?= date('Y') ?> Kykky Project. TQHYG.
</footer>

</body>
</html>