<?php
session_start(); // Khởi tạo session

// Nếu người dùng nhấn nút "Đăng xuất" xác nhận
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_logout'])) {
    // Xóa tất cả các biến session
    $_SESSION = array();

    // Nếu cần, xóa cookie session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Hủy session
    session_destroy();

    // Chuyển hướng đến trang đăng nhập
    header("Location: login.php");
    exit();
}

// Giao diện đăng xuất
echo '<div class="logout-container" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;">';
echo '<h3 style="color: blue; font-size: 3.5vh;text-align: center;">Đăng xuất</h3>';
echo '<div style="border-radius: 1vw; background:white;  margin-bottom: 15px; padding:10px">';
echo '<p style="margin: 0; padding-bottom: 10px;font-weight: bold;">Kiểm tra và bàn giao đầy đủ đơn cho ca sau!</p>';
echo '<p style="margin: 0; padding-bottom: 10px;font-weight: bold;">Kết thúc phiên làm việc của ca hiện tại!</p>';

// Form xác nhận đăng xuất
echo '<form method="POST" action="logout.php">';
echo '<button type="submit" name="confirm_logout" style="margin-top: 10px; padding: 10px; background-color: red; color: white; border: none; border-radius: 4px; cursor: pointer;">Đăng xuất</button>'; 
echo '</form>';

echo '</div>';
echo '</div>';
?>
