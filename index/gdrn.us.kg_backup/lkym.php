<?php
// 设置默认时区为北京时间
date_default_timezone_set("Asia/Shanghai");

// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取订单号
    $orderNumber = isset($data["订单号"]) ? $data["订单号"] : null;

    // 从$data中提取实际金额
    $actualAmount = isset($data["实际金额"]) ? $data["实际金额"] : null;

    // 从$data中提取账号
    $account = isset($data["账号"]) ? $data["账号"] : null;

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

        if ($orderNumber && $actualAmount && $account) {
            // 修改SQL语句，从订单视图中选择符合条件的记录，包括号码与密码
            $stmt = $conn->prepare("SELECT `订单金额`, `号码`, `密码` FROM `订单视图` WHERE `商户订单号` = :orderNumber");
            $stmt->bindParam(':orderNumber', $orderNumber);
            $stmt->execute();

            // 检查是否有匹配的记录
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row['订单金额'] == $actualAmount) {
                    // 如果实际金额与订单视图中订单金额相同，修改回调列为1，并更新回调时间为当前北京时间，同时更新实际金额
                    $updateStmt = $conn->prepare("UPDATE `订单视图` SET `回调` = 1, `回调时间` = NOW(), `实际金额` = :actualAmount WHERE `商户订单号` = :orderNumber");
                    $updateStmt->bindParam(':orderNumber', $orderNumber);
                    $updateStmt->bindParam(':actualAmount', $actualAmount);
                    $updateStmt->execute();

                    // 使用账号查找usvf_sorted表中对应的region值并更新amount
                    $logMessage = "获取到的region: " . $account . "，实际金额: " . $actualAmount . "\n";
                    // 更新usvf_sorted表中amount字段，使其等于amount + actualAmount
                    $updateSql = "UPDATE usvf_sorted SET amount = amount + :actualAmount WHERE region = :region";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':actualAmount', $actualAmount);
                    $updateStmt->bindParam(':region', $account);
                    $updateStmt->execute();

                    // 查询更新后的amount和zong值
                    $checkSql = "SELECT amount, zong FROM usvf_sorted WHERE region = :region";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bindParam(':region', $account);
                    $checkStmt->execute();

                    if ($checkStmt->rowCount() > 0) {
                        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
                        // 确保zong列有值，并且amount大于等于zong
                        if ($row['zong'] !== null && $row['amount'] >= $row['zong']) {
                            // 如果满足条件，设置$response["uu"] = "暂停"
                            $response["状态"] = "成功";
                            $response["uu"] = "暂停";
                        } else {
                            // 如果不满足暂停条件，保持原有的成功消息
                            $response["状态"] = "成功";
                            $response["uu"] = "回调状态更新为1，回调时间已更新，实际金额与amount已更新";
                        }
                    }
                } else {
// 如果实际金额与订单视图中订单金额不相同，将信息插入到`未回调`表中，并将账号插入到账号列
$insertStmt = $conn->prepare("INSERT INTO `未回调` (`实际金额`, `订单号`, `号码`, `密码`, `回调时间`, `账号`) VALUES (:actualAmount, :orderNumber, :phone, :password, NOW(), :account)");
$insertStmt->bindParam(':actualAmount', $actualAmount);
$insertStmt->bindParam(':orderNumber', $orderNumber);
$insertStmt->bindParam(':phone', $row['号码']);
$insertStmt->bindParam(':password', $row['密码']);
$insertStmt->bindParam(':account', $account); // 将账号插入到账号列
$insertStmt->execute();

                    $response["状态"] = "失败";
                    $response["uu"] = "实际金额与订单金额不匹配，已添加未回调";
                }
            } else {
                // 如果没有找到匹配的订单号
                $response["状态"] = "失败";
                $response["uu"] = "未找到匹配的订单号";
            }
        } else {
            // 如果请求数据中缺少订单号、实际金额或账号
            $response["状态"] = "失败";
            $response["uu"] = "请求数据不完整";
        }
    } catch(PDOException $e) {
        // 捕获并处理PDO异常
        $response["状态"] = "异常";
        $response["uu"] = "数据库错误: " . $e->getMessage();
    }

    // 关闭数据库连接
    $conn = null;

    // 将响应数组编码为JSON格式并输出
    echo json_encode($response);
} else {
    // 如果不是POST请求
    echo "仅支持POST请求";
}
?>