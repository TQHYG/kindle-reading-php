<?php
// func/common.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_tools.php';
$config = require __DIR__ . '/../config.php';

// 设置 30 天长效 Session
ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
session_start();

/**
 * 获取当前登录用户信息
 */
function get_current_user_info() {
    if (!isset($_SESSION['user_id'])) return null;
    $rows = db_query("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    return $rows[0] ?? null;
}

/**
 * 重定向
 */
function redirect($path) {
    // 如果已经是完整路径则直接跳转
    if (strpos($path, 'http') === 0) {
        header("Location: $path");
        exit;
    }

    // 获取当前协议 (http 或 https)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

    // 获取当前域名
    $host = $_SERVER['HTTP_HOST'];

    // 确保 $path 以 / 开头
    if (strpos($path, '/') !== 0) {
        $path = '/' . $path;
    }

    // 拼凑完整的 URL
    header("Location: " . $protocol . $host . $path);
    exit;
}
 // 将秒数转换为易读的时长格式
function format_duration($seconds) {
    if ($seconds <= 0) return '0 <span class="stat-unit">分</span>';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);

    $res = '';
    if ($h > 0) $res .= $h . ' <span class="stat-unit">时</span> ';
    if ($m > 0 || $h == 0) $res .= $m . ' <span class="stat-unit">分</span>';
    return $res;
}
