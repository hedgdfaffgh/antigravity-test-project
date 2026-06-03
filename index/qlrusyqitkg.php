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
        .paused {
            background-color: #ff4d4f; /* 红色背景 */
            color: white; /* 白色文字 */
        }
        #pagination button {
            cursor: pointer;
            border: 1px solid #d9d9d9;
            background: white;
            border-radius: 2px;
        }
        #pagination button:hover {
            border-color: #1890ff;
            color: #1890ff;
        }
        #rowsPerPage {
            padding: 3px;
            border: 1px solid #d9d9d9;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <table id="myTable">
        <thead>
            <tr>
                <th><button type="button" class="button" onclick="addRow()">增加</button></th>
                <th>号码</th>
                <th>密码</th>
                <th>状态</th>
                <th>UID</th>
                <th>保存</th>
                <th>操作</th>
                <th>删除</th>
            </tr>
        </thead>
        <tbody>
            <!-- 表格行将在这里动态添加 -->
        </tbody>
    </table>

    <!-- 使用Flexbox布局 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
        <!-- 页码选择器 -->
        <div id="pagination" style="display: flex; justify-content: center;"></div>

        <!-- 选择显示行数 -->
        <div>
            显示页数:
            <select id="rowsPerPage" onchange="updateTable()">
                <option value="15">15</option>
                <option value="30">30</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

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

        let allData = []; // 存储所有数据
        let currentPage = 1;

        function updateTable() {
            const rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
            const tableBody = document.getElementById("myTable").getElementsByTagName('tbody')[0];
            tableBody.innerHTML = ''; // 清空表格

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageData = allData.slice(start, end);

            pageData.forEach(rowData => {
                addRow(rowData);
            });

            updatePagination();
        }

        function updatePagination() {
            const rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
            const totalPages = Math.ceil(allData.length / rowsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            // 添加"当前页码/总页数"的显示
            const pageInfo = document.createElement('span');
            pageInfo.style.margin = '0 10px';
            pageInfo.style.fontSize = '14px';
            pageInfo.innerText = `第 ${currentPage} / ${totalPages} 页`;
            pagination.appendChild(pageInfo);

            // 添加分页按钮
            for (let i = 1; i <= totalPages; i++) {
                const pageButton = document.createElement('button');
                pageButton.innerText = i;
                pageButton.style.margin = '0 2px';
                pageButton.style.padding = '3px 8px';
                pageButton.onclick = function() {
                    currentPage = i;
                    updateTable();
                };
                if (i === currentPage) {
                    pageButton.style.backgroundColor = '#1890ff';
                    pageButton.style.color = 'white';
                    pageButton.style.border = 'none';
                }
                pagination.appendChild(pageButton);
            }
        }

        window.onload = function() {
            fetch('/gest/qlrusyqitkg001.php')
                .then(handleResponse)
                .then(data => {
                    console.log(data);
                    allData = data; // 保存所有数据
                    updateTable(); // 更新表格显示
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

            // 号码
            var countCell = row.insertCell(cellIndex++);
            var countInput = document.createElement('input');
            countInput.type = 'text';
            countInput.name = 'count';
            countInput.value = rowData['号码'] || '';
            countCell.appendChild(countInput);

            // 密码
            var passwordCell = row.insertCell(cellIndex++);
            var passwordInput = document.createElement('input');
            passwordInput.type = 'text';
            passwordInput.name = 'password';
            passwordInput.value = rowData['密码'] || '';
            passwordCell.appendChild(passwordInput);

            // 状态
            var statusCell = row.insertCell(cellIndex++);
            var statusValue = rowData['状态'] || '';
            statusCell.innerText = statusValue === '1' ? '暂停中' : statusValue;

            // UID
            var uidCell = row.insertCell(cellIndex++);
            var uidInput = document.createElement('input');
            uidInput.type = 'text';
            uidInput.name = 'uid';
            uidInput.value = rowData['UID'] || '';
            uidCell.appendChild(uidInput);

            // 保存按钮
            var saveCell = row.insertCell(cellIndex++);
            var saveButton = document.createElement('button');
            saveButton.className = 'button';
            saveButton.innerText = '保存';
            saveButton.onclick = function() { saveRow(this); };
            saveCell.appendChild(saveButton);

            // 操作按钮
            var pauseCell = row.insertCell(cellIndex++);
            var pauseButton = document.createElement('button');
            pauseButton.className = 'button';
            var operationValue = rowData['操作'] !== undefined ? rowData['操作'] : '0'; // 默认值为 '0'
            pauseButton.innerText = operationValue === '1' ? '暂停' : '启动';
            if (operationValue === '1') {
                pauseButton.classList.add('paused');
            }
            pauseButton.onclick = function() { togglePauseRow(this); };
            pauseCell.appendChild(pauseButton);

            // 删除按钮
            var deleteCell = row.insertCell(cellIndex++);
            var deleteButton = document.createElement('button');
            deleteButton.className = 'button';
            deleteButton.innerText = '删除';
            deleteButton.onclick = function() { deleteRow(this); };
            deleteCell.appendChild(deleteButton);
        }

        function saveRow(button) {
            var row = button.closest('tr');
            var data = {
                name: row.querySelector('input[name="name"]').value,      // 名称
                count: row.querySelector('input[name="count"]').value,    // 号码
                password: row.querySelector('input[name="password"]').value, // 密码
                uid: row.querySelector('input[name="uid"]').value,        // UID
                custom_id: row.getAttribute('data-id'),                   // custom_id
                operation: row.querySelector('button').innerText === '暂停' ? '1' : '0' // 操作
            };

            fetch('/gest/qlrusyqitkg001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    if (data.custom_id) {
                        row.setAttribute('data-id', data.custom_id);
                    }
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
            var customId = row.getAttribute('data-id');

            fetch('/gest/qlrusyqitkg001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    action: 'delete',
                    custom_id: customId 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove(); // 从表格中移除该行
                    alert(data.message);
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
            var nameInput = row.querySelector('input[name="name"]');
            var currentName = nameInput.value;
            var action = button.innerText === '启动' ? 'pause' : 'resume';

            fetch('/gest/qlrusyqitkg001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    custom_id: customId, 
                    action: action,
                    name: currentName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (action === 'pause') {
                        button.innerText = '暂停';
                        button.classList.add('paused');
                        nameInput.value = ''; // 清空名称
                        row.querySelector('td:nth-child(4)').innerText = '暂停中'; // 更新状态列
                    } else {
                        button.innerText = '启动';
                        button.classList.remove('paused');
                        nameInput.value = data.original_name; // 恢复原始名称
                        row.querySelector('td:nth-child(4)').innerText = ''; // 清空状态列
                    }
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