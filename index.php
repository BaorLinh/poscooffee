<?php
session_start(); // Khởi tạo session

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Chuyển hướng đến trang đăng nhập
    exit(); // Dừng thực hiện mã tiếp theo
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db_connect.php'; // Bao gồm kết nối CSDL

// Kiểm tra nếu ca làm việc đang mở
$shiftOpenQuery = "SELECT ShiftStartTime, StartAmount FROM WorkShift WHERE EmployeeID = ? AND ShiftEndTime IS NULL";
$stmt = $conn->prepare($shiftOpenQuery);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Nếu ca đang mở, lấy thông tin
    $stmt->bind_result($shiftStartTime, $startAmount);
    $stmt->fetch();
    $_SESSION['shift_opened'] = true; // Đánh dấu ca đã mở
    $_SESSION['shift_start_time'] = $shiftStartTime; // Lưu thời gian bắt đầu ca
    $_SESSION['start_amount'] = $startAmount; // Lưu số tiền đầu ca
} else {
    $_SESSION['shift_opened'] = false; // Không có ca nào mở
}

// Truy vấn để lấy danh sách khu vực và các bàn
$areasQuery = "SELECT a.AreaID, a.AreaName, t.TableNumber, t.TableName, t.Status, COUNT(b.BillID) as UnpaidCount
               FROM Areas a
               JOIN Tables t ON a.AreaID = t.AreaID
               LEFT JOIN Bills b ON t.TableNumber = b.TableNumber AND b.Status = 0
               GROUP BY a.AreaID, a.AreaName, t.TableNumber, t.TableName, t.Status
               ORDER BY a.AreaID, t.TableNumber";
$areasResult = mysqli_query($conn, $areasQuery);

// Truy vấn tất cả các hóa đơn chưa thanh toán
$billQuery = "SELECT b.BillID, 
                     b.TotalAmount, 
                     b.Timeorder, 
                     c.CustomerName, 
                     b.TableNumber, 
                     e.FullName AS EmployeeName
              FROM Bills b
              LEFT JOIN Customers c ON b.CustomerID = c.CustomerID
              LEFT JOIN Employees e ON b.EmployeeOrder = e.EmployeeID
              WHERE b.Status = 0";

$billResult = mysqli_query($conn, $billQuery);

if (!$billResult) {
    die("Truy vấn không thành công: " . mysqli_error($conn));
}

// Khởi tạo mảng hóa đơn chưa thanh toán
$bills = [];
while ($bill = mysqli_fetch_assoc($billResult)) {
    // Truy vấn để lấy thông tin món ăn từ bảng InforBill
    $inforBillQuery = "SELECT ib.Quantity, ib.UnitPrice, p.ProductName
                       FROM InforBill ib
                       LEFT JOIN Product p ON ib.ProductID = p.ProductID
                       WHERE ib.BillID = ?";

    // Chuẩn bị câu lệnh
    $stmt = $conn->prepare($inforBillQuery);

    if (!$stmt) {
        die("Không thể chuẩn bị truy vấn: " . mysqli_error($conn));
    }

    $stmt->bind_param('i', $bill['BillID']);
    $stmt->execute();
    
    // Liên kết kết quả
    $stmt->bind_result($quantity, $unitPrice, $productName);

    // Lưu thông tin món ăn vào biến $bill
    $bill['items'] = [];
    while ($stmt->fetch()) {
        $bill['items'][] = [
            'Quantity' => $quantity,
            'UnitPrice' => $unitPrice,
            'ProductName' => $productName
        ]; // Thêm từng món vào mảng items
    }

    $stmt->close();

    // Truy vấn để tính tổng TotalPrice của hóa đơn
    $totalPriceQuery = "SELECT SUM(TotalPrice) as TotalPrice FROM InforBill WHERE BillID = ?";
    $stmtTotal = $conn->prepare($totalPriceQuery);

    if (!$stmtTotal) {
        die("Không thể chuẩn bị truy vấn: " . mysqli_error($conn));
    }

    $stmtTotal->bind_param('i', $bill['BillID']);
    $stmtTotal->execute();
    $stmtTotal->bind_result($totalPrice);
    $stmtTotal->fetch();
    $stmtTotal->close();

    // Gán tổng tiền vào hóa đơn
    $bill['TotalPrice'] = $totalPrice;

    $bills[$bill['TableNumber']][] = $bill; // Gán hóa đơn vào biến bills
}


// Khởi tạo mảng để lưu dữ liệu khu vực và bàn
$areas = [];
$tables = []; // Khởi tạo biến tables
while ($row = mysqli_fetch_assoc($areasResult)) {
    $areas[$row['AreaName']][] = $row;
    $tables[] = $row; // Gán bàn vào biến tables
}

