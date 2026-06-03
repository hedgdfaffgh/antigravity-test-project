<?php
// 关闭所有错误报告
error_reporting(0);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 直接返回数据
die(json_encode(array("001" => 1)));
?> 