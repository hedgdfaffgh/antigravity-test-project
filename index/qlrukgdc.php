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

// 生成服务器当前时间
$sentTime = date('Y-m-d H:i:s');

// 插入数据到订单视图表
$insert_sql = "INSERT INTO `订单视图` (`文件`, `订单金额`, `商户订单号`, `订单时间`) VALUES (?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("ssss", $name, $amount, $merchantOrderId, $sentTime);
$insert_stmt->execute();
$insert_stmt->close();

// 处理请求来更新状态
if (!empty($name)) {
    $name = $conn->real_escape_string($name);
    
    // 更新所有符合条件的记录
    $sql = "UPDATE 号码 SET 状态=NULL, 结束时间=NULL WHERE 名称='$name' AND 结束时间 IS NOT NULL AND 结束时间 < '$sentTime'";
    
    if (!$conn->query($sql)) {
        $result['message'] = "更新失败: " . $conn->error;
        echo json_encode($result);
        exit;
    }

    // 1. 获取限时值
    $getLimitTimeSql = "SELECT * FROM 链接数据 WHERE 名称='$name'";
    $limitTimeResult = $conn->query($getLimitTimeSql);
    $limitTime = 0;
    if ($limitTimeResult->num_rows > 0) {
        $row = $limitTimeResult->fetch_assoc();
        $limitTime = intval($row['限时']);
    }
    $result['limitTime'] = $limitTime;

    // 2. 计算新的结束时间
    $endTime = date('Y-m-d H:i:s', strtotime($sentTime) + $limitTime);
    $result['endTime'] = $endTime;

    // 3. 更新一行符合条件的记录，设置状态为1和新的结束时间
    $updateSql = "UPDATE 号码 SET 结束时间='$endTime', 状态='1' WHERE 名称='$name' AND 状态 IS NULL AND 结束时间 IS NULL LIMIT 1";
    if ($conn->query($updateSql)) {
        if ($conn->affected_rows > 0) {
            $result['success'] = true;
            $result['message'] = "更新成功";
            $result['affectedRows'] = $conn->affected_rows;

            // 获取刚刚更新的记录的号码值
            $getNumberSql = "SELECT 号码 FROM 号码 WHERE 名称='$name' AND 状态='1' AND 结束时间='$endTime' LIMIT 1";
            $numberResult = $conn->query($getNumberSql);
            if ($numberResult && $numberResult->num_rows > 0) {
                $numberRow = $numberResult->fetch_assoc();
                $result['number'] = $numberRow['号码'];
                
                // 更新订单视图表中的号码
                $updateOrderViewSql = "UPDATE `订单视图` SET `号码` = ? WHERE `商户订单号` = ?";
                $updateOrderViewStmt = $conn->prepare($updateOrderViewSql);
                $updateOrderViewStmt->bind_param("ss", $numberRow['号码'], $merchantOrderId);
                $updateOrderViewStmt->execute();
                $updateOrderViewStmt->close();
                
                // 根据 name 和 amount 查询网址表
                $amount = $conn->real_escape_string($amount);
                $getUrlSql = "SELECT 网址 FROM 网址 WHERE 名称='$name' AND 金额='$amount' LIMIT 1";
                $urlResult = $conn->query($getUrlSql);
                if ($urlResult && $urlResult->num_rows > 0) {
                    $urlRow = $urlResult->fetch_assoc();
                    $result['url'] = $urlRow['网址'];
                } else {
                    $result['message'] .= "，但无法获取对应的网址";
                    $result['url'] = ''; // 设置一个空的URL
                }
                
                // 定义原始HTML文件路径
                $originalHtmlFilePath = __DIR__ . '/whnt/' . strtolower($name) . '.html';

                // 读取原始HTML文件内容
                $htmlContent = file_get_contents($originalHtmlFilePath);

                // 替换HTML内容中的占位符
                $htmlContent = str_replace('{{order_amount}}', $amount, $htmlContent);
                $htmlContent = str_replace('{{number}}', $result['number'], $htmlContent);
                $htmlContent = str_replace('{{website_url}}', $result['url'], $htmlContent);
                $htmlContent = str_replace('{{ORDER_NUMBER}}', $merchantOrderId, $htmlContent);

                // 定义新的HTML文件路径
                $newHtmlFilePath = __DIR__ . '/mqfh/' . strtolower($merchantOrderId) . '.html';

                // 保存修改后的HTML内容到新路径
                file_put_contents($newHtmlFilePath, $htmlContent);

                // 生成完整的URL
                $url = 'https://' . getenv('BASE_URL') . '/mqfh/' . rawurlencode(strtolower($merchantOrderId)) . '.html';

                // 将URL添加到结果数组中
                $result['redirectUrl'] = $url;
                $result['fullRedirectUrl'] = "https://" . getenv('BASE_URL') . "/qlru.html?website_url=" . urlencode($url);
            } else {
                $result['message'] .= "，但无法获取号码值";
            }
        } else {
            $result['success'] = false;
            $result['message'] = "更新失败";
        }
    } else {
        $result['success'] = false;
        $result['message'] = "更新失败: " . $conn->error;
    }
} else {
    $result['message'] = "名称参数为空";
}

// 关闭数据库连接
$conn->close();

// 输出JSON结果
echo json_encode($result);
?>