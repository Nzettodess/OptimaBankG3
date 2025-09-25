<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? "User";

// Generate voucher code (unique per download)
$voucherCode = strtoupper(substr(md5($userId . time()), 0, 10));
$date = date("Y-m-d H:i:s");

// Prepare voucher text (we'll pass this to JS for download)
$voucherContent = "***** YOUR VOUCHER *****\n\n";
$voucherContent .= "Voucher Code : {$voucherCode}\n";
$voucherContent .= "Issued To    : {$username}\n";
$voucherContent .= "Issued Date  : {$date}\n";
$voucherContent .= "Redeemable at participating outlets.\n";
$voucherContent .= "******************************\n";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher Download</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 100px auto;
            text-align: center;
        }
        .header-text {
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 20px;
        }
        .message-text {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 20px;
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
        .btn-outline-secondary:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-text">Voucher Ready!</div>
        <div class="message-text">Your voucher is now downloading...</div>
        <a href="home.php" class="btn btn-outline-secondary">Back to Home</a>
    </div>

    <script>
        // Automatically trigger download
        const voucherText = <?php echo json_encode($voucherContent); ?>;
        const blob = new Blob([voucherText], { type: "text/plain" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "voucher_<?php echo $voucherCode; ?>.txt";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    </script>
</body>
</html>
