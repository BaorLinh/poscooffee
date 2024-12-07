<?php
session_start();
include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

// Kiểm tra nếu ca làm việc đang mở
$shiftOpenQuery = "SELECT ShiftStartTime, StartAmount FROM WorkShift WHERE EmployeeID = ? AND ShiftEndTime IS NULL";
$stmt = $conn->prepare($shiftOpenQuery);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Nếu có ca làm việc đang mở, lấy thông tin
    $stmt->bind_result($shiftStartTime, $startAmount);
    $stmt->fetch();
    
    // Hiển thị thông tin ca làm việc
    
    echo '<div class="workshift-container" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;">';
    echo '<h3 style="color: blue; font-size: 3.5vh;text-align: center;">Thông tin ca làm việc</h3>';
    echo '<div style="border-radius: 1vw; background:white; padding: 10px; margin-bottom: 15px;">';
    echo "<p style='margin: 0; padding-bottom: 10px;'>Bắt đầu ca: <strong>$shiftStartTime</strong></p>";
        // Hiển thị tên nhân viên
    echo "<p style='margin: 0; padding-bottom: 10px;'>Nhân viên: <strong>" . $_SESSION['full_name'] . "</strong></p>";
    echo "<p style='margin: 0; padding-bottom: 10px;'>Số tiền đầu ca: <strong>" . number_format($startAmount, 0, ',', '.') . " đ</strong></p>";
    
    // Input để nhập số tiền cuối ca
    echo '<label for="endAmount" style="color: black;">Số tiền cuối ca:</label>';
    echo '<input type="number" id="endAmount" required placeholder="Nhập số tiền cuối ca" style="width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">';
    
    // Nút kết thúc ca
    echo '<button onclick="closeShift()" style="margin-top: 10px; padding: 10px; background-color: #5cba47; color: white; border: none; border-radius: 4px; cursor: pointer;">Kết ca</button>';
    echo '</div>';
} else {
    // Nếu không có ca nào mở
    echo '<div style="text-align: center;">';
    echo '<p>Không có ca làm việc nào đang mở.</p>';
    echo '</div>';
}

// Đóng câu lệnh và kết nối
$stmt->close();
$conn->close();
?>
