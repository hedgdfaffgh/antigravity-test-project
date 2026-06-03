<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'db_error.log');

// 设置响应头为JSON
header('Content-Type: application/json');

// 数据库配置
$servername = "154.37.220.31"; // 数据库服务器地址
$port = "8089";                // 数据库端口
$username = "ovrn";            // 数据库用户名
$password = "6a35decdf8d8a";   // 数据库密码
$dbname = "ovrn";              // 数据库名称

try {
    // 创建数据库连接，禁用SSL
    $conn = mysqli_init();
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    mysqli_real_connect($conn, $servername, $username, $password, $dbname, $port);

    // 检查连接
    if ($conn->connect_error) {
        error_log("数据库连接错误: " . $conn->connect_error);
        throw new Exception("连接失败: " . $conn->connect_error);
    }

    // 设置字符集
    $conn->set_charset("utf8");

    // 连接成功，返回预定格式
    $response = array(
        '001' => '1'
    );
    
    echo json_encode($response);

} catch (Exception $e) {
    // 记录错误到日志
    error_log("数据库连接异常: " . $e->getMessage());
    
    // 发生错误时的响应
    $error_response = array(
        '001' => '0',
        'error' => $e->getMessage()
    );
    echo json_encode($error_response);
} finally {
    // 关闭数据库连接
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
}
?> 