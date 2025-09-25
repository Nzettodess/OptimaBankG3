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

// Fetch last redeemed voucher info from session (flash)
$lastVoucherId = $_SESSION['last_redeemed_voucher'] ?? null;
$redeemMessage = $_SESSION['redeem_message'] ?? null;
$voucherTitle = "";
$voucherPoints = null;

if ($lastVoucherId) {
    $s = $conn->prepare("SELECT Title, VoucherPoints FROM VOUCHER WHERE VoucherID = ?");
    $s->bind_param("i", $lastVoucherId);
    $s->execute();
    $s->bind_result($voucherTitle, $voucherPoints);
    $s->fetch();
    $s->close();
}

// Clear flash/session items so refresh won't show them again
unset($_SESSION['last_redeemed_voucher'], $_SESSION['redeem_message']);
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
            max-width: 520px;
            margin: 80px auto;
        }

        .header-text {
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 12px;
        }

        .message-text {
            font-size: 15px;
            color: #6c757d;
            margin-bottom: 18px;
        }

        .remaining-points {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 20px;
        }

        .voucher-info {
            background: #f1f8f3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
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
        <?php if ($redeemMessage): ?>
            <div class="message-text"><?= htmlspecialchars($redeemMessage) ?></div>
        <?php else: ?>
            <div class="message-text">Your voucher has been successfully redeemed.</div>
        <?php endif; ?>

        <?php if ($lastVoucherId && $voucherTitle): ?>
            <div class="voucher-info text-start">
                <strong>Voucher:</strong> <?= htmlspecialchars($voucherTitle) ?><br>
                <?php if ($voucherPoints !== null): ?>
                    <strong>Points used:</strong> <?= htmlspecialchars($voucherPoints) ?> pts
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="remaining-points">Your remaining points: <strong><?= htmlspecialchars($userPoints) ?> pts</strong></div>

        <!-- Button to Download Voucher - create download_voucher.php to serve actual voucher -->
        <a href="download_voucher.php" class="btn-custom mb-2">Download Voucher</a>

        <!-- Button to go to Home -->
        <a href="home.php" class="btn-outline-secondary">Back to Home</a>
    </div>
</body>
</html>
