<?php
// func/auth_tools.php

// 生成函数
function generate_token($user_id, $device_id) {
    $config = require __DIR__ . '/../config.php';
    $payload = [
        'uid' => $user_id,
        'did' => $device_id, // Device ID
        'exp' => time() + (86400 * 365) // 一年有效期
    ];
    // 使用简单的 base64 组合签名
    $data = base64_encode(json_encode($payload));
    $sign = hash_hmac('sha256', $data, $config['app_secret']);
    return $data . '.' . $sign;
}

// 校验函数
function validate_token($token) {
    $config = require __DIR__ . '/../config.php';
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;

    list($data, $sign) = $parts;
    
    // 1. 签名校验：确保 Token 是由本服务器签发的，且未被篡改
    if (hash_hmac('sha256', $data, $config['app_secret']) !== $sign) return null;

    $payload = json_decode(base64_decode($data), true);
    
    // 2. 时效性检查：确保 Token 未过期
    if (!isset($payload['exp']) || $payload['exp'] < time()) return null;

    // 3. 归属性与存在性双重检查 
    $sql = "SELECT COUNT(*) as cnt FROM devices WHERE code = ? AND user_id = ? AND status = 1";
    $res = db_query($sql, [$payload['did'], $payload['uid']]);
    
    if (!$res || $res[0]['cnt'] == 0) {
        return null; 
    }

    return $payload;
}

function require_auth() {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
    // 处理 Bearer 格式
    if (strpos($token, 'Bearer ') === 0) $token = substr($token, 7);

    $payload = validate_token($token);
    if (!$payload) {
        api_response(['status' => 'error', 'msg' => 'Unauthorized or invalid token'], 401);
    }
    return $payload;
}

/**
 * 统一 JSON 返回
 */
function api_response($data, $code = 200) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($data);
    exit;
}
