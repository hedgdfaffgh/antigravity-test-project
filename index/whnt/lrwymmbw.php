<?php
// 调试信息
error_log("接收到的POST数据: " . print_r($_POST, true));

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    error_log("环境变量BASE_URL未设置");
    echo json_encode([]);
    exit;
}

// 从POST请求中接收数据
$data = json_decode(file_get_contents('php://input'), true);

// 检查是否接收到 parent_id
if (isset($data['parent_id'])) {
    $parentId = $data['parent_id'];
    error_log("接收到的 parent_id: " . $parentId);
} else {
    error_log("没有接收到 parent_id");
    echo json_encode([]);
    exit;
}

// 检查是否接收到 status
if (isset($data['status'])) {
    $status = $data['status'];
    error_log("接收到的 status: " . $status);
} else {
    error_log("没有接收到 status");
    echo json_encode([]);
    exit;
}

// 构建JSON文件搜索模式，包括 parent_id
$jsonFilePattern = '/www/wwwroot/' . $baseUrl . '/whnt/*_' . $parentId . '.json';

// 调试信息
error_log("搜索模式: " . $jsonFilePattern);

// 使用glob函数查找匹配的文件
$matchedFiles = glob($jsonFilePattern);

// 使用自然排序算法对文件进行排序
natsort($matchedFiles);

// 调试信息
error_log("找到的文件: " . print_r($matchedFiles, true));

// 根据status值处理文件
if ($status == 1) {
    // 如果status为1，修改文件名添加"暂停_"
    foreach ($matchedFiles as $file) {
        $newName = '暂停_' . basename($file);
        rename($file, dirname($file) . '/' . $newName);
        error_log("文件重命名为: " . $newName);
    }
} elseif ($status == 0) {
    // 如果status为0，找到文件名包含"暂停_"的文件并去掉"暂停_"
    foreach ($matchedFiles as $file) {
        if (strpos(basename($file), '暂停_') === 0) {
            $newName = str_replace('暂停_', '', basename($file));
            rename($file, dirname($file) . '/' . $newName);
            error_log("文件重命名为: " . $newName);
        }
    }
}

// 返回找到的文件列表（更新后的）
echo json_encode(array_map('basename', glob($jsonFilePattern)));
?>