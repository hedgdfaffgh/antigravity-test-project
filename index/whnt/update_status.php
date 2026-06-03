<?php
$data = json_decode(file_get_contents('php://input'), true);
error_log(print_r($data, true));  // 将接收到的数据记录到错误日志中

$id = $data['id'];
$name = $data['name'];
$mtkg = $data['mtkg'];

// 定义可能的文件路径
$pausedFilePath = '暂停_' . $id . '_' . $mtkg . '.json';
$activeFilePath = $name . '_' . $id . '_' . $mtkg . '.json';

if (file_exists($activeFilePath)) {
    // 如果存在以 name 开头的文件，则重命名为以 “暂停_” 开头
    rename($activeFilePath, $pausedFilePath);
    echo json_encode(['success' => true, 'message' => '状态更新为暂停', 'newName' => $pausedFilePath]);
} elseif (file_exists($pausedFilePath)) {
    // 如果存在以 “暂停_” 开头的文件，则重命名为以 name 开头
    rename($pausedFilePath, $activeFilePath);
    echo json_encode(['success' => true, 'message' => '状态更新为激活', 'newName' => $activeFilePath]);
} else {
    // 如果两种文件都不存在
    echo json_encode(['success' => false, 'message' => '文件不存在']);
}
?>