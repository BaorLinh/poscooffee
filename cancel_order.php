<?php
header('Content-Type: application/json');
include 'db_connect.php';
session_start();

// Kiểm tra session để lấy thông tin nhân viên
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Không có thông tin nhân viên đăng nhập.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Truy vấn `EmployeeID` và `PositionID` từ cơ sở dữ liệu
$query = "SELECT EmployeeID, PositionID FROM Employees WHERE EmployeeID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($employeeID, $positionID);
    $stmt->fetch();

    // Kiểm tra quyền hạn
    if ($positionID != 1 && $positionID != 2) {
        echo json_encode(['success' => false, 'redirect' => true, 'message' => 'Bạn không có quyền hủy đơn.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin nhân viên.']);
    exit;
}

// Nhận và kiểm tra dữ liệu JSON từ frontend
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['tableNumber']) || !isset($data['billID'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$tableNumber = intval($data['tableNumber']);
$billID = intval($data['billID']);

// Cập nhật trạng thái hủy đơn cho hóa đơn
$queryCancelBill = "UPDATE Bills SET Status = 2, EmployeePayment = ?, Timepayment = NOW() WHERE BillID = ?";
$stmtCancelBill = $conn->prepare($queryCancelBill);
$stmtCancelBill->bind_param('ii', $employeeID, $billID);

if ($stmtCancelBill->execute()) {
    // Kiểm tra số lượng hóa đơn chưa thanh toán còn lại trên bàn
    $queryCheckBills = "SELECT COUNT(*) FROM Bills WHERE TableNumber = ? AND Status = 0";
    $stmtCheckBills = $conn->prepare($queryCheckBills);
    $stmtCheckBills->bind_param('i', $tableNumber);
    $stmtCheckBills->execute();
    $stmtCheckBills->bind_result($activeBillsCount);
    $stmtCheckBills->fetch();
    $stmtCheckBills->close();

    // Nếu không còn hóa đơn chưa thanh toán, cập nhật trạng thái bàn về trống
    if ($activeBillsCount === 0) {
        $queryUpdateTable = "UPDATE Tables SET Status = 0 WHERE TableNumber = ?";
        $stmtUpdateTable = $conn->prepare($queryUpdateTable);
        $stmtUpdateTable->bind_param('i', $tableNumber);
        $stmtUpdateTable->execute();
        $stmtUpdateTable->close();
    }

    echo json_encode(['success' => true, 'message' => 'Đơn đã được hủy thành công.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi hủy đơn.']);
}

// Đóng các statement và kết nối
$stmtCancelBill->close();
$conn->close();
?>
