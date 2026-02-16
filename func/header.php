<?php
// func/header.php
$user = get_current_user_info();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/"><i class="fa-solid fa-book-open me-2"></i>Kykky 阅读数据</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/rank.php"><i class="fa-solid fa-chart-line me-1"></i>排行榜</a></li>
                <li class="nav-item"><a class="nav-link" href="/analysis.php"><i class="fa-solid fa-chart-pie me-1"></i>数据分析</a></li>
                <li class="nav-item"><a class="nav-link" href="/upload.php"><i class="fa-solid fa-cloud-arrow-up me-1"></i>导入数据</a></li>
            </ul>
            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item d-flex align-items-center me-2">
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" class="rounded-circle me-1" width="28" height="28" style="border:1px solid rgba(255,255,255,0.3);">
                        <span class="navbar-text"><?= htmlspecialchars($user['nickname']) ?></span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/user.php">用户中心</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="/login.php?action=logout">退出</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-primary btn-sm" href="/login.php">登录</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
