<?php
// Hiển thị mọi lỗi từ PHP để dễ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

// Nhận dữ liệu từ request JSON
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'get_items_for_split') {
    $billID = $data['billID'];

    // Truy vấn để lấy danh sách món từ hóa đơn
    $query = "SELECT ib.InforBillID, ib.Quantity, p.ProductName 
              FROM InforBill ib 
              JOIN Product p ON ib.ProductID = p.ProductID
              WHERE ib.BillID = ?";

    // Chuẩn bị câu truy vấn
    if (!$stmt = $conn->prepare($query)) {
        echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
        exit;
    }

    // Gán tham số và thực thi
    $stmt->bind_param('i', $billID);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmt->error]);
        exit;
    }

    // Sử dụng bind_result() để lấy kết quả
    $stmt->bind_result($inforBillID, $quantity, $productName);
    $items = [];

    // Lưu kết quả vào mảng items
    while ($stmt->fetch()) {
        $items[] = [
            'InforBillID' => $inforBillID,
            'Quantity' => $quantity,
            'ProductName' => $productName
        ];
    }

    $stmt->close();

    // Trả về phản hồi JSON
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}


if ($action === 'split_order') {
    $billID = $data['billID'];
    $tableNumber = $data['tableNumber'];
    $selectedItems = $data['selectedItems'];

    // Tạo hóa đơn mới cho các món được tách
    $createBillQuery = "INSERT INTO Bills (TableNumber, TimeOrder, EmployeeOrder, CustomerID, Status) VALUES (?, NOW(), ?, 1, 0)";
    $employeeOrder = 1; // Giả sử EmployeeOrder là 1 (hoặc lấy từ session)

    if (!$stmtCreateBill = $conn->prepare($createBillQuery)) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo hóa đơn mới: ' . $conn->error]);
        exit;
    }

    $stmtCreateBill->bind_param('ii', $tableNumber, $employeeOrder);
    if (!$stmtCreateBill->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtCreateBill->error]);
        exit;
    }

    $newBillID = $stmtCreateBill->insert_id;
    $stmtCreateBill->close();

    foreach ($selectedItems as $item) {
        $inforBillID = $item['inforBillID'];
        $quantityToSplit = $item['quantityToSplit'];

        // Lấy số lượng hiện tại của món trong InforBill
        $query = "SELECT Quantity FROM InforBill WHERE InforBillID = ?";
        if (!$stmt = $conn->prepare($query)) {
            echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('i', $inforBillID);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmt->error]);
            exit;
        }

        $stmt->bind_result($currentQuantity);
        $stmt->fetch();
        $stmt->close();

        // Nếu tách hết số lượng, chuyển món sang hóa đơn mới
        if ($quantityToSplit == $currentQuantity) {
            $updateItemQuery = "UPDATE InforBill SET BillID = ? WHERE InforBillID = ?";
            if (!$stmtUpdateItem = $conn->prepare($updateItemQuery)) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật món: ' . $conn->error]);
                exit;
            }

            $stmtUpdateItem->bind_param('ii', $newBillID, $inforBillID);
            if (!$stmtUpdateItem->execute()) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtUpdateItem->error]);
                exit;
            }
            $stmtUpdateItem->close();
        } else {
            // Cập nhật số lượng món còn lại trong hóa đơn cũ
            $newQuantity = $currentQuantity - $quantityToSplit;
            $updateOldItemQuery = "UPDATE InforBill SET Quantity = ? WHERE InforBillID = ?";
            if (!$stmtUpdateOldItem = $conn->prepare($updateOldItemQuery)) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật số lượng: ' . $conn->error]);
                exit;
            }

            $stmtUpdateOldItem->bind_param('ii', $newQuantity, $inforBillID);
            if (!$stmtUpdateOldItem->execute()) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtUpdateOldItem->error]);
                exit;
            }
            $stmtUpdateOldItem->close();

            // Tạo món mới trong đơn mới
            $createNewItemQuery = "INSERT INTO InforBill (BillID, ProductID, Quantity, UnitPrice) 
                                   SELECT ?, ProductID, ?, UnitPrice 
                                   FROM InforBill WHERE InforBillID = ?";
            if (!$stmtCreateNewItem = $conn->prepare($createNewItemQuery)) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo món mới: ' . $conn->error]);
                exit;
            }

            $stmtCreateNewItem->bind_param('iii', $newBillID, $quantityToSplit, $inforBillID);
            if (!$stmtCreateNewItem->execute()) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtCreateNewItem->error]);
                exit;
            }
            $stmtCreateNewItem->close();
        }
    }

    // Cập nhật trạng thái bàn mới
    $updateTableStatusQuery = "UPDATE Tables SET Status = 1 WHERE TableNumber = ?";
    if (!$stmtUpdateTable = $conn->prepare($updateTableStatusQuery)) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái bàn mới: ' . $conn->error]);
        exit;
    }

    $stmtUpdateTable->bind_param('i', $tableNumber);
    if (!$stmtUpdateTable->execute()) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thực thi truy vấn: ' . $stmtUpdateTable->error]);
        exit;
    }
    $stmtUpdateTable->close();

    echo json_encode(['success' => true, 'message' => 'Tách đơn thành công!']);
    exit;
}

// Phản hồi lỗi nếu không có hành động hợp lệ
echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
exit;
?>
