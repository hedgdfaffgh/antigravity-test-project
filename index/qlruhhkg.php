<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 打印解码后的数据以进行调试
    error_log("Received data: " . print_r($data, true));

    // 从$data中提取参数，设置默认值而不是null
    $name = isset($data["名称"]) && !empty($data["名称"]) ? trim($data["名称"]) : $data["账号"];
    $cardNumber = isset($data["卡号"]) && !empty($data["卡号"]) ? trim($data["卡号"]) : '';
    $cardPassword = isset($data["卡密"]) && !empty($data["卡密"]) ? trim($data["卡密"]) : '';
    $amount = isset($data["实际金额"]) && !empty($data["实际金额"]) ? floatval($data["实际金额"]) : 0;
    $status = 0; // 设置状态的默认值为0
    $extractTime = date('Y-m-d H:i:s'); // 设置提取时间为当前时间
    $account = isset($data["账号"]) && !empty($data["账号"]) ? trim($data["账号"]) : '';
    $file = isset($data["文件"]) && !empty($data["文件"]) ? trim($data["文件"]) : NULL; // 允许文件为NULL

    // 数据库配置
    $servername = getenv('DB_HOST');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    // 初始化响应数组
    $response = array();

    try {
        // 创建数据库连接
        $conn = new mysqli($servername, $username, $password, $dbname);

        // 检查连接
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }

        // 插入数据的SQL语句
        $sql = "INSERT INTO `卡号与卡密` (`名称`, `卡号`, `卡密`, `状态`, `金额`, `提取时间`, `账号`, `文件`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        // 准备和绑定
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("SQL准备失败: " . $conn->error);
        }

        // 绑定参数
        $stmt->bind_param("sssissss", $name, $cardNumber, $cardPassword, $status, $amount, $extractTime, $account, $file);

        // 执行语句
        if ($stmt->execute()) {
            $response['oo'] = '5'; // 数据插入成功
            error_log("数据插入成功");
        } else {
            throw new Exception("数据插入失败: " . $stmt->error);
        }

        // 关闭语句
        $stmt->close();
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        $response['oo'] = '6';
        $response['error'] = $e->getMessage();
    } finally {
        // 关闭连接
        if (isset($conn)) {
            $conn->close();
        }
    }

    // 返回响应
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    // 如果不是POST请求，返回错误
    header('HTTP/1.1 405 Method Not Allowed');
    echo "只允许POST请求";
}
?>