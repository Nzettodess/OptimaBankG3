<?php
require_once "db_connect.php";
session_start();

$errors = [];

// ✅ Success message if redirected from signup
$success = "";
if (isset($_GET['signup']) && $_GET['signup'] === "success") {
    $success = "Account created successfully! You can now log in.";
}

// ✅ Manual login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Email and Password are required.";
    } else {
        $stmt = $conn->prepare("SELECT UserID, Username, Password FROM USERS WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['Password'])) {
                $_SESSION['user_id'] = $row['UserID'];
                $_SESSION['username'] = $row['Username'];
                header("Location: home.php");
                exit();
            } else {
                $errors[] = "Invalid password.";
            }
        } else {
            $errors[] = "No account found with that email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - OptimaBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-lg p-4 rounded-4" style="max-width: 450px; width: 100%;">
        <h3 class="text-center mb-3">Login</h3>

        <!-- Success message after signup -->
        <?php if ($success) : ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Display errors -->
        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error) echo "<p class='mb-0'>$error</p>"; ?>
            </div>
        <?php endif; ?>

        <!-- Manual login form -->
        <form method="POST" action="login.php" novalidate>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" placeholder="Enter email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Login</button>
        </form>

        <button class="btn btn-danger w-100 mt-2" onclick="window.location.href='google-login.php'">
            Continue with Google
        </button>

        <p class="text-center mt-3">
            Don’t have an account? <a href="signup.php">Sign up here</a>
        </p>
    </div>
</div>
</body>
</html>
