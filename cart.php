<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? "";

// Get profile image
$profileImg = "blank_profile.png";
$stmt = $conn->prepare("SELECT ProfileImage FROM USERS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($imgData);
if ($stmt->fetch() && !empty($imgData)) {
    $profileImg = "data:image/jpeg;base64," . base64_encode($imgData);
}
$stmt->close();

$errors = [];
$success = "";

// Handle update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_qty') {
    $cartId = (int) $_POST['cart_id'];
    $newQty = max(1, (int) $_POST['quantity']); // minimum 1
    $upd = $conn->prepare("UPDATE CART_ITEMS SET Quantity = ? WHERE CartID = ? AND UserID = ?");
    $upd->bind_param("iii", $newQty, $cartId, $userId);
    $upd->execute();
    $upd->close();
    header("Location: cart.php?updated=1");
    exit();
}

// Handle Redeem Now
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'redeem') {
    $sql = "SELECT ci.CartID, ci.VoucherID, ci.Quantity, v.VoucherPoints
            FROM CART_ITEMS ci
            JOIN VOUCHER v ON ci.VoucherID = v.VoucherID
            WHERE ci.UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $cartItems = [];
    $totalPoints = 0;
    while ($r = $res->fetch_assoc()) {
        $cartItems[] = $r;
        $totalPoints += $r['VoucherPoints'] * $r['Quantity'];
    }
    $stmt->close();

    if (empty($cartItems)) {
        $errors[] = "Your cart is empty.";
    } else {
        $rebate = floor($totalPoints * 0.10);
        $netDeduction = $totalPoints - $rebate;

        $conn->begin_transaction();
        try {
            // Lock and fetch user points
            $stmt = $conn->prepare("SELECT UserPoints FROM USERS WHERE UserID = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($currentPoints);
            $stmt->fetch();
            $stmt->close();

            if ($currentPoints < $netDeduction) {
                throw new Exception("Insufficient points. You need $netDeduction points but only have $currentPoints.");
            }

            // Insert into history
            $ins = $conn->prepare("INSERT INTO CART_ITEMS_HISTORY (VoucherID, UserID, Quantity, CompletedDate) VALUES (?, ?, ?, NOW())");
            foreach ($cartItems as $item) {
                $ins->bind_param("iii", $item['VoucherID'], $userId, $item['Quantity']);
                $ins->execute();
            }
            $ins->close();

            // Clear cart
            $del = $conn->prepare("DELETE FROM CART_ITEMS WHERE UserID = ?");
            $del->bind_param("i", $userId);
            $del->execute();
            $del->close();

            // Update user points
            $newBalance = $currentPoints - $netDeduction;
            $upd = $conn->prepare("UPDATE USERS SET UserPoints = ? WHERE UserID = ?");
            $upd->bind_param("ii", $newBalance, $userId);
            $upd->execute();
            $upd->close();

            $conn->commit();
            $success = "Redemption successful! You spent $totalPoints pts, rebate $rebate pts, net deduction $netDeduction pts. New balance: $newBalance pts.";
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Redemption failed: " . $e->getMessage();
        }
    }
}

// Fetch updated user points
$stmt = $conn->prepare("SELECT UserPoints FROM USERS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userPoints);
$stmt->fetch();
$stmt->close();

// ✅ Fetch cart items with CartID
$sql = "SELECT ci.CartID, ci.VoucherID, ci.Quantity, v.Title, v.VoucherPoints, v.Image, v.Description
        FROM CART_ITEMS ci
        JOIN VOUCHER v ON ci.VoucherID = v.VoucherID
        WHERE ci.UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

$cartRows = [];
$totalPoints = 0;
while ($r = $res->fetch_assoc()) {
    $r['LineTotal'] = $r['VoucherPoints'] * $r['Quantity'];
    $totalPoints += $r['LineTotal'];
    $cartRows[] = $r;
}
$stmt->close();

$rebateTotal = floor($totalPoints * 0.10);
$netTotal = $totalPoints - $rebateTotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher Cart - OptimaBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .trolley-icon { width: 28px; height: 28px; }
        .voucher-img { width: 100px; height: 70px; object-fit: cover; border-radius: 6px; background: #f3f3f3; }
        .summary-box { background: #f8f9fa; border-radius: 8px; padding: 16px; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="home.php">OptimaBank</a>
        <div class="d-flex align-items-center gap-3">
            <a href="cart.php"><img src="trolley.png" class="trolley-icon"></a>
            <a href="edit_profile.php"><img src="<?= htmlspecialchars($profileImg) ?>" class="profile-img"></a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <h2 class="mb-4 text-center">Voucher Cart</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e)."<br>"; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($cartRows)): ?>
        <div class="alert alert-info text-center">
            Your cart is empty. <a href="browse_voucher.php" class="alert-link">Browse vouchers</a> to add some!
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Voucher</th>
                                    <th>Points</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartRows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if (!empty($row['Image'])): ?>
                                                    <img src="data:image/jpeg;base64,<?= base64_encode($row['Image']) ?>" class="voucher-img">
                                                <?php else: ?>
                                                    <div class="voucher-img d-flex align-items-center justify-content-center text-muted">No Image</div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($row['Title']) ?></div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(substr($row['Description'],0,60)) ?><?= strlen($row['Description'])>60?'...':'' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= $row['VoucherPoints'] ?></td>
                                        <td>
                                            <form method="post" action="cart.php" class="d-flex gap-2">
                                                <input type="hidden" name="action" value="update_qty">
                                                <input type="hidden" name="cart_id" value="<?= $row['CartID'] ?>">
                                                <input type="number" name="quantity" value="<?= $row['Quantity'] ?>" min="1" class="form-control form-control-sm" style="width:80px;">
                                                <button class="btn btn-sm btn-outline-secondary">Update</button>
                                            </form>
                                        </td>
                                        <td><?= $row['LineTotal'] ?></td>
                                        <td>
                                            <form method="post" action="remove_from_cart.php">
                                                <input type="hidden" name="cart_id" value="<?= $row['CartID'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="summary-box shadow-sm">
                    <h5 class="mb-3">Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span><span><?= $totalPoints ?> pts</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>10% Rebate:</span><span>+<?= $rebateTotal ?> pts</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold border-top pt-2">
                        <span>Net Deduction:</span><span><?= $netTotal ?> pts</span>
                    </div>
                    <hr>
                    <div class="mb-2"><strong>Your Points:</strong> <?= $userPoints ?> pts</div>
                    <div class="d-flex gap-2">
                        <a href="browse_voucher.php" class="btn btn-outline-secondary w-50">Back to Vouchers</a>
                        <form method="post" action="cart.php" class="w-50">
                            <input type="hidden" name="action" value="redeem">
                            <button type="submit" class="btn btn-success w-100">Redeem Now</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
