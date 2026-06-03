<?php
session_start();

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    die('环境变量BASE_URL未设置');
}

// 检查用户是否从网页2.php访问
if (!isset($_SESSION['has_visited_webpage2']) || !$_SESSION['has_visited_webpage2']) {
    // 如果不是从网页2.php访问，重定向到主页或显示错误
    header('Location: https://' . $baseUrl . '/index.php');
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
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>动态添加表格行并注册子账号</title>
    <style>
        table {
            width: 100%; /* 调整表格的宽度 */
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
            width: 150px; /* 调整单元格的宽度 */
        }
        th {
            background-color: #f2f2f2;
        }
        input[type="text"], input[type="password"] {
            width: 100%; /* 宽度调整为100%，以填满单元格 */
            box-sizing: border-box; /* 添加box-sizing */
            margin: 0; /* 移除外边距 */
            padding: 10px; /* 增加内边距 */
            border: 1px solid #ccc; /* 设置边框颜色 */
            outline: none; /* 移除轮廓 */
        }
    </style>
</head>
<body>
    <table id="myTable">
        <thead>
            <tr>
                <th><button type="button" onclick="addRow()">增加</button></th>
                <th>金额</th>
                <th>条数</th>
                <th>限时</th>
                <th>限删</th>
                <th>现条</th>
                <th>在读</th>
                <th>在存</th>
                <th>操作</th>
                <th>删除</th>
            </tr>
        </thead>
        <tbody>
            <!-- 表格行将在这里动态添加 -->
        </tbody>
    </table>

    <script>
        function handleResponse(response) {
            if (!response.ok) {
                throw new Error('网络响应不是OK状态');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('无法解析为JSON:', text);
                    if (text.includes('Parse error')) {
                        throw new Error('服务器端PHP脚本语法错误');
                    } else {
                        throw new Error('响应不是有效的JSON: ' + text);
                    }
                }
            });
        }

        window.onload = function() {
            fetch('/gest/qlruovrn.php')
                .then(handleResponse)
                .then(data => {
                    console.log(data); // 打印数据以检查
                    data.forEach(rowData => {
                        addRow(rowData);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('发生错误: ' + error.message);
                });
        };

        function generateUniqueId() {
            return 'id-' + Math.random().toString(36).substr(2, 16);
        }

        function addRow(rowData = {}) {
            var table = document.getElementById("myTable").getElementsByTagName('tbody')[0];
            var row = table.insertRow();
            var uniqueId = rowData.custom_id || generateUniqueId(); // 使用 custom_id 或生成唯一 ID
            row.setAttribute('data-id', uniqueId);

            var cellIndex = 0;

            // 名称
            var cell = row.insertCell(cellIndex++);
            var nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = 'name';
            nameInput.value = rowData['名称'] || '';
            cell.appendChild(nameInput);

            // 金额
            var amountCell = row.insertCell(cellIndex++);
            var amountInput = document.createElement('input');
            amountInput.type = 'text';
            amountInput.name = 'amount';
            amountInput.value = rowData['金额'] || '';
            amountCell.appendChild(amountInput);

            // 条数
            var countCell = row.insertCell(cellIndex++);
            var countInput = document.createElement('input');
            countInput.type = 'text';
            countInput.name = 'count';
            countInput.value = rowData['条数'] || '';
            countCell.appendChild(countInput);

            // 限时
            var limitCell = row.insertCell(cellIndex++);
            var limitInput = document.createElement('input');
            limitInput.type = 'text';
            limitInput.name = 'limit';
            limitInput.value = rowData['限时'] || '';
            limitCell.appendChild(limitInput);

            // 限删
            var deleteLimitCell = row.insertCell(cellIndex++);
            var deleteLimitInput = document.createElement('input');
            deleteLimitInput.type = 'text';
            deleteLimitInput.name = 'delete_limit';
            deleteLimitInput.value = rowData['限删'] || '';
            deleteLimitCell.appendChild(deleteLimitInput);

            // 现条
            var currentCell = row.insertCell(cellIndex++);
            var currentInput = document.createElement('input');
            currentInput.type = 'text';
            currentInput.name = 'current';
            currentInput.value = rowData['现条'] || '';
            currentInput.disabled = true; // 设置为禁用
            currentCell.appendChild(currentInput);

            // 在读
            var readingCell = row.insertCell(cellIndex++);
            var readingInput = document.createElement('input');
            readingInput.type = 'text';
            readingInput.name = 'reading';
            readingInput.value = rowData['在读'] || '';
            readingInput.disabled = true; // 设置为禁用
            readingCell.appendChild(readingInput);

            // 在存
            var saveCell = row.insertCell(cellIndex++);
            var saveInput = document.createElement('input');
            saveInput.type = 'text';
            saveInput.name = 'save';
            saveInput.value = rowData['在存'] || '';
            saveInput.disabled = true; // 设置为禁用
            saveCell.appendChild(saveInput);

            // 操作
            var actionCell = row.insertCell(cellIndex++);
            var saveButton = document.createElement('button');
            saveButton.textContent = '保存';
            saveButton.onclick = function() { saveRow(this); };
            actionCell.appendChild(saveButton);

            if (rowData['保存'] === '1') {
                saveButton.disabled = true;
            }

            // 删除
            var deleteCell = row.insertCell(cellIndex++);
            var deleteButton = document.createElement('button');
            deleteButton.textContent = '删除';
            deleteButton.onclick = function() { deleteRow(this); };
            deleteCell.appendChild(deleteButton);
        }

        function saveRow(button) {
            var row = button.closest('tr');
            var data = {
                name: row.querySelector('input[name="name"]').value,
                amount: row.querySelector('input[name="amount"]').value,
                count: row.querySelector('input[name="count"]').value,
                limit: row.querySelector('input[name="limit"]').value,
                delete_limit: row.querySelector('input[name="delete_limit"]').value,
                custom_id: row.getAttribute('data-id') // 确保custom_id被正确设置
            };

            fetch('/gest/qlruovrn.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(response => response.json())
            .then(data => {
                console.log(data); // 调试输出服务器返回的数据
                if (data.success) {
                    alert(data.message);
                    if (data.custom_id) {
                        row.setAttribute('data-id', data.custom_id); // 更新行的data-id属性
                    }
                    // 不禁用按钮，允许再次保存
                } else {
                    alert('数据保存失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
            });
        }

        function deleteRow(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id'); // 获取行的data-id属性

            fetch('/gest/qlruovrn.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: 'delete' }) // 发送删除请求
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove(); // 删除行
                } else {
                    alert('删除失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
            });
        }
    </script>
</body>
</html>