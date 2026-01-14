<?php
// rank.php
require_once __DIR__ . '/func/common.php';

// --- 逻辑处理：API 接口部分 ---
if (isset($_GET['api'])) {
    $type = $_GET['type'] ?? 'today';
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $sort_field = ($type === 'month') ? 'month_seconds' : 'today_seconds';

    $response = [
        'list' => [],
        'my_rank' => null
    ];

    // 1. 如果是第一页，且用户已登录，计算个人排名
    if ($page === 1 && isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        
        // 获取用户的当前时长
        $user_data = db_query("SELECT {$sort_field} FROM stats WHERE user_id = ?", [$uid]);
        if ($user_data && $user_data[0][$sort_field] > 0) {
            $my_seconds = $user_data[0][$sort_field];
            
            // 计算排名：有多少人的时长严格大于我的时长
            $rank_res = db_query(
                "SELECT COUNT(*) + 1 as rank FROM stats WHERE {$sort_field} > ?", 
                [$my_seconds]
            );
            
            $response['my_rank'] = [
                'rank' => $rank_res[0]['rank'],
                'seconds' => $my_seconds
            ];
        }
    }

    // 2. 获取列表数据
    $sql = "SELECT s.user_id, s.{$sort_field} as seconds, u.nickname, u.avatar 
            FROM stats s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.{$sort_field} > 0
            ORDER BY s.{$sort_field} DESC 
            LIMIT {$limit} OFFSET {$offset}";
    
    $response['list'] = db_query($sql);

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --- 页面显示部分 ---
$type = $_GET['type'] ?? 'today';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>阅读排行榜 - Kykky 阅读数据统计</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<?php include __DIR__ . '/func/header.php'; ?>

<div class="container">
    <div class="tab-nav">
        <a href="?type=today" class="<?= $type == 'today' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-day"></i> 日榜
        </a>
        <a href="?type=month" class="<?= $type == 'month' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i> 月榜
        </a>
    </div>

    <div id="my-rank-container"></div>

    <div class="card" style="padding: 0;"> <div class="rank-list" id="rank-content"></div>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <button id="load-more" class="btn btn-outline" style="width: 200px; justify-content: center;">点击加载更多</button>
    </div>
</div>

<script>
let currentPage = 1;
const type = '<?= $type ?>';
const contentDiv = document.getElementById('rank-content');
const loadMoreBtn = document.getElementById('load-more');
let isEnd = false;

// 格式化时长函数
function formatDuration(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    let res = "";
    if (h > 0) res += h + " 小时 ";
    res += m + " 分钟";
    return res;
}

async function fetchRank() {
    if (isEnd) return;
    
    loadMoreBtn.innerText = "正在加载...";
    
    try {
        const response = await fetch(`rank.php?api=1&type=${type}&page=${currentPage}`);
        const resData = await response.json(); // 现在 resData 包含 {list: [], my_rank: {}}
        
        const list = resData.list;
        const myRank = resData.my_rank;

        // 处理个人排名（仅在第一页显示）
        if (currentPage === 1 && myRank) {
            const myRankHtml = `
                <div class="my-rank-card">
                <div class="rank-num">${myRank.rank}</div>
                    <span class="label">我的排名</span>
                    <div class="info" style="margin-left:20px;">
                        <span class="user-name">当前状态</span>
                        <span class="duration">已阅读: ${formatDuration(myRank.seconds)}</span>
                    </div>
                </div>
            `;
            document.getElementById('my-rank-container').innerHTML = myRankHtml;
        }

        // 处理列表数据
        if (list.length < 20) {
            isEnd = true;
            loadMoreBtn.innerText = "没有更多了";
        } else {
            loadMoreBtn.innerText = "点击加载更多";
        }

        list.forEach((item, index) => {
            const rankIndex = (currentPage - 1) * 20 + index + 1;
            let rankDisplay = rankIndex;
            let topClass = '';

            if (rankIndex === 1) {
                topClass = 'top-3';
                rankDisplay = `<i class="fa-solid fa-crown"></i>`;
            } else if (rankIndex <= 3) {
                topClass = 'top-3';
            }
            
            const html = `
                <div class="rank-item">
                    <div class="rank-num ${topClass}">${rankDisplay}</div>
                    <img src="${item.avatar || 'default-avatar.png'}" class="user-avatar" style="flex-shrink:0; width:40px; height:40px;">
                    <div class="user-info" style="flex-grow:1; margin-left:15px; display:flex; flex-direction:column;">
                        <span class="user-name" style="font-weight:600; font-size:15px;">${escapeHtml(item.nickname)}</span>
                        <span class="duration" style="font-size:12px; color:#666;">
                            <i class="fa-regular fa-clock"></i> ${formatDuration(item.seconds)}
                        </span>
                    </div>
                </div>
            `;
            contentDiv.insertAdjacentHTML('beforeend', html);
        });

        currentPage++;
    } catch (e) {
        console.error(e);
        loadMoreBtn.innerText = "加载失败";
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 初始化加载
fetchRank();

// 点击加载
loadMoreBtn.onclick = fetchRank;

// 滚动到底部自动加载

window.onscroll = function() {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
        fetchRank();
    }
};

</script>

</body>
</html>