// Đóng kết nối nếu còn tồn tại
if ($conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS MECOFFEE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* Header styles */
    .header {
        background-color: #003eff; /* Màu nền header */
        color: white; /* Màu chữ trong header */
        display: flex; /* Sử dụng Flexbox */
        justify-content: space-between; /* Căn chỉnh giữa các nút */
        align-items: center; /* Căn giữa theo chiều dọc */
        position: fixed; /* Đặt vị trí cố định */
        top: 0; /* Đỉnh trang */
        width: 100%; /* Chiều rộng 100% */
        height: 10vh; /* Chiều cao của header */
        z-index: 1000; /* Đặt z-index cao hơn */
        box-shadow: 0 0.4vh 0.6vh rgba(0, 0, 0, 0.1); /* Bóng đổ cho header */
    }
    
    .header-left {
        display: flex; /* Sử dụng Flexbox cho các nút */
        align-items: center; /* Căn giữa theo chiều dọc */
    }
    
    .header-left button {
        background: none; /* Không có nền */
        border: none; /* Không có viền */
        color: white; /* Màu chữ */
        font-size: 100%; /* Kích thước chữ */
        cursor: pointer; /* Con trỏ khi hover */
        margin: 0 1vw; /* Khoảng cách giữa các nút */
        padding: 1vh 1.5vw; /* Padding cho các nút */
        transition: background-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease; /* Hiệu ứng chuyển tiếp cho nền, bóng đổ và chuyển động */
        border-radius: 1vw; /* Bo tròn góc */
    }
    
    .header-left button:hover {
        background-color: rgba(255, 255, 255, 0.2); /* Nền khi hover */
        box-shadow: 0 0.4vh 0.8vh rgba(0, 0, 0, 0.2); /* Bóng đổ mạnh hơn khi hover */
        transform: translateY(-0.3vh); /* Đẩy nút lên khi hover */
    }
    
    /* Thêm class active để hiển thị trạng thái được chọn */
    
    /* General styles */
    body {
        font-family: Arial, sans-serif; /* Font chữ */
        margin-top: 10vh;
        margin-left: 0;
        margin-bottom: 0;
        margin-right: 0;
        background-color: #f0f0f0; /* Màu nền tổng quan */
        color: #333; /* Màu chữ mặc định */
        display: flex; /* Sử dụng Flexbox */
        flex-direction: column; /* Định hướng theo cột */
        min-height: 100vh; /* Chiều cao tối thiểu là 100% chiều cao viewport */
    }
    
    h2 {
        text-align: center; /* Căn giữa chữ */
        font-size: 100%; /* Kích thước chữ */
        color: #000; /* Màu chữ */
        margin-bottom: 3vh; /* Khoảng cách dưới */
        border-bottom: 0.3vh solid #000; /* Viền dưới */
        padding-bottom: 1vh; /* Padding dưới */
        padding-left: 1vw; /* Padding bên trái */
        text-transform: uppercase; /* Chữ in hoa */
    }
    
    .content {
        display: block; /* Đảm bảo hiển thị */
        background-color: #f0f0f0; /* Màu nền ví dụ */
        padding-bottom: 4vh; /* Padding dưới, có thể điều chỉnh */

        border-radius: 1vw; /* Bo tròn góc */
    }
    
    .area-section {
        margin-bottom: 5vh; /* Khoảng cách dưới cho mỗi khu vực */
        margin-right: 2vw;
        margin-left: 2vw;
        margin-top: 2vh;
        padding-top: 0.5vh; /* Padding cho khu vực */
        background-color: #ffffff; /* Màu nền cho khu vực */
        border-radius: 1.5vw; /* Bo tròn góc cho khu vực */
    }
    
    .menu-container {
        display: flex; /* Sử dụng Flexbox để chia thành hai phần */
        height: calc(100vh - 10vh); /* Chiều cao của menu bằng chiều cao viewport trừ chiều cao header */
    }
    
    .menu-list {
        width: 20%; /* Chiếm 20% chiều rộng */
        background-color: #ffffff; /* Màu nền cho danh sách menu */
        padding: 10px; /* Padding cho danh sách menu */
        text-align: center;
        border-right: 1px solid #ccc; /* Đường viền phải */
    }
    
    .menu-list h2 {
        margin-top: 0; /* Bỏ margin trên */
    }
    
    .menu-list ul {
        list-style-type: none; /* Bỏ dấu đầu dòng */
        padding: 0; /* Bỏ padding */
    }
    
    .menu-list li {
        padding: 10px; /* Padding cho mỗi mục */
        cursor: pointer; /* Hiển thị con trỏ khi hover */
    }
    
    .menu-list li:hover {
        background-color: #e0e0e0; /* Màu nền khi hover */
    }
    
    .menu-detail {
        width: 100%;
        padding-left: 2vw;
        overflow-y: auto;
        padding-right: 2vw;
        padding-top: 2vh;
    }
    
    .menu-detail h3 {
        margin-top: 1vh; /* Bỏ margin trên */
    }
    
    /* Table styles */
    .table-container {
        display: flex;
        flex-wrap: wrap; /* Cho phép xuống dòng nếu không đủ không gian */
        justify-content: space-around; /* Căn đều khoảng cách giữa các thẻ bàn */
        gap: 2vw; /* Khoảng cách giữa các thẻ bàn */
        padding: 2vh 2vw; /* Padding bên trong */
    }

    /* Thiết lập chiều cao và chiều rộng cố định cho .table-card */
    .table-card {
        width: calc(25% - 2vw); /* Mỗi thẻ bàn chiếm 25% chiều rộng của lớp cha, với khoảng cách trừ đi 2vw */
        max-width: 250px; /* Giới hạn chiều rộng tối đa */
        min-width: 200px; /* Giới hạn chiều rộng tối thiểu */
        height: 15rem; /* Thiết lập chiều cao cố định */
        background-color: white; /* Màu nền cho thẻ bàn */
        border: 1.5px solid #0072ff; /* Viền cho thẻ bàn */
        border-radius: 1.5vw; /* Bo tròn góc cho thẻ bàn */
        padding: 2vh; /* Padding bên trong cho thẻ bàn */
        box-shadow: 0 0.2vh 0.5vh rgba(0, 0, 0, 0.1); /* Bóng đổ cho thẻ bàn */
        display: flex; /* Sử dụng Flexbox để căn giữa nội dung */
        flex-direction: column;
        justify-content: space-between; /* Căn đều khoảng cách giữa các thành phần bên trong */
        align-items: center; /* Căn giữa theo chiều ngang */
        transition: transform 0.3s; /* Hiệu ứng khi hover */
        cursor: pointer; /* Con trỏ khi hover */
        position: relative;
        margin-bottom:2vh;
    }

    .table-card:hover {
        transform: translateY(-5px); /* Đẩy lên khi hover */
        box-shadow: 0 0.6vh 1.2vh rgba(0, 0, 0, 0.15); /* Bóng đổ mạnh hơn khi hover */
    }

    /* Khi không có hóa đơn */
    .table-card .empty {
        font-size: 1.5em; /* Kích thước chữ cho thông tin "Trống" */
        color: #999; /* Màu chữ cho "Trống" */
        display: flex;
        align-items: center; /* Căn giữa theo chiều dọc */
        justify-content: center; /* Căn giữa theo chiều ngang */
        height: 100%; /* Chiều cao chiếm toàn bộ thẻ bàn */
    }

    /* Header chứa nút menu, tên bàn và số lượng hóa đơn chưa thanh toán */
    .table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        margin-bottom: 1vh;
    }

    /* Nút menu nằm bên trái, tên bàn nằm bên phải */
.menu-button {
    cursor: pointer;
    color: #666;
    font-size: 1.5em;
}

