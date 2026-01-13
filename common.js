/**
 * common.js - 公共逻辑库
 * 包含：昵称管理、图表配置、截图导出
 */

const STORAGE_NICKNAME_KEY = 'kykky_nickname';

// --- 1. 昵称管理模块 ---

function initNicknameSystem() {
    const nickname = localStorage.getItem(STORAGE_NICKNAME_KEY);
    const displayEl = document.getElementById('nickname-display');
    
    if (displayEl) {
        displayEl.textContent = nickname || '我';
    }

    // 如果没有昵称，自动打开弹窗
    if (!nickname) {
        openNicknameModal();
    }
}

function saveNickname() {
    const input = document.getElementById('nickname-input');
    const name = input.value.trim();
    if (name) {
        localStorage.setItem(STORAGE_NICKNAME_KEY, name);
        const displayEl = document.getElementById('nickname-display');
        if (displayEl) displayEl.textContent = name;
        closeNicknameModal();
    } else {
        alert('请输入一个昵称');
    }
}

function openNicknameModal() {
    const modal = document.getElementById('nickname-modal');
    const input = document.getElementById('nickname-input');
    // 填充当前昵称
    input.value = localStorage.getItem(STORAGE_NICKNAME_KEY) || '';
    modal.classList.add('active');
}

function closeNicknameModal() {
    document.getElementById('nickname-modal').classList.remove('active');
}


// --- 2. 图表通用配置 ---

const urlParams = new URLSearchParams(window.location.search);
const exportMode = urlParams.get('exporting') === '1';
const isMobile = exportMode ? false : window.innerWidth < 768;

// 坐标轴设置
const pcConfig = {
    indexAxis: 'x',
    scales: {
        x: {
            ticks: { maxRotation: 60 },
            grid: { display: false }
        },
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value) { return value + 'm'; }
            }
        }
    }
};

const mobileConfig = {
    indexAxis: 'y',
    scales: {
        x: {
            beginAtZero: true,
            ticks: {
                callback: function(value) { return value + 'm'; }
            }
        },
        y: {
            reverse: false
        }
    }
};

// 根据设备类型合并配置
const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } }
};

const finalOptions = isMobile 
    ? Object.assign({}, baseOptions, mobileConfig)
    : Object.assign({}, baseOptions, pcConfig);

const commonConfig = finalOptions

// 导出函数
async function sectionExport(element, filename) {
    // 临时增加 padding 防止截图边缘切断阴影
    const originalPadding = element.style.padding;

    const canvas = await html2canvas(element, {
            scale: 2,
            useCORS: true,
            backgroundColor: "#d1ecfeff",
        });

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = filename + '.png';
    link.click();
}

async function fullExport(element, filename) {
    if (window.innerWidth < 768) {
        // 构建新URL，添加参数
        const currentUrl = window.location.href;
        const separator = currentUrl.includes('?') ? '&' : '?';
        const exportUrl = `${currentUrl}${separator}exporting=1`;
            
        // 打开新窗口
        const exportWindow = window.open(exportUrl, '_blank');
            
         // 等待新窗口加载完成后，监听导出事件
        if (exportWindow) {
            // 设置一个标记，防止重复导出
            window.exportInProgress = true;
                
            // 监听新窗口的消息（新窗口导出完成后会发送消息）
            window.addEventListener('message', function(event) {
                if (event.data === 'export_complete') {
                    // 新窗口可以关闭了
                    try {
                        console.log('新窗口导出结束');
                        //exportWindow.close();
                    } catch (e) {
                        console.log('新窗口已关闭或无法关闭');
                    }
                    window.exportInProgress = false;
                }
            });
            
            return; // 手机端直接返回，后续在新窗口处理
        }
    }
    const fab = document.querySelector('.fab-container');

    // 1. 锁定宽度并隐藏UI
    fab.style.visibility = 'hidden';

    try {
        const canvas = await html2canvas(element, {
            scale: 2,
            useCORS: true,
            backgroundColor: "#d1ecfeff",
            windowWidth: 1000 // 强制渲染器按此宽度解析
        });

        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = filename + '.png';
        link.click();
    } catch (e) {
        console.log('生成失败');
    }
    fab.style.visibility = 'visible';
}

function quickExport(id, name) {
    sectionExport(document.getElementById(id), '阅读统计-' + name);
}

function autoExport(filename) {
    // 隐藏悬浮按钮
    document.querySelector('.fab-container').style.display = 'none';
            
    // 添加一个提示信息
    const notice = document.createElement('div');
    notice.id = 'export-notice';
    notice.innerHTML = '<div style="position: fixed; top: 10px; left: 10px; right: 10px; background: #ffc107; padding: 20px; border-radius: 5px; text-align: left; z-index: 10000;">正在生成截图，请稍候...</div>';
    document.body.appendChild(notice);

    // 延迟执行导出，确保页面完全渲染
    setTimeout(async () => {
        try {
            const canvas = await html2canvas(document.getElementById('capture-area'), {
                scale: 2,
                useCORS: true,
                backgroundColor: "#daf0ffff",
                windowWidth: 1200
            });

            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = `${filename}.png`;
            link.click();
                    
            // 移除提示
            document.getElementById('export-notice').remove();
                    
            // 通知父窗口导出完成
            if (window.opener) {
                window.opener.postMessage('export_complete', '*');
            }
                
        } catch (error) {
            console.error('导出失败:', error);
            document.getElementById('export-notice').innerHTML = 
                '<div style="position: fixed; top: 10px; left: 10px; right: 10px; background: #dc3545; color: white; padding: 10px; border-radius: 5px; text-align: center; z-index: 10000;">导出失败，请重试</div>';
        }
    }, 1000);
}