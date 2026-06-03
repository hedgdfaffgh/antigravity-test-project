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

    // 生成服务器当前时间
    $sentTime = date('Y-m-d H:i:s');
    $orderTime = $sentTime; // 保存初始时间用于订单时间

    // 数据库配置
    $servername = getenv('DB_HOST');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    // 初始化响应数组
    $response = array();

    try {
        // 创建PDO连接
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 查询限删值
        $stmt = $conn->prepare("SELECT `限删` FROM `链接数据` WHERE `名称` = :name AND `金额` = :orderAmount");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':orderAmount', $orderAmount);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $limitDelete = $result['限删'];
            $sentTime = date('Y-m-d H:i:s', strtotime($sentTime) + $limitDelete);
        }

        // 插入数据到`链接网址`表
        $stmt = $conn->prepare("INSERT INTO `链接网址` (`名称`, `网址`, `平台订单号`, `订单金额`, `账号`, `结束时间`, `订单时间`) VALUES (:name, :website, :platformOrderNumber, :orderAmount, :account, :sentTime, :orderTime)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':website', $website);
        $stmt->bindParam(':platformOrderNumber', $platformOrderNumber);
        $stmt->bindParam(':orderAmount', $orderAmount);
        $stmt->bindParam(':account', $account);
        $stmt->bindParam(':sentTime', $sentTime);
        $stmt->bindParam(':orderTime', $orderTime); // 使用初始时间

        // 打印绑定参数以进行调试
        error_log("绑定参数: 名称=$name, 网址=$website, 平台订单号=$platformOrderNumber, 订单金额=$orderAmount, 账号=$account, 结束时间=$sentTime, 订单时间=$orderTime");

        $stmt->execute();

        // 设置响应为成功
        $response["状态"] = "成功";
        $response["oo"] = "3";

        // 更新链接数据表中的在存和现条列
        $stmt = $conn->prepare("UPDATE `链接数据` SET `在存` = `在存` - 1, `现条` = `现条` + 1 WHERE `名称` = :name AND `金额` = :orderAmount");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':orderAmount', $orderAmount);
        $stmt->execute();
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