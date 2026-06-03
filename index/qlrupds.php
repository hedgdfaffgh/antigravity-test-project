<?php
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

    // 初始化响应数组，只要收到orderNumber就返回成功
    $response = array("oo" => 1);

    try {
        // 创建PDO连接，添加端口配置
        $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 开始事务
        $conn->beginTransaction();
        
        try {
            // 首先查询链接网址表获取所有匹配记录的订单金额和名称
            $stmt0 = $conn->prepare("SELECT 订单金额, 名称 FROM `链接网址` WHERE `账号` = :orderNumber");
            $stmt0->bindParam(':orderNumber', $orderNumber);
            $stmt0->execute();
            $results = $stmt0->fetchAll(PDO::FETCH_ASSOC);

            // 更新链接数据表中的现条
            foreach ($results as $result) {
                $amount = $result['订单金额'];
                $name = $result['名称'];

                $stmt1 = $conn->prepare("UPDATE `链接数据` SET `现条` = `现条` - 1 WHERE `名称` = :name AND `金额` = :amount");
                $stmt1->bindParam(':name', $name);
                $stmt1->bindParam(':amount', $amount);
                $stmt1->execute();
            }

            // 删除链接网址表中所有匹配的记录
            $stmt2 = $conn->prepare("DELETE FROM `链接网址` WHERE `账号` = :orderNumber");
            $stmt2->bindParam(':orderNumber', $orderNumber);
            $stmt2->execute();

            // 提交事务
            $conn->commit();
        } catch (Exception $e) {
            // 如果出现错误，回滚事务
            $conn->rollBack();
            throw $e;
        }
    } catch (PDOException $e) {
        // 即使发生错误也返回成功，但记录错误信息
        $response["error"] = "数据库错误: " . $e->getMessage();
    }

    // 关闭连接
    $conn = null;

    // 返回JSON响应
    echo json_encode($response);
}
?> 