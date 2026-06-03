<?php
// 开启错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$nickname = $_POST['nickname'] ?? '';
$password = $_POST['password'] ?? '';
$captcha = $_POST['captcha'] ?? '';

// 检查用户是否已经通过了验证码验证
if (isset($_SESSION['captcha']) && $captcha === $_SESSION['captcha']) {
    $_SESSION['captcha_verified'] = true;

    // 直接使用环境变量
    $servername = getenv('DB_HOST');
    $db_username = getenv('DB_USERNAME');
    $db_password = getenv('DB_PASSWORD');
    $dbname = getenv('DB_NAME');
    $port = getenv('DB_PORT');

    // 创建数据库连接
    try {
        $conn = new mysqli($servername, $db_username, $db_password, $dbname, $port);

        // 检查连接
        if ($conn->connect_error) {
            die("连接失败: " . $conn->connect_error);
        }
    } catch (Exception $e) {
        die("发生错误: " . $e->getMessage());
    }

    // 使用预处理语句防止SQL注入
    $stmt = $conn->prepare("SELECT id, parent_id, 操作 FROM etyn WHERE 用户名=? AND 密码=?");
    $stmt->bind_param("ss", $nickname, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // 检查操作列的值
        if ($row['操作'] == 1) {
            // 操作列为1，禁止登录
            header('Location: http://' . getenv('BASE_URL') . '/?error=operation_blocked');
            exit();
        } else {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['parent_id'] = $row['parent_id'];

            // 查询网址表以获取对应的名称和网址
            $stmt_url = $conn->prepare("SELECT `网址` FROM `网址` WHERE `名称`=?");
            $stmt_url->bind_param("s", $row['parent_id']);
            $stmt_url->execute();
            $result_url = $stmt_url->get_result();

            if ($result_url->num_rows > 0) {
                $row_url = $result_url->fetch_assoc();
                $redirect_url = $row_url['网址'];
                header('Location: ' . $redirect_url);
            } else {
                // 如果没有找到对应的名称，重定向到默认页面
                header('Location: default.php');
            }
            exit();
        }
    } else {
        // 登录失败，重定向回
        header('Location: http://' . getenv('BASE_URL') . '/?error=login_failed');
        exit();
    }

    // 关闭数据库连接
    $stmt->close();
    $conn->close();
} else {
    // 验证码错误，重定向回
    header('Location: http://' . getenv('BASE_URL') . '/?error=captcha_failed');
    exit();
}
?>