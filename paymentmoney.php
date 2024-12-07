<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Khởi tạo session
include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    die("Vui lòng đăng nhập để thực hiện giao dịch.");
}

// Kiểm tra ca làm việc hiện tại
$shiftOpened = false;
$workShiftQuery = "SELECT ShiftID, ShiftStartTime, StartAmount, ShiftEndTime 
                   FROM WorkShift 
                   WHERE EmployeeID = ? AND ShiftEndTime IS NULL";
$stmtWorkShift = $conn->prepare($workShiftQuery);
$stmtWorkShift->bind_param('i', $_SESSION['user_id']);
$stmtWorkShift->execute();
$stmtWorkShift->bind_result($shiftID, $shiftStartTime, $startAmount, $shiftEndTime);

if ($stmtWorkShift->fetch()) {
    $shiftOpened = true;
    $currentShift = [
        'ShiftID' => $shiftID,
        'ShiftStartTime' => $shiftStartTime,
        'StartAmount' => $startAmount,
        'ShiftEndTime' => $shiftEndTime
    ];
}
$stmtWorkShift->close(); // Đóng truy vấn sau khi xử lý xong

$successMessage = '';
$errorMessage = '';

// Xử lý rút tiền (chỉ khi ca đang mở)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $shiftOpened) {
    $numberMoney = $_POST['numberMoney'];
    $note = $_POST['note'];
    $employee = $_SESSION['user_id'];
    $action = 0; // Phiếu chi (rút tiền)
    $date = date('Y-m-d H:i:s');

    // Đảm bảo số tiền là số âm nếu người dùng nhập dương
    if ($numberMoney > 0) {
        $numberMoney = -abs($numberMoney); // Chuyển số dương thành số âm
    }

    // Tính tổng hóa đơn đã thanh toán trong ca
    $totalPaidBillsQuery = "
        SELECT COALESCE(SUM(TotalAmount), 0) AS totalPaidBills 
        FROM Bills 
        WHERE EmployeePayment = ? AND Timepayment BETWEEN ? AND NOW()";
    $stmtBills = $conn->prepare($totalPaidBillsQuery);
    $stmtBills->bind_param('is', $_SESSION['user_id'], $currentShift['ShiftStartTime']);
    $stmtBills->execute();
    $stmtBills->bind_result($totalPaidBills);
    $stmtBills->fetch();
    $stmtBills->close();

    // Tính tổng tiền thu trong ca hiện tại (Action = 1)
    $totalReceivedQuery = "
        SELECT COALESCE(SUM(NumberMoney), 0) 
        FROM money 
        WHERE Employee = ? AND Action = 1 AND Date BETWEEN ? AND NOW()";
    $stmtReceived = $conn->prepare($totalReceivedQuery);
    $stmtReceived->bind_param('is', $_SESSION['user_id'], $currentShift['ShiftStartTime']);
    $stmtReceived->execute();
    $stmtReceived->bind_result($totalReceived);
    $stmtReceived->fetch();
    $stmtReceived->close();

    // Tính tổng tiền chi trong ca hiện tại (Action = 0)
    $totalSpentQuery = "
        SELECT COALESCE(SUM(NumberMoney), 0) 
        FROM money 
        WHERE Employee = ? AND Action = 0 AND Date BETWEEN ? AND NOW()";
    $stmtSpent = $conn->prepare($totalSpentQuery);
    $stmtSpent->bind_param('is', $_SESSION['user_id'], $currentShift['ShiftStartTime']);
    $stmtSpent->execute();
    $stmtSpent->bind_result($totalSpent);
    $stmtSpent->fetch();
    $stmtSpent->close();

    // Tính tổng tiền khả dụng
    $availableMoney = $currentShift['StartAmount'] + $totalPaidBills + $totalReceived + $totalSpent;

    // Kiểm tra nếu số tiền muốn rút <= số tiền khả dụng
    if ($availableMoney >= abs($numberMoney)) {
        // Thực hiện rút tiền
        $query = "INSERT INTO money (NumberMoney, Date, Employee, Note, Action) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            die('Lỗi truy vấn SQL: ' . $conn->error);
        }
        $stmt->bind_param('dsssi', $numberMoney, $date, $employee, $note, $action);
        
        if ($stmt->execute()) {
            $successMessage = 'Thành công: Giao dịch phiếu chi đã được lưu.';
        } else {
            $errorMessage = 'Lỗi: Không thể lưu giao dịch.';
        }
        $stmt->close();
    } else {
        // Thông báo lỗi khi số tiền rút vượt quá số tiền khả dụng
        $errorMessage = 'Lỗi: Số tiền rút vượt quá số tiền khả dụng.';
    }
}

