<?php

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    echo "环境变量BASE_URL未设置\n";
    exit;
}

// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

if (!$username || !$password) {
    echo "数据库环境变量未设置\n";
    exit;
}

// 指定搜索的目录
$directory = '/www/wwwroot/' . $baseUrl . '/whnt/';

// 从POST请求中获取partialFileName和orderAmount
$partialFileName = isset($_POST['partialFileName']) ? $_POST['partialFileName'] : '';
$orderAmount = isset($_POST['orderAmount']) ? $_POST['orderAmount'] : '';

// 初始化找到的文件名变量
$foundFileName = '';

// 检查目录是否存在
if (is_dir($directory)) {
    // 打开目录
    $dir = new DirectoryIterator($directory);
    // 遍历目录中的文件
    foreach ($dir as $fileinfo) {
        if (!$fileinfo->isDot()) {
            $filename = $fileinfo->getFilename();
            // 检查文件名是否包含partialFileName
            if (strpos($filename, $partialFileName) !== false) {
                $foundFileName = $filename;
                break; // 找到匹配的文件后退出循环
            }
        }
    }
}

// 检查是否找到了文件
if (!empty($foundFileName)) {
    // 构建完整的文件路径
    $filePath = $directory . $foundFileName;
    // 尝试读取并解码文件内容
    $jsonData = json_decode(file_get_contents($filePath), true);
    if ($jsonData === null) {
        echo "无法读取文件或文件内容不是有效的JSON。\n";
        exit;
    }

    // 重置为初始设置
    $jsonData['limit'] = $jsonData['initial_limit']; // 重置limit为initial_limit的值
    $jsonData['last_access_time'] = 0; // 重置最后访问时间为0
    $jsonData['running'] = true; // 确保服务状态为运行中

    // 将更新后的数据写回文件
    if (file_put_contents($filePath, json_encode($jsonData))) {
        echo "文件 '{$foundFileName}' 已成功重置为初始设置。\n";
    } else {
        echo "文件重置失败。\n";
    }

    // 从文件名中提取ID
    $id = intval(preg_replace('/[^0-9]/', '', $foundFileName));

    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 检查连接
    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 准备SQL语句并绑定参数
    $stmt = $conn->prepare("UPDATE `usvf_sorted` SET `amount` = amount + ? WHERE `ID` = ?");
    $stmt->bind_param("di", $orderAmount, $id);

    // 执行SQL语句
    if ($stmt->execute()) {
        echo "记录更新成功。\n";
        
        // 检查更新后的amount是否大于等于zong
        $checkStmt = $conn->prepare("SELECT `amount`, `zong`, `name` FROM `usvf_sorted` WHERE `ID` = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        // 只有当zong列有值时，才会比较amount和zong的值，并根据比较结果更新button列
        if ($row && $row['zong'] !== NULL && $row['amount'] >= $row['zong']) {
            // 如果amount大于等于zong，更新button列为1
            $updateStmt = $conn->prepare("UPDATE `usvf_sorted`             SET `button` = 1 WHERE `ID` = ?");
            $updateStmt->bind_param("i", $id);
            if ($updateStmt->execute()) {
                echo "按钮状态更新为1。\n";
                
                // 获取name字段的值
                $name = $row['name'];
                
                // 定义新的文件名
                $pausedFilePath = $directory . '暂停_' . $id . '.json';
                
                // 检查文件是否存在并重命名
                if (file_exists($filePath)) {
                    rename($filePath, $pausedFilePath);
                    echo "文件已重命名为 '{$pausedFilePath}'。\n";
                } else {
                    echo "未找到文件以重命名。\n";
                }
            } else {
                echo "按钮状态更新失败: " . $updateStmt->error;
            }
            $updateStmt->close();
        }
        
        $checkStmt->close();
    } else {
        echo "记录更新失败: " . $stmt->error;
    }

    // 关闭语句和连接
    $stmt->close();
    $conn->close();
} else {
    echo "没有找到包含 '{$partialFileName}' 的文件。\n";
}
?>