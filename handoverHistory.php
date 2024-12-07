<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db_connect.php'; // Kết nối đến cơ sở dữ liệu

// Lấy ngày hôm nay và ngày hôm qua
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$hasUnfinishedShift = false; // Biến kiểm tra có ca chưa kết thúc hay không

// Truy vấn các ca chưa kết thúc (ShiftEndTime IS NULL) của tất cả nhân viên
$queryUnfinished = "SELECT WorkShift.ShiftID, WorkShift.ShiftStartTime, 
                           Employees.FullName, WorkShift.StartAmount 
                    FROM WorkShift 
                    JOIN Employees ON WorkShift.EmployeeID = Employees.EmployeeID
                    WHERE WorkShift.ShiftEndTime IS NULL";
$stmtUnfinished = $conn->prepare($queryUnfinished);
$stmtUnfinished->execute();
$stmtUnfinished->bind_result($unfinishedShiftID, $unfinishedShiftStartTime, $unfinishedEmployeeName, $unfinishedStartAmount);
$stmtUnfinished->store_result();
// Truy vấn lịch sử bàn giao ca - chỉ lấy các ca đã kết thúc của nhân viên đang đăng nhập
$queryFinished = "SELECT WorkShift.ShiftID, WorkShift.ShiftStartTime, WorkShift.ShiftEndTime, 
                         WorkShift.StartAmount, WorkShift.EndAmount, WorkShift.DifferenceAmount, 
                         Employees.FullName
                  FROM WorkShift 
                  JOIN Employees ON WorkShift.EmployeeID = Employees.EmployeeID
                  WHERE WorkShift.EmployeeID = ? 
                  AND WorkShift.ShiftEndTime IS NOT NULL
                  AND DATE(WorkShift.ShiftStartTime) BETWEEN ? AND ?
                  ORDER BY WorkShift.ShiftStartTime DESC";

$stmtFinished = $conn->prepare($queryFinished);
$stmtFinished->bind_param('iss', $_SESSION['user_id'], $yesterday, $today);
$stmtFinished->execute();
$stmtFinished->store_result();
$stmtFinished->bind_result($shiftID, $shiftStartTime, $shiftEndTime, $startAmount, $endAmount, $differenceAmount, $employeeName);

?>
<!-- Phần "Chưa bàn giao" -->
<div class="handover-history" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;    margin-bottom: 2vh;">
    <h3 style="color: blue; font-size: 3.5vh;text-align: center;">Chưa giao ca</h3>
    <div style="border-radius: 1vw; background: white; margin-bottom: 15px;">
        <?php if ($stmtUnfinished->num_rows > 0): ?>
            <?php while ($stmtUnfinished->fetch()): ?>
                <div class="shift-item" style="margin-bottom: 10px; border-bottom: 1px solid #ddd;border-radius: 4px; padding: 10px;">
                    <p style='margin: 0; padding-bottom: 10px;'><strong>Ca bắt đầu:</strong> <?= date('H:i:s d/m/Y', strtotime($unfinishedShiftStartTime)) ?></p>
                    <button class="toggle-button" data-target="unfinished-<?= $unfinishedShiftID ?>">&gt;</button>
                    <div id="unfinished-<?= $unfinishedShiftID ?>" class="shift-details" style="display: none; margin-top: 10px;">
                        <p>Nhân viên: <strong><?= $unfinishedEmployeeName ?></strong></p>
                        <p>Tiền mặt đầu ca: <strong><?= number_format($unfinishedStartAmount, 0, ',', '.') ?> đ</strong></p>
                        <p>Tiền mặt kết ca: <strong>Chưa kết thúc</strong></p>
                        <p>Chênh lệch: <strong>0 đ</strong></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Không có ca nào chưa bàn giao.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Phần "Đã bàn giao" -->
<div class="history" style="background-color: #fff; border-radius: 1.5vw; text-align: left; width: 100%; height: fit-content; padding-top: 0.5vh;">
    <h3 style="color: blue; font-size: 3.5vh;text-align: center;">Đã giao ca</h3>
    <div style="border-radius: 1vw; background:white; padding: 10px; margin-bottom: 15px;">
        <?php if ($stmtFinished->num_rows > 0): ?>
            <?php while ($stmtFinished->fetch()): ?>
                <div class="shift-item" style="margin-bottom: 10px; border-bottom: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                    <p style='margin: 0; padding-bottom: 10px;'><strong>Ca bắt đầu:</strong> <?= date('H:i:s d/m/Y', strtotime($shiftStartTime)) ?></p>
                    <p style='margin: 0; padding-bottom: 10px;'><strong>Ca kết thúc:</strong> <?= date('H:i:s d/m/Y', strtotime($shiftEndTime)) ?></p>
                    <button class="toggle-button" data-target="finished-<?= $shiftID ?>">&gt;</button>
                    <div id="finished-<?= $shiftID ?>" class="shift-details" style="display: none; margin-top: 10px;">
                        <p>Nhân viên: <strong><?= $employeeName ?></strong></p>
                        <p>Tiền mặt đầu ca: <strong><?= number_format($startAmount, 0, ',', '.') ?> đ</strong></p>
                        <p>Tiền mặt kết ca: <strong><?= number_format($endAmount, 0, ',', '.') ?> đ</strong></p>
                        <?php
                        $color = 'black';
                        if ($differenceAmount > 0) {
                            $color = 'blue'; // Màu xanh cho số dương
                        } elseif ($differenceAmount < 0) {
                            $color = 'red'; // Màu đỏ cho số âm
                        }
                        ?>
                        <p>Chênh lệch: <strong style="color: <?= $color ?>;"><?= number_format($differenceAmount, 0, ',', '.') ?> đ</strong></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Không có lịch sử bàn giao ca nào từ ngày hôm qua đến hôm nay.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$stmtUnfinished->close();
$stmtFinished->close();
$conn->close();

?>
