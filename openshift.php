<?php
session_start();
header('Content-Type: application/json'); // Đặt tiêu đề cho phản hồi là JSON
include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $startAmount = $_POST['startAmount'];
    $employeeID = $_SESSION['user_id']; // ID của nhân viên từ session

    // Thực hiện truy vấn để lưu dữ liệu
    $stmt = $conn->prepare("INSERT INTO WorkShift (EmployeeID, ShiftStartTime, StartAmount) VALUES (?, NOW(), ?)");
    if (!$stmt) {
        echo json_encode(['error' => 'Không thể chuẩn bị truy vấn: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('id', $employeeID, $startAmount); // 'i' cho int, 'd' cho double

    if ($stmt->execute()) {
        // Trả về thông tin ca làm việc vừa mở
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Có lỗi xảy ra khi mở ca: ' . $stmt->error]); // Thông báo lỗi
    }

    $stmt->close();
    $conn->close();
    exit; // Dừng thực hiện mã PHP
}
?>

<div class="workshift-container" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: 100%; padding-top: 0.5vh;">
    <h3 style="color: blue; font-size: 3.5vh; text-align: center;">Mở ca làm việc</h3>
    <div style="border-radius: 1vw; background:white; padding: 10px; margin-bottom: 15px;">
    <label for="startAmount" style="color: black;">Số tiền đầu ca:</label>
    <input type="number" id="startAmount" placeholder="Nhập số tiền đầu ca" style="width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px;">
    
    <button onclick="openShift()" style="padding: 10px 20px; background-color: #5cba47; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s ease;">Mở ca</button>
</div>

