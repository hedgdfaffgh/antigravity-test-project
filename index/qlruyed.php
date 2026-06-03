<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取号码
    $number = isset($data["号码"]) ? $data["号码"] : null;

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

        // 查询链接网址表，查找查询为0的记录
        $stmt = $conn->prepare("
            SELECT * FROM `链接网址`
            WHERE `账号` = :number AND `查询` = 0
            LIMIT 1
        ");
        $stmt->bindParam(':number', $number);
        $stmt->execute();
        
        // 获取匹配的记录
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // 如果没有查询为0的记录，检查是否所有记录查询都为1
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM `链接网址`
                WHERE `账号` = :number AND `查询` = 1
            ");
            $stmt->bindParam(':number', $number);
            $stmt->execute();
            $countRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($countRow['count'] > 0) {
                // 如果所有记录查询都为1，设置oo为2，并将所有查询重置为0
                $response["oo"] = "2";
                $updateStmt = $conn->prepare("
                    UPDATE `链接网址`
                    SET `查询` = 0
                    WHERE `账号` = :number
                ");
                $updateStmt->bindParam(':number', $number);
                $updateStmt->execute();
            } else {
                $response["状态"] = "失败";
                $response["uu"] = "无匹配数据";
            }
        } else {
            // 提取平台订单号
            $platformOrder = $row["平台订单号"];
            preg_match('/\d+$/', $platformOrder, $matches);
            $orderNumber = $matches[0];

            // 设置响应
            $response["oo"] = "1";
            $response["kk"] = "https://m.jiaoyimao.com/order/detail/" . $orderNumber;

            // 更新查询为1
            $updateStmt = $conn->prepare("
                UPDATE `链接网址`
                SET `查询` = 1
                WHERE `id` = :id
            ");
            $updateStmt->bindParam(':id', $row['id']);
            $updateStmt->execute();
        }
    } catch(PDOException $e) {
        // 捕获并处理异常
        $response["状态"] = "失败";
    } finally {
        // 关闭数据库连接
        $conn = null;
    }

    // 设置响应类型为JSON
    header('Content-Type: application/json');
    // 发送响应
    echo json_encode($response);
} 