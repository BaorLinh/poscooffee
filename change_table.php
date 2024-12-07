<?php
include 'db_connect.php';

// Nhận dữ liệu từ yêu cầu AJAX
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'get_tables') {
    // Truy vấn để lấy danh sách các bàn
    $query = "SELECT TableNumber, TableName FROM Tables";
    $result = $conn->query($query);

    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }

    echo json_encode(['success' => true, 'tables' => $tables]);
    exit;
}

if ($action === 'change_table') {
    $billID = $data['billID'];
    $newTableNumber = $data['newTableNumber'];
    $currentTableNumber = $data['currentTableNumber'];

    // Cập nhật BillID sang bàn mới
    $updateBillQuery = "UPDATE Bills SET TableNumber = ? WHERE BillID = ?";
    $stmt = $conn->prepare($updateBillQuery);
    $stmt->bind_param('ii', $newTableNumber, $billID);
    $stmt->execute();
    $stmt->close();

    // Kiểm tra xem bàn cũ có bao nhiêu đơn chưa thanh toán
    $checkBillCountQuery = "SELECT COUNT(*) FROM Bills WHERE TableNumber = ? AND Status = 0";
    $stmtCheck = $conn->prepare($checkBillCountQuery);
    $stmtCheck->bind_param('i', $currentTableNumber);
    $stmtCheck->execute();
    $stmtCheck->bind_result($billCount);
    $stmtCheck->fetch();
    $stmtCheck->close();

    // Nếu chỉ còn 1 đơn, tắt trạng thái bàn cũ
    if ($billCount <= 1) {
        $updateOldTableStatus = "UPDATE Tables SET Status = 0 WHERE TableNumber = ?";
        $stmtUpdateOld = $conn->prepare($updateOldTableStatus);
        $stmtUpdateOld->bind_param('i', $currentTableNumber);
        $stmtUpdateOld->execute();
        $stmtUpdateOld->close();
    }

    // Bật trạng thái bàn mới
    $updateNewTableStatus = "UPDATE Tables SET Status = 1 WHERE TableNumber = ?";
    $stmtUpdateNew = $conn->prepare($updateNewTableStatus);
    $stmtUpdateNew->bind_param('i', $newTableNumber);
    $stmtUpdateNew->execute();
    $stmtUpdateNew->close();

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
?>
