<?php
include 'db_connect.php';

// Nhận dữ liệu từ yêu cầu POST
$data = json_decode(file_get_contents("php://input"), true);
$areaID = $data['areaID'] ?? null;
$tableID = $data['tableID'] ?? null;
$customerID = $data['customerID'] ?? null;
$billID = $data['billID'] ?? null;

if ($billID && $tableID && $customerID) {
    // Kiểm tra dữ liệu hiện tại
    $queryCheck = "SELECT TableNumber, CustomerID FROM Bills WHERE BillID = ?";
    $stmtCheck = $conn->prepare($queryCheck);
    $stmtCheck->bind_param("i", $billID);
    $stmtCheck->execute();
    $stmtCheck->bind_result($currentTableNumber, $currentCustomerID);
    $stmtCheck->fetch();
    $stmtCheck->close();

    // So sánh và cập nhật nếu có thay đổi
    if ($currentTableNumber != $tableID || $currentCustomerID != $customerID) {
        // Thực hiện cập nhật
        $query = "UPDATE Bills SET TableNumber = ?, CustomerID = ? WHERE BillID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $tableID, $customerID, $billID);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
?>
