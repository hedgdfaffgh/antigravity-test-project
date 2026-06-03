<?php
// 检查是否收到POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 登录 API URL
    $loginApiUrl = "http://api.uomsg.com/zc/data.php?code=signIn&user=kgdfnyy&password=y85872101";
    
    // 使用 file_get_contents 获取登录 API 响应
    $loginResponse = file_get_contents($loginApiUrl);
    
    // 检查是否成功获取响应
    if ($loginResponse !== false) {
        // 检查响应是否包含错误信息
        if (strpos($loginResponse, 'ERROR:') === 0) {
            // 如果返回错误信息，将 oo 设置为 2
            $response["oo"] = 2;
        } else {
            // 假设成功返回的是 token
            $token = $loginResponse;
            
            // 将 token 存储在 $response["yy"] 中
            $response["yy"] = $token;
            
            // 使用 token 获取电话号码
            $phoneApiUrl = "https://api.uomsg.com/zc/data.php?code=getPhone&token=$token&phone=&province=&cardType=%E5%AE%9E%E5%8D%A1";
            $phoneResponse = file_get_contents($phoneApiUrl);
            
            // 检查是否成功获取电话号码
            if ($phoneResponse !== false && strpos($phoneResponse, 'ERROR:') !== 0) {
                // 将电话号码存储在 $response["oo"] 中
                $response["oo"] = $phoneResponse;
            } else {
                // 处理获取电话号码的错误
                $response["oo"] = "无法获取电话号码";
            }
        }
    } else {
        // 处理登录 API 的错误
        $response["oo"] = "无法获取登录API响应";
    }
    
    // 输出响应（根据需要）
    echo json_encode($response);
}
?> 