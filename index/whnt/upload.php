<?php
// upload.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    echo json_encode(["message" => "环境变量BASE_URL未设置"]);
    exit;
}

// 使用环境变量构建上传目录路径
$uploadDir = '/www/wwwroot/' . $baseUrl . '/whnt/';

// 检查文件字段是否存在
if (!isset($_FILES["file"])) {
    echo json_encode(["message" => "文件字段缺失"]);
    exit;
}

// 获取额外的表单字段值
$customName = isset($_POST["name"]) ? $_POST["name"] : "";

$originalFileName = $_FILES["file"]["name"]; // 获取原始文件名

// 为了安全起见，清理文件名以避免文件系统注入等安全问题
// 现在也移除点（.）
$originalFileName = preg_replace("/[^a-zA-Z0-9\x{4e00}-\x{9fa5}\-_]/u", "", $originalFileName);

// 如果提供了自定义名称，则使用它替换原始文件名（不改变扩展名）
if (!empty($customName)) {
    $fileExt = pathinfo($originalFileName, PATHINFO_EXTENSION); // 获取文件扩展名
    // 清理自定义名称，这里不移除点（.）
    $cleanCustomName = preg_replace("/[^a-zA-Z0-9\x{4e00}-\x{9fa5}\-_.]/u", "", $customName);
    $newFileName = $cleanCustomName . ($fileExt ? '.' . $fileExt : ''); // 使用自定义名称和原始扩展名，如果有扩展名则添加
} else {
    $newFileName = $originalFileName;
}

$uploadFile = $uploadDir . $newFileName;

if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile)) {
    // 如果需要，删除服务器上与原始文件名相同的文件
    $originalFilePath = $uploadDir . $originalFileName;
    if (file_exists($originalFilePath) && $originalFileName !== $newFileName) {
        unlink($originalFilePath);
    }

    echo json_encode(["message" => "文件上传成功", "fileName" => $newFileName]);
} else {
    echo json_encode(["message" => "文件上传失败"]);
}
?>