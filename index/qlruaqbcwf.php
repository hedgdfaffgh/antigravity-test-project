<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取号码值和文件值
    $number = isset($data["账号"]) ? $data["账号"] : null;
    $file = isset($data["文件"]) ? $data["文件"] : null;
    // 生成服务器当前时间
    $sentTime = date('Y-m-d H:i:s');

    // 数据库配置
    $servername = "localhost";
    $username = getenv('DB_USERNAME'); // 直接从环境变量获取
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    $response = []; // 初始化响应数组

    try {
        // 创建PDO连接
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($number || $file) {
            // 准备SQL语句，查询密码和回调为NULL，且时间差在两分钟内的记录，按订单时间升序排序
            $stmt = $conn->prepare("SELECT * FROM `订单视图` WHERE `密码` IS NULL AND `回调` IS NULL AND (`号码` = :number OR `文件` = :file) AND ABS(TIMESTAMPDIFF(MINUTE, `订单时间`, :sentTime)) <= 2 ORDER BY `订单时间` ASC LIMIT 1");
            $stmt->bindParam(':number', $number);
            $stmt->bindParam(':file', $file);
            $stmt->bindParam(':sentTime', $sentTime);
            $stmt->execute();

            // 检查是否有匹配的记录
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                // 处理找到的记录
                $response = [
                    '状态' => '成功',
                    '数据' => $result,
                    'oo' => $result['订单金额'],
                    'pp' => $result['商户订单号'],
                    'ww' => $result['订单时间']
                ];
                // 更新回调列为-1
                $updateStmt = $conn->prepare("UPDATE `订单视图` SET `回调` = -1 WHERE `ID` = :orderId");
                $updateStmt->bindParam(':orderId', $result['ID']);
                $updateStmt->execute();
                if ($updateStmt->rowCount() > 0) {
                    $response["uu"] = "-1"; // 更新成功
                } else {
                    $response["uu"] = "10"; // 更新失败
                }
            } else {
                $response = ['状态' => '失败', '消息' => '无匹配订单'];
            }
        } else {
            $response = ['状态' => '失败', '消息' => '缺少数据'];
        }

    } catch(PDOException $e) {
        // 捕获并处理异常
        $response = ['状态' => '失败', '消息' => '数据库错误: ' . $e->getMessage()];
    } finally {
        // 关闭数据库连接
        $conn = null;
    }
} else {
    $response = ['状态' => '失败', '消息' => '非POST请求'];
}

echo json_encode($response); // 输出JSON格式的响应
?>