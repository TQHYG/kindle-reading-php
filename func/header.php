<?php
// func/header.php
$user = get_current_user_info();
?>
<header>
    <div class="logo">
        <a href="/"><i class="fa-solid fa-book-open"></i> Kykky 阅读数据</a>
    </div>
    <nav>
        <a href="/rank.php"><i class="fa-solid fa-chart-line"></i> 排行榜</a>
        <a href="/upload.php"><i class="fa-solid fa-cloud-arrow-up"></i> 导入数据</a>
        <?php if ($user): ?>
            <span style="display: flex; align-items: center; margin-right: 10px; font-size: 14px; color: #c9d1d9;">
                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="user-avatar">
                <?= htmlspecialchars($user['nickname']) ?>
            </span>
            <a href="/user.php">用户中心</a>
            <a href="/login.php?action=logout" class="btn-logout">退出</a>
        <?php else: ?>
            <a href="/login.php" class="btn btn-primary">登录</a>
        <?php endif; ?>
    </nav>
</header>