.table-card-header h3 {
    font-size: 1.2em;
    color: #333;
    margin: 0 auto;
    text-align: center;
}

    .menu-button:hover {
        color: #333; /* Màu chữ khi hover */
    }

    /* Thông tin hóa đơn */
    .table-card .recent-bill {
        font-size: 1em; /* Kích thước chữ cho thông tin hóa đơn */
        color: #666; /* Màu chữ cho thông tin hóa đơn */
    }

    /* Nút menu */
    .table-card .menu-button {
        cursor: pointer; /* Con trỏ khi hover */
        color: #666; /* Màu chữ cho nút menu */
        font-size: 2rem; /* Kích thước chữ cho nút menu */
        margin-right: 1vw; /* Khoảng cách bên phải cho nút menu */
    }

    .table-card .menu-button:hover {
        color: #333; /* Màu chữ khi hover */
    }

    .payment-button {
        display: inline-block;
        padding: 1vh 2vw; /* Padding cho nút thanh toán */
        background-color: #5cba47; /* Màu nền cho nút thanh toán */
        color: white; /* Màu chữ cho nút thanh toán */
        font-size: 1em; /* Kích thước chữ cho nút thanh toán */
        font-weight: bold; /* Chữ đậm */
        text-transform: uppercase; /* Chữ in hoa */
        border-radius: 0.8vw; /* Bo tròn góc cho nút thanh toán */
        border: none; /* Không có viền */
        cursor: pointer; /* Con trỏ khi hover */
        transition: background-color 0.3s ease; /* Hiệu ứng khi hover */
    }

    .payment-button:hover {
        background-color: #4a9d38; /* Màu nền khi hover */
    }

    .table-card .unpaid-count {
        position: absolute; /* Vị trí tuyệt đối cho số lượng hóa đơn chưa thanh toán */
        top: 10%; /* Đặt ở giữa theo chiều dọc của header */
        left: 10%; /* Căn bên phải */
        transform: translateY(-50%); /* Căn giữa theo chiều dọc */
        background-color: red; /* Màu nền cho số lượng hóa đơn chưa thanh toán */
        color: white; /* Màu chữ cho số lượng hóa đơn chưa thanh toán */
        padding: 0.5vh 0.5rem; /* Padding cho số lượng hóa đơn chưa thanh toán */
        border-radius: 50%; /* Bo tròn cho số lượng hóa đơn chưa thanh toán */
        font-size: 1.1rem; /* Kích thước chữ cho số lượng hóa đơn chưa thanh toán */
        font-weight: bold; /* Chữ đậm cho số lượng hóa đơn chưa thanh toán */
        display: block; /* Đảm bảo nó được hiển thị */
    }
    /* Modal Styles */
    /* Modal Background */
    .modal {
        display: none; /* Ẩn modal */
        position: fixed; /* Vị trí cố định cho modal */
        z-index: 999; /* Đặt z-index cao hơn */
        left: 0; /* Đặt từ trái */
        top: 0; /* Đặt từ trên */
        width: 100%; /* Chiều rộng 100% */
        height: 100%; /* Chiều cao 100% */
        background-color: rgba(0, 0, 0, 0.7); /* Màu nền cho modal */
        backdrop-filter: blur(8px); /* Làm mờ nền phía sau */
        display: flex; /* Sử dụng Flexbox */
        justify-content: center; /* Căn giữa nội dung modal */
        align-items: center; /* Căn giữa nội dung modal */
        transition: opacity 0.3s ease; /* Hiệu ứng chuyển tiếp cho hiển thị */
    }
    
    /* Modal Content */
    .modal-content {
        background-color: #ffffff; /* Màu nền cho nội dung modal */
        padding: 0.5rem; /* Padding cho nội dung modal */
        border-radius: 1.2vw; /* Bo tròn góc cho nội dung modal */
        width: 100%; /* Chiều rộng 30% */
        height:100%;
        max-width: 22rem; /* Chiều rộng tối đa 50% */
        box-shadow: 0 1.5vh 3vh rgba(0, 0, 0, 0.2); /* Bóng đổ cho nội dung modal */
        animation: slideIn 0.3s ease; /* Hiệu ứng slide in */
        max-height: 25rem; /* Chiều cao tối đa của modal */
        overflow-y: auto; /* Cho phép cuộn dọc nếu nội dung vượt quá chiều cao tối đa */
    }
    
    /* Modal Header */
    .modal-header {
        font-size: 1.3rem; /* Kích thước chữ cho tiêu đề modal */
        margin-bottom: 1vh;; /* Khoảng cách dưới cho tiêu đề modal */
        text-align: center; /* Căn giữa chữ cho tiêu đề modal */
        font-weight: bold; /* Chữ đậm cho tiêu đề modal */
        color: #003eff; /* Màu chữ cho tiêu đề modal */
    }
    
    /* Modal Body */
    .modal-body {
        font-size: 0.8rem; /* Kích thước chữ cho nội dung modal */
        color: #555; /* Màu chữ cho nội dung modal */
        line-height: 1.4; /* Khoảng cách giữa các dòng */
        display: flex; /* Sử dụng Flexbox để căn giữa nội dung */
        flex-direction: column; /* Đặt hướng theo cột */
        align-items: flex-start; /* Căn trái các nội dung */
        height: auto; /* Chiều cao tự động */
    }
    
    .modal-body h3 {
        font-size: 1.1rem; /* Kích thước chữ cho tiêu đề đơn hàng */
        margin-bottom: 10px; /* Khoảng cách dưới cho tiêu đề */
    }
    
    .modal-body p {
        margin: 5px 0; /* Khoảng cách cho các đoạn văn */
        font-size: 1rem; /* Kích thước chữ cho các đoạn văn */
    }
    
    .bill-item {
        padding: 10px 0; /* Khoảng cách trên và dưới cho mỗi đơn hàng */
        width: 100%; /* Đảm bảo sử dụng chiều rộng đầy đủ */
        display: flex; /* Sử dụng Flexbox cho căn chỉnh */
        justify-content: space-between; /* Đẩy các phần tử về hai bên */
        align-items: center; /* Căn giữa theo chiều dọc */
        text-align: left; /* Căn trái cho văn bản */
    }
    
    .bill-item button {
        margin-left: 10px; /* Khoảng cách giữa văn bản và nút */
    }
    
    .bill-item p {
        margin: 0; /* Đặt lại margin cho p để đường kẻ rõ hơn */
    }
    
    .bill-separator {
        border: 0.5px solid black; /* Đường kẻ */
        width: 100%; /* Đảm bảo chiều rộng của đường kẻ đầy đủ */
        margin: 10px 0; /* Thêm khoảng cách trên và dưới cho đường kẻ */
    }
    
    /* Close Button */
    .close-modal {
        position: absolute; /* Vị trí tuyệt đối cho nút đóng modal */
        top: 1vh; /* Đặt cách trên 1vh */
        right: 1.5vw; /* Đặt cách phải 1.5vw */
        font-size: 1vw; /* Kích thước chữ cho nút đóng modal */
        color: #333; /* Màu chữ cho nút đóng modal */
        cursor: pointer; /* Con trỏ khi hover */
        transition: color 0.3s; /* Hiệu ứng chuyển tiếp cho màu chữ */
    }
    
    .close-modal:hover {
        color: #ff3d3d; /* Màu chữ khi hover */
    }
    
    /* Nút trong modal */
    .modal-body button {
        background-color: #5cba47; /* Màu nền cho nút trong modal */
        color: white; /* Màu chữ cho nút trong modal */
        padding: 1vh 2vw; /* Padding cho nút */
        border: none; /* Không có viền */
        border-radius: 0.5vw; /* Bo tròn góc cho nút */
        font-size: 0.8rem; /* Kích thước chữ cho nút */
        cursor: pointer; /* Con trỏ khi hover */
        transition: background-color 0.3s ease;
        margin-top: 0.5rem;/* Hiệu ứng chuyển tiếp cho nền */
    }
    
    .modal-body button:hover {
        background-color: #4a9d38; /* Màu nền khi hover */
    }
    
    .items-container {
        margin-top: 10px; /* Khoảng cách trên cho container món ăn */
    }
    
    .items-table {
        width: 100%; /* Chiều rộng 100% của modal */
        border-collapse: collapse; /* Gộp các đường viền lại với nhau */
        margin-top: 0; /* Khoảng cách trên cho bảng */
    }
    
    .items-table th, .items-table td {
        border: 1px solid #ddd; /* Đường viền cho ô */
        padding: 8px; /* Padding cho ô */
        text-align: left; /* Căn trái chữ trong ô */
    }
    
    .items-table th {
        background-color: #f2f2f2; /* Màu nền cho hàng tiêu đề */
    }
    
    .total-row {
        margin-top: 10px; /* Khoảng cách trên cho tổng tiền */
        font-weight: bold; /* Chữ đậm cho tổng tiền */
        display: flex; /* Sử dụng Flexbox cho hàng tổng tiền */
        justify-content: space-between; /* Căn giữa các cột */
    }
    
    .item-row {
        display: flex; /* Sử dụng Flexbox cho mỗi hàng */
        justify-content: space-between; /* Căn giữa các cột */
        margin-bottom: 5px; /* Khoảng cách dưới cho mỗi hàng */
    }
    
    .item-name {
        flex: 2; /* Tên món chiếm 2 cột */
        text-align: left; /* Căn trái */
    }
    
    .item-quantity {
        flex: 1; /* Số lượng chiếm 1 cột */
        text-align: center; /* Căn giữa */
    }
    
    .item-price {
        flex: 1; /* Giá tiền chiếm 1 cột */
        text-align: right; /* Căn phải */
    }
    
    .items-header {
        display: flex; /* Sử dụng Flexbox cho tiêu đề hàng */
        font-weight: bold; /* Chữ đậm cho tiêu đề */
        margin-bottom: 10px; /* Khoảng cách dưới cho tiêu đề */
    }
    
    .total-row {
        display: flex; /* Sử dụng Flexbox cho tổng tiền */
        justify-content: space-between; /* Căn giữa các cột */
        margin-top: 10px; /* Khoảng cách trên cho tổng tiền */
        font-weight: bold; /* Chữ đậm cho tổng tiền */
    }
    
    /* Animation */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Footer */
    .footer {
        background-color: #003eff; /* Màu xanh header áp dụng cho footer */
        color: white;
        display: flex;
        justify-content: space-between;
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 10vh; /* Chiều cao của footer */
        z-index: 1000;
        align-items: center;
        padding: 0 2vw; /* Padding cho footer */
        box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1); /* Bóng đổ footer */
        font-size: 100%; /* Font chữ của footer giống header */
    }
    
    .footer span {
        font-size: 100%; /* Kích thước chữ cho các phần tử trong footer */
    }
    
    .footer .user-name {
        display: flex;
        align-items: center;
        padding: 1vh; /* Thêm padding cho user name */
    }
    
    .footer .user-name::before {
        margin-right: 1vw; /* Khoảng cách giữa icon và tên */
        font-size: 1.5vw; /* Kích thước chữ cho icon */
    }
    
    .current-datetime {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-right: 4vw; /* Khoảng cách bên phải cho current datetime */
    }
    
    .current-datetime .time {
        font-size: 1vw; /* Kích thước chữ cho thời gian */
        font-weight: bold; /* Chữ đậm cho thời gian */
    }
    
    .current-datetime .date {
        font-size: 1.2vw; /* Kích thước chữ cho ngày tháng */
    }
    
    /* Nút ở footer cũng có thể có hiệu ứng hover */
    .footer button {
        background: none;
        border: none;
        color: white;
        font-size: 1.4vw; /* Kích thước chữ cho nút trong footer */
        cursor: pointer;
        padding: 0.8vh 1.2vw; /* Padding cho nút trong footer */
        transition: all 0.3s ease;
        border-radius: 0.5vw; /* Bo tròn góc cho nút trong footer */
    }
    
    .footer button:hover {
        background-color: rgba(255, 255, 255, 0.2); /* Nền khi hover */
        box-shadow: 0 0.4vh 0.8vh rgba(0, 0, 0, 0.15); /* Bóng đổ khi hover */
        transform: translateY(-0.2vh); /* Đẩy nút lên khi hover */
    }

    
