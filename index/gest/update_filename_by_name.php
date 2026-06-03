<?php
// 允许从任何来源发起请求
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    echo json_encode(['success' => false, 'message' => '环境变量BASE_URL未设置']);
    exit;
}

// 确保只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只接受POST请求']);
    exit;
}

// 获取POST请求体中的JSON数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 检查是否提供了originalFileName和toggleRunning
if (!isset($data['originalFileName']) || !isset($data['toggleRunning'])) {
    echo json_encode(['success' => false, 'message' => '缺少原始文件名或toggleRunning']);
    exit;
}

$originalFileName = $data['originalFileName'];
$toggleRunning = $data['toggleRunning'];
$directoryPath = '/www/wwwroot/' . $baseUrl . '/whnt/';

$prefix = '暂停';
$newFileName = $originalFileName; // 默认新文件名为原始文件名

// 根据toggleRunning决定新文件名
if ($toggleRunning === true) {
    // 添加前缀
    $newFileName = $prefix . $originalFileName;
} else {
    // 移除前缀（如果存在）
    if (strpos($originalFileName, $prefix) === 0) {
        $newFileName = substr($originalFileName, strlen($prefix));
    }
}

// 首先尝试匹配包含"暂停"前缀的文件名
$patternWithPrefix = $directoryPath . $prefix . '*' . $originalFileName;
$filesWithPrefix = glob($patternWithPrefix);

// 如果没有找到，再尝试原始文件名
$files = $filesWithPrefix ?: glob($directoryPath . '*' . $originalFileName);

if (empty($files)) {
    echo json_encode(['success' => false, 'message' => '文件未找到']);
    exit;
}

// 遍历找到的文件并尝试重命名
$renamedFiles = [];
$renameErrors = [];
foreach ($files as $file) {
    $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
    // 修改部分：检查$newFileName是否已经包含了文件扩展名
    if (!preg_match('/\.' . preg_quote($fileExtension) . '$/', $newFileName)) {
        $newFilePath = $directoryPath . $newFileName . ($fileExtension ? '.' . $fileExtension : ''); // 仅当需要时添加文件扩展名
    } else {
        $newFilePath = $directoryPath . $newFileName; // 不需要添加文件扩展名
    }
    if (rename($file, $newFilePath)) {
        $renamedFiles[] = basename($newFilePath);
    } else {
        $renameErrors[] = basename($file);
    }
}

if (empty($renameErrors)) {
    echo json_encode(['success' => true, 'message' => '文件重命名成功', 'renamedFiles' => $renamedFiles]);
} else {
    $message = '部分文件重命名失败: ' . implode(', ', $renameErrors);
    echo json_encode(['success' => false, 'message' => $message, 'renamedFiles' => $renamedFiles]);
}
?>