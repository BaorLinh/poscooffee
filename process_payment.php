<?php
header('Content-Type: application/json');
include 'db_connect.php'; // Kết nối CSDL

// Lấy dữ liệu JSON từ yêu cầu POST
$data = json_decode(file_get_contents('php://input'), true);

// Kiểm tra dữ liệu
if (!$data || !isset($data['billID']) || !isset($data['receivedAmount']) || !isset($data['paymentMethod'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

$billID = intval($data['billID']);
$receivedAmount = floatval($data['receivedAmount']);
$paymentMethod = intval($data['paymentMethod']);
$employeePayment = $_SESSION['user_id']; // Lấy EmployeeID từ session

// Cập nhật trạng thái thanh toán và thông tin thanh toán trong bảng Bills
$query = "UPDATE Bills SET Status = 1, PaymentMethodID = ?, EmployeePayment = ?, Timepayment = NOW() WHERE BillID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $paymentMethod, $employeePayment, $billID);

if ($stmt->execute()) {
    // Cập nhật trạng thái bàn về 0 (trống)
    $queryUpdateTable = "UPDATE Tables SET Status = 0 WHERE TableNumber = (SELECT TableNumber FROM Bills WHERE BillID = ?)";
    $stmtUpdateTable = $conn->prepare($queryUpdateTable);
    $stmtUpdateTable->bind_param('i', $billID);
    $stmtUpdateTable->execute();
    $stmtUpdateTable->close();

    echo json_encode(['success' => true, 'message' => 'Thanh toán thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật hóa đơn']);
}

$stmt->close();
$conn->close();
?>
