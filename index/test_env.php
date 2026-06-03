<?php
// 测试脚本 - 检查PHP和数据库连接
header('Content-Type: application/json; charset=utf-8');

$result = [
    'php_version' => phpversion(),
    'extensions' => [],
    'database' => [
        'status' => 'unknown',
        'message' => ''
    ]
];

// 检查必要扩展
$required_extensions = ['mysqli', 'curl', 'mbstring', 'gd'];
foreach ($required_extensions as $ext) {
    $result['extensions'][$ext] = extension_loaded($ext) ? 'OK' : 'MISSING';
}

// 测试数据库连接
$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: 'root123';
$dbname = getenv('DB_NAME') ?: 'ovrn';
$port = getenv('DB_PORT') ?: '3306';

try {
    $conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
    
    if ($conn->connect_error) {
        $result['database']['status'] = 'ERROR';
        $result['database']['message'] = $conn->connect_error;
    } else {
        $result['database']['status'] = 'OK';
        $result['database']['message'] = '连接成功';
        
        // 查询表数量
        $tables = $conn->query("SHOW TABLES");
        $result['database']['table_count'] = $tables->num_rows;
        
        $conn->close();
    }
} catch (Exception $e) {
    $result['database']['status'] = 'ERROR';
    $result['database']['message'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
