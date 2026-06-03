<?php
// 使用环境变量进行数据库连接配置
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取 POST 数据
    $input = json_decode(file_get_contents('php://input'), true);

    // 处理删除请求
    if (isset($input['action']) && $input['action'] === 'delete' && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $sql = "DELETE FROM 号码 WHERE custom_id = '$custom_id'";

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
        $operation_value = $input['action'] === 'pause' ? 1 : 0; // 将操作列的值修改为1或0

        if ($operation_value === 1) {
            // 暂停操作，将名称列的值存储到 original_name 列，并将名称列的值设置为空白
            $sql = "UPDATE 号码 SET 操作=$operation_value, original_name=名称, 名称='' WHERE custom_id='$custom_id'";
        } else {
            // 恢复操作，将 original_name 列的值恢复到名称列
            $sql = "UPDATE 号码 SET 操作=$operation_value, 名称=original_name WHERE custom_id='$custom_id'";
        }

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '操作成功');
        } else {
            $response = array('success' => false, 'message' => '操作失败: ' . $conn->error);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // 处理金额归零请求
    if (isset($input['action']) && $input['action'] === 'reset' && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $sql = "UPDATE 号码 SET 金额=0 WHERE custom_id='$custom_id'";

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '金额归零成功');
        } else {
            $response = array('success' => false, 'message' => '金额归零失败: ' . $conn->error);
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
        $count = isset($input['count']) ? $conn->real_escape_string($input['count']) : NULL;
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $delete_limit = isset($input['delete_limit']) ? $conn->real_escape_string($input['delete_limit']) : NULL;
        $operation = isset($input['operation']) ? intval($input['operation']) : NULL; // 获取操作列的值并转换为整数

        // 检查是否存在该ID的数据
        $check_sql = "SELECT * FROM 号码 WHERE custom_id = '$custom_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // 更新数据
            $sql = "UPDATE 号码 SET 名称=" . ($name ? "'$name'" : "NULL") . ", 金额=" . ($amount ? "'$amount'" : "NULL") . ", 号码=" . ($count ? "'$count'" : "NULL") . ", 总限=" . ($delete_limit ? "'$delete_limit'" : "NULL") . ", 操作=" . ($operation !== NULL ? $operation : "NULL") . " WHERE custom_id='$custom_id'";
        } else {
            // 插入数据
            $sql = "INSERT INTO 号码 (名称, 金额, 号码, 总限, 操作, custom_id) VALUES (" . ($name ? "'$name'" : "NULL") . ", " . ($amount ? "'$amount'" : "NULL") . ", " . ($count ? "'$count'" : "NULL") . ", " . ($delete_limit ? "'$delete_limit'" : "NULL") . ", " . ($operation !== NULL ? $operation : "NULL") . ", '$custom_id')";
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
$sql = "SELECT * FROM 号码";
$result = $conn->query($sql);

$data = array();

if ($result->num_rows > 0) {
    // 输出数据
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    // 如果没有结果，返回一个空数组
    $data = [];
}
$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
?>