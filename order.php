<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'db_connect.php';

$tableID = isset($_GET['tableNumber']) ? intval($_GET['tableNumber']) : null;
$billID = isset($_GET['billId']) ? intval($_GET['billId']) : null;

$queryPosition = "SELECT PositionID FROM Employees WHERE EmployeeID = ?";
$stmtPosition = $conn->prepare($queryPosition);
$stmtPosition->bind_param('i', $_SESSION['user_id']);
$stmtPosition->execute();
$stmtPosition->bind_result($positionID);
$stmtPosition->fetch();
$stmtPosition->close();

if (!$tableID) {
    die("Không có ID bàn được cung cấp.");
}

$queryTable = "SELECT t.TableName, a.AreaName FROM Tables t 
               LEFT JOIN Areas a ON t.AreaID = a.AreaID 
               WHERE t.TableNumber = ?";
$stmtTable = $conn->prepare($queryTable);
$stmtTable->bind_param('i', $tableID);
$stmtTable->execute();
$stmtTable->bind_result($tableName, $areaName);
$stmtTable->fetch();
$stmtTable->close();

$employeeOrder = null;
if ($billID) {
    $queryEmployee = "SELECT e.FullName AS EmployeeOrder 
                      FROM Bills b 
                      LEFT JOIN Employees e ON e.EmployeeID = b.EmployeeOrder 
                      WHERE b.BillID = ?";
    $stmtEmployee = $conn->prepare($queryEmployee);
    $stmtEmployee->bind_param('i', $billID);
    $stmtEmployee->execute();
    $stmtEmployee->bind_result($employeeOrder);
    $stmtEmployee->fetch();
    $stmtEmployee->close();
} else {
    $employeeOrder = $_SESSION['full_name'] ?? 'N/A';
}

$queryCategory = "SELECT ProductCatalogID, CatalogName FROM ProductCatalog";
$resultCategory = $conn->query($queryCategory);

$queryProducts = "SELECT ProductName, image, p.ProductCatalogID, Price FROM Product p";
$resultProducts = $conn->query($queryProducts);

$customerID = null;
$customerName = 'Khách lẻ';
$customerPhone = 'Không có số điện thoại';
$customerEmail = 'Không có email';
$customerAddress = 'Không có địa chỉ';

if ($billID) {
    $queryCustomer = "SELECT c.CustomerID, c.CustomerName, c.PhoneNumber, c.Email, c.Address
                      FROM Bills b
                      LEFT JOIN Customers c ON c.CustomerID = b.CustomerID
                      WHERE b.BillID = ?";
    $stmtCustomer = $conn->prepare($queryCustomer);
    $stmtCustomer->bind_param('i', $billID);
    $stmtCustomer->execute();
    $stmtCustomer->bind_result($customerID, $customerName, $customerPhone, $customerEmail, $customerAddress);
    $stmtCustomer->fetch();
    $stmtCustomer->close();
}

