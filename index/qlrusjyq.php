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

    // 初始化响应数组
    $response = array();

    try {
        // 创建PDO连接，添加端口配置
        $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 查询`链接数据`表
        $stmt = $conn->prepare("SELECT * FROM `链接数据` WHERE `名称` = :orderNumber ORDER BY `custom_id`");
        $stmt->bindParam(':orderNumber', $orderNumber);
        $stmt->execute();

        // 获取所有匹配的记录
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $response["状态"] = "失败";
            $response["uu"] = "无匹配数据";
        } else {
            foreach ($rows as $row) {
                // 将NULL值替换为0并更新数据库
                foreach ($row as $key => $value) {
                    if (is_null($value)) {
                        $row[$key] = 0;
                        $updateNullStmt = $conn->prepare("UPDATE `链接数据` SET `$key` = 0 WHERE `custom_id` = :custom_id LIMIT 1");
                        $updateNullStmt->bindParam(':custom_id', $row['custom_id']);
                        $updateNullStmt->execute();
                    }
                }

                // 检查现条与在存与在读的和是否大于等于条数
                if (($row['现条'] + $row['在存'] + $row['在读']) >= $row['条数']) {
                    // 设置响应
                    $response["oo"] = "2";
                    $response["uu"] = $row["金额"]; // 返回金额列的值
                    continue; // 继续检查下一行
                }

                // 检查现条与在存与在读的和是否小于条数
                if (($row['现条'] + $row['在存'] + $row['在读']) < $row['条数']) {
                    // 更新在存列的值
                    $updateStmt = $conn->prepare("UPDATE `链接数据` SET `在存` = `在存` + 1 WHERE `custom_id` = :custom_id LIMIT 1");
                    $updateStmt->bindParam(':custom_id', $row['custom_id']);
                    $updateStmt->execute();

                    // 设置响应为拉单成功
                    $response["oo"] = "-1";
                    $response["kk"] = "6";
                    // 检查在存列的值是否增加了1
                    $row['在存'] += 1;
                    if ($row['在存'] == $row['条数']) {
                        $response["uu"] = $row["金额"]; // 返回金额列的值
                        break;
                    }
                }

                // 如果在存值等于条数值，继续检查下一行
                if ($row['在存'] == $row['条数']) {
                    continue;
                } else {
                    // 设置响应
                    $response["uu"] = $row["金额"]; // 返回金额列的值
                    break;
                }
            }
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