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

                // 数据库连接和更新逻辑
                $servername = "localhost";
                $username = getenv('DB_USERNAME'); // 从环境变量获取数据库用户名
                $password = getenv('DB_PASSWORD'); // 从环境变量获取数据库密码
                $dbname = "ovrn";

                // 创建连接
                $conn = new mysqli($servername, $username, $password, $dbname);
                // 检查连接
                if ($conn->connect_error) {
                    $response["message"] = "数据库连接失败";
                    $response["uu"] = "11";
                } else {
                    $newPassword = 'https://' . $baseUrl . '/mqfh/' . $jsonData['名字'] . '.jpg';
                    $merchantOrderNumber = $jsonData['名字']; // 使用名字字段作为商户订单号

                    // 准备和绑定
                    $stmt = $conn->prepare("UPDATE `订单视图` SET `密码` = ? WHERE `商户订单号` = ?");
                    $stmt->bind_param("ss", $newPassword, $merchantOrderNumber);

                    // 执行
                    if ($stmt->execute()) {
                        $response["message"] = "密码更新成功";
                    } else {
                        $response["message"] = "密码更新失败";
                        $response["uu"] = "11";
                    }
                    $stmt->close();
                    $conn->close();
                }
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