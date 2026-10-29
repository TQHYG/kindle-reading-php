(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', async function () {
        document.getElementById('year').textContent = new Date().getFullYear();
        const session = await Kykky.initNav('home');
        const actions = document.getElementById('hero-actions');

        if (session.authenticated) {
            actions.innerHTML =
                '<a href="/user.html" class="btn btn-primary btn-lg">进入个人中心</a>' +
                '<a href="/rank.html" class="btn btn-outline-light btn-lg">查看排行榜</a>';
            document.getElementById('quick-actions').hidden = false;
        } else {
            actions.innerHTML =
                '<a href="/login.html" class="btn btn-primary btn-lg">使用 GitHub 登录</a>' +
                '<a href="/rank.html" class="btn btn-outline-light btn-lg">先看看排行榜</a>';
        }
    });
})();
