<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    die("Vui lòng đăng nhập để xem hóa đơn.");
}

// Kiểm tra nếu ca làm việc hiện tại đã mở
$shiftOpened = false;
$workShiftQuery = "SELECT ShiftID, ShiftStartTime, ShiftEndTime FROM WorkShift WHERE EmployeeID = ? AND ShiftEndTime IS NULL";
$stmtWorkShift = $conn->prepare($workShiftQuery);
$stmtWorkShift->bind_param('i', $_SESSION['user_id']);
$stmtWorkShift->execute();
$stmtWorkShift->bind_result($shiftID, $shiftStartTime, $shiftEndTime);

// Kiểm tra xem ca làm việc có mở không
if ($stmtWorkShift->fetch()) {
    $shiftOpened = true;
    $currentShift = [
        'ShiftID' => $shiftID,
        'ShiftStartTime' => $shiftStartTime,
        'ShiftEndTime' => $shiftEndTime
    ];
}
$stmtWorkShift->close(); // Đóng truy vấn sau khi xử lý xong

// Nếu ca làm việc chưa kết thúc, sử dụng thời gian hiện tại
$endTime = $currentShift['ShiftEndTime'] ?? date('Y-m-d H:i:s');
?>
<!-- Hiển thị danh sách hóa đơn đã thanh toán -->
<div class="bill-container" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;">
    <h3 style="color: blue; font-size: 3.5vh;text-align: center;">Hóa đơn</h3>
    <div style="border-radius: 1vw; background:white; margin-bottom: 15px;overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border-bottom: 1px solid #000; padding: 10px;text-align: center;">Hóa đơn</th>
                    <th style="border-bottom: 1px solid #000; padding: 10px;text-align: center;">Thời gian</th>
                    <th style="border-bottom: 1px solid #000; padding: 10px;text-align: center;">Phục vụ</th>
                    <th style="border-bottom: 1px solid #000; padding: 10px;text-align: center;">Thu ngân</th>
                    <th style="border-bottom: 1px solid #000; padding: 10px;text-align: center;">Số tiền</th>
                    <th style="border-bottom: 1px solid #000; padding: 10px;text-align: center;">Phương thức</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($shiftOpened) {
                // Truy vấn lấy hóa đơn đã thanh toán trong ca làm việc hiện tại của nhân viên
                $paidBillsQuery = "SELECT BillID, Timeorder, TotalAmount, EmployeeOrder, PaymentMethodID, Timepayment, EmployeePayment 
                                   FROM Bills 
                                   WHERE EmployeePayment = ? 
                                   AND Status = 1 
                                   AND Timepayment BETWEEN ? AND ?";
                $stmtBills = $conn->prepare($paidBillsQuery);
                $stmtBills->bind_param('iss', $_SESSION['user_id'], $currentShift['ShiftStartTime'], $endTime);
                $stmtBills->execute();
                $stmtBills->bind_result($billID, $timeorder, $totalAmount, $employeeOrder, $paymentMethodID, $timepayment, $employeePayment);

                $bills = [];
                while ($stmtBills->fetch()) {
                    $bills[] = [
                        'BillID' => $billID,
                        'Timeorder' => $timeorder,
                        'TotalAmount' => $totalAmount,
                        'EmployeeOrder' => $employeeOrder,
                        'PaymentMethodID' => $paymentMethodID,
                        'Timepayment' => $timepayment,
                        'EmployeePayment' => $employeePayment
                    ];
                }
                $stmtBills->close(); // Đóng truy vấn sau khi hoàn thành

                if (!empty($bills)) {
                    foreach ($bills as $bill) {
                        // Lấy tên nhân viên phục vụ và thu ngân
                        $employeeOrderName = getEmployeeName($conn, $bill['EmployeeOrder']);
                        $employeePaymentName = getEmployeeName($conn, $bill['EmployeePayment']);

                        // Lấy tên phương thức thanh toán
                        $paymentMethodName = getPaymentMethodName($conn, $bill['PaymentMethodID']);

                        // Hiển thị hóa đơn dưới dạng bảng
                        echo '<tr style="border-bottom: 1px solid #ddd;">';
                        echo '<td style="padding: 10px; text-align: center;">#' . $bill['BillID'] . '</td>';
                        echo '<td style="padding: 10px; text-align: center;">' . date('H:i:s d/m/Y', strtotime($bill['Timeorder'])) . ' <br>' . date('H:i:s d/m/Y', strtotime($bill['Timepayment'])) . '</td>';
                        echo '<td style="padding: 10px; text-align: center;">' . $employeeOrderName . '</td>';
                        echo '<td style="padding: 10px; text-align: center;">' . $employeePaymentName . '</td>';
                        echo '<td style="padding: 10px; text-align: center;">' . number_format($bill['TotalAmount'], 0, ',', '.') . ' đ</td>';
                        echo '<td style="padding: 10px; text-align: center;">' . $paymentMethodName . '</td>';
                        echo '<td style="padding: 10px; text-align: center;"><button class="toggle-button" onclick="toggleBillDetails(\'details-' . $bill['BillID'] . '\')">&gt;</button></td>';
                        echo '</tr>';

                        // Thêm chi tiết hóa đơn (ẩn lúc đầu)
                        echo '<tr id="details-' . $bill['BillID'] . '" style="display: none; background-color: #f9f9f9;">'; 
                        echo '<td colspan="7" style="padding: 10px;">';
                        echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th style="border-bottom: 1px solid #ddd; padding: 10px; text-align: center;">Tên món</th>';
                        echo '<th style="border-bottom: 1px solid #ddd; padding: 10px; text-align: center;">Số lượng</th>';
                        echo '<th style="border-bottom: 1px solid #ddd; padding: 10px; text-align: center;">Đơn giá</th>';
                        echo '<th style="border-bottom: 1px solid #ddd; padding: 10px; text-align: center;">Tổng</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';

                        // Truy vấn chi tiết hóa đơn
                        $billDetailsQuery = "SELECT ProductID, Quantity, UnitPrice, TotalPrice FROM InforBill WHERE BillID = ?";
                        $stmtDetails = $conn->prepare($billDetailsQuery);
                        $stmtDetails->bind_param('i', $bill['BillID']);
                        $stmtDetails->execute();
                        $stmtDetails->store_result(); 
                        $stmtDetails->bind_result($ProductID, $quantity, $unitPrice, $totalPrice);

                        while ($stmtDetails->fetch()) {
                            // Lấy tên sản phẩm từ bảng `product`
                            $ProductName = getProductName($conn, $ProductID);

                            echo '<tr>';
                            echo '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . $ProductName . '</td>';
                            echo '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . $quantity . '</td>';
                            echo '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . number_format($unitPrice, 0, ',', '.') . ' đ</td>';
                            echo '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . number_format($totalPrice, 0, ',', '.') . ' đ</td>';
                            echo '</tr>';
                        }
                        $stmtDetails->close(); // Đóng truy vấn chi tiết hóa đơn

                        echo '</tbody>';
                        echo '</table>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo "<tr><td colspan='7' style='padding: 10px; text-align: center;'>Không có hóa đơn đã thanh toán trong ca làm việc này.</td></tr>";
                }
            } else {
                echo '<tr><td colspan="7" style="padding: 10px; text-align: center;">Bạn cần mở ca làm việc để xem hóa đơn</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>


<script>
    // Định nghĩa hàm toggleBillDetails
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
</script>

<?php
// Hàm lấy tên nhân viên từ EmployeeID
function getEmployeeName($conn, $employeeID) {
    $query = "SELECT FullName FROM Employees WHERE EmployeeID = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die('Lỗi truy vấn SQL khi lấy tên nhân viên: ' . $conn->error);
    }

    $stmt->bind_param('i', $employeeID);
    $stmt->execute();
    $stmt->bind_result($fullName);
    $stmt->fetch();
    $stmt->close();
    return $fullName;
}

// Hàm lấy tên phương thức thanh toán từ PaymentMethodID
function getPaymentMethodName($conn, $paymentMethodID) {
    $query = "SELECT PaymentMethodName FROM PaymentMethods WHERE PaymentMethodID = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die('Lỗi truy vấn SQL khi lấy phương thức thanh toán: ' . $conn->error);
    }

    $stmt->bind_param('i', $paymentMethodID);
    $stmt->execute();
    $stmt->bind_result($paymentMethodName);
    $stmt->fetch();
    $stmt->close();
    return $paymentMethodName;
}

// Hàm lấy tên sản phẩm từ ProductID
function getProductName($conn, $ProductID) {
    $query = "SELECT ProductName FROM Product WHERE ProductID = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die('Lỗi truy vấn SQL khi lấy tên sản phẩm: ' . $conn->error);
    }

    $stmt->bind_param('i', $ProductID);
    $stmt->execute();
    $stmt->bind_result($ProductName);
    $stmt->fetch();
    $stmt->close();
    return $ProductName;
}

$conn->close(); // Đóng kết nối sau khi xử lý xong
?>
