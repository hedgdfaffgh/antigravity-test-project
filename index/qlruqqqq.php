<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $name = isset($data["名称"]) ? $data["名称"] : null;
    $actualAmount = isset($data["订单金额"]) ? $data["订单金额"] : null;

    if ($name !== null && $actualAmount !== null) {
        $servername = "localhost";
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');
        $dbname = "ovrn";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("连接失败: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("SELECT * FROM `金限` WHERE 名称 = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();

        $response = ["uu" => 0, "tt" => null, "nn" => null, "ss" => null, "mm" => null];

        while ($row = $result->fetch_assoc()) {
            $limits = explode('@', $row['金限']);
            foreach ($limits as $limit) {
                // 去掉小数点后的零进行比较
                if ((float)$actualAmount == (float)$limit) {
                    $response["uu"] = 1;
                    $response["tt"] = $row['金额'];
                    $response["nn"] = $row['名字'];
                    $response["ss"] = $row['判名']; // 新增
                    $response["mm"] = $row['顺序']; // 新增
                    $response["yy"] = $row['实金']; // 新增
                    break 2; // 退出外层循环
                }
            }
        }

        $stmt->close();
        $conn->close();

        echo json_encode($response);
    } else {
        echo json_encode(["error" => "Invalid input"]);
    }
}
?>