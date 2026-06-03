<?php
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    echo json_encode(['success' => false, 'message' => '环境变量BASE_URL未设置']);
    exit;
}

// 文件路径: {$baseUrl}/gest/qlrupnqe001.php

header('Content-Type: application/json');

function generateMerchantKey() {
    return md5(uniqid(mt_rand(), true));
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['custom_id'])) {
    $response = [
        'success' => true,
        'key' => generateMerchantKey()
    ];
} else {
    $response = [
        'success' => false,
        'message' => '缺少custom_id参数'
    ];
}

echo json_encode($response);
?>