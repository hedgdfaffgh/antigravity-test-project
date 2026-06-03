<?php
session_start(); // 确保会话已启动

// 启用错误报告以帮助调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = getenv('DB_HOST'); // 确保环境变量正确配置
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT'); // 如果需要端口，确保使用它

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 检查连接
if ($conn->connect_error) {
    echo json_encode(array("error" => "连接失败: " . $conn->connect_error));
    exit;
}

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取 POST 数据
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        echo json_encode(array("error" => "无效的 JSON 输入"));
        exit;
    }

    // 处理删除请求
    if (isset($input['action']) && $input['action'] === 'delete' && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $sql = "DELETE FROM 核销卡密表 WHERE custom_id = '$custom_id'";

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

        if ($operation_value === 1) {
            $sql = "UPDATE 核销卡密表 SET 操作=$operation_value, original_name=名称, 名称='' WHERE custom_id='$custom_id'";
        } else {
            $sql = "UPDATE 核销卡密表 SET 操作=$operation_value, 名称=original_name WHERE custom_id='$custom_id'";
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

    // 处理重置请求
    if (isset($input['action']) && $input['action'] === 'reset' && isset($input['reset_all']) && $input['reset_all']) {
        // 重置所有金额和已充值字段
        $sql = "UPDATE 核销卡密表 SET 金额 = 0, 已充 = 0";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $conn->close();
        exit();
    }

    // 处理重置金额请求
    if (isset($input['action']) && $input['action'] === 'reset_amount' && isset($input['custom_id'])) {
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $sql = "UPDATE 核销卡密表 SET 金额 = 0 WHERE custom_id = '$custom_id'";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $conn->close();
        exit();
    }

    // 检查必填字段
    if (isset($input['custom_id'])) {
        $name = isset($input['name']) ? $conn->real_escape_string($input['name']) : NULL;
        $times = isset($input['times']) ? $conn->real_escape_string($input['times']) : NULL;
        $count = isset($input['count']) ? $conn->real_escape_string($input['count']) : NULL;
        $amount = isset($input['amount']) ? $conn->real_escape_string($input['amount']) : NULL;
        $recharged = isset($input['recharged']) ? $conn->real_escape_string($input['recharged']) : NULL;
        $custom_id = $conn->real_escape_string($input['custom_id']);
        $operation = isset($input['operation']) ? intval($input['operation']) : NULL;
        $parent_id = isset($input['parent_id']) ? intval($input['parent_id']) : 0; // 获取 parent_id

        // 检查是否存在该ID的数据
        $check_sql = "SELECT * FROM 核销卡密表 WHERE custom_id = '$custom_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // 更新数据
            $sql = "UPDATE 核销卡密表 SET 
                    名称=" . ($name ? "'$name'" : "NULL") . ", 
                    次数=" . ($times ? "'$times'" : "NULL") . ", 
                    号码=" . ($count ? "'$count'" : "NULL") . ", 
                    金额=" . ($amount ? "'$amount'" : "NULL") . ", 
                    已充=" . ($recharged ? "'$recharged'" : "NULL") . ", 
                    操作=" . ($operation !== NULL ? $operation : "NULL") . ", 
                    parent_id=$parent_id 
                    WHERE custom_id='$custom_id'";
        } else {
            // 插入数据
            $sql = "INSERT INTO 核销卡密表 (名称, 次数, 号码, 金额, 已充, 操作, custom_id, parent_id) 
                    VALUES (" . 
                    ($name ? "'$name'" : "NULL") . ", " . 
                    ($times ? "'$times'" : "NULL") . ", " . 
                    ($count ? "'$count'" : "NULL") . ", " . 
                    ($amount ? "'$amount'" : "NULL") . ", " . 
                    ($recharged ? "'$recharged'" : "NULL") . ", " . 
                    ($operation !== NULL ? $operation : "NULL") . ", 
                    '$custom_id', $parent_id)";
        }

        if ($conn->query($sql) === TRUE) {
            $response = array('success' => true, 'message' => '数据保存成功', 'custom_id' => $custom_id);
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

// 获取 parent_id
$parent_id = isset($_SESSION['parent_id']) ? intval($_SESSION['parent_id']) : 0;

// 修改查询条件：如果 parent_id < 50，则查询所有数据；否则只查询对应 parent_id 的数据
$sql = $parent_id < 50 
    ? "SELECT * FROM 核销卡密表" 
    : "SELECT * FROM 核销卡密表 WHERE parent_id = $parent_id";

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