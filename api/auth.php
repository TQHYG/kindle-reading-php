<?php
// api/auth.php

require_once __DIR__ . '/../func/db.php';
require_once __DIR__ . '/../func/auth_tools.php';

// 强制所有返回都是 JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_GET['action'] ?? '';

    // ==========================================
    // 1. 获取绑定设备 URL
    // ==========================================
    if ($action === 'get_url') {
        $ip = $_SERVER['REMOTE_ADDR'];
    
        // 简单的频率限制
        $recent_count = db_query(
            "SELECT COUNT(*) as cnt FROM devices WHERE status = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)", 
            []
        )[0]['cnt'];

        if ($recent_count > 10) { // 全局限制
            api_response(['error' => 'Too many requests. Please try again later.'], 429);
        }
        $device_code = bin2hex(random_bytes(8));
        // status 0:等待, 1:成功
        db_query("INSERT INTO devices (code, status) VALUES (?, 0)", [$device_code]);
        
        $login_url = "https://" . $_SERVER['HTTP_HOST'] . "/device.php?device_code=" . $device_code;
        
        api_response([
            'device_code' => $device_code,
            'login_url'   => $login_url
        ]);
    }

    // ==========================================
    // 2. 轮询状态
    // ==========================================
    if ($action === 'check_status') {
        $code = $_GET['device_code'] ?? '';
        
        if (empty($code)) {
            api_response(['status' => 'error', 'msg' => 'missing_code'], 400);
        }

        // 查询数据库
        $rows = db_query("SELECT * FROM devices WHERE code = ?", [$code]);
        $row = $rows[0] ?? null;
        
        // 情况 A: 码不存在或已过期 (被删除)
        if (!$row) {
            api_response(['status' => 'expired'], 404);
        }
        
        // 情况 B: 已成功 (Status = 1)
        // 注意：数据库取出的可能是字符串 "1"，用 == 宽松比较
        if ($row['status'] == 1) {
            $user_id = $row['user_id'];
            $device_name = $row['device_name'];
            
            // 生成 Token
            $token = generate_token($row['user_id'], $row['code']);
            
            // 获取用户昵称用于显示
            $u_rows = db_query("SELECT nickname, avatar FROM users WHERE id = ?", [$user_id]);
            $user_info = $u_rows[0] ?? ['nickname' => 'User', 'avatar' => ''];
            
            api_response([
                'status'       => 'success',
                'access_token' => $token,
                'nickname'     => $user_info['nickname'],
                'avatar'       => $user_info['avatar'],
                'device_name'  => $device_name
            ]);
        } 
        // 情况 C: 等待中 (Status = 0)
        else {
            api_response(['status' => 'pending']);
        }
    }

    // 未知动作
    api_response(['status' => 'error', 'msg' => 'invalid_action'], 400);

} catch (Exception $e) {
    // 捕获所有致命错误并以 JSON 返回
    api_response(['status' => 'error', 'msg' => $e->getMessage()], 500);
}
