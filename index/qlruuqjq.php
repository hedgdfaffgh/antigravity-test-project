<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $account = isset($data["账号"]) ? $data["账号"] : null;
    $file = isset($data["文件"]) ? $data["文件"] : null;

    if ($account !== null && $file !== null) {
        $servername = "localhost";
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');
        $dbname = "ovrn";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("连接失败: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("SELECT `查询`, `id`, `平台订单号` FROM `链接网址` WHERE `账号` = ? AND `名称` = ? AND `状态` = '1'");
        $stmt->bind_param("ss", $account, $file);
        $stmt->execute();
        $result = $stmt->get_result();

        $updated = false;
        $allQueried = true; // 用于检查是否所有查询列都为1
        $response = [];

        while ($row = $result->fetch_assoc()) {
            if ($row['查询'] == '1') {
                continue; // 如果查询列为1，跳过当前记录
            }
            $allQueried = false; // 如果有查询列不为1，设置为false

            // 更新查询列为1，只更新第一条查询列不为1的记录
            $updateStmt = $conn->prepare("UPDATE `链接网址` SET `查询` = '1' WHERE `id` = ?");
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
            $updated = true;

            // 提取平台订单号中的数字部分
            preg_match('/-(\d+)$/', $row['平台订单号'], $matches);
            if (!empty($matches)) {
                $orderNumber = $matches[1];
                // 返回更新的记录的“平台订单号”
                $response["yy"] = "https://m.jiaoyimao.com/order/detail/" . $orderNumber;
            }
            break; // 只更新第一条符合条件的记录
        }

        if (!$updated && $allQueried) {
            // 如果没有更新任何记录且所有查询列都为1，将所有查询列设置为NULL
            $resetStmt = $conn->prepare("UPDATE `链接网址` SET `查询` = NULL WHERE `账号` = ? AND `名称` = ? AND `状态` = '1'");
            $resetStmt->bind_param("ss", $account, $file);
            $resetStmt->execute();
            $resetStmt->close();
            echo "所有查询列已重置为NULL。";
        } elseif (!$updated) {
            echo "没有找到需要更新的记录。";
        }

        $stmt->close();
        $conn->close();

        // 输出响应
        if (isset($response["yy"])) {
            echo json_encode($response);
        }
    } else {
        echo "账号或文件信息缺失。";
    }
}