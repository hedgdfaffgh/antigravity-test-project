<?php
// 设置响应头为JSON
header('Content-Type: application/json');

// 数据库配置
$servername = "154.37.220.31"; // 外部连接的 IP 地址
$port = "3306";                // MySQL 默认端口
$username = "root";            // 数据库用户名
$password = "6a35decdf8d8a";   // 数据库密码
$dbname = "ovrn";              // 数据库名称

$response = array();

try {
    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    // 检查连接
    if ($conn->connect_error) {
        throw new Exception("连接失败: " . $conn->connect_error);
    }

    // 连接成功，返回001=1
    $response['001'] = '1';

} catch (Exception $e) {
    // 发生错误时的响应
    $response['001'] = '0';
    $response['error'] = $e->getMessage();
} finally {
    // 关闭数据库连接
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
}

// 返回响应
echo json_encode($response);
?> 