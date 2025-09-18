<?php
session_start();
require_once "db_connect.php";

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Check if voucher_id was passed
if (!isset($_POST['voucher_id'])) {
    header("Location: browse_voucher.php");
    exit();
}

$voucherId = (int) $_POST['voucher_id'];
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

// Check if voucher is already in cart
$stmt = $conn->prepare("SELECT CartID, Quantity FROM CART_ITEMS WHERE UserID = ? AND VoucherID = ?");
$stmt->bind_param("ii", $userId, $voucherId);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // If exists → update quantity
    $newQty = $row['Quantity'] + $quantity;
    $upd = $conn->prepare("UPDATE CART_ITEMS SET Quantity = ? WHERE CartID = ?");
    $upd->bind_param("ii", $newQty, $row['CartID']);
    $upd->execute();
    $upd->close();
} else {
    // If not exists → insert new row
    $ins = $conn->prepare("INSERT INTO CART_ITEMS (VoucherID, UserID, Quantity) VALUES (?, ?, ?)");
    $ins->bind_param("iii", $voucherId, $userId, $quantity);
    $ins->execute();
    $ins->close();
}
$stmt->close();

// Redirect user to cart page
header("Location: browse_voucher.php?added=1");

exit();
