<?php
// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME'); // 直接从环境变量获取
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

// 获取GET请求中的名称参数
$name = isset($_GET['name']) ? $_GET['name'] : '';

// 获取GET请求中的amount参数，如果没有提供amount参数，则默认为0
$amount = isset($_GET['amount']) ? $_GET['amount'] : '0';

// 新增：获取GET请求中的merchantOrderId参数
$merchantOrderId = isset($_GET['merchantOrderId']) ? $_GET['merchantOrderId'] : '';

// 新增：获取GET请求中的platformOrderId参数
$platformOrderId = isset($_GET['platformOrderId']) ? $_GET['platformOrderId'] : null;

// 构建JSON文件搜索模式
$jsonFilePattern = __DIR__ . '/whnt/' . $name . '_*_*' . '.json';

// 使用glob查找匹配的文件路径
$matchedFiles = glob($jsonFilePattern);

// 使用自然排序算法对文件进行排序
natsort($matchedFiles);

// 初始化变量用于存储第一个没有访问限制的文件数据
$firstAvailableFileData = null;

// 遍历并输出所有匹配的文件路径和内容
foreach ($matchedFiles as $filePath) {
    // 读取JSON文件内容
    $jsonData = json_decode(file_get_contents($filePath), true);

    // 检查访问限制
    if (!checkAccessLimit($jsonData, $filePath, $name, false)) {
        continue; // 如果访问受限，则跳过当前文件
    }

    // 如果找到第一个没有访问限制的文件，则保存其数据
    if (!$firstAvailableFileData) {
        $firstAvailableFileData = ['filePath' => $filePath, 'jsonData' => $jsonData];
    }

    // 输出找到的文件路径
    echo "找到的文件路径: " . $filePath . "\n";

    // 添加日志记录到debug.log
    error_log("读取文件: " . $filePath . "\n", 3, __DIR__ . "/debug.log");

    // 输出文件内容
    echo "文件内容: " . json_encode($jsonData) . "\n";
}

// 基于amount和name构建TXT文件搜索模式
$txtFilePattern = __DIR__ . '/whnt/' . $amount . '_' . $name . '*.txt';

// 使用glob查找匹配的TXT文件路径
$matchedTxtFiles = glob($txtFilePattern);

// 初始化变量用于存储找到的TXT文件中的website_url
$websiteUrlFromTxt = '';

// 如果找到匹配的TXT文件，则读取第一个文件
if (!empty($matchedTxtFiles)) {
    $firstTxtFilePath = $matchedTxtFiles[0]; // 获取第一个匹配的TXT文件路径
    
    // 读取TXT文件的每一行
    $lines = file($firstTxtFilePath); // file函数将文件读入数组，每行为一个元素
    foreach ($lines as $line) {
        $decodedLine = json_decode($line, true); // 尝试解析每一行为JSON
        if ($decodedLine && isset($decodedLine['website_url'])) {
            $websiteUrlFromTxt = $decodedLine['website_url'];
            break; // 找到website_url后退出循环
        }
    }
}

// 检查是否找到website_url并准备替换HTML内容中的{{website_url}}占位符
if (!empty($websiteUrlFromTxt)) {
    echo "找到的website_url: " . $websiteUrlFromTxt . "\n";
    
    // 假设您已经有了HTML内容存储在变量$htmlContent中
       // $htmlContent = ...; // 您的HTML内容

    // 使用找到的website_url替换HTML内容中的{{website_url}}占位符
    $htmlContent = str_replace('{{website_url}}', $websiteUrlFromTxt, $htmlContent);

    // 输出或进一步处理替换后的HTML内容
    // echo $htmlContent;
} else {
    echo "没有找到website_url。\n";
}

// 检查访问限制
function checkAccessLimit(&$jsonData, $filePath, $name, $decreaseLimit = true) {
    $currentTime = time(); // 当前时间
    $timeLimitSeconds = $jsonData['time_limit'] * 60; // 时间限制，以秒为单位
    $lastAccessTime = $jsonData['last_access_time']; // 上次访问时间

    $timeStamp = date('[Y-m-d H:i:s] ');

    // 检查是否满足重置条件：当前时间超过上次访问时间加上时间限制，且limit为0
    if ($lastAccessTime + $timeLimitSeconds <= $currentTime && $jsonData['limit'] <= 0) {
        resetAccessLimit($jsonData, $filePath); // 重置访问限制
        $jsonData = json_decode(file_get_contents($filePath), true); // 重新加载json数据
        error_log($timeStamp . "访问限制已重置\n", 3, __DIR__ . "/debug.log");
    } elseif ($jsonData['limit'] <= 0) {
        return false; // 如果limit为0且不满足重置条件，则拒绝访问
    }

    if (!$jsonData['running']) {
        error_log($timeStamp . "服务暂停\n", 3, __DIR__ . "/debug.log");
        return false; // 如果服务暂停，也拒绝访问
    }

    // 减少limit并返回true（允许访问），如果decreaseLimit为true
    if ($decreaseLimit) {
        $jsonData['limit']--; // 减少limit
        $jsonData['last_access_time'] = $currentTime; // 更新访问时间
        file_put_contents($filePath, json_encode($jsonData)); // 保存更新
        error_log($timeStamp . "访问成功，limit减少到" . $jsonData['limit'] . "\n", 3, __DIR__ . "/debug.log");
        return true;
    }

    // 如果不减少limit，直接返回true（允许访问）
    return true;
}

