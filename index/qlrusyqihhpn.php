<?php
session_start();

// 检查用户是否已登录并通过验证码验证
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || !isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
    // 如果未登录或未通过验证码验证，重定向到登录页面
    header('Location: index.html');
    exit();
}
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
            background-color: #ff4d4f;
            color: white;
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
                <th>次数</th>
                <th>号码</th>
                <th>金额</th>
                <th>已充</th>
                <th>保存</th>
                <th>操作</th>
                <th><button type="button" class="button" onclick="resetAllRows()">重置</button></th>
                <th>删除</th>
            </tr>
        </thead>
        <tbody>
            <!-- 表格行将在这里动态添加 -->
        </tbody>
    </table>

    <!-- 使用Flexbox布局 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
        <!-- 显示金额总和 -->
        <div id="totalAmount" style="font-weight: bold;">金额总和: 0</div>

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

        window.onload = function() {
            fetch('/gest/qlrusyqihhpn001.php')
                .then(handleResponse)
                .then(data => {
                    console.log(data);
                    allData = data;
                    updateTable();
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

            // 次数
            var timesCell = row.insertCell(cellIndex++);
            var timesInput = document.createElement('input');
            timesInput.type = 'text';
            timesInput.name = 'times';
            timesInput.value = rowData['次数'] || '';
            timesCell.appendChild(timesInput);

            // 号码
            var countCell = row.insertCell(cellIndex++);
            var countInput = document.createElement('input');
            countInput.type = 'text';
            countInput.name = 'count';
            countInput.value = rowData['号码'] || '';
            countCell.appendChild(countInput);

            // 金额
            var amountCell = row.insertCell(cellIndex++);
            var amountInput = document.createElement('input');
            amountInput.type = 'text';
            amountInput.name = 'amount';
            // 如果是新行（没有rowData数据）则设为0，否则使用数据库中的值
            amountInput.value = !rowData.custom_id ? '0' : (rowData['金额'] || '0');
            amountInput.disabled = true;
            amountInput.style.backgroundColor = '#f5f5f5';
            amountCell.appendChild(amountInput);

            // 已充
            var rechargedCell = row.insertCell(cellIndex++);
            var rechargedInput = document.createElement('input');
            rechargedInput.type = 'text';
            rechargedInput.name = 'recharged';
            // 如果是新行（没有rowData数据）则设为0，否则使用数据库中的值
            rechargedInput.value = !rowData.custom_id ? '0' : (rowData['已充'] || '0');
            rechargedInput.disabled = true;
            rechargedInput.style.backgroundColor = '#f5f5f5';
            rechargedCell.appendChild(rechargedInput);

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
            var operationValue = rowData['操作'] !== undefined ? rowData['操作'] : '0';
            pauseButton.innerText = operationValue === '1' ? '暂停' : '启动';
            if (operationValue === '1') {
                pauseButton.classList.add('paused');
            }
            pauseButton.onclick = function() { togglePause(this); };
            pauseCell.appendChild(pauseButton);

            // 清零按钮
            var resetCell = row.insertCell(cellIndex++);
            var resetButton = document.createElement('button');
            resetButton.className = 'button';
            resetButton.innerText = '清零';
            resetButton.onclick = function() { resetRow(this); };
            resetCell.appendChild(resetButton);

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
                name: row.querySelector('input[name="name"]').value,
                times: row.querySelector('input[name="times"]').value,
                count: row.querySelector('input[name="count"]').value,
                amount: row.querySelector('input[name="amount"]').value,
                recharged: row.querySelector('input[name="recharged"]').value,
                custom_id: row.getAttribute('data-id'),
                operation: row.querySelector('button').innerText === '暂停' ? '1' : '0',
                parent_id: <?php echo json_encode($_SESSION['parent_id'] ?? 0); ?>
            };

            fetch('/gest/qlrusyqihhpn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(response => response.json())
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

        function deleteRow(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id');

            fetch('/gest/qlrusyqihhpn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: 'delete' })
            })
            .then(response => response.json())
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

        function togglePause(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id');
            var action = button.innerText === '暂停' ? 'resume' : 'pause';
            var newButtonText = action === 'pause' ? '恢复' : '暂停';

            fetch('/gest/qlrusyqihhpn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: action })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新按钮状态
                    button.innerText = newButtonText;
                    if (newButtonText === '暂停') {
                        button.classList.add('paused');
                        row.querySelector('input[name="name"]').value = '';
                    } else {
                        button.classList.remove('paused');
                    }
                    // 重新获取数据并更新表格
                    fetch('/gest/qlrusyqihhpn001.php')
                        .then(handleResponse)
                        .then(newData => {
                            allData = newData;
                            updateTable();
                            alert('操作成功');
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('发生错误: ' + error.message);
                        });
                } else {
                    alert('操作失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
            });
        }

        function resetRow(button) {
            var row = button.closest('tr');
            var customId = row.getAttribute('data-id');

            fetch('/gest/qlrusyqihhpn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ custom_id: customId, action: 'reset_amount' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var amountInput = row.querySelector('input[name="amount"]');
                    if (amountInput) amountInput.value = '0';
                    alert('金额已清零');
                } else {
                    alert('清零失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
            });
        }

        function resetAllRows() {
            fetch('/gest/qlrusyqihhpn001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'reset', reset_all: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 重新获取数据
                    fetch('/gest/qlrusyqihhpn001.php')
                        .then(handleResponse)
                        .then(newData => {
                            allData = newData;
                            updateTable();
                            alert('所有数据已重置');
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('发生错误: ' + error.message);
                        });
                } else {
                    alert('重置失败: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function updateTable() {
            const rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
            const tableBody = document.getElementById("myTable").getElementsByTagName('tbody')[0];
            tableBody.innerHTML = ''; // 清空表格

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageData = allData.slice(start, end);

            let totalAmount = 0;
            pageData.forEach(rowData => {
                addRow(rowData);
                totalAmount += parseFloat(rowData['金额']) || 0;
            });

            document.getElementById('totalAmount').innerText = '金额总和: ' + totalAmount;
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
    </script>
</body>
</html> 