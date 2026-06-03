<?php
// 设置数据库连接信息
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

// 创建与数据库的连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 获取 POST 数据
$data = json_decode(file_get_contents('php://input'), true);

// 验证必要的数据是否存在
if (!isset($data['orderNumber']) || !isset($data['timestamp'])) {
    $response = array('success' => false, 'message' => 'Missing required data');
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$orderNumber = $data['orderNumber'];
$timestamp = $data['timestamp'];

// 验证时间戳（例如，不接受超过5分钟的请求）
if (time() - ($timestamp / 1000) > 300) {
    $response = array('success' => false, 'message' => 'Request expired');
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 获取密钥并生成签名
$secretKey = getenv('SECRET_KEY');
$dataToSign = $orderNumber . $timestamp . $secretKey;
$signature = hash('sha256', $dataToSign);

// 更新订单视图表中的签名
$updateSql = "UPDATE 订单视图 SET 签名 = ? WHERE 商户订单号 = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("ss", $signature, $orderNumber);
$updateStmt->execute();

// 检查更新是否成功
if ($updateStmt->affected_rows > 0) {
    // 更新成功，继续查询密码
    $sql = "SELECT 密码 FROM 订单视图 WHERE 商户订单号 = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $orderNumber);

    // 执行 SQL 语句
    $stmt->execute();
    $result = $stmt->get_result();

    // 检查是否有结果
    if ($result->num_rows > 0) {
        // 输出数据
        $row = $result->fetch_assoc();
        $response = array('success' => true, 'paymentUrl' => $row['密码']);
    } else {
        $response = array('success' => false, 'message' => 'No record found');
    }
} else {
    $response = array('success' => false, 'message' => 'Failed to update signature');
}

// 关闭连接
$updateStmt->close();
$stmt->close();
$conn->close();

// 设置返回类型为 JSON
header('Content-Type: application/json');
echo json_encode($response);
?>