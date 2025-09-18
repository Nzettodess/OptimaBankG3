<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once "db_connect.php";

session_start();

$client = new Google_Client();
$client->setClientId("124073192361-hh1e2cqkt39h6octqa85a4gqeci3csp3.apps.googleusercontent.com");
$client->setClientSecret("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");
$client->setRedirectUri("http://localhost/OptimaBankG3/google-callback.php");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        die("Google OAuth error: " . htmlspecialchars($token['error_description']));
    }

    $client->setAccessToken($token);

    $oauth = new Google_Service_Oauth2($client);
    $google_account_info = $oauth->userinfo->get();

    $email = $google_account_info->email;
    $name = $google_account_info->name;

    // --- sanitize username ---
    $username = preg_replace('/[^A-Za-z0-9._]/', '', $name); // only letters, numbers, dot, underscore
    if (strlen($username) < 3) {
        $username = explode('@', $email)[0]; // fallback: email prefix
    }
    if (strlen($username) < 3) {
        $username .= rand(100, 999); // final fallback
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT UserID FROM USERS WHERE Email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        // Insert new user
        $stmtInsert = $conn->prepare("INSERT INTO USERS (Email, Username, PhoneNumber, Password) VALUES (?, ?, ?, ?)");
        $emptyPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT); // random password
        $nullPhone = "";
        $stmtInsert->bind_param("ssss", $email, $username, $nullPhone, $emptyPassword);
        $stmtInsert->execute();
    }

    $stmt = $conn->prepare("SELECT UserID FROM USERS WHERE Email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($userId);
    $stmt->fetch();

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;

    header("Location: home.php");
    exit;
} else {
    echo "Login failed!";
}
