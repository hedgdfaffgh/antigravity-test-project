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
// 设置 $mtkg，如果 $_SESSION['parent_id'] 不存在或为空，则默认为 '99'
$mtkg = $_SESSION['parent_id'] ?? '99';

// 检查 $mtkg 是否为默认值 '99'，如果是，则停止页面加载
if ($mtkg === '99') {
    die('错误：没有设置有效的账号。');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>动态添加表格行</title>
<style>
    table {
        border-collapse: collapse;
        width: 100%;
        background-color: #f9f9f9; /* 调整背景颜色为更柔和的色调 */
        box-shadow: 0 4px 8px rgba(0,0,0,0.05); /* 增加阴影的模糊度 */
        border-radius: 10px; /* 增加边框圆角的大小 */
        overflow: hidden; /* 保持溢出隐藏 */
        margin-top: 20px; /* 增加表格顶部的间距 */
        margin-bottom: 20px; /* 增加表格底部的间距 */
    }
    td, th {
        border: 1px solid #d3d3d3; /* 调整边框颜色为更柔和的灰色 */
        padding: 12px 15px; /* 增加内边距，使得内容更为宽敞 */
        text-align: center; /* 水平居中 */
        vertical-align: middle; /* 垂直居中 */
        font-size: 14px; /* 调整字体大小 */
    }
    th {
        background-color: #e8e8e8; /* 调整表头背景颜色为更淡的灰色 */
        color: #333; /* 调整表头字体颜色 */
        font-weight: bold; /* 字体加粗 */
    }
    input[type="text"] {
        width: 100%; /* 调整输入框宽度为100%，以填满单元格 */
        box-sizing: border-box; /* 保持盒模型 */
        margin: 4px 0; /* 调整外边距，增加上下间距 */
        padding: 8px; /* 增加内边距，使得输入框更易点击 */
        border-radius: 4px; /* 调整边框圆角 */
        border: 1px solid #d3d3d3; /* 调整边框颜色 */
        outline: none; /* 移除轮廓线 */
        transition: border-color 0.3s; /* 添加边框颜色过渡效果 */
        text-align: center; /* 输入框内的文本居中 */
    }
    input[type="text"]:focus {
        border-color: #007bff; /* 输入框聚焦时边框颜色变化 */
    }
    input[name="amount"] {
    box-sizing: border-box; /* 确保宽度包含padding和border */
}
    .new-column-button {
        background-color: #f0f0f0; /* 新列按钮背景色 */
        color: #333; /* 新列按钮字体颜色 */
        border: 1px solid #d3d3d3; /* 新列按钮边框颜色 */
        border-radius: 4px; /* 新列按钮边框圆角 */
        transition: background-color 0.3s; /* 新列按钮背景颜色过渡效果 */
    }
    .new-column-button:hover {
        background-color: #e0e0e0; /* 新列按钮悬停背景色 */
    }
    .pause-button {
        background-color: #ff4d4f; /* 暂停按钮的背景色调整为更鲜艳的红色 */
        color: white; /* 暂停按钮的字体颜色为白色 */
        border: none; /* 移除暂停按钮边框 */
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
    .pagination a {
        padding: 8px 16px;
margin: 0 4px;
border: 1px solid #ddd;
text-decoration: none;
color: #333;
display: block; /* 使链接表现为块级元素 */
border-radius: 5px; /* 新增边框圆角 */
transition: background-color 0.3s ease; /* 新增背景颜色过渡效果 */
}
.pagination a.active {
    background-color: #007bff;
    color: white;
    border: 1px solid #007bff;
}
.pagination a:hover:not(.active) {
    background-color: #ddd;
}
.id-column {
    display: none;
}
tr:nth-child(even) {
    background-color: #f2f2f2; /* 为偶数行添加条纹背景 */
}
tr:hover {
    background-color: #e8e8e8; /* 鼠标悬停时行的背景颜色 */
}
/* 调整增加、操作、清零、删除列与金额列、时间列、人数限制列、总限列的宽度 */
td:nth-child(1), th:nth-child(1), /* 增加列 */
td:nth-child(10), th:nth-child(10), /* 操作列 */
td:nth-child(11), th:nth-child(11), /* 清零列 */
td:nth-child(12), th:nth-child(12), /* 删除列 */
td:nth-child(3), th:nth-child(3), /* 金额列 */
td:nth-child(6), th:nth-child(6), /* 时间列 */
td:nth-child(7), th:nth-child(7), /* 人数限制列 */
td:nth-child(8), th:nth-child(8) { /* 总限列 */
    width: 50px; /* 统一设置这些列的宽度 */
}

/* 调整名称列、地区列、号码列的宽度 */
td:nth-child(2), th:nth-child(2), /* 名称列 */
td:nth-child(4), th:nth-child(4), /* 地区列 */
td:nth-child(5), th:nth-child(5) { /* 号码列 */
    width: 150px; /* 统一设置这些列的宽度 */
}
</style>
</head>
<body>
    <table id="myTable">
        <thead>
            <tr>
                <th><button type="button" onclick="addRow()">增加</button></th>
                <th>名称</th>
                <th>金额</th>
                <th>地区</th>
                <th>号码</th>
                <th>时间</th>
                <th>人数限制</th>
                <th>总限</th> 
                <th>保存</th>
                <th>操作</th>
                <th>清零</th>
                <th>删除</th>
            </tr>
        </thead>
        <tbody>
            <!-- 表格行将在这里动态添加 -->
        </tbody>
    </table>

    <script>
var mtkg = '<?php echo isset($mtkg) ? $mtkg : "0"; ?>'; // 从 PHP 获取 mtkg，确保 $mtkg 已定义

window.onload = function() {
    var url = '/gest/yfbc.php';
    var mtkg = '<?php echo $mtkg; ?>'; // 确保 mtkg 变量在这个作用域中可用

    if (mtkg !== '0') {
        url += '?mtkg=' + mtkg;
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
            data.forEach(rowData => {
                addRow(rowData);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('发生错误: ' + error.message);
        });
};
function toggleAction(button) {
    var row = button.closest('tr');
    var id = row.getAttribute('data-id');
    var name = row.querySelector('input[name="name"]').value;
    var mtkg = '<?php echo $mtkg; ?>'; // 从 PHP 获取 mtkg

    var currentState = button.textContent === '启动' ? '0' : '1';
    var newState = currentState === '0' ? '1' : '0';

    // 准备发送的数据
    var dataToSend = JSON.stringify({
        id: id,
        name: name,
        mtkg: mtkg,
        button: newState
    });

    // 发送请求到 update_status.php
    fetch('https://' + baseUrl + '/whnt/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: dataToSend
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            // 如果服务器处理失败，根据之前的状态恢复按钮
            toggleButtonState(button, currentState);
            throw new Error('操作失败: ' + data.message);
        }
        // 根据服务器的最终状态更新按钮（如果需要）
        toggleButtonState(button, newState);

        // 同时发送请求到 gest/yfbc.php
        return fetch('gest/yfbc.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: dataToSend
        });
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error('操作失败: ' + data.message);
        }
        console.log('状态更新成功:', data);
    })
    .catch(error => {
        console.error('Error:', error);
        // 如果请求失败，根据之前的状态恢复按钮
        toggleButtonState(button, currentState);
        alert('发生错误: ' + error.message);
    });
}