$orderItems = [];
if ($billID) {
    $queryOrderItems = "SELECT p.ProductName, ib.Quantity, ib.UnitPrice, ib.TotalPrice
                        FROM InforBill ib
                        JOIN Product p ON ib.ProductID = p.ProductID
                        WHERE ib.BillID = ?";
    $stmtOrderItems = $conn->prepare($queryOrderItems);
    $stmtOrderItems->bind_param('i', $billID);
    $stmtOrderItems->execute();
    $stmtOrderItems->bind_result($productName, $quantity, $unitPrice, $totalPrice);

    while ($stmtOrderItems->fetch()) {
        $orderItems[] = [
            'ProductName' => $productName,
            'Quantity' => $quantity,
            'UnitPrice' => $unitPrice,
            'TotalPrice' => $totalPrice
        ];
    }
    $stmtOrderItems->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 95%;
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    color: #333;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #003eff;
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 10vh;
    padding: 0 20px;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header h2 {
    font-size: 1.5rem;
    font-weight: bold;
}

.body {
    display: flex;
    margin-top: 10vh;
    justify-content: space-between;
    height: calc(100vh - 20vh);
    width: 100%;
    position: fixed;
}

.category {
    width: 15%;
    height: 70vh;
    max-height: calc(100vh - 20vh);
    overflow-y: auto; 
    padding: 1em;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.category ul {
    list-style: none;
}

.category-item {
    padding: 10px;
    background-color: #f9f9f9;
    cursor: pointer;
    border-radius: 5px;
    transition: background-color 0.3s ease;
    text-align: center;
}

.category-item:hover, .category-item.active {
    background-color: #003eff;
    color: white;
}

.products {
    width: 65%;
    height: 70vh;
    display: flex;
    overflow-y: auto; 
    background: white;
    gap: 0.5em;
    padding: 1em;
    align-content: flex-start;
    flex-wrap: wrap;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.product-item {
    width: calc(24% - 0.25em);
    height: 170px;
    margin-bottom: 0.5em;
    padding: 2px;
    text-align: center;
    cursor: pointer;
    border: 1.5px solid green;
    transition: transform 0.3s ease;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.product-item img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
}

.product-item:nth-child(4n) {
    margin-right: 0;
}

.product-item h2, .product-item p {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 1rem;
    color: #333;
    margin-top: 10px;
    font-weight: bold;
}

.order-display {
    background: white;
    overflow-y: auto; 
    height: 70vh;
    width: 62%;
    padding: 0.5em 0.5em 3em;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.button-container {
    position: fixed;
    bottom: 10vh;
    height: 10vh;
    background: white;
    width: 100%;
    padding: 1vh;
    border: 2px solid #ddd;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.button-group {
    text-align: right;
}

.order-display h4 {
    font-size: 1.1rem;
    text-align: center;
}

.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5); /* Màu nền mờ */
    z-index: 999; /* Đặt nó ở trên nhưng dưới order-display */
    display: none; /* Mặc định ẩn */
}

.order-summary {
    width: 100%;
    border-collapse: collapse;
}

.order-summary td {
    font-size: 0.8rem;
    border: 1px solid #ddd;
    padding: 0.5em;
    text-align: center;
}

.order-summary th {
    font-size: 0.9rem;
    background-color: #f1f1f1;
    border: 1px solid #ddd;
    padding: 0.1em;
    text-align: center;
}

.order-summary tbody tr:hover {
    background-color: #f9f9f9;
}

.total-amount {
    font-size: 1rem;
    font-weight: bold;
    color: #d9534f;
    margin-top: 10px;
    display: block;
    text-align: right;
}

.payment-popup {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    width: 400px;
    max-width: 90%;
    display: none;
}

.payment-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    display: none;
}

.payment-popup h2 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 3vh;
    color: #333;
}

.payment-popup label {
    display: block;
    margin-bottom: 5px;
    font-size: 16px;
    color: #333;
}

.payment-popup input, .payment-popup select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 16px;
}

.payment-popup button {
    width: 100%;
    padding: 1vh;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 2vh;
    cursor: pointer;
}

.payment-popup button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.payment-popup .change-amount {
    font-size: 16px;
    color: #333;
    margin-top: 10px;
}

button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    margin-top: 10px;
    cursor: pointer;
    font-size: 1rem;
    border-radius: 5px;
    transition: background-color 0.3s ease;
}

button:hover {
    background-color: #0056b3;
}

.modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
    width: 300px;
    text-align: center;
}

.modal-content input,
.modal-content select {
    margin-bottom: 10px;
    width: 100%;
    padding: 8px;
    font-size: 16px;
}

#confirmPaymentBtn, #closeModal {
    margin-top: 10px;
    padding: 1vh;
    width: 100%;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

#closeModal {
    background-color: #dc3545;
}

#confirmPaymentBtn:hover, #closeModal:hover {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .header h2 {
        font-size: 1.2rem;
    }

    .body {
        flex-direction: column;
        height: auto;
    }

    .product-item {
        width: 48%;
    }

    .order-display {
        width: 100%;
    }
}

.footer {
    background-color: #003eff;
    color: white;
    display: flex;
    justify-content: space-between;
    position: fixed;
    bottom: 0;
    width: 100%;
    height: 10vh;
    z-index: 1000;
    align-items: center;
    padding: 0 2vw;
    box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
}

.footer span {
    font-size: 100%;
}

.footer .user-name {
    display: flex;
    align-items: center;
    padding: 1vh;
}

