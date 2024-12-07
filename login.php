<?php
session_start(); // Khởi tạo session
include 'db_connect.php'; // Bao gồm kết nối CSDL

// Kiểm tra nếu người dùng đã đăng nhập
if (isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Chuyển hướng đến trang chính nếu đã đăng nhập
    exit();
}

$errorMessage = ''; // Biến để lưu thông báo lỗi

// Xử lý đăng nhập khi người dùng gửi thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Truy vấn để kiểm tra thông tin đăng nhập
    $query = "SELECT EmployeeID, FullName, Password FROM Employees WHERE Email = ?";

    // Chuẩn bị câu lệnh
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $email); // Bảo vệ chống SQL Injection
    $stmt->execute();

    // Liên kết kết quả
    $stmt->bind_result($userID, $fullName, $dbPassword);
    $stmt->fetch();

    // Kiểm tra mật khẩu
    if ($userID && $password === $dbPassword) { // So sánh mật khẩu trực tiếp
        // Nếu thông tin hợp lệ, lưu ID người dùng vào session
        $_SESSION['user_id'] = $userID;
        $_SESSION['full_name'] = $fullName; // Lưu tên người dùng

        header('Location: index.php'); // Chuyển hướng đến trang chính
        exit();
    } else {
        // Nếu không hợp lệ, hiển thị thông báo lỗi
        $errorMessage = 'Email hoặc mật khẩu không đúng.';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #007aff 0%, #00d0ff 100%);
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            color: #1e3c72;
            font-size: 24px;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #139c14 0%, #33ce63 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .error-message {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
            opacity: 1;
            transition: opacity 0.3s ease;
            animation: fadeInOut 3.3s ease forwards;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            h2 {
                font-size: 30px;
            }

            input {
                padding: 10px 14px;
            }

            button {
                padding: 12px;
            }
        }
    </style>

</head>
<body>
    <div class="login-container">
        <h2>Đăng Nhập</h2>
        <?php if ($errorMessage): ?>
            <div class="error-message"><?php echo $errorMessage; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit">Đăng Nhập</button>
        </form>
    </div>
</body>
</html>
