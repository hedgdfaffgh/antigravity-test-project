<?php
// 参数
$merchantId = 884;
$outTradeNo = "CCBxyl55Nm645";
$timestamp = 1728795827;
$key = "2bba980a8a65bc3cdabc909ee973f116";

// 拼接字符串
$data = "merchantId=$merchantId&outTradeNo=$outTradeNo&timestamp=$timestamp&key=$key";

// 计算 MD5 哈希并转换为小写
$sign = strtolower(md5($data));

echo "签名: " . $sign;
?>