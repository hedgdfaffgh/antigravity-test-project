<?php
// 启用错误报告以帮助调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('display_errors', 0); // 关闭错误显示
header('Content-Type: application/json'); // 设置返回类型为 JSON
$servername = getenv('DB_HOST'); // 确保环境变量正确配置
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT'); // 如果需要端口，确保使用它

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 检测连接
if ($conn->connect_error) {
    echo json_encode(array("error" => "连接失败: " . $conn->connect_error));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        echo json_encode(array("error" => "无效的 JSON 输入"));
        exit;
    }

    if (isset($data['toggleRunning']) && isset($data['id'])) {
        $id = $data['id'];
        $toggleRunning = $data['toggleRunning'] ? 1 : 0;

        // 更新操作列
        $stmt = $conn->prepare("UPDATE etyn SET `操作` = ? WHERE `id` = ?");
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
    } elseif (isset($data['username']) && isset($data['password']) && isset($data['parent_id'])) {
        // 处理插入新记录的逻辑
        $username = $data['username'];
        $password = $data['password'];
        $parent_id = $data['parent_id'];
        $保存 = 1;
        $操作 = 0;

        $stmt = $conn->prepare("INSERT INTO etyn (`用户名`, `密码`, `保存`, `操作`, `parent_id`) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            echo json_encode(array("error" => "准备失败: " . $conn->error));
            exit;
        }
        $bind = $stmt->bind_param("ssiss", $username, $password, $保存, $操作, $parent_id);
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
    } else {
        echo json_encode(array("error" => "未提供必要的参数"));
        exit;
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求，获取已经存在的数据行，并按id排序
    // 直接查询所有数据
    $sql = "SELECT `id`, `用户名`, `密码`, `保存`, `操作`, `parent_id` FROM etyn ORDER BY `id`";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(array("error" => "准备失败: " . $conn->error));
        exit;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        echo json_encode(array("error" => "查询失败: " . $conn->error));
        exit;
    }

    $rows = array();
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    echo json_encode($rows);
    $stmt->close();
}
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['id'])) {
        $id = $data['id'];

        $stmt = $conn->prepare("DELETE FROM etyn WHERE `id` = ?");
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
        echo json_encode(array("error" => "未提供必要的参数"));
        exit;
    }
}
else {
    echo json_encode(array("error" => "不支持的请求方法"));
    exit;
}

$conn->close();
?>