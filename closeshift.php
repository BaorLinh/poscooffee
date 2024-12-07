<?php
session_start();
header('Content-Type: application/json'); // Đặt kiểu phản hồi JSON
include 'db_connect.php'; // Kết nối cơ sở dữ liệu

$response = []; // Mảng phản hồi JSON

// Kiểm tra nếu ca làm việc đang mở
$shiftOpenQuery = "SELECT ShiftID, ShiftStartTime, StartAmount FROM WorkShift WHERE EmployeeID = ? AND ShiftEndTime IS NULL";
$stmt = $conn->prepare($shiftOpenQuery);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($shiftID, $shiftStartTime, $startAmount);
    $stmt->fetch();
    
    // Lấy số tiền cuối ca từ yêu cầu POST
    $endAmount = isset($_POST['endAmount']) ? (float)$_POST['endAmount'] : 0;
    
    // Tính toán tổng số tiền từ các hóa đơn thanh toán trong ca với phương thức thanh toán là tiền mặt (PaymentMethodID = 1)
    $totalPaidBillsQuery = "SELECT SUM(Total) FROM Bills WHERE EmployeePayment = ? AND Timepayment BETWEEN ? AND NOW() AND PaymentMethodID = 1";
    $stmtPaidBills = $conn->prepare($totalPaidBillsQuery);
    $stmtPaidBills->bind_param('is', $_SESSION['user_id'], $shiftStartTime);
    $stmtPaidBills->execute();
    $stmtPaidBills->bind_result($totalPaidBills);
    $stmtPaidBills->fetch();
    $stmtPaidBills->close();
    
    // Tính tổng tiền thu và chi (bao gồm cả các phiếu thu và chi)
    $totalMoneyQuery = "SELECT SUM(NumberMoney) FROM money WHERE Employee = ? AND Date BETWEEN ? AND NOW()";
    $stmtMoney = $conn->prepare($totalMoneyQuery);
    $stmtMoney->bind_param('is', $_SESSION['user_id'], $shiftStartTime);
    $stmtMoney->execute();
    $stmtMoney->bind_result($totalMoney);
    $stmtMoney->fetch();
    $stmtMoney->close();
    
    // Tính toán DifferenceAmount
    $expectedAmount = $startAmount + $totalPaidBills + $totalMoney;
    $differenceAmount = $endAmount - $expectedAmount;

    // Cập nhật ca làm việc trong cơ sở dữ liệu
    $updateShiftQuery = "UPDATE WorkShift SET EndAmount = ?, DifferenceAmount = ?, ShiftEndTime = NOW() WHERE ShiftID = ?";
    $stmtUpdateShift = $conn->prepare($updateShiftQuery);
    $stmtUpdateShift->bind_param('ddi', $endAmount, $differenceAmount, $shiftID);
    
    if ($stmtUpdateShift->execute()) {
        $response['success'] = true;
        $response['message'] = 'Kết ca thành công.';
    } else {
        $response['success'] = false;
        $response['message'] = 'Lỗi trong quá trình kết ca.';
    }
    $stmtUpdateShift->close();
    
} else {
    $response['success'] = false;
    $response['message'] = 'Không có ca làm việc nào đang mở.';
}

$stmt->close();
$conn->close();

echo json_encode($response); // Trả về phản hồi JSON
?>
