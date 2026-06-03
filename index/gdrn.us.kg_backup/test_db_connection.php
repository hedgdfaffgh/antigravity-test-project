<?php
// 数据库配置
$servername = getenv('DB_HOST'); // 确保环境变量正确配置
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT'); // 如果需要端口，确保使用它

// 初始化响应数组
$response = array();

try {
    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    // 检查连接
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }

    // 如果连接成功，返回001=1
    $response['001'] = 1;

} catch (Exception $e) {
    // 连接失败时返回错误信息
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
?>

<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 20px;
}
div {
    margin: 10px 0;
    padding: 10px;
    border-radius: 5px;
}
</style> 