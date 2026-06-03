<?php
// SSL证书配置
$ssl_options = [
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'cafile' => '/path/to/zerossl/chain.pem'  // ZeroSSL根证书路径
    ]
];

// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取名称
    $orderNumber = isset($data["名称"]) ? $data["名称"] : null;

    // 使用环境变量进行数据库连接配置
    $servername = getenv('DB_HOST');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    // 初始化响应数组
    $response = array();

    try {
        // 创建PDO连接
        $conn = new PDO(
            "mysql:host=$servername;port=$port;dbname=$dbname",
            $username, 
            $password
        );
        
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 查询`链接数据`表
        $stmt = $conn->prepare("SELECT * FROM `链接数据` WHERE `名称` = :orderNumber LIMIT 1");
        $stmt->bindParam(':orderNumber', $orderNumber);
        $stmt->execute();

        // 只要能查询到表就返回oo=1
        $response["oo"] = "1";

    } catch(PDOException $e) {
        // 捕获并处理异常
        $response["状态"] = "失败";
        $response["uu"] = "数据库错误: " . $e->getMessage();
    } finally {
        // 关闭数据库连接
        $conn = null;
    }

    // 设置响应类型为JSON
    header('Content-Type: application/json');
    // 发送响应
    echo json_encode($response);
}