<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $phoneNumber = isset($data["号码"]) ? $data["号码"] : null;
    $platformOrderNumber = isset($data["平台订单号"]) ? $data["平台订单号"] : null;
    
    $servername = "localhost";
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 修改：先插入新数据
    $insertSql = "INSERT INTO `订单视图` (号码, 平台订单号) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("ss", $phoneNumber, $platformOrderNumber);
    $insertResult = $insertStmt->execute();

    if ($insertResult) {
        $response["uu"] = "13"; // 插入成功
    } else {
        $response["uu"] = "14"; // 插入失败
    }

    $insertStmt->close();

    // 原有的查询逻辑保持不变
    $sql = "SELECT 平台订单号 FROM `订单视图` WHERE 号码 = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $result = $stmt->get_result();

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
        $response["query_result"] = "成功"; // 查询成功，找到了完全匹配的记录或平台订单号为空
    } else {
        $response["query_result"] = "失败"; // 查询失败，找到了号码但平台订单号不匹配
    }

    $stmt->close();
    $conn->close();

    header('Content-Type: application/json');
    echo json_encode($response);
}