</style>
<script>
// Khai báo biến global để lưu giá trị bills
let currentBills = [];

// Hiển thị modal với các tùy chọn khi nhấn vào nút 3 chấm
function showModal(tableNumber, bills) {
    currentBills = bills; // Lưu bills vào biến global
    const modal = document.getElementById('modal');
    const modalContent = document.querySelector('.modal-body');
    modalContent.innerHTML = ''; // Làm trống nội dung modal

    // Hiển thị các tùy chọn khi nhấn nút 3 chấm
    modalContent.innerHTML += `
        <h3> Hành động:</h3>
        <ul class="option-list">
            <li><button onclick="createNewOrder(${tableNumber})">Tạo đơn mới cùng bàn</button></li>
            <li><button onclick="mergeOrders(${tableNumber})">Gộp đơn</button></li>
            <li><button onclick="splitOrder(${tableNumber})">Tách đơn</button></li>
            <li><button onclick="changeTable(${tableNumber})">Thay đổi bàn</button></li>
            <li><button id="viewOrderDetailsBtn" data-table-number="${tableNumber}">Xem thông tin đơn</button></li>
            <li><button id="cancelOrderBtn" data-table-number="${tableNumber}">Hủy đơn</button></li>
        </ul>
        <button onclick="closeModal()" style="margin-top: 10px;">Đóng</button>
    `;

    modal.style.display = 'flex'; // Hiển thị modal

    // Gán sự kiện click cho nút "Xem thông tin đơn"
    document.getElementById('viewOrderDetailsBtn').addEventListener('click', function() {
        const tableNumber = this.getAttribute('data-table-number');
        viewOrderDetails(tableNumber, currentBills); // Gọi hàm xem chi tiết đơn
    });

    // Gán sự kiện click cho nút "Hủy đơn"
    document.getElementById('cancelOrderBtn').addEventListener('click', function() {
        const tableNumber = this.getAttribute('data-table-number');
        showCancelOrderOptions(tableNumber, currentBills); // Gọi hàm hủy đơn
    });
}

