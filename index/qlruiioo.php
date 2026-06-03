<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取名称和号码
    $orderNumber = isset($data["名称"]) ? $data["名称"] : null;
    $kgdc = isset($data["号码"]) ? $data["号码"] : null;

    // 使用环境变量进行数据库连接配置
    $servername = getenv('DB_HOST');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    // 初始化响应数组
    $response = array();

    try {
        // 创建PDO连接，添加端口配置
        $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 查询`链接数据`表
        $stmt = $conn->prepare("SELECT * FROM `链接数据` WHERE `名称` = :orderNumber");
        $stmt->bindParam(':orderNumber', $orderNumber);
        $stmt->execute();

        // 获取所有匹配的记录
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $response["oo"] = 2;
        } else {
            // 复制并新建一条记录
            foreach ($rows as $row) {
                $newCustomId = uniqid('id-');
                $insertStmt = $conn->prepare("
                    INSERT INTO `链接数据` (`名称`, `条数`, `限时`, `金额`, `在读`, `限删`, `现条`, `在存`, `复名`, `状态`, `custom_id`)
                    SELECT :kgdc, `条数`, `限时`, `金额`, `在读`, `限删`, `现条`, `在存`, :orderNumber, `状态`, :customId
                    FROM `链接数据` WHERE `custom_id` = :originalCustomId
                ");
                $insertStmt->bindParam(':kgdc', $kgdc);
                $insertStmt->bindParam(':orderNumber', $orderNumber);
                $insertStmt->bindParam(':customId', $newCustomId);
                $insertStmt->bindParam(':originalCustomId', $row['custom_id']);
                $insertStmt->execute();

                $response["oo"] = 1;
            }
        }
    } catch(PDOException $e) {
        $response["oo"] = 2;
    } finally {
        $conn = null;
    }

    // 设置响应类型为JSON
    header('Content-Type: application/json');
    echo json_encode($response);
} 