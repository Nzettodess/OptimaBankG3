<?php
session_start();
require_once "db_connect.php";

// ✅ Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// ✅ Fetch user info function
function getUserData($conn, $userId) {
    $stmt = $conn->prepare("SELECT Username, Email, ProfileImage, UserPoints FROM USERS WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($dbUsername, $email, $imgData, $points);
    
    // Initialize default values
    $dbUsername = $email = $imgData = $points = null;
    
    if ($stmt->fetch()) {
        // Data found and fetched successfully
        $stmt->close();
        return [$dbUsername, $email, $imgData, $points];
    } else {
        // No data found, return defaults
        $stmt->close();
        return [null, null, null, 0];
    }
}

// Initial fetch
list($dbUsername, $email, $imgData, $points) = getUserData($conn, $userId);

// Handle case where user data might not exist
if ($dbUsername === null) {
    // Redirect to login if user not found
    session_destroy();
    header("Location: login.php");
    exit();
}

// Profile image handling
$profileImg = "IMG/blank_profile.png";
if (!empty($imgData)) {
    $profileImg = "data:image/jpeg;base64," . base64_encode($imgData);
}

$success = "";
$errors = [];

// ✅ Handle profile image upload
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['profile_image']['tmp_name'];
        $fileType = mime_content_type($fileTmp);

        if (in_array($fileType, ['image/jpeg', 'image/png'])) {
            $newImgData = file_get_contents($fileTmp);

            $stmt = $conn->prepare("UPDATE USERS SET ProfileImage=? WHERE UserID=?");
            $stmt->bind_param("si", $newImgData, $userId);
            if ($stmt->execute()) {
                $success = "Profile image updated successfully.";

                // ✅ Re-fetch updated data
                list($dbUsername, $email, $imgData, $points) = getUserData($conn, $userId);
                $profileImg = "data:image/jpeg;base64," . base64_encode($imgData);
            } else {
                $errors[] = "Error updating image: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Only JPG and PNG images are allowed.";
        }
    } else {
        $errors[] = "No image selected.";
    }
}

// ✅ Handle profile update
if (isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username'] ?? $dbUsername);
    $newEmail = trim($_POST['email'] ?? $email);
    $newPassword = $_POST['password'] ?? "";
    $confirmPassword = $_POST['confirm_password'] ?? "";

    // Validation
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!preg_match('/^[A-Za-z_]{3,20}$/', $newUsername)) {
        $errors[] = "Username must be 3-20 characters (letters and underscores only).";
    }
    if (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE USERS SET Username=?, Email=?, Password=? WHERE UserID=?");
            $stmt->bind_param("sssi", $newUsername, $newEmail, $hashedPassword, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE USERS SET Username=?, Email=? WHERE UserID=?");
            $stmt->bind_param("ssi", $newUsername, $newEmail, $userId);
        }

        if ($stmt->execute()) {
            $success = "Profile updated successfully.";
            $_SESSION['username'] = $newUsername;
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - OptimaBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-img {
            width: 150px; height: 150px; border-radius: 50%; object-fit: cover; cursor: pointer;
            border: 3px solid #198754;
        }
        .btn-highlight {
            background-color: #198754; color: white; font-weight: bold;
        }
        .points-badge {
            position: absolute; top: 10px; right: 20px;
            font-size: 1rem; padding: 8px 12px;
        }
        .fade-out {
            transition: opacity 0.5s ease;
            opacity: 1;
        }
        .fade-out.hide {
            opacity: 0;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">

            <div class="card shadow-lg rounded-4 position-relative">
                <!-- Points at Top Right -->
                <span class="badge bg-success points-badge">Points: <?= htmlspecialchars($points) ?></span>
                
                <div class="card-body p-4">
                    
                    <!-- Success / Error Alerts -->
                    <?php if ($success): ?>
                        <div id="alertBox" class="alert alert-success fade-out"><?= $success ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors)): ?>
                        <div id="alertBox" class="alert alert-danger fade-out">
                            <?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Left Side (Back + Image Upload) -->
                        <div class="col-md-4 text-center border-end">
                            <!-- Back to Home button -->
                            <div class="d-grid mb-3">
                                <a href="home.php" class="btn btn-secondary w-100">⬅ Back to Home</a>
                            </div>

                            <!-- Profile Image Upload -->
                            <form method="POST" action="edit_profile.php" enctype="multipart/form-data">
                                <input type="file" name="profile_image" id="profileInput" class="d-none" accept="image/*" onchange="this.form.submit();">
                                <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-img mb-3" onclick="document.getElementById('profileInput').click();">
                                <input type="hidden" name="upload_image" value="1">
                            </form>
                        </div>

                        <!-- Right Side (Profile Update) -->
                        <div class="col-md-8">
                            <form method="POST" action="edit_profile.php" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="update_profile" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($dbUsername) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                                </div>
                                <button type="submit" class="btn btn-success w-100 mb-2">Save Changes</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Auto-dismiss alerts after 3s
    document.addEventListener("DOMContentLoaded", function() {
        const alertBox = document.getElementById("alertBox");
        if (alertBox) {
            setTimeout(() => {
                alertBox.classList.add("hide");
                setTimeout(() => alertBox.remove(), 500);
            }, 3000);
        }
    });
</script>

</body>
</html>
