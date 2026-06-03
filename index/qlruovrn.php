<?php
header("Access-Control-Allow-Origin: *");
// 设置响应头为JSON
header('Content-Type: application/json');

// 打印接收到的 POST 数据
file_put_contents('php://stderr', print_r($_POST, true));

// 初始化结果数组
$result = array('success' => false, 'message' => '', 'redirectUrl' => '', 'targetPhp' => '');

// 数据库配置
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    $result['message'] = "连接失败: " . $conn->connect_error;
    echo json_encode($result);
    exit;
}

// 获取POST请求中的参数并进行简单的验证
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : '';
$amount = isset($_POST['amount']) ? filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT) : 0;
$merchantOrderId = isset($_POST['merchantOrderId']) ? htmlspecialchars(trim($_POST['merchantOrderId']), ENT_QUOTES, 'UTF-8') : '';

// 生成服务器当前时间
$sentTime = date('Y-m-d H:i:s');

// 查询数据库
$sql = "SELECT * FROM `链接数据` WHERE 名称 = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $name);
$stmt->execute();
$queryResult = $stmt->get_result();

if ($queryResult->num_rows > 0) {
    $row = $queryResult->fetch_assoc();
    if ($row['条数'] === null || $row['条数'] == 0) {
        // 条数为 NULL 或 0,转发到 qlrukgdc.php
        $redirectUrl = 'https://' . getenv('BASE_URL') . '/qlrukgdc.php';
        $targetPhp = 'qlrukgdc.php';
    } else {
        // 条数大于 0,转发到 qlrutgdn.php
        $redirectUrl = 'https://' . getenv('BASE_URL') . '/qlrutgdn.php';
        $targetPhp = 'qlrutgdn.php';
    }
} else {
    // 没有找到对应的名称,转发到 qlruhhkghhpn.php
    $redirectUrl = 'https://' . getenv('BASE_URL') . '/qlruhhkghhpn.php';
    $targetPhp = 'qlruhhkghhpn.php';
}

// 构建要转发的数据
$postData = http_build_query(array(
    'name' => $name,
    'amount' => $amount,
    'merchantOrderId' => $merchantOrderId,
    'sentTime' => $sentTime
));

// 使用 cURL 转发请求
$ch = curl_init($redirectUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false, // 禁用 SSL 验证（仅用于测试）
    CURLOPT_SSL_VERIFYHOST => 0, // 禁用 SSL 验证（仅用于测试）
]);

$response = curl_exec($ch);

if ($response === false) {
    $result['message'] = 'cURL错误: ' . curl_error($ch);
} else {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode == 200) {
        $result['success'] = true;
        $result['message'] = '请求成功转发';
        $result['redirectUrl'] = $redirectUrl;
        $result['targetPhp'] = $targetPhp;
        $result['response'] = $response;
    } else {
        $result['message'] = "请求失败，HTTP状态码: $httpCode";
    }
}

curl_close($ch);

// 关闭数据库连接
$conn->close();

// 输出结果
echo json_encode($result);