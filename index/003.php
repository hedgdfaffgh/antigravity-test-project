<?php
session_start();

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    die("环境变量BASE_URL未设置");
}

// 检查用户是否从网页2.php访问
if (!isset($_SESSION['has_visited_webpage2']) || !$_SESSION['has_visited_webpage2']) {
    // 如果不是从网页2.php访问，重定向到主页或显示错误
    header('Location: https://' . $baseUrl . '/index.php'); // 使用环境变量构建URL
    exit();
}

// 生成并存储唯一令牌
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

// 检查用户是否已经通过了验证码验证
if (!isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
    header('Location: https://' . $baseUrl . '/captcha.php');
    exit();
}

// 设置用户已访问网页2的会话变量
$_SESSION['has_visited_webpage2'] = true;

// 获取 parent_id
$parent_id = $_SESSION['parent_id'];

// 检查 parent_id 是否为父账号
$is_parent = ($parent_id === '0');
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>示例页面</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }
    .search-table {
        width: 100%;
        margin-bottom: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    .search-table td {
        padding: 0;
    }
    .search-table input[type="text"],
    .search-table input[type="button"] {
        width: 100%;
        box-sizing: border-box;
        margin: 0;
        text-align: center;
        padding: 10px;
        border-radius: 0px;
        border: 1px solid #ccc;
        outline: none;
    }
    .search-button {
        background-color: #007bff;
        color: white;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .search-button:hover {
        background-color: #0056b3;
    }
    table {
        width: 100%;
        background-color: #FFFFFF;
        border-collapse: collapse;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    td, th {
        border: 1px solid #E0E0E0;
        padding: 10px;
        text-align: center;
    }
    th {
        background-color: #f2f2f2;
    }
    .centered-input {
        display: block;
        margin: 0 auto;
        text-align: center;
        width: 60px;
    }
    .pagination {
        display: flex;
        justify-content: center; /* 使分页控件居中 */
        list-style-type: none; /* 移除列表样式 */
        padding: 0;
        width: 70%; /* 添加宽度以支持居中 */
    }
    .pagination a {
        padding: 8px 16px;
        margin: 0 4px;
        border: 1px solid #ddd;
        text-decoration: none;
        color: #333;
        display: block; /* 使链接表现为块级元素 */
    }
    .pagination a.active {
        background-color: #007bff;
        color: white;
        border: 1px solid #007bff;
    }
    .pagination a:hover:not(.active) {
        background-color: #ddd;
    }
    .total-amount-container {
        display: flex;
        justify-content: center;
        align-items: center;
        position: fixed;
        bottom: 0;
        width: 100%;
        background-color: #f2f2f2;
        padding: 10px 0;
    }
.footer-controls {
    display: flex;
    justify-content: flex-end; /* 修改此处，使子元素靠近容器的末端对齐 */
    align-items: center;
    padding: 8px;
}
/* 实际金额总和显示样式 */
.footer-controls > div:first-child {
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
    font-weight: bold;
    color: #333;
}

/* 分页控件样式 */
.pagination a {
    border-radius: 5px;
    transition: background-color 0.3s ease;
}

.pagination a:hover:not(.active) {
    background-color: #f0f0f0;
}

/* 每页显示数量选择器样式 */
.page-size-selector {
    border: 1px solid #ccc;
    padding: 5px 10px;
    border-radius: 5px;
    background-color: #f8f8f8;
    cursor: pointer;
}

.page-size-selector:hover {
    background-color: #e9e9e9;
}
</style>
</head>
<body>
        <table class="search-table">
        <tr class="search-row">
            <td colspan="2"><input type="text" id="keyword" placeholder="输入关键字符"></td>
            <td colspan="2"><input type="text" class="datepicker" id="startDate" placeholder="开始"></td>
            <td colspan="2"><input type="text" class="datepicker" id="endDate" placeholder="结束"></td>
            <td><input type="button" value="查找" class="search-button" onclick="searchOrders()"></td>
        </tr>
    </table>
<table id="ordersTable">
    <tr>
        <th>号码</th>
        <th>密码</th>
        <th>实际金额</th>
        <th>回调时间</th>
        <th>订单号</th>
        <th>账号</th>
        <th>操作</th>
    </tr>
    <tbody id="ordersData">
<!-- PHP代码开始 -->
<?php
// 使用环境变量进行数据库连接配置
$servername = getenv('DB_HOST');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

if (!$username || !$password || !$servername || !$dbname || !$port) {
    die("数据库环境变量未完全设置");
}

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 检查是否是 AJAX 请求并且是删除操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'deleteOrder' && isset($_POST['merchantOrderId'])) {
    $merchantOrderId = $conn->real_escape_string($_POST['merchantOrderId']);

    // 首先，根据ID获取要删除的记录的账号和实际金额
    $query = "SELECT 账号, 实际金额 FROM 未回调视图 WHERE ID = '$merchantOrderId'";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $account = $row['账号'];
        $actualAmount = $row['实际金额'];

// 然后，根据账号查找usvf_sorted表中的number列和对应的amount值
$selectQuery = "SELECT number, amount FROM usvf_sorted WHERE number = '$account'"; // 使用$account查找number和amount
$selectResult = $conn->query($selectQuery);
if ($selectResult->num_rows > 0) {
    $row = $selectResult->fetch_assoc();
    $number = $row['number'];
    $amount = $row['amount']; // 获取amount值

    // 更新usvf_sorted表中的amount值
    $newAmount = $amount + $actualAmount; // 新的amount值为原始值加上实际金额
    $updateQuery = "UPDATE usvf_sorted SET amount = '$newAmount' WHERE number = '$account'";
    if ($conn->query($updateQuery) !== TRUE) {
        echo "更新账号 $account 的amount值失败: " . $conn->error;
    }
} else {
    // 在这里处理没有找到记录的情况
    echo "没有找到usvf_sorted表中对应的记录";
    // 执行一些特定的逻辑，比如设置默认值或进行清理
    $number = "默认值"; // 根据需要设置默认值
    $amount = 0; // 可以设置amount的默认值为0或适当的值
}

        // 删除操作的 SQL 语句
        $deleteSql = "DELETE FROM 未回调视图 WHERE ID = '$merchantOrderId'";
        if ($conn->query($deleteSql) === TRUE) {
            echo "删除成功";
        } else {
            echo "删除失败: " . $conn->error;
        }
    } else {
        echo "没有找到指定的记录";
    }
    $conn->close();
    exit; // 停止执行后续的 PHP 代码
}

$perPage = 15; // 默认每页显示的记录数
if (isset($_GET['perPage']) && in_array($_GET['perPage'], [20, 30, 50, 100])) {
    $perPage = (int)$_GET['perPage']; // 从URL获取每页显示数量并验证其值
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // 当前页码
$offset = ($page - 1) * $perPage; // 计算当前页的第一条记录的偏移量

// 根据输入框字符和时间区间值筛选数据
$keyword = isset($_GET['keyword']) ? $conn->real_escape_string($_GET['keyword']) : '';
$startDate = isset($_GET['startDate']) ? $conn->real_escape_string($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? $conn->real_escape_string($_GET['endDate']) : '';

// 编写SQL查询，选择未回调视图表中的数据
$sql = "SELECT ID, 号码, 密码, 实际金额, 回调时间, 订单号, 账号 FROM 未回调视图 WHERE 1=1";

if (!empty($keyword)) {
    $sql .= " AND (号码 LIKE '%$keyword%' OR 密码 LIKE '%$keyword%' OR 实际金额 LIKE '%$keyword%' OR 回调时间 LIKE '%$keyword%' OR 订单号 LIKE '%$keyword%' OR 账号 LIKE '%$keyword%')";
}

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND 回调时间 BETWEEN '$startDate' AND '$endDate'";
}

// 先查询总记录数以计算总页数
$totalSql = "SELECT COUNT(*) AS total FROM ($sql) AS sub";
$totalResult = $conn->query($totalSql);
if ($totalResult === false) {
    die("查询失败: " . $conn->error);
}
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $perPage); // 计算总页数

// 添加LIMIT子句以实现分页
$sql .= " LIMIT $offset, $perPage";

// 执行分页查询
$result = $conn->query($sql);

// 初始化实际金额的总和
$actualAmountTotal = 0;

if ($result->num_rows > 0) {
    // 输出每行数据
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        // 隐藏的ID字段
        echo "<td style='display:none;'><input type='hidden' value='" . htmlspecialchars($row["ID"]) . "'></td>";
        echo "<td>" . htmlspecialchars($row["号码"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["密码"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["实际金额"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["回调时间"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["订单号"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["账号"]) . "</td>";
        echo "<td><button style='background-color: #ff0000; color: white; border: none; padding: 5px 10px; cursor: pointer;' onclick='deleteOrder(\"" . $row["ID"] . "\")'>删除</button></td>";
        echo "</tr>";
        // 累加实际金额总和
        $actualAmountTotal += $row["实际金额"];
    }
} else {
    echo "<tr><td colspan='7'>没有找到匹配的结果</td></tr>";
}
$conn->close();
?>
<!-- PHP代码结束 -->
</table>
<div class="footer-controls">
    <!-- 显示实际金额总和 -->
    <div>实际金额总和: <?php echo $actualAmountTotal; ?></div>
    
    <!-- 分页控件 -->
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&keyword=<?php echo $keyword; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="page-link">上一页</a>
    <?php endif; ?>
    
    <!-- 当前页 -->
    <a href="?page=<?php echo $page; ?>&keyword=<?php echo $keyword; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="page-link active"><?php echo $page; ?></a>
    
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&keyword=<?php echo $keyword; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="page-link">下一页</a>
    <?php endif; ?>
</div>
    
<!-- 每页显示数量选择器 -->
<div class="footer-controls">
    <label for="page-size-selector">显示页数:</label>
    <select id="page-size-selector" class="page-size-selector" onchange="location = this.value;">
        <?php
        $pageSizes = [15, 30, 50, 100]; // 可选择的每页显示数量
        foreach ($pageSizes as $size) {
            $selected = $perPage == $size ? "selected" : "";
            echo "<option value='?page=$page&keyword=$keyword&startDate=$startDate&endDate=$endDate&perPage=$size' $selected>$size</option>";
        }
        ?>
    </select>
</div>
<br>

    <!-- PHP代码结束 -->
    </table>
    <br>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/zh.js"></script>
    <script>
    // 确保flatpickr的JS链接是正确的
    flatpickr(".datepicker", {
        "locale": "zh",
        enableTime: true,
        dateFormat: "Y-m-d H:i",
    });

function deleteOrder(merchantOrderId) {
    var isConfirmed = confirm('确定要删除这条数据？');
    if (!isConfirmed) {
        return; // 如果用户点击"取消"，则不执行任何操作
    }
    // 如果用户确认删除，执行删除操作的 AJAX 请求
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true); // 假设您的删除逻辑在当前页面处理，或者替换为处理删除请求的URL
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status == 200) {
            alert('删除成功');
            location.reload(); // 重新加载页面以显示更新后的数据
        } else {
            alert('删除失败');
        }
    };
    xhr.send('action=deleteOrder&merchantOrderId=' + encodeURIComponent(merchantOrderId));
}
function searchAndUpdate() {
    var keyword = document.getElementById('keyword').value;
    var startDate = document.getElementById('startDate').value;
    var endDate = document.getElementById('endDate').value;

    // 使用 AJAX 请求获取数据
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?keyword=' + encodeURIComponent(keyword) + '&startDate=' + encodeURIComponent(startDate) + '&endDate=' + encodeURIComponent(endDate), true);
    xhr.onload = function() {
        if (xhr.status == 200) {
            // 解析返回的 HTML
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = xhr.responseText;

            // 更新订单数据
            var ordersData = tempDiv.querySelector('#ordersData');
            if (ordersData) {
                document.getElementById('ordersData').innerHTML = ordersData.innerHTML;
            }

            // 更新实际金额总和
            var totalAmountContainer = tempDiv.querySelector('.footer-controls div:first-child');
            if (totalAmountContainer) {
                document.querySelector('.footer-controls div:first-child').textContent = totalAmountContainer.textContent;
            }

            // 更新分页控件
            var pagination = tempDiv.querySelector('.pagination');
            if (pagination) {
                document.querySelector('.pagination').innerHTML = pagination.innerHTML;
            }
        } else {
            alert('查询失败');
        }
    };
    xhr.send();
}

// 修改 searchOrders 函数，使其调用新的 searchAndUpdate 函数
function searchOrders() {
    searchAndUpdate(); // 调用新函数以处理搜索和页面内容更新
}

// 确保在页面加载完成后绑定 searchOrders 函数到搜索按钮的点击事件
document.addEventListener('DOMContentLoaded', function() {
    var searchButton = document.querySelector('.search-button');
    if (searchButton) {
        searchButton.addEventListener('click', searchOrders);
    }
});
</script>
</body>
</html>