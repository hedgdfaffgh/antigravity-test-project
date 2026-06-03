<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $phoneNumber = isset($data["商户订单号"]) ? $data["商户订单号"] : null;
    $platformOrderNumber = isset($data["平台订单号"]) ? $data["平台订单号"] : null;
    $name = isset($data["名称"]) ? $data["名称"] : null; // 新增
    $number = isset($data["号码"]) ? $data["号码"] : null; // 新增
    $actualAmount = isset($data["实际金额"]) ? $data["实际金额"] : null; // 新增
    if ($platformOrderNumber !== null) {
        $platformOrderNumber = str_replace(["\r", "\n"], '', $platformOrderNumber);
    }
    
    $servername = "localhost";
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 生成服务器当前时间
    $sentTime = date('Y-m-d H:i:s');

    // 查找对应的结束时间
    $sql = "SELECT `结束时间` FROM `号码` WHERE `名称` = ? AND `号码` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $number);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $endTime = $row ? $row['结束时间'] : null;

    // 查找订单视图中的订单金额
    $sql = "SELECT `订单金额` FROM `订单视图` WHERE `商户订单号` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $orderAmount = $row ? $row['订单金额'] : null;

    if ($endTime !== null && $sentTime <= $endTime && abs($actualAmount - $orderAmount) <= 2) {
        // 更新平台订单号并设置回调列为1，插入回调时间，同时插入实际金额
        $sql = "UPDATE `订单视图` SET `平台订单号` = ?, `回调` = 1, `回调时间` = ?, `实际金额` = ? WHERE `商户订单号` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $platformOrderNumber, $sentTime, $actualAmount, $phoneNumber);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response["uu"] = "15"; // 更新成功

            // 发送请求到 qlruuddy.php，把 $actualAmount 与 $phoneNumber 发送
            $callbackData = json_encode(["号码" => $number, "名称" => $name, "实际金额" => $actualAmount, "商户订单号" => $phoneNumber]);
            $ch = curl_init('https://' . getenv('BASE_URL') . '/qlruuddy.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $callbackData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $result = curl_exec($ch);
            curl_close($ch);
        } else {
            $response["uu"] = "16"; // 未找到匹配的订单或更新失败
        }
    } else {
        // 插入到未回调视图
        $sql = "INSERT INTO `未回调视图` (`号码`, `账号`, `回调时间`, `订单号`, `实际金额`) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $number, $name, $sentTime, $phoneNumber, $actualAmount);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response["uu"] = "18"; // 插入未回调视图成功
        } else {
            $response["uu"] = "19"; // 插入未回调视图失败
        }

        // 更新订单视图中的平台订单号
        $sql = "UPDATE `订单视图` SET `平台订单号` = ?, `实际金额` = ? WHERE `商户订单号` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $platformOrderNumber, $actualAmount, $phoneNumber);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();

    header('Content-Type: application/json');
    echo json_encode($response);
}
?>