<?php
// 检查是否有POST数据
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 获取并验证环境变量
    $baseUrl = getenv('BASE_URL');
    if (!$baseUrl) {
        echo json_encode(['message' => '环境变量BASE_URL未设置', 'uu' => '11']);
        exit;
    }

    // 获取输入数据
    $inputContent = file_get_contents('php://input');

    // 尝试将输入解析为JSON
    $jsonData = json_decode($inputContent, true);
    $response = []; // 初始化响应数组

    if (json_last_error() === JSON_ERROR_NONE) {
        // 检查是否包含图片数据和名字
        if (isset($jsonData['图片']) && isset($jsonData['名字'])) {
            // Base64编码的图片数据
            $base64_string = $jsonData['图片'];
            // 解码图片数据
            $image_data = base64_decode($base64_string);
            // 构建文件路径
            $filename = '/www/wwwroot/' . $baseUrl . '/mqfh/' . $jsonData['名字'] . '.jpg';
            // 保存图片文件
            if (file_put_contents($filename, $image_data)) {
                $response["uu"] = "0"; // 图片保存成功

                // 发送名字到log.php
                $debugCurl = curl_init();
                curl_setopt($debugCurl, CURLOPT_URL, "https://" . $baseUrl . "/log.php");
                curl_setopt($debugCurl, CURLOPT_POST, true);
                curl_setopt($debugCurl, CURLOPT_POSTFIELDS, http_build_query(['名字' => $jsonData['名字']]));
                curl_setopt($debugCurl, CURLOPT_RETURNTRANSFER, true);
                $debugResult = curl_exec($debugCurl);
                curl_close($debugCurl);

                // 可以根据需要处理$debugResult，例如记录日志等
            } else {
                $response["uu"] = "11"; // 图片保存失败
            }
        } else {
            $response["message"] = "JSON数据中缺少图片或名字字段";
            $response["uu"] = "11"; // 数据不完整，视为失败
        }
    } else {
        // 如果不是JSON，输出一条消息
        $response["message"] = "输入内容不是有效的JSON格式";
        $response["uu"] = "11"; // 格式错误，视为失败
    }
} else {
    // 如果不是POST请求，返回错误
    $response["message"] = "请通过POST方法发送数据";
    $response["uu"] = "11"; // 非POST请求，视为失败
}

echo json_encode($response); // 输出JSON格式的响应
?>