<?php
session_start();

// 检查用户是否从网页2.php访问
if (!isset($_SESSION['has_visited_webpage2']) || !$_SESSION['has_visited_webpage2']) {
    // 如果不是从网页2.php访问，重定向到主页或显示错误
    header('Location: ' . getenv('BASE_URL') . '/index.php');
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
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>动态添加表格行并注册子账号</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        .search-table {
            width: 100%;
            margin-bottom: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
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
    </style>
</head>
<body>
    <!-- 搜索表单 -->
    <table class="search-table">
        <tr class="search-row">
            <td colspan="2"><input type="text" id="keyword" placeholder="输入关键字符"></td>
            <td colspan="2"><input type="text" id="keykey" placeholder="输入关键字符"></td>
            <td colspan="2"><input type="text" class="datepicker" id="startDate" placeholder="开始"></td>
            <td colspan="2"><input type="text" class="datepicker" id="endDate" placeholder="结束"></td>
            <td><input type="button" value="查找" class="search-button" onclick="searchOrders()"></td>
            <td><input type="button" value="删除" class="search-button" onclick="deleteSelectedRows()"></td>
            <td><input type="button" value="导出" class="search-button" onclick="exportData()"></td>
        </tr>
    </table>

    <!-- 现有表格 -->
    <table id="myTable">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                <th>名称</th>
                <th>卡号</th>
                <th>卡密</th>
                <th>状态</th>
                <th>金额</th>
                <th>提取时间</th>
                <th>账号</th>
            </tr>
        </thead>
        <tbody>
            <!-- 数据将通过JavaScript动态加载 -->
        </tbody>
    </table>

    <!-- 显示金额总和和分页 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
        <div id="totalAmount" style="font-weight: bold;">金额总和: 0</div>
        <div id="pagination" style="display: flex; justify-content: center;"></div>
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

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/zh.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('.datepicker', {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: "zh"
            });
        });

        let allData = []; // 存储所有数据
        let currentPage = 1;

        function handleResponse(response) {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Response error:', text);
                    throw new Error('网络响应不是OK状态');
                });
            }
            return response.json();
        }

        function addRow(rowData) {
            const tableBody = document.getElementById("myTable").getElementsByTagName('tbody')[0];
            const row = tableBody.insertRow();
            row.setAttribute('data-id', rowData.id);

            // 创建单元格并填充数据
            row.insertCell(0).innerHTML = `<input type='checkbox' class='rowCheckbox'>`;
            row.insertCell(1).innerHTML = `<input type='text' name='name' value='${rowData.名称 || ''}'>`;
            row.insertCell(2).innerHTML = `<input type='text' name='card_number' value='${rowData.卡号 || ''}'>`;
            row.insertCell(3).innerHTML = `<input type='text' name='card_secret' value='${rowData.卡密 || ''}'>`;
            row.insertCell(4).innerHTML = `<input type='text' name='status' value='${rowData.状态 || ''}'>`;
            row.insertCell(5).innerHTML = `<input type='text' name='amount' value='${rowData.金额 || ''}'>`;
            row.insertCell(6).innerHTML = `<input type='text' name='extraction_time' value='${rowData.提取时间 || ''}'>`;
            row.insertCell(7).innerHTML = `<input type='text' name='account' value='${rowData.账号 || ''}'>`;
        }

        function deleteSelectedRows() {
            var selectedRows = document.querySelectorAll('.rowCheckbox:checked');
            if (selectedRows.length === 0) {
                alert('请选择要删除的行');
                return;
            }

            var idsToDelete = [];
            selectedRows.forEach(rowCheckbox => {
                var row = rowCheckbox.closest('tr');
                var customId = row.getAttribute('data-id');
                if (customId) { // 确保 data-id 存在
                    idsToDelete.push(customId);
                }
            });

            if (idsToDelete.length === 0) {
                alert('没有可删除的行');
                return;
            }

            fetch('gest/qlrurjbc001.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: idsToDelete, action: 'delete' })
            })
            .then(handleResponse)
            .then(data => {
                if (data.success) {
                    selectedRows.forEach(rowCheckbox => {
                        var row = rowCheckbox.closest('tr');
                        row.remove();
                    });
                } else {
                    alert('删除失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
            });
        }

        function searchOrders() {
            var keyword = document.getElementById('keyword').value;
            var keykey = document.getElementById('keykey').value;
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;

            var queryParams = new URLSearchParams({
                keyword: keyword,
                keykey: keykey,
                startDate: startDate,
                endDate: endDate
            });

            fetch('gest/qlrurjbc001.php?' + queryParams.toString(), {
                method: 'GET'
            })
            .then(handleResponse)
            .then(data => {
                if (data.success) {
                    var tbody = document.querySelector('#myTable tbody');
                    tbody.innerHTML = '';
                    data.data.forEach(row => {
                        addRow(row);
                    });
                } else {
                    alert('查询失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误: ' + error.message);
            });
        }

        function toggleSelectAll(checkbox) {
            var checkboxes = document.querySelectorAll('.rowCheckbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function exportData() {
            var selectedRows = document.querySelectorAll('.rowCheckbox:checked');
            if (selectedRows.length === 0) {
                alert('请选择要导出的行');
                return;
            }

            var csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "名称,卡号,卡密,状态,金额,提取时间,账号\n"; // CSV header

            let totalAmount = 0;
            let totalSuccess = 0;

            selectedRows.forEach(rowCheckbox => {
                var row = rowCheckbox.closest('tr');
                var amount = parseFloat(row.querySelector('input[name="amount"]').value);
                var status = row.querySelector('input[name="status"]').value;

                totalAmount += amount;
                if (status === '提取成功') {
                    totalSuccess += amount;
                }

                var rowData = [
                    `'${row.querySelector('input[name="name"]').value}`,
                    `'${row.querySelector('input[name="card_number"]').value}`,
                    `'${row.querySelector('input[name="card_secret"]').value}`,
                    `'${status}`,
                    `'${amount.toFixed(2)}`,
                    `'${row.querySelector('input[name="extraction_time"]').value}`,
                    `'${row.querySelector('input[name="account"]').value}`
                ];
                csvContent += rowData.join(",") + "\n";
            });

            let totalPending = totalAmount - totalSuccess;

            // 添加总和信息
            csvContent += `总金额,提取成功,未提取\n`;
            csvContent += `${totalAmount.toFixed(2)},${totalSuccess.toFixed(2)},${totalPending.toFixed(2)}\n`;

            var encodedUri = encodeURI(csvContent);
            var link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "exported_data.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
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

        // 在页面加载时获取数据
        window.onload = function() {
            fetch('gest/qlrurjbc001.php')
                .then(handleResponse)
                .then(data => {
                    if (data.success) {
                        allData = data.data; // 确保 allData 是一个数组
                        updateTable();
                    } else {
                        alert('获取数据失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('发生错误: ' + error.message);
                });
        };
    </script>
</body>
</html>