// Hiển thị chi tiết đơn khi chọn "Xem thông tin đơn"
function viewOrderDetails(tableNumber, bills) {
    const modalContent = document.querySelector('.modal-body');
    modalContent.innerHTML = ''; // Làm trống nội dung modal

    if (bills.length > 1) {
        // Nếu có nhiều hơn 1 hóa đơn, hiển thị danh sách để chọn
        modalContent.innerHTML += `<h3>Chọn hóa đơn từ bàn ${tableNumber}:</h3>`;
        bills.forEach((bill, index) => {
            modalContent.innerHTML += `
                <div class="bill-item">
                    <p>Mã đơn #${bill.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(bill.TotalAmount)}</p>
                    <button class="bill-details-button" data-bill-index="${index}" data-table-number="${tableNumber}">Xem chi tiết</button>
                </div>
            `;

            if (index < bills.length - 1) {
                modalContent.innerHTML += `<div class="bill-separator"></div>`;
            }
        });
        modalContent.innerHTML += `<button onclick="showModal(${tableNumber}, currentBills)" style="margin-bottom: 10px;">Quay lại</button>`;

        // Gán sự kiện click cho các nút "Xem chi tiết"
        document.querySelectorAll('.bill-details-button').forEach(button => {
            button.addEventListener('click', function() {
                const billIndex = this.getAttribute('data-bill-index');
                showBillDetails(billIndex, tableNumber, bills); // Gọi hàm để hiển thị chi tiết hóa đơn
            });
        });

    } else if (bills.length === 1) {
        // Nếu chỉ có 1 hóa đơn, hiển thị chi tiết hóa đơn đó
        showBillDetails(0, tableNumber, bills);
    } else {
        modalContent.innerHTML = '<p>Chưa có đơn hàng</p>';
        modalContent.innerHTML += `<button onclick="closeModal()" style="margin-bottom: 10px;">Đóng</button>`;
    }
}
function showBillDetails(billIndex, tableNumber, bills) {
    const selectedBill = bills[billIndex];
    const modalContent = document.querySelector('.modal-body');

    modalContent.innerHTML = `
        <h3 style="text-align: center; color: #003eff; font-size: 1rem; margin-bottom: 1rem;">Chi tiết hóa đơn #${selectedBill.BillID}</h3>
        <p>Ngày tạo: ${new Date(selectedBill.Timeorder).toLocaleString('vi-VN')}</p>
        <p>Khách hàng: ${selectedBill.CustomerName || 'N/A'}</p>
        <p>Nhân viên: ${selectedBill.EmployeeName || 'N/A'}</p>
        <p>Tổng cộng: <span style="font-weight: bold; color: #ff4d4d;">${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(selectedBill.TotalPrice)}</span></p>
        <h4 style="margin: 0; color: #5cba47; text-align: center; font-size: 1rem;">Danh sách món</h4>
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2; text-align: left;">Tên món</th>
                    <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2; text-align: center;">Số lượng</th>
                    <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2; text-align: right;">Đơn giá</th>
                </tr>
            </thead>
            <tbody>
                ${
                    selectedBill.items && selectedBill.items.length > 0
                    ? selectedBill.items.map(item => `
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;">${item.ProductName}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${item.Quantity}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.UnitPrice)}</td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="3" style="border: 1px solid #ddd; padding: 8px; text-align: center;">Không có món hàng trong hóa đơn này</td></tr>'
                }
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 1.5rem;">
            <button onclick="showModal(${tableNumber}, currentBills)" style="padding: 10px 20px; background-color: #5cba47; color: white; border: none; border-radius: 5px; cursor: pointer;">Quay lại</button>
        </div>
    `;
}

// Các hàm xử lý cho các lựa chọn khác
function createNewOrder(tableNumber) {
    // Chuyển hướng đến trang order.php và truyền tableNumber qua URL
    window.location.href = `order.php?tableNumber=${tableNumber}`;
}

// Hàm hiển thị danh sách các hóa đơn của bàn hiện tại để chọn hóa đơn cần gộp đi
function mergeOrders(tableNumber) {
    const bills = currentBills; // Lấy bills từ biến toàn cục đã lưu trước đó

    // Kiểm tra nếu bàn có nhiều hơn 1 đơn thì hiển thị danh sách để chọn đơn gộp đi
    if (bills.length > 1) {
        const modalContent = document.querySelector('.modal-body');
        modalContent.innerHTML = ''; // Làm trống nội dung modal

        modalContent.innerHTML += `<h3>Chọn hóa đơn cần gộp từ bàn ${tableNumber}:</h3>`;
        bills.forEach((bill, index) => {
            modalContent.innerHTML += `
                <div class="bill-item">
                    <p>Mã đơn #${bill.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(bill.TotalAmount)}</p>
                    <button class="merge-bill-button" data-bill-id="${bill.BillID}" data-table-number="${tableNumber}">Chọn đơn này để gộp</button>
                </div>
            `;

            if (index < bills.length - 1) {
                modalContent.innerHTML += `<div class="bill-separator"></div>`;
            }
        });

        modalContent.innerHTML += `<button onclick="showModal(${tableNumber}, currentBills)" style="margin-top: 10px;">Quay lại</button>`;
        const modal = document.getElementById('modal');
        modal.style.display = 'flex'; // Hiển thị modal

        // Gán sự kiện click cho các nút chọn đơn để gộp đi
        document.querySelectorAll('.merge-bill-button').forEach(button => {
            button.addEventListener('click', function() {
                const billIDToMerge = this.getAttribute('data-bill-id');
                selectTargetOrder(tableNumber, billIDToMerge); // Gọi hàm để chọn đơn muốn gộp đến
            });
        });
    } else if (bills.length === 1) {
        // Nếu chỉ có 1 đơn, thực hiện gộp trực tiếp
        const billIDToMerge = bills[0].BillID;
        selectTargetOrder(tableNumber, billIDToMerge); // Gọi hàm để chọn đơn muốn gộp đến
    } else {
        alert('Không có hóa đơn để gộp.');
    }
}
// Hiển thị các hóa đơn có trạng thái = 0 để gộp vào (sau khi đã chọn hóa đơn muốn gộp đi)
function selectTargetOrder(tableNumber, billIDToMerge) {
    // Gọi server để lấy danh sách các hóa đơn khả dụng (bỏ qua hóa đơn muốn gộp đi)
    const data = { action: 'get_orders', billIDToExclude: billIDToMerge };
    
    fetch('merge_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.orders.length > 0) {
            const modal = document.getElementById('modal');
            const modalContent = document.querySelector('.modal-body');
            modalContent.innerHTML = ''; // Làm trống nội dung modal

            modalContent.innerHTML += `<h3>Chọn hóa đơn muốn gộp đến:</h3>`;
            data.orders.forEach((order) => {
                modalContent.innerHTML += `
                    <div class="order-item">
                        <p>Bàn #${order.TableNumber} - Mã đơn #${order.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(order.TotalAmount)}</p>
                        <button class="select-target-bill-button" data-target-bill-id="${order.BillID}" data-bill-id-to-merge="${billIDToMerge}" data-target-table-number="${order.TableNumber}">Gộp vào đơn này</button>
                    </div>
                `;
            });
            modalContent.innerHTML += `<button onclick="showModal(${tableNumber}, currentBills)" style="margin-top: 10px;">Quay lại</button>`;
            modal.style.display = 'flex'; // Hiển thị modal

            // Gán sự kiện click cho các nút chọn hóa đơn muốn gộp đến
            document.querySelectorAll('.select-target-bill-button').forEach(button => {
                button.addEventListener('click', function() {
                    const targetBillID = this.getAttribute('data-target-bill-id');
                    const billIDToMerge = this.getAttribute('data-bill-id-to-merge');
                    const targetTableNumber = this.getAttribute('data-target-table-number');
                    processMergeOrders(tableNumber, billIDToMerge, targetTableNumber, targetBillID);
                });
            });
        } else {
            alert('Không có hóa đơn khả dụng để gộp.');
        }
    })
    .catch(error => {
        console.error('Lỗi khi lấy danh sách hóa đơn khả dụng:', error);
    });
}

// Hàm xử lý gộp đơn khi đã chọn được cả hai đơn
function processMergeOrders(tableNumber, billIDToMerge, targetTableNumber, targetBillID) {
    const data = { action: 'merge', tableNumber, billIDToMerge, targetTableNumber, targetBillID };

    fetch('merge_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Gộp đơn thành công!');
            window.location.reload(); // Tải lại trang sau khi gộp thành công
        } else {
            alert('Có lỗi xảy ra khi gộp đơn: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        alert('Có lỗi xảy ra khi gộp đơn.');
    });
}


function splitOrder(tableNumber) {
    const unpaidBills = JSON.parse(document.querySelector(`.table-card[data-table-number="${tableNumber}"]`).getAttribute('data-unpaid-bills'));

    if (unpaidBills.length === 1) {
        const billID = unpaidBills[0].BillID;
        showItemsForSplit(billID, tableNumber);
    } else {
        let modalContent = `<h3>Chọn đơn cần tách cho bàn ${tableNumber}:</h3>`;
        unpaidBills.forEach((bill) => {
            modalContent += `
                <div class="bill-item">
                    <p>Mã đơn #${bill.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(bill.TotalPrice)}đ</p>
                    <button class="select-bill-button" onclick="showItemsForSplit(${bill.BillID}, ${tableNumber})">Chọn đơn này</button>
                </div>
            `;
        });

        modalContent += `<button onclick="showModal(${tableNumber}, currentBills)" class="back-button">Quay lại</button>`;

        document.querySelector('.modal-body').innerHTML = modalContent;
        document.querySelector('#modal').style.display = 'flex';
    }
}
function showItemsForSplit(billID, tableNumber) {
    fetch('split_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_items_for_split', billID }) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let itemSelection = `<h3>Chọn món cần tách từ đơn #${billID}:</h3>`;
            data.items.forEach(item => {
                itemSelection += `
                    <div class="item-split">
                        <label>
                            <input type="checkbox" name="splitItems" value="${item.InforBillID}" data-max-quantity="${item.Quantity}">
                            ${item.ProductName} (Số lượng: ${item.Quantity})
                        </label>
                        ${item.Quantity > 1 ? `<input type="number" min="1" max="${item.Quantity}" value="${item.Quantity}" class="split-quantity" data-inforbillid="${item.InforBillID}">` : ''}
                    </div>
                `;
            });

            itemSelection += `
                <button onclick="confirmSplit(${billID}, ${tableNumber})" class="split-button">Tách</button>
                <button onclick="showModal(${tableNumber}, currentBills)" class="back-button">Quay lại</button>
            `;
            
            document.querySelector('.modal-body').innerHTML = itemSelection;
            document.querySelector('#modal').style.display = 'flex';
        } else {
            alert('Có lỗi xảy ra khi lấy danh sách món: ' + data.message);
        }
    })
    .catch(error => console.error('Lỗi khi lấy danh sách món:', error));
}
function confirmSplit(billID, tableNumber) {
    const selectedItems = [];
    document.querySelectorAll('input[name="splitItems"]:checked').forEach(checkbox => {
        const inforBillID = checkbox.value;
        const maxQuantity = checkbox.getAttribute('data-max-quantity');
        const quantityInput = document.querySelector(`.split-quantity[data-inforbillid="${inforBillID}"]`);
        const quantityToSplit = quantityInput ? parseInt(quantityInput.value) : maxQuantity;

        selectedItems.push({ inforBillID, quantityToSplit });
    });

    if (selectedItems.length < 1) {
        alert('Vui lòng chọn ít nhất 1 món để tách.');
        return;
    }

    fetch('split_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'split_order',
            billID,
            tableNumber,
            selectedItems
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tách đơn thành công!');
            window.location.reload();
        } else {
            alert('Có lỗi xảy ra khi tách đơn: ' + data.message);
        }
    })
    .catch(error => console.error('Lỗi khi tách đơn:', error));
}

