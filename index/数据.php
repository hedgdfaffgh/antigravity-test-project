<?php
session_start();

// 检查用户是否从网页2.php访问
if (!isset($_SESSION['has_visited_webpage2']) || !$_SESSION['has_visited_webpage2']) {
    // 如果不是从网页2.php访问，重定向到主页或显示错误
    header('Location: ' . getenv('BASE_URL') . '/index.php'); // 重定向到一个你选择的页面
    exit();
}

// 生成并存储唯一令牌
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

// 检查用户是否已经通过了验证码验证
if (!isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
    header('Location: ' . getenv('BASE_URL') . '/captcha.php');
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
    .password-column {
        max-width: 250px; /* 设置最大宽度 */
        white-space: nowrap; /* 禁止换行 */
        overflow: hidden; /* 隐藏溢出内容 */
        text-overflow: ellipsis; /* 使用省略号表示溢出内容 */
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
            <td colspan="2"><input type="text" id="keykey" placeholder="输入关键字符"></td> <!-- 新增输入框 -->
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
            <th>回调</th>
            <th>订单时间</th>
            <th>回调时间</th>
            <th>订单金额</th>
            <th>平台订单号</th>
            <th>商户订单号</th>
        </tr>
        <tbody id="ordersData"> <!-- 添加此行 -->
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

// 检查是否是AJAX请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'updateOrder') {
        $merchantOrderId = $conn->real_escape_string($_POST['merchantOrderId']);
        $actualAmount = $conn->real_escape_string($_POST['actualAmount']);
        $callbackTime = date('Y-m-d H:i:s'); // 使用服务器时间作为回调时间
        $callback = 1;
        // 更新数据库记录
        $sql = "UPDATE 订单视图 SET 实际金额='$actualAmount', 回调时间='$callbackTime', 回调='$callback' WHERE 商户订单号='$merchantOrderId'";
        if ($conn->query($sql) === TRUE) {
            echo "记录更新成功";
        } else {
            echo "错误: " . $conn->error;
        }
        $conn->close();
        exit; // 结束脚本执行
    }
}
$perPage = 15; // 默认每页显示的记录数
if (isset($_GET['perPage']) && in_array($_GET['perPage'], [20, 30, 50, 100])) {
    $perPage = (int)$_GET['perPage']; // 从URL获取每页显示数量并验证其值
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // 当前页码
$offset = ($page - 1) * $perPage; // 计算当前页的第一条记录的偏移量

// 确保在构建SQL查询时使用$perPage变量
$sql .= " LIMIT $offset, $perPage";

// 根据输入框字符和时间区间值筛选数据
$keyword = isset($_GET['keyword']) ? $conn->real_escape_string($_GET['keyword']) : '';
$keykey = isset($_GET['keykey']) ? $conn->real_escape_string($_GET['keykey']) : ''; // 新增输入框处理
$startDate = isset($_GET['startDate']) ? $conn->real_escape_string($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? $conn->real_escape_string($_GET['endDate']) : '';

// 编写SQL查询，选择订单视图表中的数据
$sql = "SELECT 号码, 密码, 实际金额, 回调, 订单时间, 回调时间, 订单金额, 平台订单号, 商户订单号, 文件, 商户编码 FROM 订单视图 WHERE 1=1";

if (!empty($keyword)) {
    $sql .= " AND (号码 LIKE '%$keyword%' OR 密码 LIKE '%$keyword%' OR 平台订单号 LIKE '%$keyword%' OR 商户订单号 LIKE '%$keyword%' OR 文件 LIKE '%$keyword%' OR 商户编码 LIKE '%$keyword%')";
}

if (!empty($keykey)) {
    if ($keykey == '1') {
        // 如果 keykey 是 1，则只显示回调列为 1 的记录
        $sql .= " AND 回调 = '1'";
    } else {
        $sql .= " AND (号码 LIKE '%$keykey%' OR 密码 LIKE '%$keykey%' OR 平台订单号 LIKE '%$keykey%' OR 商户订单号 LIKE '%$keykey%' OR 文件 LIKE '%$keykey%' OR 商户编码 LIKE '%$keykey%')";
    }
}

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND 订单时间 BETWEEN '$startDate' AND '$endDate'";
}

// 先查询总记录数
$totalSql = "SELECT COUNT(*) AS total FROM ($sql) AS sub";
$totalResult = $conn->query($totalSql);
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $perPage); // 计算总页数

// 添加LIMIT子句以实现分页
$sql .= " LIMIT $offset, $perPage";

$result = $conn->query($sql);

// 初始化实际金额的总和
$actualAmountTotal = 0;

