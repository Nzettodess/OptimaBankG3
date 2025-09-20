<?php
session_start();
require_once "db_connect.php";

// ✅ If not logged in, redirect
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ✅ Get profile image if exists
$profileImg = "IMG/blank_profile.png"; // fallback image file
$stmt = $conn->prepare("SELECT ProfileImage, UserPoints FROM USERS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($imgData, $userPoints);
if ($stmt->fetch() && !empty($imgData)) {
    $profileImg = "data:image/jpeg;base64," . base64_encode($imgData);
}
$stmt->close();

// ✅ Get all voucher images for slider (top carousel)
$voucherImages = [];
$result = $conn->query("SELECT Image, Title FROM VOUCHER WHERE Image IS NOT NULL ORDER BY CreatedAt DESC");
while ($row = $result->fetch_assoc()) {
    if (!empty($row['Image'])) {
        $voucherImages[] = [
            'img' => base64_encode($row['Image']),
            'title' => $row['Title']
        ];
    }
}

// ✅ Get up to 3 categories and fetch an image (from any voucher in this category) for each
$categories = [];
$catResult = $conn->query("SELECT CategoryID, Name FROM CATEGORY ORDER BY CreatedAt DESC LIMIT 3");
while ($cat = $catResult->fetch_assoc()) {
    // Try to get any voucher image for this category
    $catImg = null;
    $stmt = $conn->prepare("SELECT Image FROM VOUCHER WHERE CategoryID = ? AND Image IS NOT NULL ORDER BY CreatedAt DESC LIMIT 1");
    $stmt->bind_param("i", $cat['CategoryID']);
    $stmt->execute();
    $stmt->bind_result($imgData);
    if ($stmt->fetch() && !empty($imgData)) {
        $catImg = "data:image/jpeg;base64," . base64_encode($imgData);
    }
    $stmt->close();
    $categories[] = [
        'CategoryID' => $cat['CategoryID'],
        'Name' => $cat['Name'],
        'Image' => $catImg,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - OptimaBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-img {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        }
        .trolley-icon {
            width: 28px; height: 28px;
        }
        .voucher-slider-container {
            position: relative;
            width: 100%;
            max-width: 1050px;
            margin: 0 auto 40px auto;
            box-shadow: 0 4px 32px #dbeee3;
            border-radius: 1.2rem;
            overflow: hidden;
            background: #f7fcfb;
            min-height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .voucher-slide-img {
            width: 100%;
            height: 360px;
            object-fit: cover;
            border-radius: 1.2rem;
            display: block;
        }
        .slide-caption {
            position: absolute;
            left: 0;
            bottom: 0;
            background: rgba(0,0,0,0.44);
            color: #fff;
            width: 100%;
            padding: 16px 36px;
            font-size: 1.25em;
            border-radius: 0 0 1.2rem 1.2rem;
            letter-spacing: 1px;
        }
        .slider-dot-group {
            position: absolute;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }
        .slider-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(255,255,255,0.7);
            border: 2.5px solid #42a96a;
            cursor: pointer;
            transition: background 0.2s;
        }
        .slider-dot.active {
            background: #42a96a;
            border-color: #fff;
        }
        .welcome-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .welcome-user {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1d4d22;
        }
        .user-points {
            background: #e7fae6;
            color: #168d36;
            font-weight: bold;
            padding: 10px 28px;
            border-radius: 2rem;
            font-size: 1.23rem;
            box-shadow: 0 1px 8px #eaf4ea;
        }
        .category-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 1rem 1rem 0 0;
            background: #e8eae9;
        }
        .category-placeholder {
            width: 100%;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8eae9;
            color: #888;
            border-radius: 1rem 1rem 0 0;
            font-size: 2rem;
            font-weight: 500;
        }
        .category-card .card-body {
            padding-top: 18px;
        }
        @media (max-width: 1100px) {
            .voucher-slider-container { max-width: 98vw; min-height: 260px;}
            .voucher-slide-img { height: 180px;}
            .slide-caption{ font-size: 1em; padding: 10px 12px;}
        }
        @media (max-width: 700px) {
            .voucher-slider-container { max-width:100vw; min-height: 120px;}
            .voucher-slide-img { height: 100px;}
            .welcome-row { flex-direction: column; align-items: flex-start; gap: 10px;}
            .user-points { align-self: flex-end;}
        }
    </style>
</head>
<body class="bg-light">

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="home.php">OptimaBank</a>
        <div class="d-flex align-items-center gap-3">
            <a href="cart.php">
                <img src="IMG/trolley.png" alt="Cart" class="trolley-icon">
            </a>
            <a href="edit_profile.php">
                <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-img">
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- Welcome and Points Row -->
    <div class="welcome-row mb-3">
        <div class="welcome-user">Welcome, <?= htmlspecialchars($username) ?>!</div>
        <div class="user-points">Points: <?= htmlspecialchars($userPoints ?? 0) ?></div>
    </div>
    <!-- Voucher Slider -->
    <?php if (!empty($voucherImages)): ?>
    <div class="voucher-slider-container mb-4" id="voucherSlider">
        <?php foreach ($voucherImages as $idx => $img): ?>
            <img src="data:image/jpeg;base64,<?= $img['img'] ?>"
                 class="voucher-slide-img"
                 id="slide-img-<?= $idx ?>"
                 style="<?= $idx === 0 ? '' : 'display:none;' ?>"
                 alt="<?= htmlspecialchars($img['title']) ?>">
            <div class="slide-caption" id="slide-caption-<?= $idx ?>" style="<?= $idx === 0 ? '' : 'display:none;' ?>">
                <?= htmlspecialchars($img['title']) ?>
            </div>
        <?php endforeach; ?>
        <div class="slider-dot-group" id="sliderDots">
            <?php foreach ($voucherImages as $idx => $img): ?>
                <span class="slider-dot <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>"></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info text-center mb-4">No vouchers available yet.</div>
    <?php endif; ?>

    <!-- Categories Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Redeem Your Vouchers!</h2>
        <a href="browse_voucher.php" class="btn btn-outline-success">More</a>
    </div>
    <div class="row g-4">
        <?php if (!empty($categories)) : ?>
            <?php foreach ($categories as $cat) : ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm category-card">
                        <?php if (!empty($cat['Image'])): ?>
                            <img src="<?= $cat['Image'] ?>" class="category-img" alt="<?= htmlspecialchars($cat['Name']) ?>">
                        <?php else: ?>
                            <div class="category-placeholder"><?= htmlspecialchars($cat['Name'][0]) ?></div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column text-center">
                            <h5 class="card-title"><?= htmlspecialchars($cat['Name']) ?></h5>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No categories available yet.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// JS for slider
document.addEventListener('DOMContentLoaded', function() {
    let slides = document.querySelectorAll('.voucher-slide-img');
    let captions = document.querySelectorAll('.slide-caption');
    let dots = document.querySelectorAll('.slider-dot');
    let idx = 0;
    let total = slides.length;
    if (total < 1) return;

    function showSlide(i) {
        slides.forEach((el, n) => el.style.display = (n === i ? "" : "none"));
        captions.forEach((el, n) => el.style.display = (n === i ? "" : "none"));
        dots.forEach((el, n) => el.classList.toggle('active', n === i));
    }

    let sliderInt = setInterval(() => {
        idx = (idx + 1) % total;
        showSlide(idx);
    }, 3000);

    dots.forEach(dot => {
        dot.addEventListener('click', function() {
            idx = Number(this.getAttribute('data-index'));
            showSlide(idx);
            clearInterval(sliderInt);
            sliderInt = setInterval(() => {
                idx = (idx + 1) % total;
                showSlide(idx);
            }, 3000);
        });
    });
});
</script>

</body>
</html>