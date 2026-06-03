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
        $arg .= "outTradeNo=" . $para['outTradeNo'] . "&";
        $arg .= "timestamp=" . $para['timestamp'] . "&";
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
        // 签名验证成功后，根据 merchantId 和 outTradeNo 查找订单信息
        $outTradeNo = $data['outTradeNo'];
        $orderStmt = $mysqli->prepare("SELECT 订单金额, 回调地址, 回调 FROM `订单视图` WHERE 商户编码 = ? AND 商户订单号 = ?");
        $orderStmt->bind_param("is", $merchantId, $outTradeNo);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();

        if ($orderResult->num_rows > 0) {
            $orderRow = $orderResult->fetch_assoc();
            $statusMap = [
                NULL => 0,  // 未拉单
                -1 => 1,    // 等待支付
                1 => 2      // 支付成功
            ];
            $status = $statusMap[$orderRow['回调']] ?? 0;

            $response = [
                "code" => 200,
                "msg" => "SUCCESS",
                "data" => [
                    "merchantId" => $merchantId,
                    "amount" => $orderRow['订单金额'] * 100,
                    "outTradeNo" => $outTradeNo,
                    "notifyUrl" => $orderRow['回调地址'],
                    "status" => $status
                ]
            ];
        } else {
            $response = [
                "code" => 404,
                "msg" => "订单未找到"
            ];
        }
        $orderStmt->close();
    } else {
        $response = [
            "code" => 401,
            "msg" => "签名验证失败"
        ];
    }
} else {
    $response = [
        "code" => 404,
        "msg" => "未找到商户密钥"
    ];
}

$stmt->close();
$mysqli->close();

// 输出响应
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>