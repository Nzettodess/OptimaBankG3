<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once "db_connect.php";

use Google_Client;
use Google_Service_Oauth2;
use Dotenv\Dotenv;

session_start();

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Google Client
$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

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

    // Retrieve user ID
    $stmt = $conn->prepare("SELECT UserID FROM USERS WHERE Email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($userId);
    $stmt->fetch();

    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;

    header("Location: home.php");
    exit;
} else {
    echo "Login failed!";
}
