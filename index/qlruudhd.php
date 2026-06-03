<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取名称和状态
    $orderNumber = isset($data["名称"]) ? $data["名称"] : null;
    $status = isset($data["状态"]) ? $data["状态"] : null;

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

        if ($status == "3") {
            // 如果状态为3，查找状态为2的记录
            $stmt = $conn->prepare("
                SELECT * FROM `账号表` 
                WHERE `名称` = :orderNumber 
                AND `状态` = 2
                LIMIT 1
            ");
        } elseif ($status == "2") {
            // 如果状态为2，查找状态为1的记录
            $stmt = $conn->prepare("
                SELECT * FROM `账号表` 
                WHERE `名称` = :orderNumber 
                AND `状态` = 1
                LIMIT 1
            ");
        } else {
            // 默认查找状态为NULL的记录
            $stmt = $conn->prepare("
                SELECT * FROM `账号表` 
                WHERE `名称` = :orderNumber 
                AND `状态` IS NULL
                LIMIT 1
            ");
        }
        
        $stmt->bindParam(':orderNumber', $orderNumber);
        $stmt->execute();
        
        // 获取匹配的记录
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $response["状态"] = "失败";
            $response["uu"] = "无匹配数据";
        } else {
            // 更新状态
            $newStatus = ($status == "3") ? 3 : (($status == "2") ? 2 : 1);
            $updateStmt = $conn->prepare("
                UPDATE `账号表` 
                SET `状态` = :newStatus 
                WHERE `custom_id` = :custom_id
            ");
            $updateStmt->bindParam(':newStatus', $newStatus);
            $updateStmt->bindParam(':custom_id', $row['custom_id']);
            $updateStmt->execute();

            // 设置响应
            $response["oo"] = "2";
            $response["mm"] = $row["号码"];
            $response["yy"] = $row["密码"];
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