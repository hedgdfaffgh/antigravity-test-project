<?php

// 开启输出缓冲区
ob_start();

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => '环境变量BASE_URL未设置']);
    exit;
}

// 数据库配置
$servername = "localhost";
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

if (!$username || !$password) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => '数据库环境变量未设置']);
    exit;
}

try {
    // 创建数据库连接
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // 设置 PDO 错误模式为异常
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // 清除之前的所有输出
    ob_clean();
    // 确保错误信息也不会干扰JSON格式
    echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()]);
    exit;
}

// 检查是否有POST数据
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);

    // 检查是否是删除JSON文件的请求
if (isset($data['delete']) && $data['delete'] == 'true' && isset($data['id'])) {
    $id = $data['id'];
    // 假设我们无法直接获取name和mtkg，因此使用通配符来匹配所有可能的文件
    $jsonFileNamePattern = '*_' . $id . '_*.json'; // 使用通配符*代替name和mtkg
    $jsonFilePathPattern = '/www/wwwroot/' . $baseUrl . '/whnt/' . $jsonFileNamePattern;
    // 使用glob查找匹配的文件路径
    $matchedFiles = glob($jsonFilePathPattern);
    if (!empty($matchedFiles)) {
        foreach ($matchedFiles as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        // 清除之前的所有输出
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'JSON文件删除成功']);
    } else {
        // 清除之前的所有输出
        ob_clean();
        echo json_encode(['success' => false, 'message' => '文件不存在']);
    }
    exit;
}

    // 处理number字段
    if (isset($data['number'])) {
        $number = $data['number'];
        // 根据number字段的值查询ID, time, limit, name, zong, 以及新增的region和mtkg
        $stmt = $conn->prepare("SELECT id, `time`, `limit`, `name`, `region`, `zong`, `mtkg` FROM usvf_sorted WHERE number = :number");
        $stmt->bindParam(':number', $number);
        try {
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                // 清除之前的所有输出
                ob_clean();
                $id = $result['id'];
                $time = $result['time'];
                $limit = $result['limit'];
                $name = $result['name'];
                $region = $result['region'];
                $zong = $result['zong'];
                $mtkg = $result['mtkg']; // 获取mtkg值

                // 使用name, ID, zong, region和mtkg来建立JSON文件
                $jsonFileName = $name . '_' . $id . '_' . $mtkg . '.json';
                $jsonFilePath = '/www/wwwroot/' . $baseUrl . '/whnt/' . $jsonFileName;
                $data = [
                    'initial_limit' => $limit,
                    'limit' => $limit,
                    'time_limit' => $time,
                    'last_access_time' => 0,
                    'running' => true,
                    'region' => $region ?: '',
                    'number' => $number ?: '',
                    'zong' => $zong ?: '',
                    'mtkg' => $mtkg ?: '' // 如果mtkg是null，则使用空字符串
                ];
                file_put_contents($jsonFilePath, json_encode($data));

                echo json_encode(['success' => true, 'id' => $id, 'message' => '根据number获取ID成功，并创建了JSON文件']);
            } else {
                // 清除之前的所有输出
                ob_clean();
                echo json_encode(['success' => false, 'message' => '没有找到匹配的ID']);
            }
        } catch (PDOException $e) {
            // 清除之前的所有输出
            ob_clean();
            echo json_encode(['success' => false, 'message' => '查询ID时出错: ' . $e->getMessage()]);
        }
        exit;
    }
} // 这是新增的闭合大括号，确保所有开放的代码块都被正确闭合
?>