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
        $sql = "DELETE FROM 链接数据 WHERE custom_id = '$custom_id'";

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

    // 检查必填字段
    if (isset($input['name'], $input['amount'], $input['count'], $input['limit'], $input['delete_limit'], $input['custom_id'])) {
        $name = $conn->real_escape_string($input['name']);
        $amount = $conn->real_escape_string($input['amount']);
        $count = $conn->real_escape_string($input['count']);
        $limit = $conn->real_escape_string($input['limit']);
        $delete_limit = $conn->real_escape_string($input['delete_limit']);
        $custom_id = $conn->real_escape_string($input['custom_id']);

        // 将空字符串转换为 NULL
        $amount = $amount === '' ? 'NULL' : "'$amount'";
        $count = $count === '' ? 'NULL' : "'$count'";
        $limit = $limit === '' ? 'NULL' : "'$limit'";
        $delete_limit = $delete_limit === '' ? 'NULL' : "'$delete_limit'";

        // 检查是否存在该ID的数据
        $check_sql = "SELECT * FROM 链接数据 WHERE custom_id = '$custom_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // 更新数据
            $sql = "UPDATE 链接数据 SET 名称='$name', 金额=$amount, 条数=$count, 限时=$limit, 限删=$delete_limit WHERE custom_id='$custom_id'";
        } else {
            // 插入数据
            $sql = "INSERT INTO 链接数据 (名称, 金额, 条数, 限时, 限删, custom_id) VALUES ('$name', $amount, $count, $limit, $delete_limit, '$custom_id')";
        }

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '数据保存成功', 'id' => $conn->insert_id);
        } else {
            $response = array('success' => false, 'message' => '数据保存失败: ' . $conn->error);
        }
    } else {
        $response = array('success' => false, 'message' => '缺少必填字段');
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit();
}

// 查询数据
$sql = "SELECT * FROM 链接数据";
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