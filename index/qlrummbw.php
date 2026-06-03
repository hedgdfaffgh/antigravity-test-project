<?php
header('Content-Type: application/json');
error_reporting(0); // 禁用错误报告

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    echo json_encode(['success' => false, 'message' => '环境变量BASE_URL未设置']);
    exit();
}

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
$deleteAll = $data['deleteAll'] ?? false;

if (!$deleteAll) {
    echo json_encode(['success' => false, 'message' => '缺少删除标志']);
    exit();
}

// 指定要删除文件的目录
$directory = '/www/wwwroot/' . $baseUrl . '/mqfh'; // 使用环境变量构建目录路径

// 检查目录是否存在
if (!is_dir($directory)) {
    echo json_encode(['success' => false, 'message' => '目录不存在']);
    exit();
}

// 获取目录中的所有HTML和JPG文件
$files = glob($directory . '/*.{html,jpg}', GLOB_BRACE);

// 检查是否找到文件
if ($files === false) {
    echo json_encode(['success' => false, 'message' => '无法读取目录']);
    exit();
}

$deletedFiles = [];
foreach ($files as $file) {
    if (unlink($file)) {
        $deletedFiles[] = basename($file);
    } else {
        // 添加调试信息
        error_log("无法删除文件: $file");
    }
}

if (empty($deletedFiles)) {
    echo json_encode(['success' => false, 'message' => '没有文件被删除']);
} else {
    echo json_encode(['success' => true, 'message' => '文件删除成功', 'deletedFiles' => $deletedFiles]);
}
?>