// 重置访问限制
function resetAccessLimit(&$jsonData, $filePath) {
    $jsonData['limit'] = $jsonData['initial_limit']; // 重置limit为初始值
    $jsonData['last_access_time'] = time(); // 更新最后访问时间
    $jsonData['running'] = true; // 确保服务状态为运行中
    file_put_contents($filePath, json_encode($jsonData)); // 将更新后的数据写回文件

    $timeStamp = date('[Y-m-d H:i:s] ');
    error_log($timeStamp . "重置访问限制: " . $filePath . "\n", 3, __DIR__ . "/debug.log");
}

// 修改后的insertIntoDatabase函数，包括文件名参数
function insertIntoDatabase($number, $order_amount, $merchantOrderId, $platformOrderId, $fileName) {
    global $servername, $username, $password, $dbname;

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 首先检查商户订单号是否已存在
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM `订单` WHERE `商户订单号` = :merchantOrderId");
        $checkStmt->execute(['merchantOrderId' => $merchantOrderId]);
        $exists = $checkStmt->fetchColumn() > 0;

        if ($exists) {
            error_log("商户订单号已存在，不再插入\n", 3, __DIR__ . "/debug.log");
            return; // 如果商户订单号已存在，则不执行插入操作
        }

                // 如果商户订单号不存在，则执行插入操作
        $stmt = $conn->prepare("INSERT INTO `订单` (`号码`, `订单金额`, `订单时间`, `商户订单号`, `平台订单号`, `文件`) VALUES (:number, :order_amount, NOW(), :merchantOrderId, :platformOrderId, :fileName)");
        $stmt->execute([
            'number' => $number, 
            'order_amount' => $order_amount,
            'merchantOrderId' => $merchantOrderId,
            'platformOrderId' => $platformOrderId, // 插入platformOrderId，如果为null则数据库应处理为NULL或默认值
            'fileName' => $fileName // 插入文件名
        ]);

        error_log("数据库插入成功\n", 3, __DIR__ . "/debug.log");
    } catch(PDOException $e) {
        error_log("数据库插入失败: " . $e->getMessage() . "\n", 3, __DIR__ . "/debug.log");
    }
}

// 构建HTML文件的路径和处理逻辑
$filePath = __DIR__ . '/mqfh/' . strtolower($name) . '.html';

if ($firstAvailableFileData) {
    $region = $firstAvailableFileData['jsonData']['region'];
    $number = $firstAvailableFileData['jsonData']['number'];

    // 在这里减少limit并保存
    $firstAvailableFileData['jsonData']['limit']--;
    $firstAvailableFileData['jsonData']['last_access_time'] = time();
    file_put_contents($firstAvailableFileData['filePath'], json_encode($firstAvailableFileData['jsonData']));
    error_log(date('[Y-m-d H:i:s]') . "访问成功，limit减少到" . $firstAvailableFileData['jsonData']['limit'] . "\n", 3, __DIR__ . "/debug.log");

    // 获取文件名
    $fileName = basename($firstAvailableFileData['filePath']);

    // 插入数据库，包括文件名
    insertIntoDatabase($number, $amount, $merchantOrderId, $platformOrderId, $fileName);

    // 确保HTML文件夹存在
    if (!file_exists(__DIR__ . '/mqfh/')) {
        mkdir(__DIR__ . '/mqfh/', 0777, true);
    }

    // 定义原始HTML文件路径
    $originalHtmlFilePath = __DIR__ . '/whnt/' . strtolower($name) . '.html';

    // 读取原始HTML文件内容
    $htmlContent = file_get_contents($originalHtmlFilePath);

    // 检查是否从TXT文件中获取到了website_url
    if (!empty($websiteUrlFromTxt)) {
        // 替换HTML内容中的{{website_url}}占位符
        $htmlContent = str_replace('{{website_url}}', $websiteUrlFromTxt, $htmlContent);
    }

    // 替换其他HTML内容中的占位符
    $htmlContent = str_replace('{{order_amount}}', $amount, $htmlContent);
    $htmlContent = str_replace('{{region}}', $region, $htmlContent);
    $htmlContent = str_replace('{{number}}', $number, $htmlContent);
    $htmlContent = str_replace('{{merchantOrderId}}', $merchantOrderId, $htmlContent);

    // 定义新的HTML文件路径
    $newHtmlFilePath = __DIR__ . '/mqfh/' . strtolower($merchantOrderId) . '.html';

    // 保存修改后的HTML内容到新路径
    file_put_contents($newHtmlFilePath, $htmlContent);

    // 生成并输出完整的URL
    $url = 'https://' . getenv('BASE_URL') . '/mqfh/' . rawurlencode(strtolower($merchantOrderId)) . '.html';

    // 使用header函数重定向并附加URL
    header("Location: https://" . getenv('BASE_URL') . "/001.html?website_url=" . urlencode($url));
    exit();
}