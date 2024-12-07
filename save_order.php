<?php
header('Content-Type: application/json');
include 'db_connect.php';
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Không có dữ liệu hoặc JSON không hợp lệ']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Không có thông tin nhân viên đăng nhập']);
    exit;
}

$employeeOrder = $_SESSION['user_id'];
$customerID = isset($data['customerID']) ? intval($data['customerID']) : 1;
$tableID = isset($data['tableID']) ? intval($data['tableID']) : null;
$billID = isset($data['billID']) ? intval($data['billID']) : null;

if (!empty($data['items']) && $tableID) {
    $items = $data['items'];
    $totalAmount = 0;

    if ($billID) {
        // Xóa sản phẩm cũ khi có `billID`
        $queryDeleteItems = "DELETE FROM InforBill WHERE BillID = ?";
        $stmtDeleteItems = $conn->prepare($queryDeleteItems);
        if (!$stmtDeleteItems || !$stmtDeleteItems->bind_param('i', $billID) || !$stmtDeleteItems->execute()) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa sản phẩm cũ']);
            exit;
        }
        $stmtDeleteItems->close();
    } else {
        // Tạo mới hóa đơn nếu không có billID
        $queryCreateBill = "INSERT INTO Bills (TableNumber, TotalAmount, Timeorder, Status, CustomerID, EmployeeOrder) VALUES (?, 0, NOW(), 0, ?, ?)";
        $stmtCreateBill = $conn->prepare($queryCreateBill);
        if (!$stmtCreateBill || !$stmtCreateBill->bind_param('iii', $tableID, $customerID, $employeeOrder) || !$stmtCreateBill->execute()) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo hóa đơn mới']);
            exit;
        }
        $billID = $conn->insert_id;
        $stmtCreateBill->close();
    }

    // Cập nhật bàn và khách hàng nếu cần thiết
    $queryUpdateBillTableCustomer = "UPDATE Bills SET TableNumber = ?, CustomerID = ? WHERE BillID = ?";
    $stmtUpdateBillTableCustomer = $conn->prepare($queryUpdateBillTableCustomer);
    if (!$stmtUpdateBillTableCustomer || !$stmtUpdateBillTableCustomer->bind_param('iii', $tableID, $customerID, $billID) || !$stmtUpdateBillTableCustomer->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật bàn và khách hàng cho hóa đơn']);
        exit;
    }
    $stmtUpdateBillTableCustomer->close();

    // Chuẩn bị truy vấn để thêm sản phẩm vào hóa đơn
    $queryInsertItem = "INSERT INTO InforBill (BillID, ProductID, Quantity, UnitPrice) VALUES (?, ?, ?, ?)";
    $stmtInsertItem = $conn->prepare($queryInsertItem);
    if (!$stmtInsertItem) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm sản phẩm vào hóa đơn']);
        exit;
    }

    // Thêm từng sản phẩm vào hóa đơn
    foreach ($items as $item) {
        if (isset($item['ProductName'], $item['Quantity'], $item['UnitPrice'])) {
            $productName = $item['ProductName'];
            $quantity = intval($item['Quantity']);
            $unitPrice = floatval($item['UnitPrice']);

            if ($quantity <= 0 || $unitPrice <= 0) {
                continue;
            }

            $totalAmount += $unitPrice * $quantity;

            $queryProduct = "SELECT ProductID FROM Product WHERE ProductName LIKE ?";
            $productNameLike = "%{$productName}%";
            $stmtProduct = $conn->prepare($queryProduct);
            $stmtProduct->bind_param('s', $productNameLike);

            $stmtProduct->execute();
            $stmtProduct->bind_result($productID);
            $stmtProduct->fetch();
            $stmtProduct->close();

            if (!$productID) {
                continue;
            }

            if (!$stmtInsertItem->bind_param('iiid', $billID, $productID, $quantity, $unitPrice) || !$stmtInsertItem->execute()) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm sản phẩm vào hóa đơn']);
                exit;
            }
        }
    }
    $stmtInsertItem->close();

    // Cập nhật `TotalAmount` và `EmployeeOrder` trong `Bills`
    $queryUpdateBill = "UPDATE Bills SET TotalAmount = ?, EmployeeOrder = ? WHERE BillID = ?";
    $stmtUpdateBill = $conn->prepare($queryUpdateBill);
    if (!$stmtUpdateBill || !$stmtUpdateBill->bind_param('dii', $totalAmount, $employeeOrder, $billID) || !$stmtUpdateBill->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật hóa đơn']);
        exit;
    }
    $stmtUpdateBill->close();

    // Cập nhật trạng thái của bàn
    $queryUpdateTableStatus = "UPDATE Tables SET Status = 1 WHERE TableNumber = ?";
    $stmtUpdateTableStatus = $conn->prepare($queryUpdateTableStatus);
    if (!$stmtUpdateTableStatus || !$stmtUpdateTableStatus->bind_param('i', $tableID) || !$stmtUpdateTableStatus->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái bàn']);
        exit;
    }
    $stmtUpdateTableStatus->close();

    echo json_encode(['success' => true, 'message' => 'Cập nhật hóa đơn thành công', 'billID' => $billID]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không có dữ liệu để lưu']);
}

$conn->close();
?>
