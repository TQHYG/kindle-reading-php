<?php
// api/upload.php

require_once __DIR__ . '/../func/common.php';

// 1. 身份校验
$auth = require_auth();
$uid = $auth['uid'];

// 2. 参数获取
$today_sec = isset($_GET['today_seconds']) ? (int)$_GET['today_seconds'] : null;
$month_sec = isset($_GET['month_seconds']) ? (int)$_GET['month_seconds'] : null;

// 定义存储路径
$user_dir = __DIR__ . "/../storage/{$uid}";
if (!is_dir($user_dir)) mkdir($user_dir, 0755, true);

// ==========================================
// 逻辑 A: 处理文件上传 (带原子性保证)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logs'])) {
    $files = $_FILES['logs'];
    $verified_uploads = [];

    // 第一步：严格校验所有上传的文件
    foreach ($files['name'] as $key => $name) {
        $filename = basename($name);
        $tmp_path = $files['tmp_name'][$key];
        $file_size = $files['size'][$key];

        // 1.1 文件名白名单校验 (使用正则适配 metrics_reader_2501 等格式)
        $is_metrics = preg_match('/^metrics_reader_\d+$/', $filename);
        $is_history = ($filename === 'history.gz');

        if (!$is_metrics && !$is_history) {
            api_response(['status' => 'error', 'msg' => "非法文件名: $filename"], 403);
        }

        // 1.2 文件大小校验 (metrics 5MB, history 20MB)
        $max_size = $is_history ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
        if ($file_size > $max_size) {
            api_response(['status' => 'error', 'msg' => "文件 $filename 过大"], 413);
        }

        // 1.3 内容合法性抽样检查
        if ($is_metrics) {
            $handle = fopen($tmp_path, 'r');
            $sample = fread($handle, 4096);
            fclose($handle);
            // 检查是否包含关键的 activeDuration 字符串
            if (!strpos($sample, 'com.lab126.booklet.reader.activeDuration')) {
                api_response(['status' => 'error', 'msg' => "文件 $filename 格式不正确"], 400);
            }
        } elseif ($is_history) {
            $handle = fopen($tmp_path, 'r');
            $header = fread($handle, 2);
            fclose($handle);
            if ($header !== "\x1f\x8b") { // Gzip 魔数校验
                api_response(['status' => 'error', 'msg' => "history.gz 不是有效的 Gzip 文件"], 400);
            }
        }

        // 记录校验通过的文件信息
        $verified_uploads[] = [
            'tmp'  => $tmp_path,
            'dest' => "{$user_dir}/{$filename}"
        ];
    }

    // 第二步：原子性操作 - 只有全部校验通过才清空并写入
    if (!empty($verified_uploads)) {
        // 清空该用户目录下的旧日志文件
        array_map('unlink', glob("$user_dir/*"));

        // 写入新文件
        foreach ($verified_uploads as $upload) {
            if (!move_uploaded_file($upload['tmp'], $upload['dest'])) {
                api_response(['status' => 'error', 'msg' => "物理文件保存失败"], 500);
            }
        }
    }
}

// ==========================================
// 逻辑 B: 数据统计同步 (调用 common.php 中的函数)
// ==========================================
$db_updated = false;
if ($today_sec !== null && $month_sec !== null) {
    // 调用已有函数 update_user_stats($user_id, $today_sec, $month_sec)
    if (update_user_stats($uid, $today_sec, $month_sec)) {
        $db_updated = true;
    } else {
        // 如果数值超出逻辑范围（例如单日超过 24 小时），该函数会返回 false
        api_response(['status' => 'error', 'msg' => '阅读数据校验未通过'], 400);
    }
}

// ==========================================
// 最终返回
// ==========================================
api_response([
    'status' => 'success',
    'msg' => 'Sync completed',
    'db_synced' => $db_updated,
    'files_count' => isset($verified_uploads) ? count($verified_uploads) : 0
]);