.footer .user-name::before {
    margin-right: 1vw;
    font-size: 1.5vw;
}

.current-datetime {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-right: 4vw;
}

.current-datetime .time {
    font-size: 1vw;
    font-weight: bold;
}

.current-datetime .date {
    font-size: 1.2vw;
}

/* Ẩn nút trợ năng trên màn hình laptop */
@media (min-width: 769px) {
    #assistiveButton {
        display: none !important;
    }
}

@media (min-width: 769px) {
    .order-display {
        display: block !important;
    }
}

@media (max-width: 480px) {
    .header {
        height: 10vh; 
        font-size: 1.5vh;
    }

    .header h2 {
        font-size: 1rem;
    }

    .hide-mobile {
        display: none !important;
    }

    .footer {
        display: none;
    }
    .body {
        flex-direction: row;
        width: 100%;
        margin-top: 10vh;
        display: flex;
        height: 80vh;
        flex-wrap: nowrap;
        justify-content: flex-start;
    }
    .button-container {
    position: fixed;
    bottom: 0;
    height: 12vh; 
    background: white;
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
    .category {
        width: 20%;
        padding: 3px;
        height: 100%;
    }
    .products {
    width: 80%;
    height: 88%;
    display: flex;
    overflow-y: auto;
    background: white;
    gap: 0.5em;
    padding: 3px;
    align-content: flex-start;
    flex-wrap: wrap;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.product-item {
    width: calc(50% - 0.25em);
    height: 170px;
    margin-bottom: 0.5em;
    padding: 2px;
    text-align: center;
    cursor: pointer;
    border: 1.5px solid green;
    transition: transform 0.3s ease;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

    .order-display {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #fff;
        width: 90%;
        height: 60vh;
        max-width: 300px;
        padding: 10px;       
        border: 1.5px solid black;
        border-radius: 8px;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        overflow-y: auto;
    }
    
.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5); /* Màu nền mờ */
    z-index: 999; /* Đặt nó ở trên nhưng dưới order-display */
    display: none; /* Mặc định ẩn */
}

    .order-summary {
        width: 100%;
        flex-grow: 1;
        overflow-y: auto;
        margin-bottom: 10px;
    }

        #assistiveButton {
        position: fixed;
        bottom: 13vh;
        right: 20px;
        background-color: #007bff;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        z-index: 1001;
    }
    
}

    </style>
</head>

