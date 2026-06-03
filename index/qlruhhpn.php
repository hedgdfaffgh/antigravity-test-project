<?php
// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查数据库连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 从POST请求中获取merchantOrderId、cardNumber和cardKey
$merchantOrderId = $_POST['merchantOrderId'];
$cardNumber = $_POST['cardNumber'];
$cardKey = $_POST['cardKey'];

// 获取密钥并生成签名
$secretKey = getenv('SECRET_KEY');
$dataToSign = $merchantOrderId . $secretKey;
$signature = hash('sha256', $dataToSign);

// 准备SQL语句，更新号码列和密码列
$sql = "UPDATE 订单 SET `号码`=?, `密码`=?, `签名`=? WHERE `商户订单号`=?";

// 创建预处理语句
$stmt = $conn->prepare($sql);

// 检查预处理语句是否成功创建
if ($stmt === false) {
    die("预处理语句创建失败: " . $conn->error);
}

// 绑定参数
$stmt->bind_param("ssss", $cardNumber, $cardKey, $signature, $merchantOrderId);

// 执行预处理语句
if ($stmt->execute()) {
    echo "记录更新成功";
} else {
    echo "Error: " . $stmt->error;
}

// 关闭预处理语句和数据库连接
$stmt->close();
$conn->close();
?>