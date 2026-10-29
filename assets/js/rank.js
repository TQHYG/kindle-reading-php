(function () {
    'use strict';

    const type = Kykky.queryParam('type', 'today') === 'month' ? 'month' : 'today';
    let currentPage = 1;
    let loading = false;
    let isEnd = false;
    const content = document.getElementById('rank-content');
    const loadMore = document.getElementById('load-more');

    function avatarHtml(item) {
        if (item.avatar) {
            return '<img src="' + Kykky.escapeHtml(item.avatar) + '" class="user-avatar" alt="">';
        }
        return '<div class="user-avatar d-flex align-items-center justify-content-center">' +
            '<i class="fa-solid fa-user text-secondary"></i></div>';
    }

    async function fetchRank() {
        if (loading || isEnd) return;
        loading = true;
        loadMore.disabled = true;
        loadMore.textContent = '正在加载...';

        try {
            const payload = await Kykky.request('/api/rank.php?type=' + type + '&page=' + currentPage);
            const data = payload.data;
            const list = data.list || [];

            if (currentPage === 1) {
                content.innerHTML = '';
                if (data.my_rank) {
                    document.getElementById('my-rank-container').innerHTML =
                        '<div class="alert alert-info d-flex align-items-center gap-3">' +
                        '<div class="rank-num fs-3 fw-bold text-primary">' + data.my_rank.rank + '</div>' +
                        '<div><strong>我的排名</strong><br><small class="text-secondary">已阅读: ' +
                        Kykky.escapeHtml(Kykky.formatDuration(data.my_rank.seconds)) + '</small></div></div>';
                }
            }

            if (!list.length && currentPage === 1) {
                content.innerHTML = '<div class="text-center text-secondary py-5">' +
                    '<i class="fa-regular fa-face-smile me-1"></i> 暂无排行数据</div>';
            }

            const offset = (currentPage - 1) * data.limit;
            list.forEach(function (item, index) {
                const rankIndex = offset + index + 1;
                let rankDisplay = String(rankIndex);
                let topClass = '';
                if (rankIndex === 1) {
                    topClass = 'top-3';
                    rankDisplay = '<i class="fa-solid fa-crown"></i>';
                } else if (rankIndex <= 3) {
                    topClass = 'top-3';
                }
                content.insertAdjacentHTML('beforeend',
                    '<div class="rank-item">' +
                    '<div class="rank-num ' + topClass + '">' + rankDisplay + '</div>' +
                    avatarHtml(item) +
                    '<div class="ms-3 flex-grow-1">' +
                    '<div class="fw-semibold">' + Kykky.escapeHtml(item.nickname || 'User') + '</div>' +
                    '<small class="text-secondary"><i class="fa-regular fa-clock me-1"></i>' +
                    Kykky.escapeHtml(Kykky.formatDuration(item.seconds)) + '</small>' +
                    '</div></div>');
            });

            isEnd = !data.has_more;
            currentPage++;
            loadMore.textContent = isEnd ? '没有更多了' : '点击加载更多';
        } catch (e) {
            loadMore.textContent = '加载失败，点击重试';
            Kykky.toast(e.message, 'danger');
        } finally {
            loading = false;
            loadMore.disabled = isEnd;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        const tab = document.getElementById('tab-' + type);
        if (tab) tab.classList.add('active');
        Kykky.initNav('rank');
        fetchRank();
        loadMore.addEventListener('click', fetchRank);
        window.addEventListener('scroll', function () {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 120) {
                fetchRank();
            }
        });
    });
})();
