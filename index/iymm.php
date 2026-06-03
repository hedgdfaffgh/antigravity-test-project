<?php
session_start();

// 检查用户是否从网页2.php访问
if (!isset($_SESSION['has_visited_webpage2']) || !$_SESSION['has_visited_webpage2']) {
    // 如果不是从网页2.php访问，重定向到主页或显示错误
    header('Location: ' . $baseUrl . '/index.html');  // 重定向到一个你选择的页面
    exit();
}

// 生成并存储唯一令牌
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

// 检查用户是否已经通过了验证码验证
if (!isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
    header('Location: ' . $baseUrl . '/qlrucaptcha.php');
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
        .pause-button {
            background-color: #007bff; /* 暂停按钮的背景色改为蓝色 */
            color: white; /* 设置文字颜色为白色 */
            border: none; /* 移除边框 */
            cursor: pointer; /* 添加指针光标 */
            transition: background-color 0.3s ease; /* 添加背景色过渡效果 */
            box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* 添加阴影 */
        }
        .pause-button:hover {
            background-color: #0056b3; /* 鼠标悬停时的背景色 */
        }
    </style>
</head>
<body>
    <table id="myTable">
        <thead>
            <tr>
                <th><button type="button" onclick="addRow()">增加</button></th>
                <th>用户名</th>
                <th>密码</th>
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
        var parentId = '<?php echo $parent_id; ?>'; // 从 PHP 获取 parent_id

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
        fetch('/gest/save_user.php?parent_id=' + parentId)
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

function addRow(rowData = {}) {
    var table = document.getElementById("myTable").getElementsByTagName('tbody')[0];
    var row = table.insertRow();
    row.setAttribute('data-id', rowData.id || '');

    var cellIndex = 0;

    var cell = row.insertCell(cellIndex++);
    var parentIdInput = document.createElement('input');
    parentIdInput.type = 'text';
    parentIdInput.name = 'parent_id';
    parentIdInput.value = rowData['parent_id'] || ''; // 显示parent_id的值
    cell.appendChild(parentIdInput);

    var usernameCell = row.insertCell(cellIndex++);
    var usernameInput = document.createElement('input');
    usernameInput.type = 'text';
    usernameInput.name = 'username';
    usernameInput.value = rowData['用户名'] || '';
    usernameCell.appendChild(usernameInput);

    var passwordCell = row.insertCell(cellIndex++);
    var passwordInput = document.createElement('input');
    passwordInput.type = 'text';
    passwordInput.name = 'password';
    passwordInput.value = rowData['密码'] || '';
    passwordCell.appendChild(passwordInput);

    var saveCell = row.insertCell(cellIndex++);
    var saveButton = document.createElement('button');
    saveButton.textContent = '保存';
    saveButton.onclick = function() { saveRow(this); };
    saveCell.appendChild(saveButton);

    if (rowData['保存'] === '1') {
        saveButton.disabled = true; // 禁用保存按钮
    }

    var actionCell = row.insertCell(cellIndex++);
    var actionButton = document.createElement('button');
    actionButton.textContent = rowData['操作'] == 1 ? '暂停' : '启动';
    if (rowData['操作'] == 1) {
        actionButton.classList.add('pause-button');
    }
    actionButton.onclick = function() { toggleAction(this); };
    actionCell.appendChild(actionButton);

    var deleteCell = row.insertCell(cellIndex++);
    var deleteButton = document.createElement('button');
    deleteButton.textContent = '删除';
    deleteButton.onclick = function() { deleteRow(this); };
    deleteCell.appendChild(deleteButton);
}

function saveRow(button) {
    var row = button.closest('tr');
    var inputs = row.getElementsByTagName('input');
    var data = {};
    for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        data[input.name] = input.value;
    }

    fetch('/gest/save_user.php', {
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
            row.setAttribute('data-id', data.id);
            button.disabled = true; // 保存成功后禁用按钮
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
    var id = row.getAttribute('data-id');
    if (!id) {
        alert('无法获取ID');
        return;
    }

    fetch('/gest/save_user.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            row.remove(); // 删除成功后移除行
        } else {
            alert('删除失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('发生错误: ' + error.message);
    });
}

function toggleAction(button) {
    var row = button.closest('tr');
    var id = row.getAttribute('data-id');
    if (!id) {
        alert('无法获取ID');
        return;
    }

    var isPaused = button.textContent === '暂停';
    button.textContent = isPaused ? '启动' : '暂停';
    button.classList.toggle('pause-button', !isPaused);

    fetch('/gest/save_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id, toggleRunning: !isPaused })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('网络响应错误，状态码：' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('操作成功');
        } else {
            throw new Error('操作失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('操作失败: ' + error.message);
    });
}
    </script>
</body>
</html>