function changeTable(tableNumber) {
    // Kiểm tra xem có bao nhiêu đơn chưa thanh toán trên bàn
    const unpaidBills = JSON.parse(document.querySelector(`.table-card[data-table-number="${tableNumber}"]`).getAttribute('data-unpaid-bills'));

    // Nếu chỉ có 1 đơn thì chỉ cần hiển thị giao diện để chọn bàn
    if (unpaidBills.length === 1) {
        const billID = unpaidBills[0].BillID;
        selectNewTable(billID, tableNumber);
    } else {
        // Nếu có nhiều hơn 1 đơn, yêu cầu chọn đơn trước khi thay đổi bàn
        let modalContent = '<h3>Chọn đơn cần thay đổi bàn:</h3>';
        unpaidBills.forEach((bill) => {
            modalContent += `
                <div class="bill-item">
                    <p>Mã đơn #${bill.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(bill.TotalPrice)}đ</p>
                    <button class="select-bill-button" onclick="selectNewTable(${bill.BillID}, ${tableNumber})">Chọn đơn này</button>
                </div>
            `;
        });
        modalContent += `<button onclick="showModal(${tableNumber}, currentBills)" class="back-button">Quay lại</button>`;

        // Hiển thị modal
        document.querySelector('.modal-body').innerHTML = modalContent;
        document.querySelector('#modal').style.display = 'flex';
        
    }
}
function selectNewTable(billID, currentTableNumber) {
    // Gọi AJAX để lấy danh sách bàn từ cùng tệp change_table.php
    fetch('change_table.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_tables' }) // Gửi yêu cầu để lấy danh sách bàn
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let tableSelection = `<h3>Chọn bàn mới để di chuyển đơn #${billID}</h3>`;
            data.tables.forEach(table => {
                tableSelection += `
                    <div class="table-item">
                        <p>${table.TableName}</p>
                        <button class="select-table-button" onclick="confirmTableChange(${billID}, ${table.TableNumber}, ${currentTableNumber})">Chọn bàn này</button>
                    </div>
                `;
            });
            tableSelection += `<button onclick="showModal(${currentTableNumber})" class="back-button">Quay lại</button>`;

            // Hiển thị modal để chọn bàn mới
            document.querySelector('.modal-body').innerHTML = tableSelection;
            document.querySelector('#modal').style.display = 'flex';
        } else {
            alert('Có lỗi xảy ra khi lấy danh sách bàn: ' + data.message);
        }
    })
    .catch(error => console.error('Lỗi khi lấy danh sách bàn:', error));
}

function confirmTableChange(billID, newTableNumber, currentTableNumber) {
    const data = { action: 'change_table', billID, newTableNumber, currentTableNumber };

    fetch('change_table.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Thay đổi bàn thành công!');
            window.location.reload(); // Cập nhật giao diện sau khi thay đổi thành công
        } else {
            alert('Có lỗi xảy ra: ' + result.message);
        }
    })
    .catch(error => console.error('Lỗi khi thay đổi bàn:', error));
}



// Hiển thị các hóa đơn để chọn hủy
function showCancelOrderOptions(tableNumber, bills) {
    const modalContent = document.querySelector('.modal-body');
    modalContent.innerHTML = ''; // Làm trống nội dung modal

    if (bills.length > 1) {
        modalContent.innerHTML += `<h3>Chọn hóa đơn để hủy cho bàn ${tableNumber}:</h3>`;
        bills.forEach((bill, index) => {
            modalContent.innerHTML += `
                <div class="bill-item">
                    <p>Mã đơn #${bill.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(bill.TotalAmount)}</p>
                    <button class="cancel-bill-button" data-bill-id="${bill.BillID}" data-table-number="${tableNumber}">Hủy đơn này</button>
                </div>
            `;

            if (index < bills.length - 1) {
                modalContent.innerHTML += `<div class="bill-separator"></div>`;
            }
        });
        modalContent.innerHTML += `<button onclick="showModal(${tableNumber}, currentBills)" style="margin-top: 10px;">Quay lại</button>`;

        // Gán sự kiện click cho các nút "Hủy đơn"
        document.querySelectorAll('.cancel-bill-button').forEach(button => {
            button.addEventListener('click', function() {
                const billID = this.getAttribute('data-bill-id');
                cancelOrder(tableNumber, billID); // Gọi hàm để hủy đơn
            });
        });
    } else if (bills.length === 1) {
        // Nếu chỉ có 1 hóa đơn, hủy trực tiếp đơn đó
        cancelOrder(tableNumber, bills[0].BillID);
    } else {
        modalContent.innerHTML = '<p>Không có đơn hàng để hủy</p>';
        modalContent.innerHTML += `<button onclick="showModal(${tableNumber}, currentBills)" style="margin-top: 10px;">Quay lại</button>`;
    }
}

// Hàm xử lý hủy đơn
function cancelOrder(tableNumber, billID) {
    // Xác nhận trước khi hủy đơn
    const confirmCancel = confirm(`Bạn có chắc muốn hủy đơn #${billID} của bàn ${tableNumber}?`);
    if (!confirmCancel) {
        window.location.href = 'index.php'; // Chuyển hướng về trang index nếu cancel
        return; // Dừng hàm lại
    }

    // Gửi yêu cầu hủy đơn đến server
    const data = { tableNumber: tableNumber, billID: billID };

    fetch('cancel_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Đơn đã được hủy thành công!');
            window.location.reload(); // Tải lại trang sau khi hủy thành công
        } else if (data.redirect) {
            // Nếu không có quyền, chuyển hướng về trang index
            alert(data.message);
            window.location.href = 'index.php'; // Chuyển hướng về index
        } else {
            alert('Có lỗi xảy ra khi hủy đơn: ' + data.message);
        }
    }).catch(error => {
        console.error('Lỗi:', error);
        alert('Có lỗi xảy ra khi hủy đơn.');
    });
}



// Đóng modal
function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

    // Chuyển đổi giữa các nội dung
function toggleContent(contentId) {
    const contents = document.querySelectorAll('.content');
    contents.forEach(content => {
        content.style.display = 'none'; // Ẩn tất cả các nội dung
    });

    // Hiện nội dung được chọn
    const selectedContent = document.getElementById(contentId);
    if (selectedContent) {
        selectedContent.style.display = 'block';
    }

    // Cập nhật trạng thái nút
    document.querySelectorAll('.header-left button').forEach(btn => {
        btn.classList.remove('active'); // Bỏ trạng thái active khỏi tất cả các nút
    });
    const activeButton = document.querySelector(`[data-target="${contentId}"]`);
    if (activeButton) {
        activeButton.classList.add('active'); // Thêm trạng thái active cho nút được chọn
    }
}

// Khi trang được tải, thực hiện các sự kiện
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.style.display = 'none'; // Ẩn modal khi tải trang
    }

    // Gán sự kiện click cho các nút menu
    const menuButtons = [
        { buttonId: 'menuBtn', contentId: 'menu' },
        { buttonId: 'allOrdersBtn', contentId: 'allbill' },
        { buttonId: 'tableMapBtn', contentId: 'tablelayout' }
    ];

    menuButtons.forEach(({ buttonId, contentId }) => {
        const button = document.getElementById(buttonId);
        if (button) {
            button.addEventListener('click', () => {
                toggleContent(contentId); // Hiện phần nội dung tương ứng khi nút được nhấn
                if (contentId === 'menu') {
                    showMenuDetail('menu1'); // Hiển thị chi tiết của menu1 khi mở menu
                }
            });
        }
    });

    // Gán sự kiện click cho các thẻ bàn
    document.querySelectorAll('.table-card').forEach(card => {
        const tableNumber = card.getAttribute('data-table-number');
        const unpaidBills = JSON.parse(card.getAttribute('data-unpaid-bills')) || [];

        const menuButton = card.querySelector('.menu-button');
        if (menuButton) {
            menuButton.addEventListener('click', (e) => {
    e.stopPropagation(); // Ngăn chặn sự kiện click ở thẻ cha
    showModal(tableNumber, unpaidBills); // Hiển thị modal với hóa đơn
});
        }
    });

    // Mặc định hiển thị phần "Tất cả đơn" khi tải trang
    toggleContent('allbill');
});

    
    // Hàm tải nội dung từ file
    function loadContent(file) {
        const xhr = new XMLHttpRequest();
        xhr.open("GET", file, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                document.getElementById('menu-detail').innerHTML = xhr.responseText; // Cập nhật nội dung
            } else if (xhr.readyState === 4) {
                alert("Có lỗi xảy ra khi tải nội dung."); // Thông báo lỗi nếu cần
            }
        };
        xhr.send();
    }
    
    function openShift() {
        const startAmount = document.getElementById('startAmount').value;
        if (startAmount) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "openshift.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    // Kiểm tra và hiển thị thông báo mở ca thành công
                    if (response.success) {
                        alert("Mở ca thành công!"); // Thông báo mở ca thành công
    
                        // Refresh lại trang để hiển thị thông tin mới
                        location.reload(); // Reload lại trang
                    } else {
                        alert("Có lỗi xảy ra khi mở ca. Vui lòng thử lại.");
                    }
                }
            };
            xhr.send("startAmount=" + startAmount);
        } else {
            alert('Vui lòng nhập số tiền đầu ca.');
        }
    }


    // Hàm kết ca
