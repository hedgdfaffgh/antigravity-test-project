<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $number = isset($data["号码"]) ? $data["号码"] : null;
    $name = isset($data["名称"]) ? $data["名称"] : null;
    $actualAmount = isset($data["实际金额"]) ? $data["实际金额"] : null; // 新增
    $phoneNumber = isset($data["商户订单号"]) ? $data["商户订单号"] : null; // 新增

    if ($number !== null && $name !== null && $actualAmount !== null && $phoneNumber !== null) {
        $servername = "localhost";
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');
        $dbname = "ovrn";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("连接失败: " . $conn->connect_error);
        }

        // 更新号码表中的状态和结束时间
        $sql = "UPDATE `号码` SET `状态` = NULL, `结束时间` = NULL WHERE `号码` = ? AND `名称` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $number, $name);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // 更新成功，接下来更新金额列
            $sql = "SELECT `金额` FROM `号码` WHERE `号码` = ? AND `名称` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $number, $name);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $currentAmount = $row ? $row['金额'] : 0;

            $newAmount = $currentAmount + $actualAmount;

            $sql = "UPDATE `号码` SET `金额` = ? WHERE `号码` = ? AND `名称` = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dss", $newAmount, $number, $name);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response["status"] = "success"; // 更新成功
            } else {
                $response["status"] = "fail"; // 更新金额失败
            }
        } else {
            $response["status"] = "fail"; // 更新状态和结束时间失败
        }

        $stmt->close();
        $conn->close();
    } else {
        $response["status"] = "error"; // 缺少必要参数
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}
?>