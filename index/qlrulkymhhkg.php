<?php
// 设置时区为中国标准时间
date_default_timezone_set('Asia/Shanghai');

// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 打印解码后的数据以进行调试
    error_log("Received data: " . print_r($data, true));

    // 从$data中提取参数，设置默认值而不是null
    $card_secret = isset($data["卡密"]) && !empty($data["卡密"]) ? trim($data["卡密"]) : NULL;

    // 数据库配置
    $servername = "localhost";
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    // 初始化响应数组
    $response = array();

    try {
        // 创建数据库连接
        $conn = new mysqli($servername, $username, $password, $dbname);

        // 检查连接
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }

        // 设置MySQL时区为北京时间
        $conn->query("SET time_zone = '+08:00'");

        // 开始事务
        $conn->begin_transaction();

        // 根据卡密值修改卡号与卡密表对应状态列为1，并更新提取时间和账号列为已提
        if ($card_secret !== NULL) {
            $sql_update_status = "UPDATE `卡号与卡密` SET 状态 = 1, 提取时间 = NOW(), 账号 = '已提' WHERE 卡密 = ?";
            $stmt_update_status = $conn->prepare($sql_update_status);
            if ($stmt_update_status === false) {
                throw new Exception("SQL准备失败: " . $conn->error);
            }
            $stmt_update_status->bind_param("s", $card_secret);
            $stmt_update_status->execute();
            if ($stmt_update_status->affected_rows === 0) {
                throw new Exception("未找到匹配的卡密记录");
            }
            $stmt_update_status->close();
        }

        // 提交事务
        $conn->commit();

        // 设置返回值
        $response['oo'] = '5';

    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();

        error_log($e->getMessage());
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