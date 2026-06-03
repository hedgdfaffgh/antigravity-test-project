<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取并验证环境变量
    $baseUrl = getenv('BASE_URL');
    if (!$baseUrl) {
        header('Content-Type: application/json');
        echo json_encode(['状态' => '失败', 'uu' => '环境变量BASE_URL未设置']);
        exit;
    }

    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取号码值
    $number = isset($data["号码"]) ? $data["号码"] : null;

    // 从$data中提取订单金额
    $orderAmount = isset($data["实际金额"]) ? $data["实际金额"] : null;

    // 从$data中提取商户订单号
    $merchantOrderNumber = isset($data["商户订单号"]) ? $data["商户订单号"] : null;

    // 生成服务器当前时间
    $sentTime = date('Y-m-d H:i:s');

    // 数据库配置
    $servername = "localhost";
    $username = getenv('DB_USERNAME'); // 直接从环境变量获取
    $password = getenv('DB_PASSWORD');
    $dbname = "ovrn";

    // 日志目录检查
    if (!is_dir('whnt')) {
        mkdir('whnt', 0777, true);
    }

    // 初始化响应数组
    $response = array();

    // 封装插入未回调数据的函数，使用number同时插入到号码和账号列
    function insertUnCallbacked($conn, $number, $orderAmount, $sentTime) {
        $insertStmt = $conn->prepare("INSERT INTO `未回调` (`号码`, `实际金额`, `回调时间`, `账号`) VALUES (:number, :orderAmount, :sentTime, :account)");
        $insertStmt->bindParam(':number', $number);
        $insertStmt->bindParam(':orderAmount', $orderAmount);
        $insertStmt->bindParam(':sentTime', $sentTime);
        $insertStmt->bindParam(':account', $number); // 正确绑定账号参数为$number的值
        $insertStmt->execute();
    }

    try {
        // 创建PDO连接
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($number && $orderAmount) {
            if ($merchantOrderNumber) {
                // 如果提取到商户订单号，根据商户订单号查询
                $stmt = $conn->prepare("SELECT `订单时间`, `回调`, `订单金额`, `文件`, `号码`, `密码`, `商户订单号` FROM `订单视图` WHERE `商户订单号` = :merchantOrderNumber");
                $stmt->bindParam(':merchantOrderNumber', $merchantOrderNumber);
            } else {
                // 如果没有提取到商户订单号，选择符合条件的前五行
                $stmt = $conn->prepare("SELECT `订单时间`, `回调`, `订单金额`, `文件`, `号码`, `密码` FROM `订单视图` WHERE `订单时间` <= :sentTime ORDER BY `订单时间` DESC LIMIT 5");
            }
            $stmt->bindParam(':sentTime', $sentTime);
            $stmt->execute();

            // 其他逻辑处理...
            $callbackFound = false;
            $amountMismatch = false;
            $timeMismatch = false;
            $recordFound = false; // 新增变量，用于标记是否找到符合所有条件的记录
            $partialFileName = ''; // 用于存储部分文件名

            // 检查是否有匹配的记录
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $orderTime = $row['订单时间'];
                    $diff = strtotime($sentTime) - strtotime($orderTime);

                    if ($diff < 0 || $diff > 360) {
                        $timeMismatch = true;
                        continue; // 如果时间不匹配，就检查下一条记录
                    }

                                        if ($row['回调'] == 1) {
                        $callbackFound = true;
                        continue; // 如果这条记录的回调已经是1，就检查下一条记录
                    }

                    // 检查号码或密码列与提供的号码是否匹配
                    $numberMatch = (is_null($row['密码']) || $row['密码'] != $number) ? $row['号码'] == $number : true;
                    if (!$numberMatch) {
                        continue; // 如果号码不匹配，就检查下一条记录
                    }

                    if ($row['订单金额'] != $orderAmount) {
                        $amountMismatch = true;
                        continue; // 如果金额不匹配，就检查下一条记录
                    }

                    // 如果当前记录符合所有条件
                    $recordFound = true;
                    $partialFileName = $row['文件']; // 获取部分文件名

                    // 更新回调状态为1的逻辑放在这里
                    $updateStmt = $conn->prepare("UPDATE `订单视图` SET `回调` = 1, `实际金额` = :orderAmount, `回调时间` = :sentTime WHERE (`密码` = :number OR `号码` = :number) AND `订单金额` = :orderAmount AND `订单时间` = :orderTime");
                    $updateStmt->bindParam(':number', $number);
                    $updateStmt->bindParam(':orderAmount', $orderAmount);
                    $updateStmt->bindParam(':sentTime', $sentTime);
                    $updateStmt->bindParam(':orderTime', $orderTime);
                    $updateStmt->execute();

                    // 准备发送到另一个URL的数据
                    $dataToSend = [
                        'partialFileName' => $partialFileName,
                        'orderAmount' => $orderAmount
                    ];

                    // 使用cURL发送数据
                    $ch = curl_init('https://' . $baseUrl . '/processFile.php');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataToSend));
                    $responseCurl = curl_exec($ch);
                    curl_close($ch);

                    // 检查发送结果，这一步根据您的需求可选
                    if ($responseCurl) {
                        // 处理响应
                        $response["状态"] = "成功";
                        $response["uu"] = "处理成功，回调状态更新为1，实际金额和订单时间已更新，文件处理请求已发送";
                    } else {
                        // 发送失败的处理
                        $response["状态"] = "失败";
                        $response["uu"] = "文件处理请求发送失败";
                    }
                    break; // 找到符合条件的记录后，退出循环
                }

                if (!$recordFound) {
                    // 如果没有找到符合条件的记录，则根据错误类型设置响应
                    insertUnCallbacked($conn, $number, $orderAmount, $sentTime);
                    $response["状态"] = "失败";
                    if ($callbackFound) {
                        $response["uu"] = "重复";
                    } elseif ($amountMismatch) {
                        $response["uu"] = "金额不匹配";
                    } elseif ($timeMismatch) {
                        $response["uu"] = "时间不匹配";
                    } else {
                        $response["uu"] = "未知错误";
                    }
                }
            } else {
                // 如果没有找到匹配的记录，也插入到未回调表中
                insertUnCallbacked($conn, $number, $orderAmount, $sentTime);
                $response["状态"] = "失败";
                $response["uu"] = "无匹配订单";
            }
        } else {
            // 如果缺少必要的数据，设置响应状态和uu
            $response["状态"] = "失败";
            $response["uu"] = "缺少数据";
        }

    } catch(PDOException $e) {
        // 捕获并处理异常
        $response["状态"] = "失败";
        $response["uu"] = "数据库错误: " . $e->getMessage();
        file_put_contents('whnt/db_errors.log', $e->getMessage() . "\n", FILE_APPEND);
    } finally {
        // 关闭数据库连接
                $conn = null;
    }

    // 设置响应类型为JSON
    header('Content-Type: application/json');
    // 发送响应
    echo json_encode($response);
}