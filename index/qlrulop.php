<?php
// 数据库配置
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 获取商户订单号
$merchantOrderId = $_POST['merchantOrderId'];

// 查询数据库
$sql = "SELECT `回调` FROM `订单视图` WHERE `商户订单号` = '$merchantOrderId'";
$result = $conn->query($sql);

$response = array('status' => 0);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['回调'] == 1) {
        $response['status'] = 1;
    }
}

// 返回 JSON 响应
header('Content-Type: application/json');
echo json_encode($response);

// 关闭连接
$conn->close();
?> 



