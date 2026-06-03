<?php
// 设置响应头为JSON
header('Content-Type: application/json');

// 初始化结果数组
$result = array('success' => false, 'message' => '');

// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    $result['message'] = "连接失败: " . $conn->connect_error;
    echo json_encode($result);
    exit;
}

// 获取POST请求中的参数
$name = isset($_POST['name']) ? $_POST['name'] : '';
$amount = isset($_POST['amount']) ? $_POST['amount'] : '0';
$merchantOrderId = isset($_POST['merchantOrderId']) ? $_POST['merchantOrderId'] : '';

// 生成服务器当前时间
$sentTime = date('Y-m-d H:i:s');

// 插入数据到订单视图表
$insert_sql = "INSERT INTO `订单视图` (`文件`, `订单金额`, `商户订单号`, `订单时间`) VALUES (?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("ssss", $name, $amount, $merchantOrderId, $sentTime);
$insert_stmt->execute();
$insert_stmt->close();

// 根据 name 和 amount 查询网址表
$amount = $conn->real_escape_string($amount);
$getUrlSql = "SELECT 网址 FROM 网址 WHERE 名称='$name' AND 金额='$amount' LIMIT 1";
$urlResult = $conn->query($getUrlSql);
if ($urlResult && $urlResult->num_rows > 0) {
    $urlRow = $urlResult->fetch_assoc();
    $result['url'] = $urlRow['网址'];
    
    // 定义原始HTML文件路径
    $originalHtmlFilePath = __DIR__ . '/whnt/' . strtolower($name) . '.html';

    // 读取原始HTML文件内容
    $htmlContent = file_get_contents($originalHtmlFilePath);

    // 替换HTML内容中的占位符
    $htmlContent = str_replace('{{order_amount}}', $amount, $htmlContent);
    $htmlContent = str_replace('{{website_url}}', $result['url'], $htmlContent);
    $htmlContent = str_replace('{{merchantOrderId}}', $merchantOrderId, $htmlContent);
    // 定义新的HTML文件路径
    $newHtmlFilePath = __DIR__ . '/mqfh/' . strtolower($merchantOrderId) . '.html';

    // 保存修改后的HTML内容到新路径
    file_put_contents($newHtmlFilePath, $htmlContent);

    // 生成完整的URL
    $url = 'https://' . getenv('BASE_URL') . '/mqfh/' . rawurlencode(strtolower($merchantOrderId)) . '.html';

    // 将URL添加到结果数组中
    $result['redirectUrl'] = $url;
    $result['fullRedirectUrl'] = "https://" . getenv('BASE_URL') . "/qlru.html?website_url=" . urlencode($url);
    
    // 设置操作成功
    $result['success'] = true;

    // 新增：将 redirectUrl 发送到 qlruovrn.php
    $postData = http_build_query(array('redirectUrl' => $url));
    $ch = curl_init('https://' . getenv('BASE_URL') . '/qlruovrn.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // 将 qlruovrn.php 的响应添加到结果中
    $result['qlruovrnResponse'] = $response;
} else {
    $result['message'] = "未找到匹配的网址";
}

// 关闭数据库连接
$conn->close();

// 输出JSON结果
echo json_encode($result);
?>