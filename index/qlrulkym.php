<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取并验证环境变量
    $baseUrl = getenv('BASE_URL');
    if (!$baseUrl) {
        echo json_encode(['status' => 'error', 'message' => '环境变量BASE_URL未设置']);
        exit;
    }

    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 打印解码后的数据以进行调试
    error_log(print_r($data, true));

    // 从$data中提取参数
    $platformOrderNumber = isset($data["平台订单号"]) ? $data["平台订单号"] : null;

    // 生成服务器当前时间
    $sentTime = date('Y-m-d H:i:s');

    // 数据库配置
    $servername = "localhost";
    $username = getenv('DB_USERNAME'); // 直接从环境变量获取
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    // 初始化响应数组
    $response = array();

    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 检查连接
    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 查找订单视图中的平台订单号列
    $sql = "SELECT * FROM `订单视图` WHERE `平台订单号` LIKE '%$platformOrderNumber%'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // 获取商户订单号
        $row = $result->fetch_assoc();
        $merchantOrderId = $row['商户订单号'];

        // 找到对应的行，修改回调列为1，并更新回调时间和实际金额
        $updateSql = "UPDATE `订单视图` 
                      SET `回调` = 1, 
                          `回调时间` = '$sentTime', 
                          `实际金额` = `订单金额`
                      WHERE `平台订单号` LIKE '%$platformOrderNumber%'";
        if ($conn->query($updateSql) === TRUE) {
            $response['pp'] = 1; // 成功

            // 只有在成功的情况下才发送商户订单号
            if ($response['pp'] === 1) {
                // 发送商户订单号到指定URL
                $postData = http_build_query(array('merchantOrderId' => $merchantOrderId));
                $options = array(
                    'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'content' => $postData,
                    ),
                );
                $context  = stream_context_create($options);
                $result = file_get_contents('https://' . $baseUrl . '/qlrusend.php', false, $context);
            }

            // 根据$data["平台订单号"]值查找SELECT * FROM `链接网址`表里面对应的平台订单号行
            $linkSql = "SELECT `订单金额` FROM `链接网址` WHERE `平台订单号` LIKE '%$platformOrderNumber%'";
            $linkResult = $conn->query($linkSql);

            if ($linkResult->num_rows > 0) {
                $linkRow = $linkResult->fetch_assoc();
                $orderAmount = $linkRow['订单金额'];

                // 用订单金额值查找 SELECT * FROM `链接数据`对应金额列
                $dataSql = "SELECT * FROM `链接数据` WHERE `金额` = $orderAmount";
                $dataResult = $conn->query($dataSql);

                if ($dataResult->num_rows > 0) {
                    // 找到对应行，更新在读-1
                    $updateDataSql = "UPDATE `链接数据` SET `在读` = `在读` - 1 WHERE `金额` = $orderAmount";
                    $conn->query($updateDataSql);
                }

                // 删除链接网址表中对应的平台订单号行
                $deleteLinkSql = "DELETE FROM `链接网址` WHERE `平台订单号` LIKE '%$platformOrderNumber%'";
                $conn->query($deleteLinkSql);
            }
        } else {
            $response['pp'] = 2; // 失败
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = '未找到匹配的订单';
    }

    // 关闭数据库连接
    $conn->close();

    // 返回响应
    echo json_encode($response);
}
?>