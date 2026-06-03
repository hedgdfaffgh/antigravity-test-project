<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取账号
    $account = isset($data["账号"]) ? $data["账号"] : null;

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

        // 查找对应的UID
        $stmt = $conn->prepare("
            SELECT * FROM `账号表` 
            WHERE `UID` = :account
            LIMIT 1
        ");
        $stmt->bindParam(':account', $account);
        $stmt->execute();
        
        // 获取匹配的记录
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $response["状态"] = "失败";
            $response["uu"] = "无匹配数据";
        } else {
            // 删除记录
            $deleteStmt = $conn->prepare("
                DELETE FROM `账号表` 
                WHERE `UID` = :account
            ");
            $deleteStmt->bindParam(':account', $account);
            $deleteStmt->execute();

            // 设置响应
            $response["oo"] = "3";
            $response["账号"] = $account;
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