function toggleButtonState(button, state) {
    button.textContent = state === '1' ? '暂停' : '启动';
    if (state === '1') {
        button.classList.add('pause-button');
    } else {
        button.classList.remove('pause-button');
    }
}
function clearAmount(button) {
    var row = button.closest('tr');
    var id = row.getAttribute('data-id'); // 从data-id属性获取ID

    if (!id) {
        alert('无法获取ID');
        return;
    }

    // 发送数据到服务器以将usvf_sorted表中amount列的值设置为0
    fetch('/gest/igfw.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id }), // 在请求体中发送ID
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('金额已清零');
            // 可选：更新前端显示的金额值为0
            var amountInput = row.querySelector('input[name="amount"]');
            if (amountInput) {
                amountInput.value = '0';
            }
        } else {
            throw new Error('清零失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('清零失败: ' + error.message);
    });
}
function addRow(rowData = {}, includeNewColumnButton = true) {
    var table = document.getElementById("myTable").getElementsByTagName('tbody')[0];
    var row = table.insertRow();
    
    // 静态变量用于存储父ID对应的子ID数量
    if (typeof addRow.counter === 'undefined') {
        addRow.counter = {}; // 初始化为一个空对象
    }

    var cellIndex = 0;

    // 新列按钮或空单元格
    var cell = row.insertCell(cellIndex++);
    if ((rowData.parent_id === null || typeof rowData.parent_id === 'undefined') && includeNewColumnButton) {
        var newColumnButton = document.createElement('button');
        newColumnButton.textContent = '新列';
        newColumnButton.classList.add('new-column-button');
        newColumnButton.onclick = function() {
            var parentId = rowData.id || '308'; // 如果没有提供ID，使用默认的父ID '308'
            addRow.counter[parentId] = (addRow.counter[parentId] || 0) + 1;
            var newId = parentId + '-' + addRow.counter[parentId];
            addRow({ id: newId, parent_id: parentId }, false);
        };
        cell.appendChild(newColumnButton);
    } else {
        cell.innerHTML = '&nbsp;';
    }

    row.setAttribute('data-id', rowData.id || '');

    // 如果存在parent_id，则将该行作为子行添加
    if (rowData.parent_id) {
        var parentRow = document.querySelector('tr[data-id="' + rowData.parent_id + '"]');
        if (parentRow) {
            var siblingRows = document.querySelectorAll('tr[data-parent-id="' + rowData.parent_id + '"]');
            if (siblingRows.length > 0) {
                var lastSiblingRow = siblingRows[siblingRows.length - 1];
                lastSiblingRow.parentNode.insertBefore(row, lastSiblingRow.nextSibling);
            } else {
                parentRow.parentNode.insertBefore(row, parentRow.nextSibling);
            }
        }
    }

    // 其余列：文本输入
    var fields = ['name', 'amount', 'region', 'number', 'time', 'limit', 'zong'];
    fields.forEach(function(field) {
        var cell = row.insertCell(cellIndex++);
        var input = document.createElement('input');
        input.type = 'text';
        input.name = field;
        input.value = (field === 'amount' && Object.keys(rowData).length === 0) ? '0' : (rowData[field] || '');
        cell.appendChild(input);
    });

    // 隐藏的ID输入框
    var hiddenIdInput = document.createElement('input');
    hiddenIdInput.type = 'hidden';
    hiddenIdInput.name = 'id';
    hiddenIdInput.value = rowData.id || '';
    row.appendChild(hiddenIdInput);

    // 保存按钮
    var saveCell = row.insertCell(cellIndex++);
    var saveButton = document.createElement('button');
    saveButton.textContent = '保存';
    saveButton.onclick = function() { saveRow(this); };
    saveCell.appendChild(saveButton);

    // 如果rowData包含disabled字段，并且其值为'1'或1，则禁用保存按钮
    if (rowData.disabled === '1' || rowData.disabled === 1) {
        saveButton.disabled = true;
    }
    // 启动/暂停按钮
    var actionCell = row.insertCell(cellIndex++);
    var actionButton = document.createElement('button');
    actionButton.textContent = rowData.button === 1 ? '暂停' : '启动';
    if (rowData.button === 1) {
        actionButton.classList.add('pause-button'); // 确保根据 button 的值添加 pause-button 类
    }
    actionButton.onclick = function() {
        toggleAction(this, rowData.id);
    };
    actionCell.appendChild(actionButton);

    // 清零按钮
    var clearCell = row.insertCell(cellIndex++);
    var clearButton = document.createElement('button');
    clearButton.textContent = '清零';
    clearButton.onclick = function() {
        clearAmount(this);
    };
    clearCell.appendChild(clearButton);

    // 删除按钮
    var deleteCell = row.insertCell(cellIndex++);
    var deleteButton = document.createElement('button');
    deleteButton.textContent = '删除';
    deleteButton.onclick = function() { deleteRow(this); };
    deleteCell.appendChild(deleteButton);
}
function saveRow(button) {
    button.disabled = true; // 立即禁用保存按钮

    var row = button.closest('tr');
    var inputs = row.getElementsByTagName('input');
    var data = {};
    for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        if (['id', 'name', 'amount', 'region', 'number', 'time', 'limit', 'zong'].includes(input.name)) {
            if (input.name === 'id' && input.value.includes('-')) {
                var parts = input.value.split('-');
                data['parent_id'] = parts[0];
            } else {
                data[input.name] = input.value.trim();
            }
        }
    }
    // 确保 mtkg 变量在这个作用域中可用
    var mtkg = '<?php echo $mtkg; ?>'; // 从 PHP 获取 mtkg
    data['mtkg'] = mtkg; // 添加 mtkg 到发送的数据中
    fetch('/gest/yfbc.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('网络响应不正常: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.error) {
            throw new Error('数据保存失败: ' + data.error);
        }
        if (data.success) {
            alert(data.message); // 显示记录插入成功的消息
            if (data.id) {
                row.setAttribute('data-id', data.id);
            }
            // 发送number字段到my_script.php
            var numberValue = row.querySelector('input[name="number"]').value;
            fetch('/my_script.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ number: numberValue }),
            })
            .then(response => response.json())
            .then(data => {
                // 可以在这里处理my_script.php的响应
                console.log('Number sent successfully', data);
            })
            .catch(error => {
                console.error('Error sending number:', error);
            });
        } else {
            throw new Error('数据保存失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(error => {
        alert('保存过程中出现错误: ' + error.message);
        button.disabled = false; // 出现错误时重新启用按钮
    });
}
function deleteRow(button) {
    var row = button.closest('tr');
    var id = row.getAttribute('data-id'); // 从data-id属性获取ID

    if (!id) {
        alert('无法获取ID');
        return;
    }

    // 发送数据到服务器以删除数据库记录
    fetch('/gest/yfbc.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id }), // 在请求体中发送ID
    })
    .then(response => response.json()) // 使用 .json() 而不是 .text()
    .then(data => {
        // 假设第一个请求总是成功的，所以不检查success字段
        alert(data.message); // 显示服务器的响应

        // 删除JSON文件
        fetch('/my_script.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ delete: 'true', id: id }), // 发送删除JSON文件的请求
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert(data.message); // 使用后端返回的message字段显示消息
            } else {
                alert('删除文件失败: ' + data.message); // 使用后端返回的message字段显示错误消息
            }
            row.remove(); // 从表格中删除该行
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除文件失败: ' + error);
        });
    })
    .catch(error => {
        console.error('Error:', error);
        alert('删除失败: ' + error);
    });
}
</script>
</body>
</html>