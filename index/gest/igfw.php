<?php
// 数据库连接信息
$servername = "localhost";
$username = getenv('DB_USERNAME'); // 直接从环境变量获取
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

try {
    // 创建数据库连接
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // 设置PDO错误模式为异常
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 从请求体中获取数据
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'];

    // 准备SQL语句
    $sql = "UPDATE usvf_sorted SET amount = 0 WHERE id = :id";

    // 预处理SQL语句
    $stmt = $conn->prepare($sql);
    // 绑定参数
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    // 执行SQL语句
    $stmt->execute();

    // 检查是否有行被影响
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => '金额已清零']);
    } else {
        echo json_encode(['success' => false, 'message' => '未找到对应的ID']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
}
?>