<?php
header('Content-Type: text/plain; charset=utf-8');

// 确保接收到的是POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST请求的原始内容
    $content = file_get_contents('php://input');
    // 尝试解析JSON格式的请求体
    $data = json_decode($content, true);

    // 检查是否成功解析JSON以及必要的字段是否存在
    if ($data && isset($data['out_trade_no']) && isset($data['status'])) {
        // 检查订单状态是否为"success"
        if ($data['status'] === 'success') {
            // 如果是，返回"dnal"字符串
            echo 'success';
        } else {
            // 如果状态不是"success"，返回错误信息
            echo '订单状态不正确';
        }
    } else {
        // 如果解析失败或缺少字段，返回错误信息
        echo '请求格式错误或缺少必要的字段';
    }
} else {
    // 如果不是POST请求，返回错误信息
    echo '无效的请求方法';
}
?>