function closeShift() {
    const endAmount = document.getElementById('endAmount').value; // Lấy số tiền cuối ca
    if (endAmount) {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "closeshift.php", true); // Gọi file closeshift.php
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert("Kết ca thành công!"); // Thông báo kết ca thành công
                        location.reload(); // Tải lại trang
                    } else {
                        alert(response.message || "Có lỗi xảy ra khi kết ca. Vui lòng thử lại.");
                    }
                } catch (error) {
                    console.error("Lỗi phân tích JSON: ", error);
                    alert("Phản hồi không hợp lệ từ máy chủ.");
                }
            }
        };
        xhr.send("endAmount=" + encodeURIComponent(endAmount)); // Gửi số tiền cuối ca lên server
    } else {
        alert('Vui lòng nhập số tiền cuối ca.'); // Thông báo nếu không nhập số tiền
    }
}


    // Hàm hiển thị thông tin ca làm việc
function showMenuDetail(menu) {
    const menuDetail = document.getElementById('menu-detail'); // Lấy phần tử chi tiết menu
    
    switch (menu) {
        case 'menu1':
            // Kiểm tra nếu ca đang mở
            if (<?php echo isset($_SESSION['shift_opened']) && $_SESSION['shift_opened'] ? 'true' : 'false'; ?>) {
                loadContent('shiftInfo.php'); // Nếu ca đang mở, tải nội dung từ shiftInfo.php
            } else {
                loadContent('openshift.php'); // Nếu ca chưa mở, tải nội dung từ openshift.php
            }
            break;
        case 'menu2':
            loadContent('handoverHistory.php'); 
            break;
        case 'menu3':
            loadContent('paymentmoney.php'); 
            break;
        case 'menu4':
            loadContent('receiptmoney.php'); 
            break;
        case 'menu5':
            loadContent('bill.php'); 
            break;
        case 'menu6':
            loadContent('logout.php'); 
            break;
    }
}

// Hàm để tải nội dung từ file khác
function loadContent(url) {
    const menuDetail = document.getElementById('menu-detail');
    
    // Sử dụng Fetch API để tải nội dung từ URL
    fetch(url)
        .then(response => response.text())
        .then(data => {
            menuDetail.innerHTML = data; // Thay thế nội dung hiện tại bằng dữ liệu tải vào

            // Sau khi nội dung đã được load, kiểm tra và gán lại sự kiện nếu cần thiết
            document.querySelectorAll('.toggle-button').forEach(button => {
                button.addEventListener('click', function () {
                    toggleShiftDetails(this.getAttribute('data-target')); // Gọi hàm khi nhấn nút toggle để hiển thị chi tiết bàn giao ca
                });
            });
        })
        .catch(error => {
            console.error('Lỗi khi tải nội dung:', error);
        });
}

// Định nghĩa hàm toggleShiftDetails
function toggleShiftDetails(id) {
    const shiftDetails = document.getElementById(id);
    if (shiftDetails) {
        if (shiftDetails.style.display === 'none' || !shiftDetails.style.display) {
            shiftDetails.style.display = 'block'; // Hiển thị chi tiết nếu đang ẩn
            document.querySelector(`button[data-target="${id}"]`).innerHTML = '&and;'; // Đổi biểu tượng sang mũi tên xuống
        } else {
            shiftDetails.style.display = 'none'; // Ẩn chi tiết nếu đang hiển thị
            document.querySelector(`button[data-target="${id}"]`).innerHTML = '&gt;'; // Đổi biểu tượng sang mũi tên phải
        }
    } else {
        console.error("Không tìm thấy phần tử chi tiết ca làm việc.");
    }
}

    function toggleBillDetails(id) {
        const detailsRow = document.getElementById(id);
        const button = document.querySelector(`button[onclick="toggleBillDetails('${id}')"]`);

        if (detailsRow) {
            if (detailsRow.style.display === 'none' || !detailsRow.style.display) {
                detailsRow.style.display = 'table-row'; // Hiển thị chi tiết
                button.innerHTML = '&and;'; // Đổi biểu tượng
            } else {
                detailsRow.style.display = 'none'; // Ẩn chi tiết
                button.innerHTML = '&gt;'; // Đổi biểu tượng
            }
        } else {
            console.error('Không tìm thấy phần tử chi tiết hóa đơn với ID:', id);
        }
    }


    // Cập nhật thời gian hiện tại
    function updateDateTime() {
        const now = new Date();
        const time = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const date = now.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
        document.getElementById('time').innerHTML = time; // Cập nhật thời gian
        document.getElementById('date').innerHTML = date; // Cập nhật ngày
    }

    setInterval(updateDateTime, 1000); // Cập nhật thời gian mỗi giây






// Xử lý sự kiện khi nhấn vào thẻ bàn
function handleTableClick(tableNumber, unpaidBills) {
    if (unpaidBills.length === 0) {
        // Nếu không có hóa đơn, điều hướng đến order.php để tạo mới
        window.location.href = `order.php?tableNumber=${tableNumber}`;
    } else if (unpaidBills.length === 1) {
        // Nếu có 1 hóa đơn, điều hướng đến order.php với billId của hóa đơn đó
        window.location.href = `order.php?tableNumber=${tableNumber}&billId=${unpaidBills[0].BillID}`;
    } else {
        // Nếu có nhiều hóa đơn, hiển thị modal chọn hóa đơn
        showBillSelectionModal(tableNumber, unpaidBills);
    }
}

// Hiển thị modal với danh sách hóa đơn
function showBillSelectionModal(tableNumber, unpaidBills) {
    const modal = document.getElementById('bill-selection-modal'); // Modal mới
    const modalContent = document.getElementById('bill-selection-content'); // Nội dung modal
    modalContent.style.backgroundColor = '#f5f5f5'; // Ví dụ: đổi màu nền
    modalContent.style.padding = '15px'; // Ví dụ: thêm khoảng đệm
    modalContent.style.borderRadius = '8px'; // Ví dụ: bo tròn góc
    modalContent.innerHTML = ''; // Xóa nội dung cũ

    unpaidBills.forEach(bill => {
        const billOption = document.createElement('button');
        billOption.textContent = `Hóa đơn #${bill.BillID} - Tổng: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(bill.TotalAmount)} đ`;
        billOption.style.display = 'block';
        billOption.style.marginBottom = '10px';
        billOption.style.width = '100%';
        billOption.style.background = '#7db7ff';
        billOption.style.borderRadius = '8px';
        billOption.addEventListener('click', () => {
            goToOrderPage(tableNumber, bill.BillID); // Điều hướng khi chọn hóa đơn
            closeBillSelectionModal(); // Đóng modal sau khi chọn
        });
        modalContent.appendChild(billOption); // Thêm lựa chọn vào modal
    });

    modal.style.display = 'block'; // Hiển thị modal
    document.getElementById('modal-overlay').style.display = 'block'; // Hiển thị overlay
}

