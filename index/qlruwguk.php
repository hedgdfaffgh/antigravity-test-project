<?php
// 设置响应头为JSON
header('Content-Type: application/json');

// 初始化结果数组
$result = array('success' => false, 'message' => '');

// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    $result['message'] = "连接失败: " . $conn->connect_error;
    echo json_encode($result);
    exit;
}

// 获取POST请求中的参数
$name = isset($_POST['name']) ? $_POST['name'] : '';
$amount = isset($_POST['amount']) ? $_POST['amount'] : '0';
$merchantOrderId = isset($_POST['merchantOrderId']) ? $_POST['merchantOrderId'] : '';

// 新增查询和更新逻辑
$sql = "SELECT `custom_id` FROM `金限` WHERE `金限` LIKE '%@$amount@%' OR `金限` LIKE '$amount@%' OR `金限` LIKE '%@$amount' OR `金限` = '$amount'";
$resultQuery = $conn->query($sql);

if ($resultQuery->num_rows > 0) {
    $row = $resultQuery->fetch_assoc();
    $customId = $row['custom_id']; // 获取 custom_id

    // 更新处理列为2
    $updateSql = "UPDATE `金限` SET `处理` = 2 WHERE `金限` LIKE '%@$amount@%' OR `金限` LIKE '$amount@%' OR `金限` LIKE '%@$amount' OR `金限` = '$amount'";
    if ($conn->query($updateSql) === TRUE) {
        $result['success'] = true;
        $result['message'] = "记录更新成功";

        // 根据 custom_id 查找名称
        $nameSql = "SELECT `名称` FROM `金限` WHERE `custom_id` = '$customId'";
        $nameResult = $conn->query($nameSql);
        if ($nameResult->num_rows > 0) {
            $nameRow = $nameResult->fetch_assoc();
            $name = $nameRow['名称']; // 用查找到的名称替换 $name

            // 在通道编码表中查找对应的代码
            $codeSql = "SELECT `代码` FROM `通道编码` WHERE `名称` = '$name'";
            $codeResult = $conn->query($codeSql);
            if ($codeResult->num_rows > 0) {
                $codeRow = $codeResult->fetch_assoc();
                $codeValue = $codeRow['代码'];

                // 转发数据
                $postData = array(
                    'name' => $name,
                    'amount' => $amount,
                    'merchantOrderId' => $merchantOrderId
                );

                // 使用 cURL 发送 POST 请求
                $ch = curl_init($codeValue);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                $response = curl_exec($ch);
                curl_close($ch);

                // 处理响应
                $result['forward_response'] = $response;
            } else {
                $result['message'] = "未找到对应的代码";
            }
        } else {
            $result['message'] = "未找到对应的名称";
        }
    } else {
        $result['message'] = "记录更新失败";
    }
} else {
    $result['message'] = "未找到匹配的记录";
}

// 原有查询和更新逻辑
$sql = "SELECT `custom_id`, `处理` FROM `金限` WHERE (
    (
        `金限` LIKE CONCAT('%-', '$amount', '-%')
        OR `金限` LIKE CONCAT('$amount', '-%')
        OR `金限` LIKE CONCAT('%-', '$amount')
        OR `金限` = '$amount'
    )
    OR (
        `名字` IS NULL 
        AND (
            CAST(SUBSTRING_INDEX(`金限`, '-', 1) AS DECIMAL) <= '$amount'
            AND CAST(SUBSTRING_INDEX(`金限`, '-', -1) AS DECIMAL) >= '$amount'
        )
        AND `名称` != ''
    )
)";

$resultQuery = $conn->query($sql);

if ($resultQuery->num_rows > 0) {
    $updated = false;
    while ($row = $resultQuery->fetch_assoc()) {
        if ($row['处理'] == 0 || $row['金限'] == $amount) { // 如果金限等于amount，不更新处理列
            $customId = $row['custom_id'];

            if ($row['金限'] != $amount) { // 仅在金限不等于amount时更新处理列
                // 更新处理列
                $updateSql = "UPDATE `金限` SET `处理` = 1 WHERE `custom_id` = '$customId'";
                if ($conn->query($updateSql) === TRUE) {
                    $result['success'] = true;
                    $result['message'] = "记录更新成功";
                    $updated = true;
                } else {
                    $result['message'] = "更新失败: " . $conn->error;
                }
            }

            // 根据 custom_id 查找名称
            $nameSql = "SELECT `名称` FROM `金限` WHERE `custom_id` = '$customId'";
            $nameResult = $conn->query($nameSql);
            if ($nameResult->num_rows > 0) {
                $nameRow = $nameResult->fetch_assoc();
                $name = $nameRow['名称']; // 用查找到的名称替换 $name

                // 在通道编码表中查找对应的代码
                $codeSql = "SELECT `代码` FROM `通道编码` WHERE `名称` = '$name'";
                $codeResult = $conn->query($codeSql);
                if ($codeResult->num_rows > 0) {
                    $codeRow = $codeResult->fetch_assoc();
                    $codeValue = $codeRow['代码'];

                    // 转发数据
                    $postData = array(
                        'name' => $name,
                        'amount' => $amount,
                        'merchantOrderId' => $merchantOrderId
                    );

                    // 使用 cURL 发送 POST 请求
                    $ch = curl_init($codeValue);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                    $response = curl_exec($ch);
                    curl_close($ch);

                    // 处理响应
                    $result['forward_response'] = $response;
                }
            }
            break; // 只处理一条记录后跳出循环
        }
    }

    // 检查名字列为NULL且名称列不为空字符串的行
    if ($updated) {
        $checkSql = "SELECT COUNT(*) AS total, SUM(`处理`) AS processed FROM `金限` WHERE `名字` IS NULL AND `名称` != ''";
        $checkResult = $conn->query($checkSql);
        $checkRow = $checkResult->fetch_assoc();

        if ($checkRow['total'] == $checkRow['processed']) {
            // 如果所有处理列都是1，重置为0
            $resetSql = "UPDATE `金限` SET `处理` = 0 WHERE `名字` IS NULL AND `名称` != ''";
            $conn->query($resetSql);
        }
    }
} else {
    $result['message'] = "未找到符合条件的记录";
}

// 输出结果
echo json_encode($result);

// 关闭连接
$conn->close();
?>