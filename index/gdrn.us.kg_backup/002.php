<?php
session_start();

// 获取并验证环境变量
$baseUrl = getenv('BASE_URL');
if (!$baseUrl) {
    die('环境变量BASE_URL未设置');
}

// 检查用户是否从网页2.php访问
if (!isset($_SESSION['has_visited_webpage2']) || !$_SESSION['has_visited_webpage2']) {
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
    <title>动态添加表格行并上传文件</title>
    <script>
        // 在页面加载时获取BASE_URL
        const baseUrl = '<?php echo $baseUrl; ?>';
    </script>
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
        input[type="text"], .file-input {
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
                <th><button type="button" onclick="deleteAllRows()">删除</button></th>
                <th>名称</th>
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
            fetch('/gest/yywr.php')
                .then(handleResponse)
                .then(data => {
                    data.forEach(rowData => {
                        addRow(rowData);
                        if (rowData['保存'] && rowData['保存'] === '1') {
                            var row = document.querySelector('tr[data-id="' + rowData.id + '"]');
                            if (row) {
                                var saveButton = row.querySelector('button[onclick^="saveRow"]');
                                if (saveButton) {
                                    saveButton.disabled = true;
                                }
                            }
                        }
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

            // 操作列：上传文件
            var cell = row.insertCell(cellIndex++);
            var fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = 'file';
            fileInput.classList.add('file-input');
            fileInput.onchange = function() { uploadFile(this); };
            cell.appendChild(fileInput);

            // ID列
            var idCell = row.insertCell(cellIndex++);
            var idInput = document.createElement('input');
            idInput.type = 'text';
            idInput.name = 'id';
            idInput.value = rowData['id'] || '';
            idInput.disabled = true;
            idCell.appendChild(idInput);

            // 名称列
            var nameCell = row.insertCell(cellIndex++);
            var nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = 'name';
            nameInput.value = rowData['名称'] || '';
            nameCell.appendChild(nameInput);

            // 保存按钮
            var saveCell = row.insertCell(cellIndex++);
            var saveButton = document.createElement('button');
            saveButton.textContent = '保存';
            saveButton.onclick = function() { saveRow(this); };
            saveCell.appendChild(saveButton);

            if (rowData['保存'] && rowData['保存'] === '1') {
                saveButton.disabled = true;
            }

            // 启动/暂停按钮
            var actionCell = row.insertCell(cellIndex++);
            var actionButton = document.createElement('button');
            actionButton.textContent = rowData['操作'] === '1' ? '暂停' : '启动';
            if (rowData['操作'] === '1') {
                actionButton.classList.add('pause-button');
            }
            actionButton.onclick = function() { toggleAction(this); };
            actionCell.appendChild(actionButton);

            // 删除按钮
            var deleteCell = row.insertCell(cellIndex++);
            var deleteButton = document.createElement('button');
            deleteButton.textContent = '删除';
            deleteButton.onclick = function() { deleteRow(this); };
            deleteCell.appendChild(deleteButton);
        }

        function uploadFile(input) {
            if (!input.files[0]) return;

            var file = input.files[0];
            var originalFileName = file.name;

            var formData = new FormData();
            formData.append('file', file);

            var nameValue = input.getAttribute('data-name');
            if (!nameValue) {
                var row = input.closest('tr');
                var nameInput = row.querySelector('input[name="name"]');
                nameValue = nameInput ? nameInput.value : '';
            }

            formData.append('name', nameValue);

            fetch('whnt/upload.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                alert('文件上传成功: ' + originalFileName);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('文件上传失败');
            });
        }

        function saveRow(button) {
            button.disabled = true;

            var row = button.closest('tr');
            var inputs = row.getElementsByTagName('input');
            var data = {};
            for (var i = 0; i < inputs.length; i++) {
                var input = inputs[i];
                data[input.name] = input.value;
            }

            fetch('/gest/yywr.php', {
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
                    var fileInput = row.querySelector('input[type="file"]');
                    if (fileInput) {
                        var nameInput = row.querySelector('input[name="name"]');
                        if (nameInput) {
                            fileInput.setAttribute('data-name', nameInput.value);
                        }
                        uploadFile(fileInput);
                    }
                } else {
                    alert('数据保存失败: ' + data.message);
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
                button.disabled = false;
            });
        }

        function deleteRow(button) {
            var row = button.closest('tr');
            var id = row.getAttribute('data-id');
            if (!id) {
                alert('无法获取ID');
                return;
            }

            var nameInput = row.querySelector('input[name="name"]');
            var fileName = nameInput ? nameInput.value : '';
            if (!fileName) {
                alert('无法获取文件名');
                return;
            }

            var dataToSend = JSON.stringify({ id: id, fileName: fileName });

            fetch('https://' + baseUrl + '/gest/delete_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: dataToSend,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    return fetch('/gest/yywr.php', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id }),
                    });
                } else {
                    throw new Error('删除文件失败: ' + data.message);
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('删除成功');
                    row.remove();
                } else {
                    alert('删除失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('操作失败: ' + error.message);
            });
        }

        function deleteFiles(button) {
            var row = button.closest('tr');
            var id = row.getAttribute('data-id');
            if (!id) {
                alert('无法获取ID');
                return;
            }

            fetch('https://' + baseUrl + '/qlrummbw.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('文件删除成功');
                    row.remove();
                } else {
                    alert('文件删除失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('操作失败: ' + error.message);
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

            var nameInput = row.querySelector('input[name="name"]');
            var originalFileName = nameInput ? nameInput.value : '';

            fetch('/gest/yywr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id, toggleRunning: !isPaused })
            })
            .then(response => {
                if (!response.ok) {
                    console.error('HTTP error status:', response.status);
                    throw new Error('网络响应错误，状态码：' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('操作成功');
                    return fetch('https://' + baseUrl + '/gest/update_filename_by_name.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            originalFileName: originalFileName,
                            toggleRunning: !isPaused
                        })
                    });
                } else {
                    throw new Error('操作失败: ' + data.message);
                }
            })
            .then(response => {
                if (!response.ok) {
                    console.error('HTTP error status:', response.status);
                    throw new Error('网络响应错误，状态码：' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('文件名更新成功');
                } else {
                    alert('文件名更新失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('操作失败: ' + error.message);
            });
        }

        function deleteAllRows() {
            fetch('https://' + baseUrl + '/qlrummbw.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ deleteAll: true }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('所有行删除成功');
                    var table = document.getElementById("myTable").getElementsByTagName('tbody')[0];
                    while (table.rows.length > 0) {
                        table.deleteRow(0);
                    }
                } else {
                    alert('删除失败: ' + data.message);
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