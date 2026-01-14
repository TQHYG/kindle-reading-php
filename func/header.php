<?php
// func/header.php
$user = get_current_user_info();
?>
<header style="display: flex; justify-content: space-between; align-items: center; padding: 10px 5%; background: #24292e; color: white;">
    <div class="logo">
        <a href="/" style="color: white; text-decoration: none; font-weight: bold; font-size: 1.2rem;">Kykky 阅读数据</a>
    </div>
    <nav>
        <a href="/rank.php" style="color: #ccc; margin-right: 15px; text-decoration: none;">排行榜</a>
        <?php if ($user): ?>
            <span style="margin-right: 10px;">
                <img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:24px; border-radius:50%; vertical-align:middle;">
                <?= htmlspecialchars($user['nickname']) ?>
            </span>
            <a href="/user.php" style="color: #ccc; margin-right: 15px; text-decoration: none;">用户中心</a>
            <a href="/login.php?action=logout" style="color: #ff6b6b; text-decoration: none;">退出</a>
        <?php else: ?>
            <a href="/login.php" style="background: #2ea44f; color: white; padding: 5px 15px; border-radius: 5px; text-decoration: none;">登录</a>
        <?php endif; ?>
    </nav>
</header>