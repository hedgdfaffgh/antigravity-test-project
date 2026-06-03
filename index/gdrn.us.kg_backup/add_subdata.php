<?php
// 连接数据库
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

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);

// 确保父ID存在
$parent_id = isset($data['parent_id']) ? $conn->real_escape_string($data['parent_id']) : '';

// 其他子数据字段
$name = isset($data['name']) ? $conn->real_escape_string($data['name']) : '';
$amount = isset($data['amount']) ? $conn->real_escape_string($data['amount']) : '';
$region = isset($data['region']) ? $conn->real_escape_string($data['region']) : '';
$number = isset($data['number']) ? $conn->real_escape_string($data['number']) : '';

// 插入数据到数据库
$sql = "INSERT INTO usvf (parent_id, name, amount, region, number) VALUES ('$parent_id', '$name', '$amount', '$region', '$number')";

if ($conn->query($sql) === TRUE) {
    $last_id = $conn->insert_id;
    echo json_encode(array('success' => true, 'message' => '数据已保存', 'id' => $last_id));
} else {
    echo json_encode(array('success' => false, 'message' => '保存失败: ' . $conn->error));
}

$conn->close();
?>