// Đóng modal chọn hóa đơn
function closeBillSelectionModal() {
    document.getElementById('bill-selection-modal').style.display = 'none';
    document.getElementById('modal-overlay').style.display = 'none';
}

// Hàm điều hướng đến order.php với tableNumber và billId (nếu có)
function goToOrderPage(tableNumber, billId = null) {
    if (tableNumber) {
        let url = `order.php?tableNumber=${tableNumber}`;
        if (billId) {
            url += `&billId=${billId}`;
        }
        window.location.href = url; // Điều hướng đúng theo số bàn và hóa đơn (nếu có)
    } else {
        console.error("Không có ID bàn hoặc hóa đơn được cung cấp."); // Lỗi chỉ xuất hiện khi không có ID bàn
    }
}


// Khi trang được tải, thực hiện các sự kiện
document.addEventListener('DOMContentLoaded', () => {
    // Gán sự kiện click cho các thẻ bàn
    document.querySelectorAll('.table-card').forEach(card => {
        const tableNumber = card.getAttribute('data-table-number');
        const unpaidBills = JSON.parse(card.getAttribute('data-unpaid-bills')) || [];

        card.addEventListener('click', (e) => {
            if (!e.target.classList.contains('menu-button')) {
                handleTableClick(tableNumber, unpaidBills); // Gọi hàm khi nhấn vào bàn
            }
        });
    });
});


</script>

</head>
<body>

<div id="bill-selection-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width: 100%;height:100;max-width: 25rem;max-height: 300px; background-color: #fff; padding: 0.5rem; box-shadow: 0 1.5vh 3vh rgba(0, 0, 0, 0.2); z-index:1000; border-radius: 1.2vw;animation: slideInFromRight 0.3s ease; overflow-y: auto;">
    <div style="font-size: 1.2rem; margin-bottom: 1vh; text-align: center; font-weight: bold; color: #003eff;">Chọn hóa đơn</div>
    <div id="bill-selection-content" style="margin-bottom: 3vh;margin-top: 3vh;"></div> <!-- Nội dung danh sách hóa đơn -->
    <button onclick="closeBillSelectionModal()" style="    background-color: #5cba47;
    color: white;
    padding: 1vh 2vw;
    border: none;
    border-radius: 0.5vw;
    font-size: 0.8rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
    margin-top: 0.5rem;">Đóng</button> <!-- Nút đóng modal -->
</div>

<!-- Overlay cho modal -->
<div id="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0, 0, 0, 0.7); z-index:999;backdrop-filter: blur(8px);    justify-content: center;align-items: center;
    transition: opacity 0.3s ease;"></div>



    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <button id="menuBtn">☰ Menu</button>
            <button id="allOrdersBtn" class="active">Tất cả đơn</button>
            <button id="tableMapBtn">Sơ đồ bàn</button>
        </div>
    </div>

    <div class="content" id="menu" style="display: none;">
        <div class="menu-container">
            <div class="menu-list">
                <h2>Danh mục</h2>
                <ul>
                    <li onclick="showMenuDetail('menu1')">Ca làm việc</li>
                    <li onclick="showMenuDetail('menu2')">Bàn giao ca</li>
                    <li onclick="showMenuDetail('menu3')">Phiếu chi</li>
                    <li onclick="showMenuDetail('menu4')">Phiếu thu</li>
                    <li onclick="showMenuDetail('menu5')">Hóa đơn</li>
                    <li onclick="showMenuDetail('menu6')">Đăng xuất</li>
                </ul>
            </div>
            <div class="menu-detail" id="menu-detail">
                <div id="shiftInfo" style="display: none;"></div>
            </div>
        </div>
    </div>

<div class="content" id="allbill" style="display: none;">
    <div class="area-section">
        <h2>TẤT CẢ HÓA ĐƠN</h2>
        <div class="table-container">
            <?php foreach ($tables as $table): ?>
                <?php if (!empty($bills[$table['TableNumber']])): ?>
                    <div class="table-card" data-table-number="<?php echo $table['TableNumber']; ?>" data-unpaid-bills='<?php echo json_encode($bills[$table['TableNumber']] ?? []); ?>'>
                        <div class="table-card-header">
                            <h3><?php echo $table['TableName']; ?></h3>
                            <span class="menu-button">&#8942;</span>
                        </div>
                        <div class="recent-bill">
                            <p>Khách hàng: <?php echo $bills[$table['TableNumber']][0]['CustomerName']; ?></p>
                            <p>Tổng tiền: <?php echo number_format($bills[$table['TableNumber']][0]['TotalPrice'], 0, ',', '.'); ?> đ</p>
                            <p>
                                <i class="fas fa-clock" style="margin-right: 5px; color: #666;"></i>
                                <?php
                                $Timeorder = strtotime($bills[$table['TableNumber']][0]['Timeorder']);
                                echo date('H:i:s - d/m/Y', $Timeorder);
                                ?>
                            </p>
                        </div>
                        <?php if (count($bills[$table['TableNumber']]) > 1): ?>
                            <div class="unpaid-count"><?php echo count($bills[$table['TableNumber']]); ?></div>
                        <?php endif; ?>
                        <button class="payment-button">Thanh toán</button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="content" id="tablelayout" style="display: none;">
    <?php foreach ($areas as $areaName => $tables): ?>
        <div class="area-section">
            <h2><?php echo $areaName; ?></h2>
            <div class="table-container">
                <?php foreach ($tables as $table): ?>
                    <div class="table-card" data-table-number="<?php echo $table['TableNumber']; ?>" data-unpaid-bills='<?php echo json_encode($bills[$table['TableNumber']] ?? []); ?>'>
                        <div class="table-card-header">
                            <h3><?php echo $table['TableName']; ?></h3>
                            <span class="menu-button">&#8942;</span>
                        </div>
                        <?php if (!empty($bills[$table['TableNumber']])): ?>
                            <div class="recent-bill">
                                <p>Khách hàng: <?php echo $bills[$table['TableNumber']][0]['CustomerName']; ?></p>
                                <p>Tổng tiền: <?php echo number_format($bills[$table['TableNumber']][0]['TotalPrice'], 0, ',', '.'); ?> đ</p>
                                <p>
                                    <i class="fas fa-clock" style="margin-right: 5px; color: #666;"></i>
                                    <?php
                                    $Timeorder = strtotime($bills[$table['TableNumber']][0]['Timeorder']);
                                    echo date('H:i:s - d/m/Y', $Timeorder);
                                    ?>
                                </p>
                            </div>
                            <?php if (count($bills[$table['TableNumber']]) > 1): ?>
                                <div class="unpaid-count"><?php echo count($bills[$table['TableNumber']]); ?></div>
                            <?php endif; ?>
                            <button class="payment-button">Thanh toán</button>
                        <?php else: ?>
                            <div class="empty">Trống</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>


    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Thông tin đơn hàng</div>
            <div class="modal-body"></div>
        </div>
    </div>

    <div class="footer">
        <span class="user-name" style="display: flex; align-items: center;">
            <i class="fas fa-user" style="margin-right: 8px; font-size: 20px;"></i>
            <?php 
            if (isset($_SESSION['full_name'])) {
                echo $_SESSION['full_name']; 
            } 
            ?>
        </span>
        <span class="shop-name">ME COFFEE</span>
        <div class="current-datetime" style="display: flex; flex-direction: column; align-items: center;">
            <span class="time" id="time" style="font-size: 16px; font-weight: bold;"></span>
            <span class="date" id="date" style="font-size: 14px;"></span>
        </div>
    </div>
</body>
</html>


