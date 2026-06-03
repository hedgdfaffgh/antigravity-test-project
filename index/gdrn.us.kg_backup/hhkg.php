<?php
// 检查是否收到GET请求
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 数据库配置
    $servername = "localhost";
    $username = getenv('DB_USERNAME'); // 直接从环境变量获取
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    // 初始化响应数组
    $response = array();

    try {
        // 创建PDO连接
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 修改SQL语句，首先查找`密码`列有值且`回调`列为NULL的行
        $stmt = $conn->prepare("SELECT `号码`, `密码`, `商户订单号` FROM `订单视图` WHERE `密码` IS NOT NULL AND `回调` IS NULL LIMIT 1");
        $stmt->execute();

        // 检查是否有匹配的记录
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // 更新回调状态为-1，基于密码列
            $updateStmt = $conn->prepare("UPDATE `订单视图` SET `回调` = -1 WHERE `密码` = :password AND `回调` IS NULL");
            $updateStmt->bindParam(':password', $row['密码']);
            $updateStmt->execute();

            // 设置响应
            $response["号码"] = $row['号码'];
            $response["密码"] = $row['密码'];
            $response["订单号"] = $row['商户订单号'];
            $response["uu"] = "处理成功，回调状态更新为-1";
        } else {
            // 如果没有找到匹配的记录
            $response["状态"] = "失败";
            $response["uu"] = "无匹配订单";
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