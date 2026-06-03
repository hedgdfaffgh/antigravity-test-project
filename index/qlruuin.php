<?php
// 设置响应头为JSON
header('Content-Type: application/json');

// 初始化结果数组
$result = array(
    'success' => false,
    'message' => '',
    'redirectUrl' => ''
);

// 数据库配置
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

try {
    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    
    // 检查连接是否成功
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }

    // 获取并过滤POST参数
    $name = isset($_POST['name']) ? $conn->real_escape_string(trim($_POST['name'])) : '';
    $amount = isset($_POST['amount']) ? (int)$conn->real_escape_string($_POST['amount']) : 0;
    $merchantOrderId = isset($_POST['merchantOrderId']) ? $conn->real_escape_string(trim($_POST['merchantOrderId'])) : '';

    // 参数验证
    if (empty($name) || $amount <= 0 || empty($merchantOrderId)) {
        throw new Exception("参数错误：名称、金额和商户订单号不能为空");
    }

    // 生成当前服务器时间
    $sentTime = date('Y-m-d H:i:s');

    // 1. 插入新订单到订单视图表
    $insert_sql = "INSERT INTO `订单视图` (`文件`, `订单金额`, `商户订单号`, `订单时间`) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("siss", $name, $amount, $merchantOrderId, $sentTime);
    $insert_stmt->execute();
    $insert_stmt->close();

    // 2. 检查是否存在符合条件的记录
    $check_sql = "SELECT * FROM `链接网址` WHERE `名称` = ? AND `订单金额` = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $name, $amount);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    // 如果没有找到记录，直接返回
    if ($check_result->num_rows == 0) {
        $result['message'] = "请稍等再重试";
        $result['redirectUrl'] = null;
        echo json_encode($result);
        exit;
    }
    $check_stmt->close();

    // 3. 查询已过期的记录（sentTime > 结束时间）
    $select_sql = "SELECT `名称`, `订单金额`, COUNT(*) as count 
                  FROM `链接网址` 
                  WHERE ? > `结束时间` 
                  AND `名称` = ? 
                  AND `订单金额` = ? 
                  GROUP BY `名称`, `订单金额`";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bind_param("ssi", $sentTime, $name, $amount);
    $select_stmt->execute();
    $select_result = $select_stmt->get_result();

    // 存储过期记录的数量统计
    $name_amount_counts = array();
    while ($row = $select_result->fetch_assoc()) {
        $key = $row['名称'] . '_' . $row['订单金额'];
        $name_amount_counts[$key] = $row['count'];
    }
    $select_stmt->close();

    // 4. 更新链接数据表中的在读数量
    foreach ($name_amount_counts as $name_amount => $count) {
        list($name, $amount) = explode('_', $name_amount);
        $update_link_data_sql = "UPDATE `链接数据` 
                               SET `在读` = COALESCE(`在读`, 0) - ? 
                               WHERE `名称` = ? AND `金额` = ?";
        $update_stmt = $conn->prepare($update_link_data_sql);
        $update_stmt->bind_param("isi", $count, $name, $amount);
        $update_stmt->execute();
        $update_stmt->close();
    }
    // 删除 `链接网址` 表中符合条件的记录
    $delete_sql = "DELETE FROM `链接网址` WHERE ? > `结束时间` AND `名称` = ? AND `订单金额` = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ssi", $sentTime, $name, $amount);
    $delete_stmt->execute();
    $delete_stmt->close();
    // 设置成功响应
    $result['success'] = true;
    $result['message'] = '操作成功';

    // 1. 先查询并统计符合条件的记录
    $status_sql = "SELECT `id`, `名称`, `订单金额`, `账号` 
                   FROM `链接网址` 
                   WHERE `名称` = ? AND `订单金额` = ? AND `状态` = 1 
                   AND ? > `订单时间`";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("sis", $name, $amount, $sentTime);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();

    // 用于存储需要更新的记录统计
    $updates = [];
    while ($row = $status_result->fetch_assoc()) {
        $key = $row['名称'] . '_' . $row['订单金额'];
        if (!isset($updates[$key])) {
            $updates[$key] = [
                'name' => $row['名称'],
                'amount' => $row['订单金额'],
                'count' => 0,
                'ids' => []
            ];
        }
        $updates[$key]['count']++;
        $updates[$key]['ids'][] = $row['id'];
    }
    $status_stmt->close();

    // 2. 先更新链接数据表
    foreach ($updates as $update) {
        $update_link_data_sql = "
            UPDATE `链接数据` 
            SET 
                `在读` = COALESCE(`在读`, 0) - ?, 
                `现条` = COALESCE(`现条`, 0) + ? 
            WHERE `名称` = ? AND `金额` = ?";
        $update_stmt = $conn->prepare($update_link_data_sql);
        $update_stmt->bind_param("iisi", 
            $update['count'], 
            $update['count'], 
            $update['name'], 
            $update['amount']
        );
        $update_stmt->execute();
        $update_stmt->close();

        // 3. 然后更新链接网址表中的状态
        if (!empty($update['ids'])) {
            $ids = implode(',', $update['ids']);
            $update_status_sql = "UPDATE `链接网址` SET `状态` = NULL WHERE `id` IN ($ids)";
            $conn->query($update_status_sql);
        }
    }

    $found_valid_record = !empty($updates);

    // 查询限时值
    $limit_time_sql = "SELECT `限时` FROM `链接数据` WHERE `名称` = ? AND `金额` = ?";
    $limit_time_stmt = $conn->prepare($limit_time_sql);
    $limit_time_stmt->bind_param("si", $name, $amount);
    $limit_time_stmt->execute();
    $limit_time_result = $limit_time_stmt->get_result();

    if ($limit_time_result->num_rows > 0) {
        $row_limit_time = $limit_time_result->fetch_assoc();
        $limit_time = $row_limit_time['限时'];
    } else {
        throw new Exception("未找到对应的限时值");
    }

    $limit_time_stmt->close();

    // 查询状态为NULL的记录
    $sql_null_status = "SELECT * FROM `链接网址` WHERE `名称` = ? AND `订单金额` = ? AND `状态` IS NULL LIMIT 1";
    $stmt_null_status = $conn->prepare($sql_null_status);
    $stmt_null_status->bind_param("si", $name, $amount);
    $stmt_null_status->execute();
    $result_null_status = $stmt_null_status->get_result();

    if ($result_null_status->num_rows > 0) {
        $row_null = $result_null_status->fetch_assoc();
        
        // 更新订单时间为当前服务器时间 + 限时值，并将状态列修改为1
        $new_order_time = date('Y-m-d H:i:s', strtotime($sentTime) + $limit_time);
        
        // 开始事务
        $conn->begin_transaction();
        
        try {
            // 1. 首先更新状态为1
            $update_sql_null = "UPDATE `链接网址` SET `订单时间` = ?, `状态` = 1 WHERE `id` = ? AND `状态` IS NULL";
            $update_stmt_null = $conn->prepare($update_sql_null);
            $update_stmt_null->bind_param("si", $new_order_time, $row_null["id"]);
            
            // 执行更新并检查是否成功
            if (!$update_stmt_null->execute() || $update_stmt_null->affected_rows == 0) {
                throw new Exception("更新状态失败，记录可能已被其他请求处理");
            }
            $update_stmt_null->close();

            // 2. 重新查询获取更新后的记录数据
            $get_updated_record_sql = "SELECT * FROM `链接网址` WHERE `id` = ? AND `状态` = 1 AND `订单时间` = ?";
            $get_updated_stmt = $conn->prepare($get_updated_record_sql);
            $get_updated_stmt->bind_param("is", $row_null["id"], $new_order_time);
            $get_updated_stmt->execute();
            $updated_result = $get_updated_stmt->get_result();
            
            if ($updated_result->num_rows > 0) {
                $updated_row = $updated_result->fetch_assoc();
                $url_null = $updated_row["网址"];
                $account_null = $updated_row["账号"];
                $platformOrderId_null = $updated_row["平台订单号"];
            } else {
                throw new Exception("无法获取更新后的记录或记录状态不正确");
            }
            $get_updated_stmt->close();

            // 3. 更新链接数据表
            $update_link_data_sql = "UPDATE `链接数据` SET `现条` = COALESCE(`现条`, 0) - 1, `在读` = COALESCE(`在读`, 0) + 1 WHERE `名称` = ? AND `金额` = ?";
            $update_link_data_stmt = $conn->prepare($update_link_data_sql);
            $update_link_data_stmt->bind_param("si", $name, $amount);
            $update_link_data_stmt->execute();
            $update_link_data_stmt->close();

            // 4. 更新订单视图表
            $update_order_view_sql_null = "UPDATE `订单视图` SET `号码` = ?, `密码` = ?, `平台订单号` = ? WHERE `商户订单号` = ?";
            $update_order_view_stmt_null = $conn->prepare($update_order_view_sql_null);
            $update_order_view_stmt_null->bind_param("ssss", $account_null, $url_null, $platformOrderId_null, $merchantOrderId);
            $update_order_view_stmt_null->execute();
            $update_order_view_stmt_null->close();

            // 提交事务
            $conn->commit();
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            throw $e;
        }

        // 处理HTML文件
        $originalHtmlFilePath = __DIR__ . '/whnt/二维码.html';
        if (!file_exists($originalHtmlFilePath)) {
            throw new Exception("原始HTML文件不存在: " . $originalHtmlFilePath);
        }
        $htmlContent = file_get_contents($originalHtmlFilePath);
        if ($htmlContent === false) {
            throw new Exception("无法读取原始HTML文件: " . $originalHtmlFilePath);
        }
        $htmlContent = str_replace('{{order_amount}}', $amount, $htmlContent);
        $htmlContent = str_replace('{{ORDER_NUMBER}}', $merchantOrderId, $htmlContent);

        // 替换二维码URL占位符
        $htmlContent = str_replace('{{qr_code_url}}', $url_null, $htmlContent);

        // 确保目标目录存在
        $newHtmlDir = __DIR__ . '/mqfh/';
        if (!is_dir($newHtmlDir)) {
            mkdir($newHtmlDir, 0755, true);
        }

        $newHtmlFilePath = $newHtmlDir . strtolower($merchantOrderId) . '.html';
        if (file_put_contents($newHtmlFilePath, $htmlContent) === false) {
            throw new Exception("无法写入新的HTML文件: " . $newHtmlFilePath);
        }

        $result['redirectUrl'] = 'https://' . getenv('BASE_URL') . '/mqfh/' . rawurlencode(strtolower($merchantOrderId)) . '.html';
        $result['success'] = true;
        $result['message'] = "处理成功，新订单时间: " . $new_order_time;
        $found_valid_record = true;
    }

    $stmt_null_status->close();

    if (!$found_valid_record) {
        $result['message'] = "没有找到可用记录";
    }

} catch (Exception $e) {
    $result['message'] = $e->getMessage();
} finally {
    $conn->close();
}

// 输出结果
echo json_encode($result); 

    