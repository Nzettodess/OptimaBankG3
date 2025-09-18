<?php
require_once "db_connect.php";
session_start();

$errors = [];
$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $agree = isset($_POST['agree']);

    // ✅ Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (!preg_match('/^[A-Za-z_]{3,20}$/', $username)) {
        $errors[] = "Username must be 3-20 characters (letters and underscores only).";
    }

    if (!preg_match('/^((\+60)|0)[0-9]{8,13}$/', $phone)) {
        $errors[] = "Invalid Malaysia phone number format.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (!$agree) {
        $errors[] = "You must agree to the Terms & Conditions.";
    }

    // ✅ If no validation errors → insert into DB
    if (empty($errors)) {
        // Check duplicates
        $check = $conn->prepare("SELECT UserID FROM USERS WHERE Email=? OR Username=? OR PhoneNumber=?");
        $check->bind_param("sss", $email, $username, $phone);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "Email, Username, or Phone number already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO USERS (Email, Username, PhoneNumber, Password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $username, $phone, $hashedPassword);

            if ($stmt->execute()) {
                // ✅ Redirect after success
                header("Location: login.php?signup=success");
                exit();
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - OptimaBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-lg p-4 rounded-4" style="max-width: 450px; width: 100%;">
        <h3 class="text-center mb-3">Create an Account</h3>

        <!-- Display errors -->
        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?>
            </div>
        <?php endif; ?>

        <!-- Signup Form -->
        <form method="POST" action="signup.php" novalidate>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" placeholder="Enter email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" placeholder="Choose a username" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" class="form-control" name="phone" placeholder="+60 or 0..." required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" placeholder="Enter password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" placeholder="Re-enter password" required>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="agree" id="agree" required>
                <label class="form-check-label" for="agree">
                    I agree to the <a href="#">Terms & Conditions</a>
                </label>
            </div>

            <button type="submit" class="btn btn-success w-100">Sign Up</button>

            <button type="button" class="btn btn-danger w-100 mt-2"
                    onclick="window.location.href='google-login.php'">
                Continue with Google
            </button>

            <p class="text-center mt-3">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </form>
    </div>
</div>
</body>
</html>
