<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取名称和订单金额
    $orderNumber = isset($data["名称"]) ? $data["名称"] : null;
    $orderAmount = isset($data["订单金额"]) ? floatval($data["订单金额"]) : 0;

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
        $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 首先查询顺序为NULL的记录
        $stmt = $conn->prepare("
            SELECT * FROM `金限` 
            WHERE `名称` = :orderNumber 
            AND `实金` = :orderAmount 
            AND `顺序` IS NULL
            LIMIT 1
        ");
        $stmt->bindParam(':orderNumber', $orderNumber);
        $stmt->bindParam(':orderAmount', $orderAmount);
        $stmt->execute();
        
        // 获取匹配的记录
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 如果没有找到顺序为NULL的记录，则按顺序值排序查询
        if (!$row) {
            $stmt = $conn->prepare("
                SELECT * FROM `金限` 
                WHERE `名称` = :orderNumber 
                AND `实金` = :orderAmount 
                ORDER BY `顺序` ASC
                LIMIT 1
            ");
            $stmt->bindParam(':orderNumber', $orderNumber);
            $stmt->bindParam(':orderAmount', $orderAmount);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$row) {
            $response["状态"] = "失败";
            $response["uu"] = "无匹配数据";
        } else {
            // 更新顺序值（+1）
            $updateStmt = $conn->prepare("
                UPDATE `金限` 
                SET `顺序` = COALESCE(`顺序`, 0) + 1 
                WHERE `custom_id` = :custom_id
            ");
            $updateStmt->bindParam(':custom_id', $row['custom_id']);
            $updateStmt->execute();

            // 设置响应
            $response["oo"] = "1";
            $response["kk"] = $row["判名"];
            $response["uu"] = $row["金限"];
            $response["jj"] = $row["名字"];
        }
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