<?php
ini_set('display_errors', 0); // 关闭错误显示
header('Content-Type: application/json'); // 设置返回类型为 JSON
$servername = "localhost";
$username = getenv('DB_USERNAME'); // 直接从环境变量获取
$password = getenv('DB_PASSWORD');
$dbname = "ovrn";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检测连接
if ($conn->connect_error) {
    echo json_encode(array("error" => "连接失败: " . $conn->connect_error));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // 检查是否传递了 name 字段
    if (isset($data['name'])) {
        $name = $data['name'];
        $保存 = 1; // 假设上传成功后保存列显示数字1
        $操作 = 0; // 假设默认操作状态为0

        // 插入数据到数据库
        $stmt = $conn->prepare("INSERT INTO 文件表 (`名称`, `保存`, `操作`) VALUES (?, ?, ?)");
        if ($stmt === false) {
            echo json_encode(array("error" => "准备失败: " . $conn->error));
            exit;
        }
        $bind = $stmt->bind_param("sii", $name, $保存, $操作);
        if ($bind === false) {
            echo json_encode(array("error" => "绑定失败: " . $stmt->error));
            exit;
        }
        $execute = $stmt->execute();
        if ($execute === false) {
            echo json_encode(array("error" => "执行失败: " . $stmt->error));
            exit;
        } else {
            $last_id = $conn->insert_id;
            echo json_encode(array("success" => true, "message" => "新记录插入成功", "id" => $last_id));
        }
        $stmt->close();
    } elseif (isset($data['toggleRunning'])) {
// 检查是否传递了 toggleRunning 参数
$id = $data['id'];
$toggleRunning = $data['toggleRunning'] ? 1 : 0; // 如果 toggleRunning 为 true，则操作状态设置为 1（暂停），否则为 0（启动）

// 更新数据库中的操作状态
$stmt = $conn->prepare("UPDATE 文件表 SET `操作` = ? WHERE `id` = ?");
        if ($stmt === false) {
            echo json_encode(array("error" => "准备失败: " . $conn->error));
            exit;
        }
        $bind = $stmt->bind_param("ii", $toggleRunning, $id);
        if ($bind === false) {
            echo json_encode(array("error" => "绑定失败: " . $stmt->error));
            exit;
        }
        $execute = $stmt->execute();
        if ($execute === false) {
            echo json_encode(array("error" => "执行失败: " . $stmt->error));
            exit;
        } else {
            echo json_encode(array("success" => true, "message" => "操作状态更新成功"));
        }
        $stmt->close();
    } else {
        echo json_encode(array("error" => "未提供必要的参数"));
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求，获取已经存在的数据行，并按id排序
    $result = $conn->query("SELECT `id`, `名称`, `保存`, `操作` FROM 文件表 ORDER BY `id`");
    if ($result === false) {
        echo json_encode(array("error" => "查询失败: " . $conn->error));
        exit;
    }
    $rows = array();
    while($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
    }
    echo json_encode($rows);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // 从DELETE请求中获取ID
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? $data['id'] : null;

    if ($id === null) {
        echo json_encode(array("error" => "未提供ID"));
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM 文件表 WHERE `id` = ?");
    if ($stmt === false) {
        echo json_encode(array("error" => "准备失败: " . $conn->error));
        exit;
    }
    $bind = $stmt->bind_param("i", $id);
    if ($bind === false) {
        echo json_encode(array("error" => "绑定失败: " . $stmt->error));
        exit;
    }
    $execute = $stmt->execute();
    if ($execute === false) {
        echo json_encode(array("error" => "执行失败: " . $stmt->error));
        exit;
    } else {
        echo json_encode(array("success" => true, "message" => "记录删除成功"));
    }
    $stmt->close();
} else {
    // 如果不是POST, GET, 或 DELETE请求
    echo json_encode(array("error" => "不支持的请求方法"));
}

$conn->close();
?>