<?php
header('Content-Type: application/json');
include 'db_connect.php';

// Lấy danh sách hóa đơn khả dụng
$query = "SELECT BillID, TableNumber, TotalAmount FROM Bills WHERE Status = 0";
$result = $conn->query($query);

$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Trả về kết quả dưới dạng JSON
echo json_encode(['success' => true, 'orders' => $orders]);
$conn->close();
?>
