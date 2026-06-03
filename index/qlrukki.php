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
    $servername = getenv('DB_HOST');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    // 初始化响应数组
    $response = array();

    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    // 检查连接
    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 查找订单视图中的平台订单号列，按订单时间排序（选择最晚的记录）
    $sql = "SELECT * FROM `订单视图` WHERE `平台订单号` LIKE '%$platformOrderNumber%' ORDER BY `订单时间` DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // 找到对应的行，修改回调列为1，并更新回调时间
        $row = $result->fetch_assoc();
        $merchantOrderId = $row['商户订单号']; // 使用商户订单号作为唯一标识符
        
        // 检查是否已经回调过
        if ($row['回调'] == 1) {
            $response['pp'] = 2; // 已经回调过
        } else {
            $updateSql = "UPDATE `订单视图` 
                          SET `回调` = 1, 
                              `回调时间` = '$sentTime' 
                          WHERE `商户订单号` = '$merchantOrderId'";
            if ($conn->query($updateSql) === TRUE) {
                $response['pp'] = 1; // 成功回调
                
                // 用platformOrderNumber值查找链接网址表中对应平台订单号
                $linkSql = "SELECT `名称`, `订单金额` FROM `链接网址` WHERE `平台订单号` LIKE '%$platformOrderNumber%'";
                $linkResult = $conn->query($linkSql);

                if ($linkResult->num_rows > 0) {
                    $linkRow = $linkResult->fetch_assoc();
                    $name = $linkRow['名称'];
                    $orderAmount = $linkRow['订单金额'];

                    // 用名称和订单金额查找链接数据表中的对应行
                    $dataSql = "SELECT * FROM `链接数据` WHERE `名称` = '$name' AND `金额` = '$orderAmount'";
                    $dataResult = $conn->query($dataSql);

                    if ($dataResult->num_rows > 0) {
                        // 更新在读-1
                        $updateDataSql = "UPDATE `链接数据` SET `在读` = `在读` - 1 WHERE `名称` = '$name' AND `金额` = '$orderAmount'";
                        $conn->query($updateDataSql);
                    }

                    // 删除链接网址表中对应的平台订单号行
                    $deleteLinkSql = "DELETE FROM `链接网址` WHERE `平台订单号` LIKE '%$platformOrderNumber%'";
                    $conn->query($deleteLinkSql);
                }
            }
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = '未找到订单';
    }

    // 关闭数据库连接
    $conn->close();

    // 返回JSON响应
    echo json_encode($response);
} else {
    echo json_encode(['status' => 'error', 'message' => '无效的请求方法']);
}
?> 