<body>
    <div class="header">
        <p>Khu vực: 
            <select id="areaSelect" onchange="updateTableOptions()">
                <?php 
                $areaQuery = "SELECT AreaID, AreaName FROM Areas";
                $resultArea = $conn->query($areaQuery);
                while ($area = $resultArea->fetch_assoc()): ?>
                    <option value="<?php echo $area['AreaID']; ?>" 
                        <?php echo ($area['AreaName'] === $areaName) ? 'selected' : ''; ?>>
                        <?php echo $area['AreaName']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </p>

        <p>Bàn: 
            <select id="tableSelect">
                <?php 
                $tableQuery = "SELECT TableNumber, TableName FROM Tables WHERE AreaID = (SELECT AreaID FROM Areas WHERE AreaName = '$areaName')";
                $resultTable = $conn->query($tableQuery);
                while ($table = $resultTable->fetch_assoc()): ?>
                    <option value="<?php echo $table['TableNumber']; ?>" 
                        <?php echo ($table['TableName'] === $tableName) ? 'selected' : ''; ?>>
                        <?php echo $table['TableName']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </p>

        <p>KH: 
            <select id="customerSelect" onchange="updateCustomerInfo()">
                <?php 
                $customerQuery = "SELECT CustomerID, CustomerName FROM Customers";
                $resultCustomer = $conn->query($customerQuery);
                while ($customer = $resultCustomer->fetch_assoc()): ?>
                    <option value="<?php echo $customer['CustomerID']; ?>" 
                        <?php echo ($customer['CustomerName'] === $customerName) ? 'selected' : ''; ?>>
                        <?php echo $customer['CustomerName']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </p>
        
        <p class="hide-mobile">Sđt: <span id="customerPhone"><?php echo $customerPhone; ?></span></p>
        <p class="hide-mobile">Email: <span id="customerEmail"><?php echo $customerEmail; ?></span></p>
        <p class="hide-mobile">Đc: <span id="customerAddress"><?php echo $customerAddress; ?></span></p>
    </div>

    <div class="body">
        <div class="category">
            <ul id="category-list">
                <?php while ($category = $resultCategory->fetch_assoc()): ?>
                    <li class="category-item" data-catalog-id="<?php echo $category['ProductCatalogID']; ?>">
                        <?php echo $category['CatalogName']; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="products" id="product-list">
            <?php while ($product = $resultProducts->fetch_assoc()): ?>
                <div class="product-item" data-catalog-id="<?php echo $product['ProductCatalogID']; ?>" 
                     onclick="addToOrder('<?php echo $product['ProductName']; ?>', <?php echo (float)$product['Price']; ?>)">
                    <img src="images/<?php echo $product['image']; ?>" alt="<?php echo $product['ProductName']; ?>">
                    <h2><?php echo number_format($product['Price'], 0, ',', '.'); ?> đ</h2>
                    <p><?php echo $product['ProductName']; ?></p>
                </div>
            <?php endwhile; ?>
        </div>

        <div id="assistiveButton" onclick="toggleOrderDisplay()">
            <span id="itemCountBadge">0</span>
            <i class="fas fa-shopping-cart"></i>
        </div>

        <div id="overlay" class="overlay" style="display: none;"></div>
        <div class="order-display" id="orderDisplay" style="display: none;">
            <h4>Hóa đơn</h4>
            <table class="order-summary">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Tên món</th>
                        <th>SL</th>
                        <th>Tiền</th>
                    </tr>
                </thead>
                <tbody id="orderItemsBody">
                    <?php if (!empty($orderItems)): ?>
                        <?php foreach ($orderItems as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo isset($item['name']) ? $item['name'] : 'N/A'; ?></td>
                                <td class="quantity-buttons">
                                    <button onclick="changeQuantity(<?php echo $index; ?>, -1)" 
                                            <?php echo (isset($item['isSaved']) && $item['isSaved'] && ($positionID !== 1 && $positionID !== 2)) ? 'disabled' : ''; ?>>-</button>
                                    <span><?php echo isset($item['quantity']) ? $item['quantity'] : 0; ?></span>
                                    <button onclick="changeQuantity(<?php echo $index; ?>, 1)" 
                                            <?php echo (isset($item['isSaved']) && $item['isSaved'] && ($positionID !== 1 && $positionID !== 2)) ? 'disabled' : ''; ?>>+</button>
                                </td>
                                <td><?php echo isset($item['totalPrice']) ? number_format($item['totalPrice'], 0, ',', '.') : '0'; ?> đ</td>
                            </tr>
                            <tr>
                                <td colspan="4" style="text-align: left; padding-left: 20px;">
                                    Giá thường: <?php echo isset($item['price']) ? number_format($item['price'], 0, ',', '.') : '0'; ?> đ
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">Chưa có món nào trong đơn hàng.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="button-container">
            <h2 class="total-amount">Tổng tiền: 0 đ</h2>
            <div class="button-group">
                <button id="thoat-btn">Thoát</button>
                <button id="luu-btn">Lưu</button>
                <button id="thanhtoan-btn">Thanh toán</button>
            </div>
        </div>
    </div>
            
    <div id="payment-modal" class="payment-popup" style="display:none;">
        <h2>Thanh toán hóa đơn</h2>
            <p>Tổng tiền: <span id="payment-total-amount"></span></p>
            <label for="discountAmount">Giảm giá (Số tiền):</label>
        <div style="display: flex;">
            <input type="number" id="discountAmount" placeholder="0">
            <span>đ</span>
        </div>
            <label for="discountPercentage">Giảm giá (Phần trăm):</label>
            <div style="display: flex;">
            <input type="number" id="discountPercentage" placeholder="0">
            <span>%</span>
        </div>
            <label for="receivedAmount">Số tiền khách đưa:</label>
            <input type="number" id="receivedAmount" placeholder="0">
            <p class="change-amount" id="changeAmount"></p>
            <label for="paymentMethod">Phương thức thanh toán:</label>
            <select id="paymentMethod">
                <option value="1">Tiền mặt</option>
                <option value="2">Chuyển khoản (CK)</option>
                <option value="3">Momo</option>
            </select>
            <button id="confirmPaymentBtn">Xác nhận thanh toán</button>
            <button id="closePaymentBtn">Đóng</button>
        </div>
    <div id="payment-overlay" class="payment-modal-overlay" style="display:none;"></div>

    <div class="footer">
        <span class="user-name" style="display: flex; align-items: center;">
            <i class="fas fa-user" style="margin-right: 8px; font-size: 20px;"></i>
            <?php if (isset($_SESSION['full_name'])) { echo $_SESSION['full_name']; } ?>
        </span>
        <span class="shop-name">ME COFFEE</span>
        <div class="current-datetime" style="display: flex; flex-direction: column; align-items: center;">
            <span class="time" id="time" style="font-size: 16px; font-weight: bold;"></span>
            <span class="date" id="date" style="font-size: 14px;"></span>
        </div>
    </div>
<script>
    let orderItems = <?php echo json_encode($orderItems); ?> || [];
    console.log("orderItems từ PHP:", orderItems);

    // Hàm cập nhật danh sách bàn theo khu vực
    function updateTableOptions() {
        const areaID = document.getElementById('areaSelect').value;
        fetch(`get_tables.php?areaID=${areaID}`)
            .then(response => response.json())
            .then(data => {
                const tableSelect = document.getElementById('tableSelect');
                tableSelect.innerHTML = '';
                data.forEach(table => {
                    const option = document.createElement('option');
                    option.value = table.TableNumber;
                    option.textContent = table.TableName;
                    tableSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Lỗi khi tải danh sách bàn:', error));
    }

    // Hàm cập nhật thông tin khách hàng khi chọn khách hàng khác
    function updateCustomerInfo() {
        const customerID = document.getElementById('customerSelect').value;
        fetch(`get_customer.php?customerID=${customerID}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('customerPhone').textContent = data.PhoneNumber || 'Không có số điện thoại';
                document.getElementById('customerEmail').textContent = data.Email || 'Không có email';
                document.getElementById('customerAddress').textContent = data.Address || 'Không có địa chỉ';
            })
            .catch(error => console.error('Lỗi khi tải thông tin khách hàng:', error));
    }
    
    // Gửi yêu cầu cập nhật bàn và khách hàng khi thay đổi khu vực, bàn, hoặc khách hàng
function updateTableAndCustomer() {
    const areaID = document.getElementById('areaSelect').value;
    const tableID = document.getElementById('tableSelect').value;
    const customerID = document.getElementById('customerSelect').value;

    // Log các giá trị để kiểm tra
    console.log("Giá trị gửi đi:");
    console.log("areaID:", areaID);
    console.log("tableID:", tableID);
    console.log("customerID:", customerID);
    console.log("billID:", <?php echo isset($billID) ? $billID : 'null'; ?>);

    // Dữ liệu cần gửi
    const data = {
        areaID: areaID,
        tableID: tableID,
        customerID: customerID,
        billID: <?php echo isset($billID) ? $billID : 'null'; ?>
    };

    fetch('update_table_customer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Cập nhật bàn và khách hàng thành công');
        } else {
            console.error('Lỗi khi cập nhật bàn và khách hàng:', data.message);
        }
    })
    .catch(error => console.error('Lỗi:', error));
}

    
    // Gắn sự kiện thay đổi
    document.getElementById('areaSelect').addEventListener('change', updateTableAndCustomer);
    document.getElementById('tableSelect').addEventListener('change', updateTableAndCustomer);
    document.getElementById('customerSelect').addEventListener('change', updateTableAndCustomer);

    // Cập nhật số lượng sản phẩm trong đơn hàng
    function updateItemCount() {
        const itemCount = orderItems.reduce((total, item) => total + item.Quantity, 0);
        document.getElementById('itemCountBadge').textContent = itemCount;
    }

    // Hiển thị hoặc ẩn chi tiết đơn hàng
    function toggleOrderDisplay() {
        const orderDisplay = document.getElementById('orderDisplay');
        const overlay = document.getElementById('overlay');
        orderDisplay.style.display = orderDisplay.style.display === 'block' ? 'none' : 'block';
        overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
        updateOrderDisplay();
    }

    // Thêm sản phẩm vào đơn hàng
    function addToOrder(productName, productPrice) {
        productPrice = parseFloat(productPrice);
        let existingProduct = orderItems.find(item => item.ProductName === productName);
        if (existingProduct) {
            existingProduct.Quantity += 1;
            existingProduct.TotalPrice += productPrice;
        } else {
            orderItems.push({
                ProductName: productName,
                UnitPrice: productPrice,
                Quantity: 1,
                TotalPrice: productPrice
            });
        }
        updateItemCount();
        updateOrderDisplay();
    }

    // Thay đổi số lượng sản phẩm
    function changeQuantity(index, amount) {
        if (orderItems[index].Quantity + amount > 0) {
            orderItems[index].Quantity += amount;
            orderItems[index].TotalPrice = orderItems[index].Quantity * orderItems[index].UnitPrice;
            updateOrderDisplay();
            updateItemCount();
        } else {
            if (!orderItems[index].isSaved || (positionID === 1 || positionID === 2)) {
                orderItems.splice(index, 1);
                updateOrderDisplay();
                updateItemCount();
            } else {
                alert("Bạn không có quyền xóa món đã lưu.");
            }
        }
    }

    // Hiển thị chi tiết đơn hàng
    function updateOrderDisplay() {
        const orderTableBody = document.querySelector('.order-summary tbody');
        orderTableBody.innerHTML = ''; // Xóa nội dung cũ trong order display

        let totalAmount = 0;

        orderItems.forEach((item, index) => {
            const productName = item.ProductName || 'N/A';
            const quantity = item.Quantity || 0;
            const unitPrice = item.UnitPrice ? parseFloat(item.UnitPrice) : 0;
            const totalPrice = item.TotalPrice ? parseFloat(item.TotalPrice) : 0;

            totalAmount += totalPrice;

            const row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${productName}</td>
                    <td>
                        ${quantity}
                        <button onclick="changeQuantity(${index}, -1)">-</button>
                        <button onclick="changeQuantity(${index}, 1)">+</button>
                    </td>
                    <td>${totalPrice.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' })}</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: left; padding-left: 20px;">
                        Giá thường: ${unitPrice.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' })}
                    </td>
                </tr>
            `;
            orderTableBody.insertAdjacentHTML('beforeend', row);
        });

        document.querySelector('.total-amount').textContent = `Tổng tiền: ${totalAmount.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' })}`;
    }

    // Lưu đơn hàng
    // Lưu đơn hàng
document.getElementById('luu-btn').addEventListener('click', function() {
    const billID = <?php echo isset($billID) ? $billID : 'null'; ?>;
    const customerID = document.getElementById('customerSelect').value;
    const tableID = document.getElementById('tableSelect').value; // Lấy tableID từ DOM
    const employeeID = <?php echo isset($_SESSION['employeeID']) ? $_SESSION['employeeID'] : 'null'; ?>;

    if (employeeID === 'null') {
        alert("Không có thông tin nhân viên đăng nhập.");
        return;
    }

    const data = {
        items: orderItems,
        tableID: tableID, // Sử dụng tableID mới nhất
        billID: billID,
        customerID: customerID,
        employeeID: employeeID
    };

    console.log("Dữ liệu gửi đi khi lưu:", data);

    fetch('save_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("Lỗi mạng hoặc lỗi server.");
        }
        return response.json();
    })
    .then(data => {
        console.log("Phản hồi từ server khi lưu:", data);
        if (data.success) {
            alert('Lưu thành công');
            window.location.href = 'index.php';
        } else {
            alert('Có lỗi xảy ra: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        alert('Có lỗi xảy ra khi lưu đơn hàng');
    });
});

    // Xử lý thanh toán
    document.getElementById('thanhtoan-btn').addEventListener('click', function() {
        const billID = <?php echo isset($billID) ? $billID : 'null'; ?>;

        if (billID) {
            document.getElementById('payment-modal').style.display = 'block';
            document.getElementById('payment-overlay').style.display = 'block';

            const totalAmount = parseFloat(document.querySelector('.total-amount').textContent.replace(/[^\d]/g, ''));
            document.getElementById('payment-total-amount').textContent = `Tổng tiền: ${totalAmount.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' })}`;
            if (document.getElementById('receivedAmount').value === '' || document.getElementById('receivedAmount').value == 0) {
                document.getElementById('receivedAmount').value = totalAmount;
            }

            function updateChangeAmount() {
                const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
                const discountPercentage = parseFloat(document.getElementById('discountPercentage').value) || 0;
                let discountedTotal = totalAmount;
                if (discountAmount > 0) {
                    discountedTotal -= discountAmount;
                }
                if (discountPercentage > 0) {
                    discountedTotal -= (discountPercentage / 100) * totalAmount;
                }

                const receivedAmount = parseFloat(document.getElementById('receivedAmount').value) || 0;
                const changeAmount = receivedAmount - discountedTotal;
                document.getElementById('changeAmount').textContent = 'Tiền thừa: ' + changeAmount.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' });
                document.getElementById('confirmPaymentBtn').disabled = receivedAmount < discountedTotal;
            }

            updateChangeAmount();
            document.getElementById('discountAmount').addEventListener('input', updateChangeAmount);
            document.getElementById('discountPercentage').addEventListener('input', updateChangeAmount);
            document.getElementById('receivedAmount').addEventListener('input', updateChangeAmount);

            document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
                const receivedAmount = parseFloat(document.getElementById('receivedAmount').value);
                const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
                const discountPercentage = parseFloat(document.getElementById('discountPercentage').value) || 0;
                const paymentMethod = document.getElementById('paymentMethod').value;

                let discountedTotal = totalAmount;
                if (discountAmount > 0) {
                    discountedTotal -= discountAmount;
                }
                if (discountPercentage > 0) {
                    discountedTotal -= (discountPercentage / 100) * totalAmount;
                }

                if (receivedAmount < discountedTotal) {
                    alert('Số tiền khách đưa không đủ.');
                    return;
                }

                const data = {
                    billID: billID,
                    discountAmount: discountAmount,
                    discountPercentage: discountPercentage,
                    receivedAmount: receivedAmount,
                    paymentMethod: paymentMethod
                };

                fetch('pay_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(response => response.json())
                  .then(data => {
                    if (data.success) {
                        alert('Thanh toán thành công');
                        document.getElementById('payment-modal').style.display = 'none';
                        document.getElementById('payment-overlay').style.display = 'none';
                        window.location.href = 'index.php';
                    } else {
                        alert('Có lỗi xảy ra: ' + data.message);
                    }
                  }).catch(error => {
                    console.error('Lỗi:', error);
                    alert('Có lỗi xảy ra khi thanh toán');
                });
            });
        } else {
            alert('Không có hóa đơn để thanh toán.');
        }
    });

    document.getElementById('closePaymentBtn').addEventListener('click', function() {
        document.getElementById('payment-modal').style.display = 'none';
        document.getElementById('payment-overlay').style.display = 'none';
    });

    document.getElementById('payment-overlay').addEventListener('click', function() {
        document.getElementById('payment-modal').style.display = 'none';
        document.getElementById('payment-overlay').style.display = 'none';
    });

    document.addEventListener('DOMContentLoaded', function() {
        const defaultCategory = document.querySelector('.category-item[data-catalog-id="1"]');
        if (defaultCategory) {
            defaultCategory.classList.add('active');
        }

        document.querySelectorAll('.product-item').forEach(product => {
            if (product.getAttribute('data-catalog-id') !== "1") {
                product.style.display = 'none';
            }
        });

        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function() {
                const catalogID = item.getAttribute('data-catalog-id');
                document.querySelectorAll('.product-item').forEach(product => {
                    if (product.getAttribute('data-catalog-id') === catalogID) {
                        product.style.display = 'block'; 
                    } else {
                        product.style.display = 'none';
                    }
                });
                document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });

        updateOrderDisplay();
    });

    document.getElementById('thoat-btn').addEventListener('click', function() {
        window.location.href = 'index.php';
    });
</script>


</body>
</html>
<?php
$conn->close();
?>
