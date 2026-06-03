<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 打印解码后的数据以进行调试
    error_log("Received data: " . print_r($data, true));

    // 从$data中提取参数，设置默认值而不是null
    $file = isset($data["文件"]) && !empty($data["文件"]) ? trim($data["文件"]) : NULL; // 允许文件为NULL

    // 数据库配置
    $servername = getenv('DB_HOST'); // 确保环境变量正确配置
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT'); // 如果需要端口，确保使用它

    // 初始化响应数组
    $response = array();

    try {
        // 创建数据库连接
        $conn = new mysqli($servername, $username, $password, $dbname);

        // 检查连接
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }

        // 从卡号与卡密表中查找状态为0的行
        $sql_check = "SELECT 卡号, 卡密, 金额 FROM `卡号与卡密` WHERE 文件 = ? AND 状态 = 0 LIMIT 1";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check === false) {
            throw new Exception("SQL准备失败: " . $conn->error);
        }

        $stmt_check->bind_param("s", $file);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 0) {
            $response['oo'] = '6';
            $stmt_check->close();
            return;
        }

        $card_row = $result_check->fetch_assoc();
        $stmt_check->close();

        // 从核销卡密表中查找符合条件的记录
        $sql_verify = "SELECT 号码, 已充, 次数 FROM `核销卡密表` WHERE 名称 = ? ORDER BY id ASC";
        $stmt_verify = $conn->prepare($sql_verify);
        if ($stmt_verify === false) {
            throw new Exception("SQL准备失败: " . $conn->error);
        }

        $stmt_verify->bind_param("s", $file);
        $stmt_verify->execute();
        $result_verify = $stmt_verify->get_result();

        $found_valid_record = false;
        while ($row = $result_verify->fetch_assoc()) {
            if ($row['已充'] < $row['次数']) {
                $verify_row = $row;
                $found_valid_record = true;
                break;
            }
        }

        $stmt_verify->close();

        if (!$found_valid_record) {
            $response['oo'] = '6';
            return;
        }

        // 设置返回值
        $response['oo'] = '5';
        $response['01'] = $card_row['卡号'];
        $response['02'] = $card_row['卡密'];
        $response['ss'] = $verify_row['号码'];
        $response['yy'] = $card_row['金额'];
        
    } catch (Exception $e) {
        error_log("Error occurred: " . $e->getMessage());
        $response['oo'] = '6';
        $response['error'] = $e->getMessage();
    } finally {
        // 关闭连接
        if (isset($conn)) {
            $conn->close();
        }
    }

    // 返回响应
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    // 如果不是POST请求，返回错误
    header('HTTP/1.1 405 Method Not Allowed');
    echo "只允许POST请求";
}
?>