<?php
// delete_file.php

// 允许从任何来源发起请求
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// 确保只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只接受POST请求']);
    exit;
}

// 获取POST请求体中的JSON数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 检查是否提供了fileName
if (!isset($data['fileName'])) {
    echo json_encode(['success' => false, 'message' => '缺少文件名']);
    exit;
}

$fileName = $data['fileName'];
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    echo json_encode(['success' => false, 'message' => '环境变量BASE_URL未设置']);
    exit;
}

$directoryPath = '/www/wwwroot/' . $baseUrl . '/whnt/';

// 使用glob函数查找所有匹配的文件
$pattern = $directoryPath . $fileName; // 直接使用fileName，包括扩展名
$files = glob($pattern);

if (empty($files)) {
    echo json_encode(['success' => false, 'message' => '文件不存在']);
    exit;
}

// 遍历找到的文件并尝试删除
$deletedFiles = [];
$deleteErrors = [];
foreach ($files as $file) {
    if (unlink($file)) {
        $deletedFiles[] = basename($file);
    } else {
        $deleteErrors[] = basename($file);
    }
}

if (empty($deleteErrors)) {
    echo json_encode(['success' => true, 'message' => '文件删除成功', 'deletedFiles' => $deletedFiles]);
} else {
    $message = '部分文件删除失败: ' . implode(', ', $deleteErrors);
    echo json_encode(['success' => false, 'message' => $message, 'deletedFiles' => $deletedFiles]);
}
?>