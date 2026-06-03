<?php
// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '环境变量BASE_URL未设置']);
    exit;
}

// 获取从URL参数传递的订单号
$orderNumber = isset($_GET['orderNumber']) ? $_GET['orderNumber'] : '';

// 将订单号统一转换为小写
$orderNumber = strtolower($orderNumber);

// 构建图片URL
$imageUrl = "https://" . $baseUrl . "/mqfh/" . $orderNumber . ".jpg";

// 检查图片是否存在于服务器上
$imagePath = $_SERVER['DOCUMENT_ROOT'] . '/mqfh/' . $orderNumber . ".jpg"; // 确保路径正确

// 构建响应数组
if (file_exists($imagePath)) {
    $response = array(
        'success' => true,
        'imageUrl' => $imageUrl
    );
} else {
    $response = array(
        'success' => false,
        'message' => 'Image not found'
    );
}

// 设置响应头为JSON
header('Content-Type: application/json');
// 输出JSON响应
echo json_encode($response);
?>