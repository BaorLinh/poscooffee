<?php
// admin.php - Trang chính, điều hướng đến các trang trong thư mục admin
include 'admin/includes/auth.php';
include 'admin/includes/header.php';
?>

<h2>Trang Quản Lý Chính</h2>
<p>Chọn một trong các chức năng quản lý bên dưới:</p>

<!-- Điều hướng đến các trang quản lý khác -->
<div>
    <a href="admin/index.php">Dashboard</a><br>
    <a href="admin/bills.php">Quản lý Hóa Đơn</a><br>
    <a href="admin/employees.php">Quản lý Nhân Viên</a><br>
    <a href="admin/customers.php">Quản lý Khách Hàng</a><br>
    <a href="admin/revenue.php">Báo Cáo Doanh Thu</a>
</div>

<?php include 'admin/includes/footer.php'; ?>
