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
        /* 添加暂停状态的样式 */
        .paused {
            background-color: #ff4d4f;
            color: white;
        }
    </style>
</head>
<body>
    <table id="myTable">
        <thead>
            <tr>
                <th><button type="button" onclick="addRow()">增加</button></th>
                <th>金额</th>
                <th>网址</th>
                <th>保存</th>
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
            fetch('/gest/qlruovrn001.php')
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
            var uniqueId = rowData.custom_id || generateUniqueId();
            row.setAttribute('data-id', uniqueId);

            var cellIndex = 0;

            // 名称输入框
            var nameCell = row.insertCell(cellIndex++);
            var nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = 'name';
            nameInput.value = rowData['名称'] || '';
            nameCell.appendChild(nameInput);

            // 金额输入框
            var amountCell = row.insertCell(cellIndex++);
            var amountInput = document.createElement('input');
            amountInput.type = 'text';
            amountInput.name = 'amount';
            amountInput.value = rowData['金额'] || '';
            amountCell.appendChild(amountInput);

            // 网址输入框
            var urlCell = row.insertCell(cellIndex++);
            var urlInput = document.createElement('input');
            urlInput.type = 'text';
            urlInput.name = 'count';
            urlInput.value = rowData['网址'] || '';
            urlCell.appendChild(urlInput);

            // 保存按钮
            var saveCell = row.insertCell(cellIndex++);
            var saveButton = document.createElement('button');
            saveButton.textContent = '保存';
            saveButton.onclick = function() { saveRow(this); };
            saveCell.appendChild(saveButton);

            // 操作按钮（启动/暂停）
            var operationCell = row.insertCell(cellIndex++);
            var operationButton = document.createElement('button');
            operationButton.textContent = rowData['操作'] === '1' ? '暂停' : '启动';
            if (rowData['操作'] === '1') {
                operationButton.classList.add('paused');
            }
            operationButton.onclick = function() { togglePauseRow(this); };
            operationCell.appendChild(operationButton);

            // 删除按钮
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
                custom_id: row.getAttribute('data-id') // 确保custom_id被正确设置
            };

            fetch('/gest/qlruovrn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(handleResponse)
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

            fetch('/gest/qlruovrn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: 'delete' }) // 发送删除请求
            })
            .then(handleResponse)
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

        function togglePauseRow(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id');
            var action = button.innerText === '启动' ? 'pause' : 'resume';
            var newButtonText = button.innerText === '启动' ? '暂停' : '启动';

            fetch('/gest/qlruovrn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: action })
            })
            .then(handleResponse)
            .then(data => {
                if (data.success) {
                    button.innerText = newButtonText;
                    if (newButtonText === '暂停') {
                        button.classList.add('paused');
                        row.querySelector('input[name="name"]').value = '';
                    } else {
                        button.classList.remove('paused');
                    }
                    alert('操作成功');
                } else {
                    alert('操作失败: ' + data.message);
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