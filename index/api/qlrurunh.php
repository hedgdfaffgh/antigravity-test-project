<?php
class Sign
{
    private $key;

    public function __construct($key) {
        $this->key = $key;
    }

    public function validateSign($params) {
        $stringA = $this->paramFilter($params);
        $sign = $this->md5Sign($stringA);
        if (!isset($params['sign']) || empty($params['sign']) || $params['sign'] != $sign) {
            return false;
        } else {
            return true;
        }
    }

    private function paramFilter($param) {
        $para_filter = array();
        foreach ($param as $key => $val) {
            if ($key == "sign" || $val == "") continue;
            else $para_filter[$key] = $param[$key];
        }
        return $this->createLinkString($para_filter);
    }

    private function createLinkString($para) {
        $arg = "";
        $arg .= "merchantId=" . $para['merchantId'] . "&";
        $arg .= "amount=" . $para['amount'] . "&";
        $arg .= "outTradeNo=" . $para['outTradeNo'] . "&";
        $arg .= "channelCode=" . $para['channelCode'] . "&";
        $arg .= "notifyUrl=" . $para['notifyUrl'] . "&";
        $arg .= "key=" . $this->key;
        return $arg;
    }

    private function md5Sign($preStr) {
        return strtolower(md5($preStr));
    }
}

// 从环境变量中获取数据库连接信息
$servername = "localhost";
$username = getenv('DB_USERNAME'); // 从环境变量获取数据库用户名
$password = getenv('DB_PASSWORD'); // 从环境变量获取数据库密码
$dbname = "ovrn";

// 创建连接
$mysqli = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($mysqli->connect_error) {
    die("连接失败: " . $mysqli->connect_error);
}

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);

// 根据 merchantId 查找密钥
$merchantId = $data['merchantId'];
$stmt = $mysqli->prepare("SELECT `密钥` FROM `商户密钥` WHERE `商户编码` = ?");
$stmt->bind_param("i", $merchantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $key = $row['密钥'];

    // 创建 Sign 实例并验证签名
    $sign = new Sign($key);
    if ($sign->validateSign($data)) {
        // 签名验证通过后，根据 channelCode 查找通道编码对应的名称与代码
        $channelCode = $data['channelCode'];
        $stmt = $mysqli->prepare("SELECT `名称`, `代码` FROM `通道编码` WHERE `通道编码` = ?");
        $stmt->bind_param("s", $channelCode);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $channelName = $row['名称'];
            $channelCodeValue = $row['代码'];

            // 将 amount 转换为小数格式
            $amount = number_format($data['amount'] / 100, 2, '.', '');

            // 使用 curl 发送数据
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $channelCodeValue); // 使用从数据库获取的代码值
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'name' => $channelName,
                'amount' => $amount,
                'merchantOrderId' => $data['outTradeNo']
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);

            // 检查是否发送成功
            if ($server_output === false) {
                $error = curl_error($ch);
                error_log("发送失败: " . $error);
                curl_close($ch);
            } else {
                curl_close($ch);
                error_log("发送成功: " . $server_output);

                // 根据 outTradeNo 查找订单视图中的商户订单号
                $outTradeNo = $data['outTradeNo'];
                $stmt = $mysqli->prepare("SELECT `商户订单号` FROM `订单视图` WHERE `商户订单号` = ?");
                $stmt->bind_param("s", $outTradeNo);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // 更新回调地址和商户编码
                    $stmt = $mysqli->prepare("UPDATE `订单视图` SET `回调地址` = ?, `商户编码` = ? WHERE `商户订单号` = ?");
                    $stmt->bind_param("sis", $data['notifyUrl'], $merchantId, $outTradeNo);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        error_log("回调地址和商户编码更新成功");

                        // 获取当前北京时间并转换为10位数UNIX时间戳
                        date_default_timezone_set('Asia/Shanghai');
                        $callbackTime = time();

                        // 查找对应的 outTradeNo.html 文件
                        $fileFound = false;
                        $lowerOutTradeNo = strtolower($outTradeNo); // 将 outTradeNo 转换为小写
                        for ($i = 0; $i < 5; $i++) {
                            $filePath = "/www/wwwroot/" . getenv('BASE_URL') . "/mqfh/{$lowerOutTradeNo}.html";
                            if (file_exists($filePath)) {
                                $fileFound = true;
                                break;
                            }
                            sleep(1); // 等待1秒后重试
                        }

                        // 返回成功信息
                        $response = [
                            "code" => 200,
                            "msg" => "SUCCESS",
                            "data" => [
                                "merchantId" => $merchantId,
                                "outTradeNo" => $outTradeNo,
                                "channelCode" => $channelCode,
                                "payUrl" => $fileFound ? "https://" . getenv('BASE_URL') . "/mqfh/{$lowerOutTradeNo}.html" : "null",
                                "timestamp" => $callbackTime,
                                "status" => $fileFound ? 1 : 2
                            ]
                        ];
                        echo json_encode($response);
                    } else {
                        error_log("回调地址和商户编码更新失败");
                    }
                } else {
                    error_log("未找到对应的商户订单号");
                }
            }
        } else {
            echo json_encode(["status" => "error", "message" => "无效的 channelCode"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "签名验证失败"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "无效的 merchantId"]);
}

// 关闭数据库连接
$mysqli->close();
?>