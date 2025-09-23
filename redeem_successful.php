<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? "";

// Fetch updated user points
$stmt = $conn->prepare("SELECT UserPoints FROM USERS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userPoints);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redeem Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }

        .container {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 100px auto;
        }

        .header-text {
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 20px;
        }

        .message-text {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 30px;
        }

        .remaining-points {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 30px;
        }

        .btn-custom {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        .btn-outline-secondary {
            background-color: white;
            color: #6c757d;
            border-color: #6c757d;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 5px;
            display: inline-block;
            width: 100%;
            margin-top: 10px;
            text-align: center;
        }

        .btn-outline-secondary:hover, .btn-custom:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container text-center">
        <div class="header-text">Congratulations!</div>
        <div class="message-text">Your voucher has been successfully redeemed.</div>
        <div class="remaining-points">Your remaining points: <strong><?= htmlspecialchars($userPoints) ?> pts</strong></div>

        <!-- Button to Download Voucher -->
        <a href="download_voucher.php" class="btn-custom">Download Voucher</a>
        
        <!-- Button to go to Home -->
        <a href="home.php" class="btn-outline-secondary">Back to Home</a>
    </div>
</body>
</html>
