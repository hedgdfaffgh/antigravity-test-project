<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始POST数据
    $rawData = file_get_contents('php://input');

    // 将JSON数据解码为PHP数组
    $data = json_decode($rawData, true);

    // 从$data中提取名称和号码
    $orderNumber = isset($data["账号"]) ? $data["账号"] : null;
    $kgdc = isset($data["号码"]) ? $data["号码"] : null;

    if ($orderNumber && $kgdc) {
        // 构建API请求URL
        $apiUrl = "https://api.uomsg.com/zc/data.php?code=getMsg&token=$orderNumber&phone=$kgdc&keyWord=%E4%BA%A4%E6%98%93%E7%8C%AB";
        
        // 获取API响应
        $apiResponse = file_get_contents($apiUrl);

        if ($apiResponse !== false) {
            // 解码API响应中的Unicode字符
            $decodedResponse = json_decode('"' . $apiResponse . '"');

            // 使用正则表达式提取验证码
            if (preg_match('/验证码(\d{4})/', $decodedResponse, $matches)) {
                // 提取到的验证码
                $verificationCode = $matches[1];
                $response["oo"] = $verificationCode;
            } else {
                // 检查API响应是否包含特定提示信息
                if (strpos($decodedResponse, '尚未收到包含关键字"交易猫"的短信') !== false) {
                    $response["yy"] = 2;
                }
                // 如果无法提取验证码，返回完整的API响应
                $response["oo"] = $decodedResponse;
            }
        } else {
            // 返回完整的API响应内容
            $response["oo"] = $apiResponse;
        }
    } else {
        // 返回完整的API响应内容
        $response["oo"] = "无效的账号或号码";
    }

    // 输出响应
    echo json_encode($response);
}
?> 