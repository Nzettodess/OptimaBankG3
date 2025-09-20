<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get profile image if exists
$profileImg = "blank_profile.png";
$stmt = $conn->prepare("SELECT ProfileImage FROM USERS WHERE UserID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($imgData);
if ($stmt->fetch() && !empty($imgData)) {
    $profileImg = "data:image/jpeg;base64," . base64_encode($imgData);
}
$stmt->close();

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// Always get all categories
$categories = [];
$cat_sql = "SELECT CategoryID, Name FROM CATEGORY ORDER BY CreatedAt DESC";
$cat_res = $conn->query($cat_sql);
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row;
}

function getVouchersForCategory($conn, $categoryId, $categoryName, $search = "") {
    if ($search !== "") {
        // If category name matches, show all its vouchers
        if (stripos($categoryName, $search) !== false) {
            $stmt = $conn->prepare(
                "SELECT VoucherID, Title, VoucherPoints, Image, Description, TaC, IsLatest 
                 FROM VOUCHER 
                 WHERE CategoryID = ? 
                 ORDER BY CreatedAt DESC"
            );
            $stmt->bind_param("i", $categoryId);
        } else {
            $stmt = $conn->prepare(
                "SELECT VoucherID, Title, VoucherPoints, Image, Description, TaC, IsLatest 
                 FROM VOUCHER 
                 WHERE CategoryID = ? AND (
                    Title LIKE ? OR
                    CAST(VoucherPoints AS CHAR) LIKE ? OR
                    Description LIKE ? OR
                    TaC LIKE ?
                 )
                 ORDER BY CreatedAt DESC"
            );
            $like = "%" . $search . "%";
            $stmt->bind_param("issss", $categoryId, $like, $like, $like, $like);
        }
    } else {
        $stmt = $conn->prepare(
            "SELECT VoucherID, Title, VoucherPoints, Image, Description, TaC, IsLatest 
             FROM VOUCHER 
             WHERE CategoryID = ? 
             ORDER BY CreatedAt DESC"
        );
        $stmt->bind_param("i", $categoryId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $vouchers = [];
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }
    $stmt->close();
    return $vouchers;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Vouchers - OptimaBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-img {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        }
        .trolley-icon {
            width: 28px; height: 28px;
        }
        .voucher-row-wrapper {
            position: relative;
        }
        .voucher-scroll-row {
            overflow-x: auto;
            padding-bottom: 8px;
            display: flex;
            flex-wrap: nowrap;
            gap: 18px;
            scroll-behavior: smooth;
        }
        .voucher-card {
            flex: 0 0 320px;
            max-width: 320px;
            min-width: 280px;
            margin-bottom: 8px;
        }
        .voucher-img {
            width: 100%; height: 160px; object-fit: cover; border-radius: .5rem .5rem 0 0;
            background: #f3f3f3;
        }
        .category-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .latest-badge {
            background: #007bff;
            color: #fff;
            font-size: .9em;
            border-radius: 5px;
            padding: 2px 8px;
        }
        .voucher-scroll-row::-webkit-scrollbar {
            display: none;
        }
        .voucher-scroll-row {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .voucher-row-fade {
            position: absolute;
            top: 0; right: 0; bottom: 0;
            width: 80px;
            pointer-events: none;
            z-index: 2;
            background: linear-gradient(to left, rgba(255,255,255,0.96) 60%, rgba(255,255,255,0));
            display: flex;
            align-items: center;
            justify-content: flex-end;
            transition: opacity 0.3s;
        }
        .voucher-row-fade .scroll-arrow {
            font-size: 2.5rem;
            color: #c0c0c0;
            opacity: 0.7;
            margin-right: 12px;
            user-select: none;
        }
        .voucher-row-fade.hide {
            opacity: 0;
            pointer-events: none;
        }
        .search-bar-wrapper {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .search-bar-wrapper {
                justify-content: center;
            }
        }
        .modal-voucher-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: .5rem;
            margin-bottom: 15px;
        }
        .terms-section {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 15px;
            margin-top: 15px;
            max-height: 150px;
            overflow-y: auto;
        }
        .terms-section h6 {
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 8px;
            color: #495057;
        }
        .terms-content {
            font-size: 0.85rem;
            color: #6c757d;
            white-space: pre-line;
        }
        .terms-modal-section {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 15px;
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        .terms-modal-section h6 {
            font-size: 0.95rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #495057;
        }
        .terms-modal-content {
            font-size: 0.9rem;
            color: #6c757d;
            white-space: pre-line;
        }
        .btn-view-terms {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-light">

<!-- Header (same as home.php) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="home.php">OptimaBank</a>
        <div class="d-flex align-items-center gap-3">
            <a href="cart.php">
                <img src="trolley.png" alt="Cart" class="trolley-icon">
            </a>
            <a href="edit_profile.php">
                <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-img">
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="search-bar-wrapper">
        <form class="d-flex" method="get" action="">
            <input class="form-control me-2" type="search" name="search" placeholder="Search category, title, points, desc, terms..." value="<?= htmlspecialchars($search) ?>" aria-label="Search">
            <?php if ($search !== ""): ?>
                <a href="browse_voucher.php" class="btn btn-outline-secondary me-2">Clear</a>
            <?php endif; ?>
            <button class="btn btn-success" type="submit">Search</button>
        </form>
    </div>
    <h2 class="mb-4 text-center">Browse & Redeem</h2>
    <?php
    $foundAny = false;
    foreach ($categories as $i => $cat):
        $vouchers = getVouchersForCategory($conn, $cat['CategoryID'], $cat['Name'], $search);
        if (empty($vouchers)) continue;
        $foundAny = true;
    ?>
        <div class="mb-4 voucher-row-wrapper">
            <div class="category-title-row mb-2">
                <h5 class="mb-0"><?= htmlspecialchars($cat['Name']) ?></h5>
            </div>
            <div class="voucher-scroll-row" id="voucher-scroll-row-<?= $i ?>">
                <?php foreach ($vouchers as $v) : ?>
                    <div class="card voucher-card shadow-sm">
                        <?php if (!empty($v['Image'])): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($v['Image']) ?>" class="voucher-img" alt="Voucher">
                        <?php else: ?>
                            <div class="voucher-img d-flex align-items-center justify-content-center text-muted" style="font-size:1.2em;">No Image</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h6 class="card-title d-flex align-items-center mb-1">
                                <?= htmlspecialchars($v['Title']) ?>
                                <?php if ($v['IsLatest']): ?>
                                    <span class="latest-badge ms-2">Latest</span>
                                <?php endif; ?>
                            </h6>
                            <div class="mb-2 text-success fw-semibold">Points: <?= htmlspecialchars($v['VoucherPoints']) ?></div>
                            <div class="mb-2 text-muted" style="font-size:0.97em;">
                                <?= nl2br(htmlspecialchars(mb_strimwidth($v['Description'], 0, 100, '...'))) ?>
                            </div>
                            
                            <!-- Terms & Conditions Preview -->
                            <div class="terms-section">
                                <h6>Terms & Conditions</h6>
                                <div class="terms-content">
                                    <?= nl2br(htmlspecialchars(mb_strimwidth($v['TaC'], 0, 120, '...'))) ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-info mt-2 btn-view-terms" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#termsModal"
                                        data-terms-title="<?= htmlspecialchars($v['Title']) ?>"
                                        data-terms-content="<?= htmlspecialchars($v['TaC']) ?>">
                                    View Full Terms
                                </button>
                            </div>
                            
                            <button type="button" class="btn btn-outline-success w-100 mt-3 view-voucher-btn" 
                                    data-voucher-id="<?= $v['VoucherID'] ?>"
                                    data-voucher-title="<?= htmlspecialchars($v['Title']) ?>"
                                    data-voucher-points="<?= htmlspecialchars($v['VoucherPoints']) ?>"
                                    data-voucher-description="<?= htmlspecialchars($v['Description']) ?>"
                                    data-voucher-terms="<?= htmlspecialchars($v['TaC']) ?>"
                                    data-voucher-image="<?= !empty($v['Image']) ? 'data:image/jpeg;base64,' . base64_encode($v['Image']) : '' ?>">
                                Add to Redeem
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="voucher-row-fade" id="voucher-row-fade-<?= $i ?>">
                <span class="scroll-arrow">&rarr;</span>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($search !== "" && !$foundAny): ?>
        <div class="alert alert-warning text-center">No results found.</div>
    <?php elseif (empty($categories)): ?>
        <div class="alert alert-info text-center">No categories available yet.</div>
    <?php endif; ?>
</div>

<!-- Terms & Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms & Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 id="termsModalVoucherTitle" class="mb-3"></h4>
                <div class="terms-modal-section">
                    <h6>Terms & Conditions</h6>
                    <div id="termsModalContent" class="terms-modal-content"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Voucher Detail Modal -->
<div class="modal fade" id="voucherModal" tabindex="-1" aria-labelledby="voucherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="voucherModalLabel">Voucher Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalVoucherImage">
                    <!-- Image will be inserted by JavaScript -->
                </div>
                <h4 id="modalVoucherTitle" class="mb-2"></h4>
                <div class="text-success fw-semibold mb-3">Points: <span id="modalVoucherPoints"></span></div>
                <p id="modalVoucherDescription" class="mb-4"></p>
                
                <!-- Terms & Conditions Section in Voucher Modal -->
                <div class="terms-modal-section">
                    <h6>Terms & Conditions</h6>
                    <div id="modalVoucherTerms" class="terms-modal-content"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="redeemForm" action="redeem_voucher.php" method="post">
                    <input type="hidden" name="voucher_id" id="modalVoucherId">
                    <button type="submit" class="btn btn-success">Confirm Redeem</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Scroll functionality
    document.querySelectorAll('.voucher-scroll-row').forEach(function(row, idx) {
        var fade = document.getElementById('voucher-row-fade-' + idx);
        function updateFade() {
            if (!fade) return;
            if (row.scrollLeft + row.clientWidth >= row.scrollWidth - 2) {
                fade.classList.add('hide');
            } else {
                fade.classList.remove('hide');
            }
        }
        updateFade();
        row.addEventListener('scroll', updateFade);
        window.addEventListener('resize', updateFade);
    });
    
    // Terms Modal functionality
    const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
    const viewTermsButtons = document.querySelectorAll('.btn-view-terms');
    
    viewTermsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const termsTitle = this.getAttribute('data-terms-title');
            const termsContent = this.getAttribute('data-terms-content');
            
            document.getElementById('termsModalLabel').textContent = 'Terms & Conditions - ' + termsTitle;
            document.getElementById('termsModalVoucherTitle').textContent = termsTitle;
            document.getElementById('termsModalContent').textContent = termsContent;
        });
    });
    
    // Voucher modal functionality
    const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'));
    const viewVoucherButtons = document.querySelectorAll('.view-voucher-btn');
    
    viewVoucherButtons.forEach(button => {
        button.addEventListener('click', function() {
            const voucherId = this.getAttribute('data-voucher-id');
            const voucherTitle = this.getAttribute('data-voucher-title');
            const voucherPoints = this.getAttribute('data-voucher-points');
            const voucherDescription = this.getAttribute('data-voucher-description');
            const voucherTerms = this.getAttribute('data-voucher-terms');
            const voucherImage = this.getAttribute('data-voucher-image');
            
            // Set modal content
            document.getElementById('modalVoucherId').value = voucherId;
            document.getElementById('modalVoucherTitle').textContent = voucherTitle;
            document.getElementById('modalVoucherPoints').textContent = voucherPoints;
            document.getElementById('modalVoucherDescription').textContent = voucherDescription;
            document.getElementById('modalVoucherTerms').textContent = voucherTerms;
            
            // Set image or placeholder
            const imageContainer = document.getElementById('modalVoucherImage');
            if (voucherImage) {
                imageContainer.innerHTML = `<img src="${voucherImage}" class="modal-voucher-img" alt="${voucherTitle}">`;
            } else {
                imageContainer.innerHTML = '<div class="modal-voucher-img d-flex align-items-center justify-content-center bg-light text-muted">No Image Available</div>';
            }
            
            // Show modal
            voucherModal.show();
        });
    });
});
</script>

</body>
</html>
