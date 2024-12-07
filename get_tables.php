<?php
include 'db_connect.php';

if (!isset($_GET['areaID'])) {
    http_response_code(400); // Thiếu tham số
    echo json_encode(["error" => "Thiếu tham số areaID"]);
    exit;
}

$areaID = intval($_GET['areaID']);
$query = "SELECT TableNumber, TableName FROM Tables WHERE AreaID = ?";
$stmt = $conn->prepare($query);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["error" => "Lỗi chuẩn bị truy vấn SQL"]);
    exit;
}

$stmt->bind_param("i", $areaID);
$stmt->execute();
$stmt->bind_result($tableNumber, $tableName);

$tables = [];
while ($stmt->fetch()) {
    $tables[] = [
        "TableNumber" => $tableNumber,
        "TableName" => $tableName
    ];
}

echo json_encode($tables);
$stmt->close();
?>