// Phần hiển thị lịch sử rút tiền
$currentTime = date('Y-m-d H:i:s');
?>

<!-- Phần thêm phiếu chi -->
<div class="paymentmoney-container" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;">
    <h3 style="color: blue; font-size: 3.5vh;text-align: center;">Phiếu chi</h3>
    <?php if ($shiftOpened): ?>
    <div style="border-radius: 1vw; background: white; padding: 10px; margin-bottom: 15px;">
        <form method="POST" action="paymentmoney.php" id="paymentForm">
            <!-- Nhập số tiền -->
            <label for="numberMoney" style="color: black; display: block;">Số tiền:</label>
            <input type="number" id="numberMoney" name="numberMoney" required placeholder="Nhập số tiền" style="width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px;">

            <!-- Nhập ghi chú -->
            <label for="note" style="color: black; display: block;">Ghi chú:</label>
            <textarea id="note" name="note" placeholder="Ghi chú rút tiền" style="width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></textarea>

            <!-- Nút thêm giao dịch -->
            <button type="submit" style="margin-top: 10px; padding: 10px; background-color: #5cba47; color: white; border: none; border-radius: 4px; cursor: pointer;">Thêm Phiếu Chi</button>
        </form>
    </div>
    <?php else: ?>
        <p style="color: red; font-weight: bold; text-align: center;">Bạn cần mở ca làm việc để thực hiện giao dịch rút tiền.</p>
    <?php endif; ?>
</div>

<!-- Phần hiển thị lịch sử rút tiền -->
<div class="history-container" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;    margin-top: 2vh;">
    <h3 style="color: blue; font-size: 3.5vh;text-align: center;">Lịch sử chi</h3>
    <div style="border-radius: 1vw; background: white; margin-bottom: 15px;">
        <?php
        if ($shiftOpened) {
            // Truy vấn lịch sử rút tiền trong ca làm việc hiện tại
            $historyQuery = "SELECT NumberMoney, Date, Note 
                             FROM money 
                             WHERE Employee = ? AND Action = 0 AND Date BETWEEN ? AND ?";
            $stmtHistory = $conn->prepare($historyQuery);
            $stmtHistory->bind_param('iss', $_SESSION['user_id'], $currentShift['ShiftStartTime'], $currentTime);
            $stmtHistory->execute();
            $stmtHistory->bind_result($numberMoney, $date, $note);

            $hasData = false;
            echo '<ul style="list-style-type: none; padding: 0;">';
            while ($stmtHistory->fetch()) {
                $hasData = true;
                echo '<li style="margin: 10px; padding: 10px; border-bottom: 1px solid #ddd;">';
                echo 'Số tiền: <span style="color: red;">' . number_format(abs($numberMoney), 0, ',', '.') . ' đ</span><br>';
                echo 'Ngày: ' . date('H:i:s d/m/Y', strtotime($date)) . '<br>';
                echo 'Ghi chú: ' . htmlspecialchars($note);
                echo '</li>';
            }

            if (!$hasData) {
                echo "<p style='color: black; display: block; margin-bottom: 10px; padding: 10px;'>Không có lịch sử rút tiền trong ca này.</p>";
            }
            echo '</ul>';
            $stmtHistory->close(); // Đóng truy vấn sau khi hoàn thành
        } else {
            echo '<p>Bạn cần mở ca để xem lịch sử rút tiền.</p>';
        }
        ?>
    </div>
</div>

<script>
    // Kiểm tra nếu có thông báo thành công hoặc lỗi
    <?php if (!empty($successMessage)): ?>
        alert('<?php echo $successMessage; ?>');
        // Điều hướng về index.php sau khi thành công
        window.location.href = 'index.php';
    <?php elseif (!empty($errorMessage)): ?>
        alert('<?php echo $errorMessage; ?>');
        // Điều hướng về index.php nếu có lỗi
        window.location.href = 'index.php';
    <?php endif; ?>
</script>

<?php
$conn->close(); // Đóng kết nối sau khi xử lý xong
?>