if ($result->num_rows > 0) {
    // 输出每行数据
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["号码"] . "</td>";
        echo "<td class='password-column'>" . $row["密码"] . "</td>";

        // 回调值不为1时，显示输入框，并且如果实际金额不为NULL，也显示值
        if ($row["回调"] != "1") {
            $actualAmountValue = is_null($row["实际金额"]) ? '' : $row["实际金额"];
            echo "<td><input type='text' name='actualAmount' value='$actualAmountValue' placeholder='输入金额' class='centered-input'></td>";
        } else {
            // 回调值为1时，直接显示实际金额
            echo "<td>" . $row["实际金额"] . "</td>";
        }

        // 回调值为-1或NULL时，显示蓝色背景的确认按钮
        if (is_null($row["回调"]) || $row["回调"] == "-1") {
            echo "<td><button style='background-color: #007bff; color: white; border: none; padding: 5px 10px; cursor: pointer;' onclick='updateOrder(this, \"" . $row["商户订单号"] . "\")'>确认</button></td>";
        } else if ($row["回调"] == "1") {
            // 回调值为1时，显示文本"已回"
            echo "<td>已回</td>";
        } else {
            // 其他情况，直接显示回调值
            echo "<td>" . $row["回调"] . "</td>";
        }

        echo "<td>" . $row["订单时间"] . "</td>";
        echo "<td>" . $row["回调时间"] . "</td>";
        echo "<td>" . $row["订单金额"] . "</td>";
        echo "<td>" . $row["平台订单号"] . "</td>";
        echo "<td>" . $row["商户订单号"] . "</td>";
        echo "</tr>";

        // 当回调列的值为1时，累加实际金额
        if ($row["回调"] == "1" && !is_null($row["实际金额"])) {
            $actualAmountTotal += $row["实际金额"];
        }
    }
} else {
    echo "<tr><td colspan='9'>没有找到匹配的结果</td></tr>";
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
        <a href="?page=<?php echo $page - 1; ?>&keyword=<?php echo $keyword; ?>&keykey=<?php echo $keykey; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="page-link">上一页</a>
    <?php endif; ?>
    
    <!-- 当前页 -->
    <a href="?page=<?php echo $page; ?>&keyword=<?php echo $keyword; ?>&keykey=<?php echo $keykey; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="page-link active"><?php echo $page; ?></a>
    
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&keyword=<?php echo $keyword; ?>&keykey=<?php echo $keykey; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="page-link">下一页</a>
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
            echo "<option value='?page=$page&keyword=$keyword&keykey=$keykey&startDate=$startDate&endDate=$endDate&perPage=$size' $selected>$size</option>";
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

function updateOrder(button, merchantOrderId) {
    var actualAmount = button.closest('tr').querySelector('input[name="actualAmount"]').value;
    if (!actualAmount) {
        alert('实际金额不能为空');
        return;
    }
    // 弹出确认对话框
    var isConfirmed = confirm('确定要更新订单吗？');
    if (!isConfirmed) {
        return; // 如果用户点击"取消"，则不执行任何操作
    }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true); // 发送到当前页面
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status == 200) {
            alert('更新成功');
            location.reload(); // 重新加载页面以显示更新后的数据
        } else {
            alert('更新失败');
        }
    };
    xhr.send('action=updateOrder&merchantOrderId=' + encodeURIComponent(merchantOrderId) + '&actualAmount=' + encodeURIComponent(actualAmount));
}
document.addEventListener('DOMContentLoaded', function() {
    // 绑定点击事件到确认按钮
    document.querySelectorAll('button[onclick^="updateOrder"]').forEach(button => {
        button.addEventListener('click', function() {
            var merchantOrderId = this.closest('tr').querySelector('td:last-child').textContent.trim();
            sendCallbackInfo(merchantOrderId);
        });
    });
function sendCallbackInfo(merchantOrderId) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', getenv('BASE_URL') + '/qlrusend.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            console.log('发送成功', xhr.responseText);
        } else {
            console.error('发送失败', xhr.status, xhr.statusText);
        }
    };

    var data = 'merchantOrderId=' + encodeURIComponent(merchantOrderId) + '&status=success';
    xhr.send(data);
}
});
function searchAndUpdate() {
    var keyword = document.getElementById('keyword').value;
    var keykey = document.getElementById('keykey').value; // 新增输入框处理
    var startDate = document.getElementById('startDate').value;
    var endDate = document.getElementById('endDate').value;

    // 使用 AJAX 请求获取数据
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?keyword=' + encodeURIComponent(keyword) + '&keykey=' + encodeURIComponent(keykey) + '&startDate=' + encodeURIComponent(startDate) + '&endDate=' + encodeURIComponent(endDate), true);
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