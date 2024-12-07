<?php
// Thông tin kết nối cơ sở dữ liệu
$servername = "localhost";
$username = "livedemo_admin";
$password = "livedemocafe@cafe";
$dbname = "livedemo_cafe";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>
