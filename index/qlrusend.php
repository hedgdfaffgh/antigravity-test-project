<?php
// 接收来自sendCallbackInfo的数据
$merchantOrderId = isset($_POST['merchantOrderId']) ? $_POST['merchantOrderId'] : '';
if (empty($merchantOrderId)) {
    die("商户订单号不能为空");
}

// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME'); // 直接从环境变量获取
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 添加短暂延迟，确保数据库有足够时间进行更新
usleep(500000); // 延迟0.5秒

// 查找对应的商户编码，订单金额，商户订单号，实际金额，回调时间，回调地址值
$sql = "SELECT 商户编码, 订单金额, 商户订单号, 实际金额, 回调时间, 回调地址 FROM `订单视图` WHERE 商户订单号 = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $merchantOrderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $merchantCode = $row['商户编码'];
    $orderAmount = $row['订单金额'] * 100;
    $merchantOrderNo = $row['商户订单号'];
    $actualAmount = $row['实际金额'] * 100;
    $callbackUrl = $row['回调地址'];
} else {
    die("未找到对应的订单");
}

$stmt->close();

// 查找对应的商户密钥
$sql = "SELECT 密钥, 金额 FROM `商户密钥` WHERE 商户编码 = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $merchantCode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $merchantKey = $row['密钥'];
    $currentAmount = $row['金额'];
} else {
    die("未找到对应的商户密钥");
}

$stmt->close();
$conn->close();

// 获取当前北京时间并转换为10位数UNIX时间戳
date_default_timezone_set('Asia/Shanghai');
$callbackTime = time();

// 准备转发的数据
$data = array(
    'merchantId' => $merchantCode, // 修改字段名
    'amount' => $orderAmount, // 修改字段名
    'outTradeNo' => $merchantOrderNo, // 修改字段名
    'realAmount' => $actualAmount, // 修改字段名
    'notifyTime' => $callbackTime, // 修改字段名
    'status' => '1', // 修改为固定值1
);

$dataString = "merchantId={$data['merchantId']}&amount={$data['amount']}&outTradeNo={$data['outTradeNo']}&realAmount={$data['realAmount']}&notifyTime={$data['notifyTime']}&status={$data['status']}&merchantKey={$merchantKey}";
// 计算 MD5 哈希并转换为小写
$sign = strtolower(md5($dataString));

// 添加签名到数据数组
$data['sign'] = $sign;

// 目标URL
$url = $callbackUrl; // 使用回调地址

// 使用cURL发送数据
$options = array(
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data), // 数据以JSON编码的形式发送
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    CURLOPT_RETURNTRANSFER => true,
);

$attempts = 0;
$maxAttempts = 5;
$success = false;

while ($attempts < $maxAttempts && !$success) {
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 根据响应处理结果
    if ($statusCode == 200 && strtolower(trim($response)) == 'ok') {
        echo "请求成功，返回值为OK";
        $success = true;

        // 更新商户金额
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("连接失败: " . $conn->connect_error);
        }

        $newAmount = $currentAmount + ($actualAmount / 100.0); // 将实际金额转换回元，使用浮点数
        $updateSql = "UPDATE `商户密钥` SET 金额 = ? WHERE 商户编码 = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ds", $newAmount, $merchantCode);
        if ($updateStmt->execute()) {
            echo "商户金额更新成功";
        } else {
            echo "商户金额更新失败";
        }

        $updateStmt->close();
        $conn->close();
    } else {
        $attempts++;
        if ($attempts < $maxAttempts) {
            sleep(1); // 等待1秒后重试
        }
    }
}

if (!$success) {
    echo "请求失败或返回值不是OK";
}
?>