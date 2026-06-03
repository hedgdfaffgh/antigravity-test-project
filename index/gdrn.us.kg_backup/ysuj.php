<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取商户订单号
    $merchantOrderNumber = isset($data["商户订单号"]) ? $data["商户订单号"] : null;

    // 从$data中提取平台订单号
    $platformOrderNumber = isset($data["平台订单号"]) ? $data["平台订单号"] : null;

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
    $stmt = $conn->prepare("UPDATE `订单视图` SET `平台订单号` = ? WHERE `商户订单号` = ?");
    $stmt->bind_param("ss", $platformOrderNumber, $merchantOrderNumber);

    // 执行更新
    $stmt->execute();

    // 检查是否成功更新
    if ($stmt->affected_rows > 0) {
        $response["uu"] = "2"; // 更新成功
    } else {
        $response["uu"] = "12"; // 更新失败
    }

    // 关闭语句和连接
    $stmt->close();
    $conn->close();

    // 返回响应
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>