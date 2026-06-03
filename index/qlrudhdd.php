<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $phoneNumber = isset($data["号码"]) ? $data["号码"] : null;
    $platformOrderNumber = isset($data["平台订单号"]) ? $data["平台订单号"] : null;
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

    $sql = "SELECT 平台订单号 FROM `订单视图` WHERE 号码 = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    $response = array();
    $found = false;

    if ($platformOrderNumber === null || $platformOrderNumber === '') {
        $found = true; // 如果平台订单号为空，直接视为成功
    } else {
        while ($row = $result->fetch_assoc()) {
            if ($row['平台订单号'] == $platformOrderNumber) {
                $found = true;
                break;
            }
        }
    }

    if ($found) {
        $response["uu"] = "13"; // 对应成功，因为找到了完全匹配的记录或平台订单号为空
    } else {
        $response["uu"] = "14"; // 对应失败，因为找到了号码但平台订单号不匹配
    }

    $stmt->close();
    $conn->close();

    header('Content-Type: application/json');
    echo json_encode($response);
}