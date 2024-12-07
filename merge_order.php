<?php
header('Content-Type: application/json');
include 'db_connect.php';
session_start();

// Kiểm tra session để lấy thông tin nhân viên
if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    echo json_encode(['success' => false, 'message' => 'Không có thông tin nhân viên.']);
    exit;
}

// Lấy dữ liệu từ yêu cầu POST
$data = json_decode(file_get_contents('php://input'), true);
$action = isset($data['action']) ? $data['action'] : '';

// Kiểm tra loại hành động: 'get_orders' để lấy danh sách hóa đơn khả dụng, 'merge' để gộp đơn
if ($action === 'get_orders') {
    // Lấy danh sách các hóa đơn chưa thanh toán (Status = 0), nhưng bỏ qua hóa đơn đang muốn gộp đi
    $billIDToExclude = isset($data['billIDToExclude']) ? intval($data['billIDToExclude']) : 0;

    $query = "SELECT BillID, TableNumber, TotalAmount FROM Bills WHERE Status = 0 AND BillID != ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('i', $billIDToExclude);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmt->error]);
        exit;
    }

    // Thay thế get_result() bằng bind_result() để lấy kết quả
    $stmt->bind_result($billID, $tableNumber, $totalAmount);

    $orders = [];
    while ($stmt->fetch()) {
        $orders[] = [
            'BillID' => $billID,
            'TableNumber' => $tableNumber,
            'TotalAmount' => $totalAmount
        ];
    }

    echo json_encode(['success' => true, 'orders' => $orders]);
    $stmt->close();
    $conn->close();
    exit;
}

if ($action === 'merge') {
    if (!$data || !isset($data['tableNumber']) || !isset($data['billIDToMerge']) || !isset($data['targetTableNumber']) || !isset($data['targetBillID'])) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit;
    }

    $tableNumber = intval($data['tableNumber']);
    $billIDToMerge = intval($data['billIDToMerge']);
    $targetTableNumber = intval($data['targetTableNumber']);
    $targetBillID = intval($data['targetBillID']);

    // Lấy thông tin nhân viên
// Lấy thông tin nhân viên - sử dụng EmployeeID thay vì FullName
$employeePayment = $_SESSION['user_id'];

    $timePayment = date('Y-m-d H:i:s');

// Cập nhật trạng thái của hóa đơn gộp đi thành 3 (bị gộp)
$queryUpdateBill = "UPDATE Bills SET Status = 3, EmployeePayment = ?, Timepayment = ? WHERE BillID = ?";
$stmtUpdateBill = $conn->prepare($queryUpdateBill);
if (!$stmtUpdateBill) {
    echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
    exit;
}
$stmtUpdateBill->bind_param('isi', $employeePayment, $timePayment, $billIDToMerge);

    if (!$stmtUpdateBill->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtUpdateBill->error]);
        exit;
    }
    $stmtUpdateBill->close();

    // Chuyển tất cả các món từ hóa đơn gộp đi sang hóa đơn gộp đến
    $queryUpdateInfoBill = "UPDATE InforBill SET BillID = ? WHERE BillID = ?";
    $stmtUpdateInfoBill = $conn->prepare($queryUpdateInfoBill);
    if (!$stmtUpdateInfoBill) {
        echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
        exit;
    }
    $stmtUpdateInfoBill->bind_param('ii', $targetBillID, $billIDToMerge);
    if (!$stmtUpdateInfoBill->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtUpdateInfoBill->error]);
        exit;
    }
    $stmtUpdateInfoBill->close();

    // Kiểm tra nếu bàn gộp đi chỉ có 1 đơn, thì tắt trạng thái bàn
    $queryCheckBillCount = "SELECT COUNT(*) FROM Bills WHERE TableNumber = ? AND Status = 0";
    $stmtCheckBillCount = $conn->prepare($queryCheckBillCount);
    if (!$stmtCheckBillCount) {
        echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
        exit;
    }
    $stmtCheckBillCount->bind_param('i', $tableNumber);
    if (!$stmtCheckBillCount->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtCheckBillCount->error]);
        exit;
    }
    $stmtCheckBillCount->bind_result($billCount);
    $stmtCheckBillCount->fetch();
    $stmtCheckBillCount->close();

    // Log giá trị billCount để kiểm tra
    if ($billCount == 0) {
        // Nếu không còn đơn nào, cập nhật trạng thái bàn về trống (0)
        $queryUpdateTable = "UPDATE Tables SET Status = 0 WHERE TableNumber = ?";
        $stmtUpdateTable = $conn->prepare($queryUpdateTable);
        if (!$stmtUpdateTable) {
            echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
            exit;
        }
        $stmtUpdateTable->bind_param('i', $tableNumber);
        if (!$stmtUpdateTable->execute()) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtUpdateTable->error]);
            exit;
        }
        $stmtUpdateTable->close();
    }

    echo json_encode(['success' => true, 'message' => 'Gộp đơn thành công']);
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
?>