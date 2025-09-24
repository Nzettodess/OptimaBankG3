<?php
session_start();
require_once "db_connect.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user profile data
$stmt = $conn->prepare("SELECT Username, Email, ProfileImage, UserPoints, CreatedAt FROM USERS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($dbUsername, $email, $imgData, $userPoints, $createdAt);
$stmt->fetch();
$stmt->close();

// Profile image handling
$profileImg = "img/blank_profile.png";
if (!empty($imgData)) {
    $profileImg = "data:image/jpeg;base64," . base64_encode($imgData);
}

// Get redemption statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_redemptions, SUM(Quantity) as total_vouchers FROM CART_ITEMS_HISTORY WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($totalRedemptions, $totalVouchers);
$stmt->fetch();
$stmt->close();

// Get recent redemption history (last 5) with expiration dates
$recentHistory = [];
$stmt = $conn->prepare("
    SELECT h.Quantity, h.CompletedDate, v.Title, v.VoucherPoints, v.Image, v.ExpirationDate, c.Name as CategoryName
    FROM CART_ITEMS_HISTORY h
    JOIN VOUCHER v ON h.VoucherID = v.VoucherID
    JOIN CATEGORY c ON v.CategoryID = c.CategoryID
    WHERE h.UserID = ?
    ORDER BY h.CompletedDate DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentHistory[] = $row;
}
$stmt->close();

// Get current cart items count
$stmt = $conn->prepare("SELECT COUNT(*) as cart_count FROM CART_ITEMS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($cartCount);
$stmt->fetch();
$stmt->close();

// Calculate total points spent
$stmt = $conn->prepare("
    SELECT SUM(v.VoucherPoints * h.Quantity) as total_spent
    FROM CART_ITEMS_HISTORY h
    JOIN VOUCHER v ON h.VoucherID = v.VoucherID
    WHERE h.UserID = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($totalSpent);
$stmt->fetch();
$stmt->close();

// Get favorite category (most redeemed)
$favoriteCategory = "None";
$stmt = $conn->prepare("
    SELECT c.Name, COUNT(*) as redemption_count
    FROM CART_ITEMS_HISTORY h
    JOIN VOUCHER v ON h.VoucherID = v.VoucherID
    JOIN CATEGORY c ON v.CategoryID = c.CategoryID
    WHERE h.UserID = ?
    GROUP BY c.CategoryID, c.Name
    ORDER BY redemption_count DESC
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $favoriteCategory = $row['Name'];
}
$stmt->close();

// Format member since date
$memberSince = date('F Y', strtotime($createdAt));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - <?= htmlspecialchars($username) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-hero {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,20 50,40 100,20 150,60 200,30 250,70 300,40 350,80 400,50 450,90 500,60 550,30 600,70 650,40 700,80 750,50 800,90 850,60 900,30 950,70 1000,50 1000,100 0,100"/></svg>') repeat-x;
        }
        .profile-img-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #198754;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .activity-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .activity-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .voucher-thumb {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            background: #f8f9fa;
        }
        .voucher-thumb-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 0.8rem;
        }
        .activity-details {
            flex: 1;
        }
        .activity-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        .activity-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .points-badge {
            background: #e7f5e7;
            color: #198754;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .profile-info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .info-value {
            color: #212529;
        }
        .trolley-icon {
            width: 28px;
            height: 28px;
        }
        .profile-img-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .action-buttons {
            gap: 1rem;
            margin-top: 2rem;
        }
        .voucher-expiry {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 4px;
        }
        .voucher-expiry.expired {
            color: #dc3545;
            font-weight: 600;
        }
        .voucher-expiry.expiring-soon {
            color: #fd7e14;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .profile-hero {
                padding: 2rem 0;
            }
            .profile-img-large {
                width: 120px;
                height: 120px;
            }
            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body class="bg-light">

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="home.php">OptimaBank</a>
        <div class="d-flex align-items-center gap-3">
            <a href="cart.php" class="position-relative">
                <img src="img/trolley.png" alt="Cart" class="trolley-icon">
                <?php if ($cartCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                        <?= $cartCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="edit_profile.php">
                <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-img-nav">
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<!-- Profile Hero Section -->
<div class="profile-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-4 text-center mb-3 mb-md-0">
                <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-img-large">
            </div>
            <div class="col-md-8">
                <h1 class="display-5 fw-bold mb-2"><?= htmlspecialchars($username) ?></h1>
                <p class="lead mb-3">OptimaBank Member since <?= htmlspecialchars($memberSince) ?></p>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="bg-white bg-opacity-25 rounded-pill px-3 py-2">
                        <i class="fas fa-coins me-2"></i>
                        <strong><?= number_format($userPoints ?? 0) ?> Points</strong>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-pill px-3 py-2">
                        <i class="fas fa-trophy me-2"></i>
                        Favorite: <?= htmlspecialchars($favoriteCategory) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalRedemptions ?? 0) ?></div>
                <div class="stat-label">Total Redemptions</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalVouchers ?? 0) ?></div>
                <div class="stat-label">Vouchers Redeemed</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalSpent ?? 0) ?></div>
                <div class="stat-label">Points Spent</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-number"><?= $cartCount ?></div>
                <div class="stat-label">Items in Cart</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-4 mb-4">
            <div class="profile-info-card">
                <h4 class="mb-4"><i class="fas fa-user-circle me-2 text-success"></i>Profile Information</h4>
                
                <div class="info-row">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?= htmlspecialchars($username) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($email) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Current Points</span>
                    <span class="info-value points-badge"><?= number_format($userPoints ?? 0) ?> pts</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?= htmlspecialchars($memberSince) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Favorite Category</span>
                    <span class="info-value"><?= htmlspecialchars($favoriteCategory) ?></span>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-column action-buttons">
                    <a href="edit_profile.php" class="btn btn-success">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </a>
                    <a href="browse_voucher.php" class="btn btn-outline-success">
                        <i class="fas fa-gift me-2"></i>Browse Vouchers
                    </a>
                </div>
            </div>
        </div>

        <!-- Voucher History -->
        <div class="col-lg-8">
            <div class="activity-card">
                <div class="activity-header">
                    <h4 class="mb-0">
                        <i class="fas fa-history me-2 text-success"></i>Voucher History
                    </h4>
                </div>
                
                <?php if (empty($recentHistory)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Voucher History</h5>
                        <p class="text-muted">Start redeeming vouchers to see your history here!</p>
                        <a href="browse_voucher.php" class="btn btn-success">Browse Vouchers</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentHistory as $item): ?>
                        <div class="activity-item">
                            <?php if (!empty($item['Image'])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($item['Image']) ?>" 
                                     class="voucher-thumb" alt="Voucher">
                            <?php else: ?>
                                <div class="voucher-thumb-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="activity-details">
                                <div class="activity-title"><?= htmlspecialchars($item['Title']) ?></div>
                                <div class="activity-meta">
                                    <span class="badge bg-light text-dark me-2"><?= htmlspecialchars($item['CategoryName']) ?></span>
                                    Quantity: <?= $item['Quantity'] ?> • 
                                    Redeemed: <?= date('M j, Y g:i A', strtotime($item['CompletedDate'])) ?>
                                </div>
                                <?php if (!empty($item['ExpirationDate'])): 
                                    $expiryDate = new DateTime($item['ExpirationDate']);
                                    $today = new DateTime();
                                    $isExpired = $expiryDate < $today;
                                    $diffDays = $today->diff($expiryDate)->days;
                                    $expiryClass = '';
                                    if ($isExpired) {
                                        $expiryClass = 'expired';
                                    } elseif ($diffDays <= 7) {
                                        $expiryClass = 'expiring-soon';
                                    }
                                ?>
                                    <div class="voucher-expiry <?= $expiryClass ?>">
                                        <i class="fas fa-calendar-times me-1"></i>
                                        <?= $isExpired ? 'Expired: ' : 'Expires: ' ?><?= date('M j, Y', strtotime($item['ExpirationDate'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-end">
                                <div class="points-badge">
                                    <?= number_format($item['VoucherPoints'] * $item['Quantity']) ?> pts
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center p-3 bg-light">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
