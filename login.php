<?php
// login.php
require_once __DIR__ . '/func/common.php';
$config = require __DIR__ . '/config.php';
$oauth_conf   = $config['oauth'];

$action = $_GET['action'] ?? '';

// 1. 处理退出
if ($action === 'logout') {
    session_destroy();
    redirect('/');
}

// 2. 处理 GitHub OAuth 回调
if (isset($_GET['code']) && isset($_GET['state'])) {
    $auth_code   = $_GET['code'];
    $state = $_GET['state'];
    // --- 核心逻辑：用 code 换取用户信息 ---
    $ch = curl_init($oauth_conf['url_access_token']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id'     => $oauth_conf['client_id'],
        'client_secret' => $oauth_conf['client_secret'],
        'code'          => $auth_code,
        'redirect_uri'  => $oauth_conf['redirect_uri']
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $token_resp = curl_exec($ch);
    curl_close($ch);
    $token_data = json_decode($token_resp, true);

    if (!isset($token_data['access_token'])) die("GitHub 授权失败。");
    $access_token = $token_data['access_token'];

    //  获取 GitHub 用户详情
    $ch = curl_init($oauth_conf['url_user_info']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $access_token",
        "User-Agent: Kykky-App"
    ]);
    $user_resp = curl_exec($ch);
    curl_close($ch);
    $user_info = json_decode($user_resp, true);

    $gh_id    = $user_info['id'];
    $nickname = $user_info['login'];
    $avatar   = $user_info['avatar_url'];

    // 同步数据库
    $u_rows = db_query("SELECT id FROM users WHERE oauth_id = ?", [$gh_id]);
    if ($u_rows) {
        $user_id = $u_rows[0]['id'];
        db_query("UPDATE users SET nickname=?, avatar=? WHERE id=?", [$nickname, $avatar, $user_id]);
    } else {
        $user_id = db_query("INSERT INTO users (oauth_id, oauth_provider, nickname, avatar) VALUES (?, 'github', ?, ?)", [$gh_id, $nickname, $avatar]);
        db_query("INSERT INTO stats (user_id) VALUES (?)", [$user_id]);
    }

    // 写入 Session
    $_SESSION['user_id'] = $user_id;

    // 决定回跳地址：如果有记录来源则跳回，否则去用户中心
    $goto = $_SESSION['redirect_after_login'] ?? '/user.php';
    unset($_SESSION['redirect_after_login']);
    redirect($goto);
}

// 3. 登录展示页
$user = get_current_user_info();
if ($user) {
    // 已登录状态，显示倒计时跳转
    $goto = $_SESSION['redirect_after_login'] ?? '/user.php';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>登录成功 - Kykky</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/func/header.php'; ?>
        <div class="container">
            <div class="card shadow-sm mx-auto text-center" style="max-width:400px; margin-top: 100px;">
                <div class="card-body py-5">
                    <div class="text-success mb-3" style="font-size: 48px;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h3>欢迎回来，<?= htmlspecialchars($user['nickname']) ?></h3>
                    <p class="text-secondary">
                        登录成功！我们将于 <span id="timer" class="fw-bold text-primary">3</span> 秒后为您跳转。
                    </p>
                    <div class="mt-3">
                        <a href="<?= $goto ?>" class="btn btn-outline-primary">立即跳转</a>
                    </div>
                </div>
            </div>
        </div>
        <script>
            let s = 3;
            const timerEl = document.getElementById('timer');
            const interval = setInterval(() => {
                s--;
                if (s <= 0) {
                    clearInterval(interval);
                    location.href = '<?= $goto ?>';
                }
                if (timerEl) timerEl.innerText = s;
            }, 1000);
        </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// 4. 未登录状态，存储来源并显示 GitHub 按钮
if (isset($_GET['from'])) {
    $_SESSION['redirect_after_login'] = $_GET['from'];
}

$github_url = $config['oauth']['url_authorize'] . "?" . http_build_query([
    'client_id'    => $config['oauth']['client_id'],
    'redirect_uri' => $config['oauth']['redirect_uri'], 
    'scope'        => 'read:user',
    'state'        => bin2hex(random_bytes(4))
]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - Kykky 阅读数据统计</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <?php include __DIR__ . '/func/header.php'; ?>
    
    <div class="container">
        <div class="card shadow-sm mx-auto text-center" style="max-width:400px; margin-top: 80px;">
            <div class="card-body py-5">
                <div class="mb-3" style="font-size: 40px;">
                    <i class="fa-solid fa-book-bookmark"></i>
                </div>
                <h2 class="mb-2">账号登录</h2>
                <p class="text-secondary small mb-4">
                    同步您的 Kindle 阅读数据，参与全球排名
                </p>
                
                <a href="<?= $github_url ?>" class="btn d-flex align-items-center justify-content-center gap-2 py-2"
                   style="background-color: #24292e; color: #fff; border: none;">
                    <i class="fa-brands fa-github"></i> 使用 GitHub 账号登录
                </a>
                
                <div class="mt-4 pt-3 border-top small text-secondary">
                    登录即代表您同意我们的隐私政策与数据同步协议
                </div>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>