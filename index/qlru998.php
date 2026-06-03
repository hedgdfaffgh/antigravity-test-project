<?php
// 参数
$merchantId = 884;
$amount = 20000;
$outTradeNo = "M5530228050804639099";
$channelCode = "2001";
$notifyUrl = "https://www.baidu.com/";
$key = "8580b50603f7f7b658a5a45388074cf8";

// 拼接字符串
$data = "merchantId=$merchantId&amount=$amount&outTradeNo=$outTradeNo&channelCode=$channelCode&notifyUrl=$notifyUrl&key=$key";

// 计算 MD5 哈希并转换为小写
$sign = strtolower(md5($data));

echo "签名: " . $sign;
?>