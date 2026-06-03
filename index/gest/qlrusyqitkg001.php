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
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action']) && $input['action'] === 'delete' && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $sql = "DELETE FROM 账号表 WHERE custom_id = '$custom_id'";

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

    if (isset($input['action']) && ($input['action'] === 'pause' || $input['action'] === 'resume') && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $操作 = $input['action'] === 'pause' ? 1 : 0;
        $状态 = $input['action'] === 'pause' ? 1 : 0;
        
        if ($input['action'] === 'pause') {
            // 暂停操作：保存当前名称到 original_name，并清空名称
            $current_name = $conn->real_escape_string($input['name']);
            $sql = "UPDATE 账号表 SET 操作=$操作, 状态=$状态, original_name='$current_name', 名称='' WHERE custom_id='$custom_id'";
        } else {
            // 恢复操作：将 original_name 的值恢复到名称字段
            $sql = "UPDATE 账号表 SET 操作=$操作, 状态=$状态, 名称=original_name WHERE custom_id='$custom_id'";
        }

        if ($conn->query($sql) === TRUE) {
            if ($input['action'] === 'resume') {
                // 如果是恢复操作，获取 original_name 的值
                $query = "SELECT original_name FROM 账号表 WHERE custom_id='$custom_id'";
                $result = $conn->query($query);
                $row = $result->fetch_assoc();
                $original_name = $row['original_name'];
                $response = array('success' => true, 'message' => '操作成功', 'original_name' => $original_name);
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

    if (isset($input['custom_id'])) {
        $名称 = $conn->real_escape_string($input['name']);      // 接收名称
        $号码 = $conn->real_escape_string($input['count']);     // 接收号码
        $密码 = $conn->real_escape_string($input['password']);  // 接收密码
        $UID = $conn->real_escape_string($input['uid']);        // 接收UID
        $custom_id = $conn->real_escape_string($input['custom_id']); // 接收custom_id
        $操作 = isset($input['operation']) ? intval($input['operation']) : NULL; // 接收操作

        $check_sql = "SELECT * FROM 账号表 WHERE custom_id = '$custom_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $sql = "UPDATE 账号表 SET 名称='$名称', 号码='$号码', 密码='$密码', UID='$UID', 操作=" . ($操作 !== NULL ? $操作 : "NULL") . " WHERE custom_id='$custom_id'";
        } else {
            $sql = "INSERT INTO 账号表 (名称, 号码, 密码, UID, 操作, custom_id) VALUES ('$名称', '$号码', '$密码', '$UID', " . ($操作 !== NULL ? $操作 : "NULL") . ", '$custom_id')";
        }

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '数据保存成功');
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
$sql = "SELECT * FROM 账号表";
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