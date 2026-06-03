<?php
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 检查连接
if ($conn->connect_error) {
    die(json_encode(array("error" => "连接失败: " . $conn->connect_error)));
}

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取 POST 数据
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(array("error" => "JSON解析错误: " . json_last_error_msg()));
        $conn->close();
        exit();
    }

    // 处理删除请求
    if (isset($input['action']) && $input['action'] === 'delete' && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $sql = "DELETE FROM 金限 WHERE custom_id = '$custom_id'";

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '数据删除成功');
        } else {
            $response = array('success' => false, 'message' => '数据删除失败: ' . $conn->error);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // 处理暂停/恢复请求
    if (isset($input['action']) && ($input['action'] === 'pause' || $input['action'] === 'resume') && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $operation_value = $input['action'] === 'pause' ? 1 : 0;
        $name = isset($input['name']) ? $conn->real_escape_string($input['name']) : '';

        if ($operation_value === 1) {
            // 暂停操作：保存当前名称到original_name，并清空名称
            $sql = "UPDATE 金限 SET 操作=$operation_value, original_name='$name', 名称='' WHERE custom_id='$custom_id'";
        } else {
            // 恢复操作：将original_name的值恢复到名称
            $sql = "UPDATE 金限 SET 操作=$operation_value, 名称=original_name WHERE custom_id='$custom_id'";
        }

        if ($conn->query($sql) === TRUE) {
            // 如果是恢复操作，返回original_name的值
            if ($operation_value === 0) {
                $query = "SELECT original_name FROM 金限 WHERE custom_id='$custom_id'";
                $result = $conn->query($query);
                $row = $result->fetch_assoc();
                $response = array('success' => true, 'message' => '操作成功', 'original_name' => $row['original_name']);
            } else {
                $response = array('success' => true, 'message' => '操作成功');
            }
        } else {
            $response = array('success' => false, 'message' => '操作失败: ' . $conn->error);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // 检查必填字段
    if (isset($input['custom_id'])) {
        $name = isset($input['name']) ? $conn->real_escape_string($input['name']) : NULL;
        $amount = isset($input['amount']) ? $conn->real_escape_string($input['amount']) : NULL;
        $nickname = isset($input['nickname']) ? $conn->real_escape_string($input['nickname']) : NULL;
        $judgeName = isset($input['judgeName']) ? $conn->real_escape_string($input['judgeName']) : NULL;
        $range = isset($input['range']) ? $conn->real_escape_string($input['range']) : NULL;
        $order = isset($input['order']) ? $conn->real_escape_string($input['order']) : NULL;
        $realGold = isset($input['realGold']) ? $conn->real_escape_string($input['realGold']) : NULL;
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $operation = isset($input['operation']) ? intval($input['operation']) : NULL;

        // 检查是否存在该ID的数据
        $check_sql = "SELECT * FROM 金限 WHERE custom_id = '$custom_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // 更新数据
            $sql = "UPDATE 金限 SET 名称=" . ($name ? "'$name'" : "NULL") . ", 金额=" . ($amount ? "'$amount'" : "NULL") . ", 名字=" . ($nickname ? "'$nickname'" : "NULL") . ", 判名=" . ($judgeName ? "'$judgeName'" : "NULL") . ", 金限=" . ($range ? "'$range'" : "NULL") . ", 顺序=" . ($order ? "'$order'" : "NULL") . ", 实金=" . ($realGold ? "'$realGold'" : "NULL") . ", 操作=" . ($operation !== NULL ? $operation : "NULL") . " WHERE custom_id='$custom_id'";
        } else {
            // 插入数据
            $sql = "INSERT INTO 金限 (名称, 金额, 名字, 判名, 金限, 顺序, 实金, 操作, custom_id) VALUES (" . ($name ? "'$name'" : "NULL") . ", " . ($amount ? "'$amount'" : "NULL") . ", " . ($nickname ? "'$nickname'" : "NULL") . ", " . ($judgeName ? "'$judgeName'" : "NULL") . ", " . ($range ? "'$range'" : "NULL") . ", " . ($order ? "'$order'" : "NULL") . ", " . ($realGold ? "'$realGold'" : "NULL") . ", " . ($operation !== NULL ? $operation : "NULL") . ", '$custom_id')";
        }

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '数据保存成功', 'id' => $conn->insert_id);
        } else {
            $response = array('success' => false, 'message' => '数据保存失败: ' . $conn->error);
        }
    } else {
        $response = array('success' => false, 'message' => '缺少必填字段: custom_id');
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit();
}

// 查询数据
$sql = "SELECT * FROM 金限";
$result = $conn->query($sql);

$data = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    $data = [];
}
$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
?>