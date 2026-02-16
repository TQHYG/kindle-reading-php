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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<section class="hero">
    <div class="container">
        <h1>阅读，不再孤单。</h1>
        <p class="lead">自动同步 Kindle 阅读记录，与同好一起发现阅读的乐趣。</p>
        
        <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
            <?php if (!$user): ?>
                <a href="/login.php" class="btn btn-primary btn-lg">使用 GitHub 登录</a>
            <?php else: ?>
                <a href="/user.php" class="btn btn-primary btn-lg">进入个人中心</a>
                <a href="/rank.php" class="btn btn-outline-light btn-lg">查看排行榜</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fa-solid fa-bolt fa-2x text-primary mb-3"></i>
                    <h5 class="card-title">实时数据统计</h5>
                    <p class="card-text text-secondary">通过 Kindle 自动云备份与数据处理，无需插线即可实时同步阅读时长，并在全网排行中展示你的进度。</p>
                    <a href="/rank.php" class="btn btn-outline-primary">
                        <i class="fa-solid fa-ranking-star me-1"></i> 立即查看排行榜
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fa-solid fa-chart-pie fa-2x text-primary mb-3"></i>
                    <h5 class="card-title">用户数据分析</h5>
                    <p class="card-text text-secondary">想要更详细的图表？查看历史趋势与阅读分布，生成专属于你的阅读分析报告。</p>
                    <a href="/analysis.php" class="btn btn-outline-primary">
                        <i class="fa-solid fa-chart-column me-1"></i> 进入数据分析
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="fa-solid fa-rocket me-1"></i> 快速操作</h5>
            <div class="d-flex gap-2 mt-3 flex-wrap">
                <a href="/upload.php" class="btn btn-outline-primary">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> 手动更新日志
                </a>
                <a href="/api/files.php?action=download_backup" class="btn btn-primary">
                    <i class="fa-solid fa-file-zipper me-1"></i> 打包下载备份 (.zip)
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-dashed mb-4" style="background: transparent; border-style: dashed;">
        <div class="card-body">
            <h5 class="card-title">如何开始？</h5>
            <ol class="text-secondary ps-3">
                <li>登录并前往个人中心。</li>
                <li>在Kindle上安装插件并注册你的设备以进行同步。</li>
                <li>在 Kindle 插件内启动同步，或手动在此处上传 <code>log</code> 文件夹。</li>
                <li>从此你的每一分钟阅读都将被铭记。</li>
            </ol>
        </div>
    </div>
</div>

<footer class="text-center py-4 text-secondary small">
    &copy; <?= date('Y') ?> Kykky Project. TQHYG.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>