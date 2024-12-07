<?php
include 'db_connect.php';
$customerID = intval($_GET['customerID']);
$query = "SELECT PhoneNumber, Email, Address FROM Customers WHERE CustomerID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$stmt->bind_result($phoneNumber, $email, $address);
$stmt->fetch();
echo json_encode(['PhoneNumber' => $phoneNumber, 'Email' => $email, 'Address' => $address]);
$stmt->close();
?>
