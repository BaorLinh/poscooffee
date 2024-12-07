<?php
header('Content-Type: application/json');
include 'db_connect.php';
session_start();

// Ghi log để kiểm tra session
$logFile = 'payment_log.txt';
$logMessage = "Session data: " . print_r($_SESSION, true) . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Kiểm tra session để đảm bảo có thông tin nhân viên đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session không có thông tin nhân viên thanh toán.']);
    exit;
}

// Lấy EmployeePayment từ session (sử dụng user_id thay cho employeeID)
$employeeID = intval($_SESSION['user_id']); // Lấy user_id từ session

// Lấy dữ liệu từ POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['billID'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$billID = intval($data['billID']);
$receivedAmount = isset($data['receivedAmount']) ? floatval($data['receivedAmount']) : 0;
$paymentMethod = intval($data['paymentMethod']);
$discountAmount = isset($data['discountAmount']) ? floatval($data['discountAmount']) : 0;
$discountPercentage = isset($data['discountPercentage']) ? floatval($data['discountPercentage']) : 0;

$totalAmount = 0;

// Kiểm tra xem nhân viên có ca làm việc đang mở không
$queryCheckShift = "SELECT COUNT(*) FROM WorkShift WHERE EmployeeID = ? AND ShiftEndTime IS NULL";
$stmtCheckShift = $conn->prepare($queryCheckShift);
$stmtCheckShift->bind_param('i', $employeeID);
$stmtCheckShift->execute();
$stmtCheckShift->bind_result($openShiftCount);
$stmtCheckShift->fetch();
$stmtCheckShift->close();

// Ghi log kiểm tra ca làm việc
file_put_contents($logFile, "Ca làm việc mở: $openShiftCount\n", FILE_APPEND);

if ($openShiftCount == 0) {
    echo json_encode(['success' => false, 'message' => 'Bạn phải mở ca làm việc trước khi thanh toán.']);
    file_put_contents($logFile, "Thanh toán thất bại: Nhân viên chưa mở ca làm việc.\n", FILE_APPEND);
    exit;
}

// Lấy tổng tiền từ BillID
$queryBill = "SELECT TotalAmount FROM Bills WHERE BillID = ?";
$stmtBill = $conn->prepare($queryBill);
$stmtBill->bind_param('i', $billID);
$stmtBill->execute();
$stmtBill->bind_result($totalAmount);
$stmtBill->fetch();
$stmtBill->close();

// Ghi log để kiểm tra giá trị TotalAmount và discount
file_put_contents($logFile, "TotalAmount: $totalAmount, DiscountAmount: $discountAmount, DiscountPercentage: $discountPercentage\n", FILE_APPEND);

// Áp dụng giảm giá nếu có
$discountedTotal = $totalAmount;
if ($discountAmount > 0) {
    $discountedTotal -= $discountAmount; // Trừ giảm giá cụ thể
}
if ($discountPercentage > 0) {
    $discountedTotal -= ($discountPercentage / 100) * $totalAmount; // Trừ giảm giá phần trăm
}

// Ghi log số tiền sau khi giảm giá
file_put_contents($logFile, "DiscountedTotal: $discountedTotal\n", FILE_APPEND);

// Kiểm tra số tiền nhận từ khách có đủ không
if ($receivedAmount < $discountedTotal) {
    file_put_contents($logFile, "Số tiền nhận không đủ để thanh toán. ReceivedAmount: $receivedAmount, DiscountedTotal: $discountedTotal\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Số tiền nhận không đủ để thanh toán.']);
    exit;
}

// Cập nhật hóa đơn với thông tin thanh toán
$queryUpdateBill = "UPDATE Bills SET DiscountAmount = ?, DiscountPercentage = ?, ReceivedAmount = ?, PaymentMethodID = ?, EmployeePayment = ?, Timepayment = NOW(), Status = 1, Total = ? WHERE BillID = ?";
$stmtUpdateBill = $conn->prepare($queryUpdateBill);
$stmtUpdateBill->bind_param('ddiidii', $discountAmount, $discountPercentage, $receivedAmount, $paymentMethod, $employeeID, $discountedTotal, $billID);

if ($stmtUpdateBill->execute()) {
    // Kiểm tra nếu còn hóa đơn nào chưa thanh toán trên bàn này
    $queryCheckUnpaid = "SELECT COUNT(*) FROM Bills WHERE TableNumber = (SELECT TableNumber FROM Bills WHERE BillID = ?) AND Status = 0";
    $stmtCheckUnpaid = $conn->prepare($queryCheckUnpaid);
    $stmtCheckUnpaid->bind_param('i', $billID);
    $stmtCheckUnpaid->execute();
    $stmtCheckUnpaid->bind_result($unpaidCount);
    $stmtCheckUnpaid->fetch();
    $stmtCheckUnpaid->close();

    // Nếu không còn hóa đơn nào chưa thanh toán, đặt trạng thái bàn về trống
    if ($unpaidCount === 0) {
        $queryUpdateTable = "UPDATE Tables SET Status = 0 WHERE TableNumber = (SELECT TableNumber FROM Bills WHERE BillID = ?)";
        $stmtUpdateTable = $conn->prepare($queryUpdateTable);
        $stmtUpdateTable->bind_param('i', $billID);
        $stmtUpdateTable->execute();
        $stmtUpdateTable->close();
    }

    file_put_contents($logFile, "Thanh toán thành công.\n", FILE_APPEND);
    echo json_encode(['success' => true, 'message' => 'Thanh toán thành công.']);
} else {
    file_put_contents($logFile, "Có lỗi xảy ra khi xử lý thanh toán.\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi xử lý thanh toán.']);
}

$stmtUpdateBill->close();
$conn->close();
?>
