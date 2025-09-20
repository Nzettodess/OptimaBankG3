<?php
require_once 'db_connect.php';

// Handle Category Insert
$category_msg = "";
if (isset($_POST['add_category'])) {
    $cat_name = trim($_POST['category_name'] ?? '');
    if ($cat_name !== "") {
        $stmt = $conn->prepare("INSERT INTO CATEGORY (Name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
        if ($stmt->execute()) {
            $category_msg = "Category added successfully!";
        } else {
            $category_msg = "Error adding category: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $category_msg = "Category name cannot be empty.";
    }
}

// Handle Voucher Insert
$voucher_msg = "";
if (isset($_POST['add_voucher'])) {
    $category_id = intval($_POST['voucher_category_id'] ?? 0);
    $points = intval($_POST['voucher_points'] ?? 0);
    $title = trim($_POST['voucher_title'] ?? '');
    $desc = trim($_POST['voucher_description'] ?? '');
    $tac = trim($_POST['voucher_tac'] ?? '');
    $is_latest = isset($_POST['voucher_is_latest']) ? 1 : 0;

    // Handle image
    $image_blob = null;
    if (isset($_FILES['voucher_image']) && $_FILES['voucher_image']['size'] > 0) {
        $image_blob = file_get_contents($_FILES['voucher_image']['tmp_name']);
    }

    if ($category_id && $points >= 0 && $title !== "" && $desc !== "" && $tac !== "") {
        $stmt = $conn->prepare("INSERT INTO VOUCHER (CategoryID, VoucherPoints, Title, Image, Description, TaC, IsLatest) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iissssi",
            $category_id,
            $points,
            $title,
            $image_blob,
            $desc,
            $tac,
            $is_latest
        );
        if ($stmt->execute()) {
            $voucher_msg = "Voucher added successfully!";
        } else {
            $voucher_msg = "Error adding voucher: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $voucher_msg = "Please fill in all required fields.";
    }
}

// Fetch all categories for voucher form (left panel)
$categories = [];
$cat_sql = "SELECT CategoryID, Name FROM CATEGORY ORDER BY Name ASC";
$cat_res = $conn->query($cat_sql);
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch vouchers grouped by category (for right panel)
$categories_with_vouchers = [];
$cat_voucher_sql = "SELECT c.CategoryID, c.Name as CategoryName, v.VoucherID, v.Title, v.VoucherPoints, v.Image, v.Description, v.TaC, v.IsLatest
                    FROM CATEGORY c
                    LEFT JOIN VOUCHER v ON c.CategoryID = v.CategoryID
                    ORDER BY c.Name ASC, v.Title ASC";
$res = $conn->query($cat_voucher_sql);
while ($row = $res->fetch_assoc()) {
    $cid = $row['CategoryID'];
    if (!isset($categories_with_vouchers[$cid])) {
        $categories_with_vouchers[$cid] = [
            'CategoryName' => $row['CategoryName'],
            'vouchers' => []
        ];
    }
    if ($row['VoucherID']) {
        $categories_with_vouchers[$cid]['vouchers'][] = [
            'VoucherID' => $row['VoucherID'],
            'Title' => $row['Title'],
            'VoucherPoints' => $row['VoucherPoints'],
            'Image' => $row['Image'],
            'Description' => $row['Description'],
            'TaC' => $row['TaC'],
            'IsLatest' => $row['IsLatest']
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insert Category and Voucher</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; }
        .container { display: flex; gap: 20px; }
        .left-panel, .right-panel { padding: 20px; }
        .left-panel { flex: 1; border-right: 1px solid #ddd; min-width: 400px; }
        .right-panel { flex: 2; background: #fafcff; min-width: 400px; }
        .form-section { border: 1px solid #ccc; padding: 18px; margin-bottom: 20px; width: 360px;}
        .msg { color: green; }
        .error { color: red; }
        label { display: block; margin-top: 10px; }
        .category-block { margin-bottom: 35px; }
        .category-title { font-size: 1.2em; font-weight: bold; margin-bottom: 8px; color: #194685;}
        .voucher-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            border: 1px solid #d3e0f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            background: #fff;
            box-shadow: 0 2px 4px #eef4fa;
        }
        .voucher-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e2e2;
            background: #f8fafc;
        }
        .voucher-info {
            flex: 1;
        }
        .voucher-title {
            font-weight: bold;
            font-size: 1.07em;
            margin-bottom: 2px;
        }
        .voucher-points {
            color: #1b7836;
            font-weight: bold;
            margin-bottom: 1px;
        }
        .voucher-desc {
            font-size: 0.96em;
            margin-bottom: 2px;
        }
        .voucher-latest {
            color: #fff;
            background: #007bff;
            font-size: 0.85em;
            border-radius: 4px;
            padding: 2px 7px;
            margin-left: 8px;
        }
        .voucher-tac {
            font-size: 0.85em;
            color: #888;
        }
        @media (max-width: 900px) {
            .container { flex-direction: column; }
            .form-section, .right-panel { width: 100%; min-width: 0; }
            .left-panel, .right-panel { padding: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="left-panel">
        <h2>Add Category</h2>
        <div class="form-section">
            <form action="" method="post">
                <label>Category Name:
                    <input type="text" name="category_name" required maxlength="100">
                </label>
                <button type="submit" name="add_category">Add Category</button>
            </form>
            <?php if ($category_msg): ?>
                <div class="<?= strpos($category_msg, 'success') !== false ? 'msg' : 'error' ?>"><?= htmlspecialchars($category_msg) ?></div>
            <?php endif; ?>
        </div>

        <h2>Add Voucher</h2>
        <div class="form-section">
            <form action="" method="post" enctype="multipart/form-data">
                <label>Category:
                    <select name="voucher_category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Points:
                    <input type="number" name="voucher_points" min="0" value="0" required>
                </label>
                <label>Title:
                    <input type="text" name="voucher_title" maxlength="100" required>
                </label>
                <label>Image (optional):
                    <input type="file" name="voucher_image" accept="image/*">
                </label>
                <label>Description:
                    <textarea name="voucher_description" required></textarea>
                </label>
                <label>Terms & Conditions:
                    <textarea name="voucher_tac" required></textarea>
                </label>
                <label>
                    <input type="checkbox" name="voucher_is_latest" value="1"> Mark as Latest
                </label>
                <button type="submit" name="add_voucher">Add Voucher</button>
            </form>
            <?php if ($voucher_msg): ?>
                <div class="<?= strpos($voucher_msg, 'success') !== false ? 'msg' : 'error' ?>"><?= htmlspecialchars($voucher_msg) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="right-panel">
        <h2>Vouchers by Category</h2>
        <?php if (empty($categories_with_vouchers)): ?>
            <p>No categories or vouchers found.</p>
        <?php else: ?>
            <?php foreach ($categories_with_vouchers as $cat): ?>
                <div class="category-block">
                    <div class="category-title"><?= htmlspecialchars($cat['CategoryName']) ?></div>
                    <?php if (empty($cat['vouchers'])): ?>
                        <div style="color: #888; margin-bottom: 16px;">No vouchers in this category.</div>
                    <?php else: ?>
                        <?php foreach ($cat['vouchers'] as $voucher): ?>
                            <div class="voucher-card">
                                <?php if ($voucher['Image']): ?>
                                    <img class="voucher-image" src="data:image/jpeg;base64,<?= base64_encode($voucher['Image']) ?>" alt="Voucher Image">
                                <?php else: ?>
                                    <div class="voucher-image" style="display: flex; align-items:center; justify-content:center; color:#aaa;">No<br>Image</div>
                                <?php endif; ?>
                                <div class="voucher-info">
                                    <div>
                                        <span class="voucher-title"><?= htmlspecialchars($voucher['Title']) ?></span>
                                        <?php if ($voucher['IsLatest']): ?>
                                            <span class="voucher-latest">Latest</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="voucher-points">Points: <?= htmlspecialchars($voucher['VoucherPoints']) ?></div>
                                    <div class="voucher-desc"><?= htmlspecialchars($voucher['Description']) ?></div>
                                    <div class="voucher-tac">T&C: <?= htmlspecialchars($voucher['TaC']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>