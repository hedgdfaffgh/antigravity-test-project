<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');

    $keyword = $_GET['keyword'] ?? '';
    $keykey = $_GET['keykey'] ?? '';
    $startDate = $_GET['startDate'] ?? '';
    $endDate = $_GET['endDate'] ?? '';

    try {
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
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }

        $sql = "SELECT * FROM `卡号与卡密` WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND `提取时间` BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= 'ss';
        }

        if (!empty($keyword)) {
            $sql .= " AND (`名称` LIKE ? OR `卡号` LIKE ? OR `卡密` LIKE ? OR `账号` LIKE ? OR `金额` LIKE ? OR `文件` LIKE ?)";
            $keyword = '%' . $keyword . '%';
            $params = array_merge($params, array_fill(0, 6, $keyword));
            $types .= str_repeat('s', 6);
        }

        if (!empty($keykey)) {
            $sql .= " AND (`名称` LIKE ? OR `卡号` LIKE ? OR `卡密` LIKE ? OR `账号` LIKE ? OR `金额` LIKE ? OR `文件` LIKE ?)";
            $keykey = '%' . $keykey . '%';
            $params = array_merge($params, array_fill(0, 6, $keykey));
            $types .= str_repeat('s', 6);
        }

        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("准备语句失败: " . $conn->error);
            }
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new Exception("执行查询失败: " . $stmt->error);
            }
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
            if (!$result) {
                throw new Exception("执行查询失败: " . $conn->error);
            }
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['状态'] = $row['状态'] == 1 ? '提取成功' : $row['状态'];
            $rows[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);

    } catch (Exception $e) {
        http_response_code(200); // 设置响应码为200
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        if (isset($conn)) {
            $conn->close();
        }
    }
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Received POST request: " . json_encode($input));

    $ids = $input['ids'] ?? [];

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(array("success" => false, "message" => "无效的ID列表"));
        exit;
    }

    $servername = getenv('DB_HOST');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        echo json_encode(array("success" => false, "message" => "连接失败: " . $conn->connect_error));
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM `卡号与卡密` WHERE `id` IN ($placeholders)";
    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        echo json_encode(array("success" => false, "message" => "准备语句失败: " . $conn->error));
        exit;
    }

    $stmt->bind_param($types, ...$ids);

    if ($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "记录删除成功"));
    } else {
        error_log("Execute statement failed: " . $stmt->error);
        echo json_encode(array("success" => false, "message" => "删除失败: " . $stmt->error));
    }

    $stmt->close();
    $conn->close();
    exit;
} else {
    echo json_encode(array("success" => false, "message" => "无效的请求方法"));
}
?>