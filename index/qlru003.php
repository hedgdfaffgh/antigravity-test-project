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
            width: 150px;
        }
        th {
            background-color: #f2f2f2;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            margin: 0;
            padding: 10px;
            border: 1px solid #ccc;
            outline: none;
        }
        .paused {
            background-color: red;
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
                <th>名字</th>
                <th>判名</th> 
                <th>金限</th>
                <th>顺序</th>
                <th>实金</th>
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
            fetch('/gest/qlruovrn003.php')
                .then(handleResponse)
                .then(data => {
                    console.log(data);
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

            // 名字
            var nicknameCell = row.insertCell(cellIndex++);
            var nicknameInput = document.createElement('input');
            nicknameInput.type = 'text';
            nicknameInput.name = 'nickname';
            nicknameInput.value = rowData['名字'] || '';
            nicknameCell.appendChild(nicknameInput);

            // 判名
            var judgeNameCell = row.insertCell(cellIndex++);
            var judgeNameInput = document.createElement('input');
            judgeNameInput.type = 'text';
            judgeNameInput.name = 'judgeName';
            judgeNameInput.value = rowData['判名'] || '';
            judgeNameCell.appendChild(judgeNameInput);

            // 金限
            var rangeCell = row.insertCell(cellIndex++);
            var rangeInput = document.createElement('input');
            rangeInput.type = 'text';
            rangeInput.name = 'range';
            rangeInput.value = rowData['金限'] || '';
            rangeCell.appendChild(rangeInput);

            // 顺序
            var orderCell = row.insertCell(cellIndex++);
            var orderInput = document.createElement('input');
            orderInput.type = 'text';
            orderInput.name = 'order';
            orderInput.value = rowData['顺序'] || '';
            orderCell.appendChild(orderInput);

            // 实金
            var realGoldCell = row.insertCell(cellIndex++);
            var realGoldInput = document.createElement('input');
            realGoldInput.type = 'text';
            realGoldInput.name = 'realGold';
            realGoldInput.value = rowData['实金'] || '';
            realGoldCell.appendChild(realGoldInput);

            // 保存
            var saveCell = row.insertCell(cellIndex++);
            var saveButton = document.createElement('button');
            saveButton.textContent = '保存';
            saveButton.onclick = function() { saveRow(this); };
            saveCell.appendChild(saveButton);

            if (rowData['保存'] === '1') {
                saveButton.disabled = true;
            }

            // 操作按钮
            var actionCell = row.insertCell(cellIndex++);
            var actionButton = document.createElement('button');
            actionButton.className = 'button';
            var operationValue = rowData['操作'] !== undefined ? rowData['操作'] : '0';
            actionButton.innerText = operationValue === '1' ? '暂停' : '启动';
            if (operationValue === '1') {
                actionButton.classList.add('paused');
            }
            actionButton.onclick = function() { togglePauseRow(this); };
            actionCell.appendChild(actionButton);

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
                nickname: row.querySelector('input[name="nickname"]').value,
                judgeName: row.querySelector('input[name="judgeName"]').value,
                range: row.querySelector('input[name="range"]').value,
                order: row.querySelector('input[name="order"]').value,
                realGold: row.querySelector('input[name="realGold"]').value, // 保存实金字段
                custom_id: row.getAttribute('data-id'),
                original_name: row.querySelector('input[name="name"]').value,
                operation: row.querySelector('button.button').innerText === '暂停' ? '1' : '0'
            };

            fetch('/gest/qlruovrn003.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(handleResponse)
            .then(data => {
                console.log(data);
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

        function togglePauseRow(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id');
            var action = button.innerText === '启动' ? 'pause' : 'resume';
            var newButtonText = button.innerText === '启动' ? '暂停' : '启动';
            var nameInput = row.querySelector('input[name="name"]');
            var originalName = nameInput.value; // 保存原始名称值

            fetch('/gest/qlruovrn003.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    custom_id: customId, 
                    action: action,
                    name: originalName // 传递当前名称值
                })
            })
            .then(handleResponse)
            .then(data => {
                if (data.success) {
                    button.innerText = newButtonText;
                    if (newButtonText === '暂停') {
                        button.classList.add('paused');
                        // 保存当前名称值到original_name并清空名称
                        nameInput.setAttribute('data-original-name', originalName);
                        nameInput.value = '';
                    } else {
                        button.classList.remove('paused');
                        // 恢复original_name的值到名称
                        nameInput.value = nameInput.getAttribute('data-original-name') || '';
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

        function deleteRow(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id');

            fetch('/gest/qlruovrn003.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: 'delete' })
            })
            .then(handleResponse)
            .then(data => {
                if (data.success) {
                    row.remove();
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