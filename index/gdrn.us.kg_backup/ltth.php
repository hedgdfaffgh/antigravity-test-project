<?php
// 设置返回值默认为0（失败）
$uu = 0;

// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 检查Content-Type
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

    if ($contentType === 'application/json') {
        // 处理application/json格式
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if (isset($data['名字'])) {
            $uu = 1;  // 成功接收到名字，设置返回值为1
        } else {
            error_log("No name provided.");
        }
    } else {
        error_log("Unsupported Content-Type or no name provided.");
    }
} else {
    error_log("Not a POST request.");
}

// 返回$uu值
echo json_encode(['uu' => $uu]);
?>