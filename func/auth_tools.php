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

// 修改校验函数
function validate_token($token) {
    $config = require __DIR__ . '/../config.php';
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;

    list($data, $sign) = $parts;
    if (hash_hmac('sha256', $data, $config['app_secret']) !== $sign) return null;

    $payload = json_decode(base64_decode($data), true);
    if ($payload['exp'] < time()) return null;

    // 返回包含 uid 和 did 的数组
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
