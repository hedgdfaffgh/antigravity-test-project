<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 打印解码后的数据以进行调试
    error_log(print_r($data, true));

    // 从$data中提取参数
    $name = isset($data["名称"]) ? $data["名称"] : null;
    $website = isset($data["网址"]) ? $data["网址"] : null;
    $platformOrderNumber = isset($data["平台订单号"]) ? $data["平台订单号"] : null;
    $orderAmount = isset($data["订单金额"]) ? $data["订单金额"] : null;
    $account = isset($data["账号"]) ? $data["账号"] : null;
    $merchantOrderNumber = isset($data["商户订单号"]) ? $data["商户订单号"] : null; // 新增商户订单号
    $realGold = isset($data["实金"]) ? $data["实金"] : null; // 新增实金

    // 生成服务器当前时间
    $sentTime = date('Y-m-d H:i:s');

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

        // 查询限删值
        $stmt = $conn->prepare("SELECT `限删`, `限时` FROM `链接数据` WHERE `名称` = :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $limitDelete = $result['限删'];
            $limitTime = $result['限时'];
            $endTime = date('Y-m-d H:i:s', strtotime($sentTime) + $limitDelete);
            $orderTime = date('Y-m-d H:i:s', strtotime($sentTime) + $limitTime);
        } else {
            $endTime = $sentTime;
            $orderTime = $sentTime;
        }

        // 插入数据到`链接网址`表
        $stmt = $conn->prepare("INSERT INTO `链接网址` (`名称`, `网址`, `平台订单号`, `订单金额`, `账号`, `结束时间`, `订单时间`, `状态`) VALUES (:name, :website, :platformOrderNumber, :orderAmount, :account, :endTime, :orderTime, 1)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':website', $website);
        $stmt->bindParam(':platformOrderNumber', $platformOrderNumber);
        $stmt->bindParam(':orderAmount', $orderAmount);
        $stmt->bindParam(':account', $account);
        $stmt->bindParam(':endTime', $endTime);
        $stmt->bindParam(':orderTime', $orderTime);

        // 打印绑定参数以进行调试
        error_log("绑定参数: 名称=$name, 网址=$website, 平台订单号=$platformOrderNumber, 订单金额=$orderAmount, 账号=$account, 结束时间=$endTime, 订单时间=$orderTime");

        $stmt->execute();

        // 用商户订单号查找订单视图
        $stmt = $conn->prepare("SELECT * FROM `订单视图` WHERE `商户订单号` = :merchantOrderNumber");
        $stmt->bindParam(':merchantOrderNumber', $merchantOrderNumber);
        $stmt->execute();
        $orderResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($orderResult) {
            // 更新订单视图中的对应列
            $stmt = $conn->prepare("UPDATE `订单视图` SET `号码` = :account, `密码` = :website, `平台订单号` = :platformOrderNumber WHERE `商户订单号` = :merchantOrderNumber");
            $stmt->bindParam(':account', $account);
            $stmt->bindParam(':website', $website);
            $stmt->bindParam(':platformOrderNumber', $platformOrderNumber);
            $stmt->bindParam(':merchantOrderNumber', $merchantOrderNumber);
            $stmt->execute();

            // 更新链接数据表中的在存和现条列
            $stmt = $conn->prepare("
                UPDATE `链接数据` 
                SET 
                    `在存` = COALESCE(`在存`, 0) - 1, 
                    `现条` = COALESCE(`现条`, 0) + 1 
                WHERE 
                    `名称` = :name AND `金额` = :orderAmount
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':orderAmount', $orderAmount);
            $stmt->execute();

            // 更新成功
            $response["状态"] = "成功";
            $response["oo"] = "3";
        } else {
            // 更新失败
            $response["状态"] = "失败";
            $response["oo"] = "4: 更新订单视图失败";
        }
    } catch(PDOException $e) {
        // 捕获并处理异常
        $response["状态"] = "失败";
        $response["oo"] = "4: " . $e->getMessage();
        // 打印异常消息以进行调试
        error_log("数据库错误: " . $e->getMessage());
    } finally {
        // 关闭数据库连接
        $conn = null;
    }

    // 设置响应类型为JSON
    header('Content-Type: application/json');
    // 发送响应
    echo json_encode($response);
}
?>