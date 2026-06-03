<?php
// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    die("环境变量BASE_URL未设置");
}

// 获取POST数据
$name = $_POST['名字'] ?? '未知';

// 查找对应的JPG文件
$jpgPath = '/www/wwwroot/' . $baseUrl . '/mqfh/' . $name . '.jpg';
if (file_exists($jpgPath)) {
    // 数据库配置
    $servername = "localhost";
    $username = getenv('DB_USERNAME'); // 从环境变量获取数据库用户名
    $password = getenv('DB_PASSWORD'); // 从环境变量获取数据库密码
    $dbname = "ovrn";

    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 检查连接
    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 准备和绑定
    $stmt = $conn->prepare("UPDATE `订单视图` SET `密码` = ? WHERE `商户订单号` = ?");
    if (!$stmt) {
        die("准备语句失败: " . $conn->error);
    }
    $stmt->bind_param("ss", $jpgPath, $name); // 'ss' 指定参数类型为字符串

    // 执行语句
    if ($stmt->execute()) {
        echo "记录更新成功";

        // 初始化cURL会话
        $ch = curl_init();
        // 设置cURL选项，请求zxing.org解码服务
        curl_setopt($ch, CURLOPT_URL, "https://zxing.org/w/decode?u=https://" . $baseUrl . "/mqfh/" . urlencode($name) . ".jpg");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 执行cURL会话
        $response = curl_exec($ch);
        if ($response === false) {
            echo "解码请求失败";
        } else {
            // 解析返回的HTML以找到解码后的URL
            preg_match('/<pre>(https:\/\/qr\.alipay\.com\/[^\s<]+)<\/pre>/', $response, $matches);
            if (!empty($matches[1])) {
                $decodedUrl = $matches[1];

                // 更新数据库中的密码列
                $updateStmt = $conn->prepare("UPDATE `订单视图` SET `密码` = ? WHERE `商户订单号` = ?");
                $updateStmt->bind_param("ss", $decodedUrl, $name);
                if ($updateStmt->execute()) {
                    echo "密码更新成功";
                } else {
                    echo "密码更新失败: " . $updateStmt->error;
                }
                $updateStmt->close();
            } else {
                echo "未找到解码URL";
            }
        }
        // 关闭cURL资源，并释放系统资源
        curl_close($ch);
    } else {
        echo "记录更新失败: " . $stmt->error;
    }

    // 关闭语句和连接
    $stmt->close();
    $conn->close();
} else {
    echo "未找到对应的JPG文件: " . $jpgPath;
}
?>