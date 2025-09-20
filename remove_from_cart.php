<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_POST['cart_id'])) {
    header("Location: cart.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$cartId = (int) $_POST['cart_id'];

// ✅ Delete only one row that belongs to this user
$stmt = $conn->prepare("DELETE FROM CART_ITEMS WHERE CartID = ? AND UserID = ?");
$stmt->bind_param("ii", $cartId, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: cart.php?removed=1");
} else {
    header("Location: cart.php?error=nofound");
}
$stmt->close();
exit();
