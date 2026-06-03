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

    // 检查是否传递了 button 字段
    if (isset($data['button']) && isset($data['id'])) {
        // 更新 button 字段
        $button = $data['button'] === '1' ? 1 : 0;
        $id = intval($data['id']);

        $stmt = $conn->prepare("UPDATE usvf SET `button` = ? WHERE `id` = ?");
        if ($stmt === false) {
            echo json_encode(array("error" => "准备失败: " . $conn->error));
            exit;
        }
        $bind = $stmt->bind_param("ii", $button, $id);
        if ($bind === false) {
            echo json_encode(array("error" => "绑定失败: " . $stmt->error));
            exit;
        }
        $execute = $stmt->execute();
        if ($execute === false) {
            echo json_encode(array("error" => "执行失败: " . $stmt->error));
            exit;
        } else {
            echo json_encode(array("success" => true, "message" => "按钮状态更新成功"));
        }
        $stmt->close();
        exit;
    } else {
        // 从POST请求中获取数据
        $parent_id = isset($data['parent_id']) && $data['parent_id'] !== '' ? intval($data['parent_id']) : null;
        $name = $data['name'];
        $amount = isset($data['amount']) && $data['amount'] !== '' ? intval($data['amount']) : null;
        $region = $data['region'];
        $number = $data['number'];
        $time = isset($data['time']) && $data['time'] !== '' ? intval($data['time']) : null;
        $limit = isset($data['limit']) && $data['limit'] !== '' ? intval($data['limit']) : null;
        $zong = isset($data['zong']) && $data['zong'] !== '' ? intval($data['zong']) : null;
        $disabled = isset($data['disabled']) && $data['disabled'] !== '' ? intval($data['disabled']) : 1;
        $mtkg = isset($data['mtkg']) && $data['mtkg'] !== '' ? $data['mtkg'] : null; // 获取 mtkg 值

        // 插入数据到数据库
        $stmt = $conn->prepare("INSERT INTO usvf (parent_id, `name`, `amount`, `region`, `number`, `time`, `limit`, `zong`, `disabled`, `mtkg`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            echo json_encode(array("error" => "准备失败: " . $conn->error));
            exit;
        }
        $bind = $stmt->bind_param("isssssisss", $parent_id, $name, $amount, $region, $number, $time, $limit, $zong, $disabled, $mtkg);
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
            echo json_encode(array("success" => true, "message" => "新记录插入成功", "id" => $last_id, "parent_id" => $parent_id, "disabled" => $disabled));
        }
        $stmt->close();
    }
   } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
       // 获取 mtkg 参数，如果没有提供，默认为 '0' 表示不过滤
       $mtkg = isset($_GET['mtkg']) ? $_GET['mtkg'] : '0';

       // 根据 mtkg 的值构建 SQL 查询
if ($mtkg === '0') {
    // 如果 mtkg 是 '0'，显示所有行
    $query = "SELECT `id`, `parent_id`, `name`, `amount`, `region`, `number`, `time`, `limit`, `zong`, `disabled`, `button` FROM usvf_sorted";
} else {
    // 否则，只显示与 mtkg 列值相同或以 mtkg 开头的行
    $query = "SELECT `id`, `parent_id`, `name`, `amount`, `region`, `number`, `time`, `limit`, `zong`, `disabled`, `button` FROM usvf_sorted WHERE `mtkg` LIKE CONCAT(?, '%')";
}

// 准备 SQL 语句
$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo json_encode(array("error" => "准备失败: " . $conn->error));
    exit;
}

// 如果 mtkg 不是 '0'，绑定参数
if ($mtkg !== '0') {
    $stmt->bind_param("s", $mtkg);
}

       // 执行查询
       $stmt->execute();
       $result = $stmt->get_result();
       if ($result === false) {
           echo json_encode(array("error" => "查询失败: " . $conn->error));
           exit;
       }

       // 获取查询结果并添加按钮状态描述
       $rows = array();
       while ($r = $result->fetch_assoc()) {
           $r['button_status'] = $r['button'] == 0 ? '启动' : '暂停';
           $rows[] = $r;
       }

       // 输出 JSON 格式的结果
       echo json_encode($rows);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // 从DELETE请求中获取ID
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? $data['id'] : null;

    if ($id === null) {
        echo json_encode(array("error" => "ID未提供"));
        exit;
    }

    // 从数据库中删除数据
    $stmt = $conn->prepare("DELETE FROM usvf WHERE `id` = ?");
    if ($stmt === false) {
        echo json_encode(array("error" => "准备失败: " . $conn->connect_error));
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
}

$conn->close();
?>